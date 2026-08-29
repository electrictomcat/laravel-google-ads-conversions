<?php

namespace ElectricTomCat\GoogleAdsConversions;

use DateTimeInterface;
use ElectricTomCat\GoogleAdsConversions\Contracts\HasConversions;
use ElectricTomCat\GoogleAdsConversions\Events\ConversionRecorded;
use ElectricTomCat\GoogleAdsConversions\Events\ConversionsSynced;
use ElectricTomCat\GoogleAdsConversions\Models\Lead;
use ElectricTomCat\GoogleAdsConversions\Support\EventResolver;
use ElectricTomCat\GoogleAdsConversions\Support\UserDataHasher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

/**
 * The main entry point — recording, buffering, and syncing conversions.
 *
 * Most users interact with the static facade:
 *
 *     GoogleAdsConversions::record('Quote Form', 100);
 *
 * The class is also resolvable from the container:
 *
 *     app(GoogleAdsConversions::class)->record('Quote Form');
 */
class GoogleAdsConversions
{
    public const CACHE_PREFIX = 'google_ads_pending_conversions:';

    public const LEAD_DATA_PREFIX = 'google_ads_pending_lead_data:';

    public const DIRTY_SET_KEY = 'google_ads_dirty_leads';

    public const BUFFER_TTL_DAYS = 2;

    protected ?string $memoizedGclid = null;

    protected ?string $memoizedGbraid = null;

    protected ?string $memoizedWbraid = null;

    protected bool $gclidMemoized = false;

    protected bool $gbraidMemoized = false;

    protected bool $wbraidMemoized = false;

    public function __construct(
        protected EventResolver $events,
        protected UserDataHasher $hasher,
    ) {}

    /**
     * The GCLID for the current visitor, or null if none can be found.
     */
    public function gclid(): ?string
    {
        if (! $this->gclidMemoized) {
            $this->memoizedGclid = $this->resolveIdentifier('gclid');
            $this->gclidMemoized = true;
        }

        return $this->memoizedGclid;
    }

    /**
     * The GBRAID for the current visitor, or null if none can be found.
     */
    public function gbraid(): ?string
    {
        if (! $this->gbraidMemoized) {
            $this->memoizedGbraid = $this->resolveIdentifier('gbraid');
            $this->gbraidMemoized = true;
        }

        return $this->memoizedGbraid;
    }

    /**
     * The WBRAID for the current visitor, or null if none can be found.
     */
    public function wbraid(): ?string
    {
        if (! $this->wbraidMemoized) {
            $this->memoizedWbraid = $this->resolveIdentifier('wbraid');
            $this->wbraidMemoized = true;
        }

        return $this->memoizedWbraid;
    }

    /**
     * Any resolved click identifier (gclid ?? gbraid ?? wbraid).
     */
    public function clickId(): ?string
    {
        return $this->gclid() ?? $this->gbraid() ?? $this->wbraid();
    }

    /**
     * Discard memoized click identifiers. Useful in tests and long-running workers.
     */
    public function forgetGclid(): void
    {
        $this->memoizedGclid = null;
        $this->memoizedGbraid = null;
        $this->memoizedWbraid = null;
        $this->gclidMemoized = false;
        $this->gbraidMemoized = false;
        $this->wbraidMemoized = false;
    }

    /**
     * Record a conversion event for the current visitor.
     *
     * @param  string  $eventName  Internal event name or Google Ads action name
     * @param  float|null  $value  Monetary conversion value
     * @param  string|null  $currency  ISO 4217 currency code (e.g. 'USD', 'EUR')
     * @param  string|null  $gclid  Optional direct GCLID override
     * @param  string|null  $gbraid  Optional direct GBRAID override
     * @param  string|null  $wbraid  Optional direct WBRAID override
     * @param  string|null  $orderId  Optional unique order / transaction ID for deduplication
     * @param  DateTimeInterface|int|string|null  $conversionDateTime  Optional conversion timestamp
     * @param  array{ad_user_data?: string|bool|null, ad_personalization?: string|bool|null}|bool|null  $consent
     * @param  array{email?: string|null, phone?: string|null, phone_number?: string|null}  $userIdentifiers
     */
    public function record(
        string $eventName,
        ?float $value = null,
        ?string $currency = null,
        ?string $gclid = null,
        ?string $gbraid = null,
        ?string $wbraid = null,
        ?string $orderId = null,
        DateTimeInterface|int|string|null $conversionDateTime = null,
        array|bool|null $consent = null,
        array $userIdentifiers = [],
    ): void {
        $resolvedClickId = $gclid ?? $gbraid ?? $wbraid ?? $this->clickId();

        if (! $resolvedClickId) {
            Log::warning("[GoogleAdsConversions] Failed to record '{$eventName}': no GCLID, GBRAID, or WBRAID found in override, session, cookie, or visitor history.");

            return;
        }

        $resolvedValue = $this->events->value($eventName, $value);
        $resolvedCurrency = $this->events->currency($eventName, $currency);

        $timestamp = match (true) {
            $conversionDateTime instanceof DateTimeInterface => $conversionDateTime->getTimestamp(),
            is_numeric($conversionDateTime) => (int) $conversionDateTime,
            is_string($conversionDateTime) => Carbon::parse($conversionDateTime)->getTimestamp(),
            default => now()->timestamp,
        };

        $conversionEntry = [
            'event' => $eventName,
            'timestamp' => $timestamp,
            'value' => $resolvedValue,
            'currency' => $resolvedCurrency,
            'status' => 'pending',
        ];

        if ($gclid) {
            $conversionEntry['gclid'] = $gclid;
        }
        if ($gbraid) {
            $conversionEntry['gbraid'] = $gbraid;
        }
        if ($wbraid) {
            $conversionEntry['wbraid'] = $wbraid;
        }
        if ($orderId !== null) {
            $conversionEntry['order_id'] = $orderId;
        }

        if ($consent !== null) {
            $conversionEntry['consent'] = is_array($consent)
                ? $consent
                : ['ad_user_data' => $consent, 'ad_personalization' => $consent];
        }

        if (! empty($userIdentifiers) && config('google-ads-conversions.enhanced_conversions.enabled', false)) {
            $conversionEntry['user_identifiers'] = $userIdentifiers;
        }

        $this->pushToCache($resolvedClickId, $conversionEntry);

        ConversionRecorded::dispatch($resolvedClickId, $conversionEntry);
    }

    /**
     * Buffer creation/update data for a lead in cache, to be flushed
     * to the database by the next syncToDatabase() run.
     */
    public function bufferLeadData(string $clickId, array $data): void
    {
        Cache::put(
            self::LEAD_DATA_PREFIX.$clickId,
            $data,
            now()->addDays(self::BUFFER_TTL_DAYS),
        );

        $this->markDirty($clickId);
    }

    /**
     * Flush the cache buffer to the database, creating or updating
     * one model per dirty click identifier.
     */
    public function syncToDatabase(): void
    {
        $dirty = Cache::get(self::DIRTY_SET_KEY, []);

        if (! is_array($dirty) || $dirty === []) {
            return;
        }

        $modelClass = $this->modelClass();
        $synced = [];

        foreach ($dirty as $clickId) {
            $leadData = Cache::pull(self::LEAD_DATA_PREFIX.$clickId);

            /** @var HasConversions&Model $lead */
            $lead = $modelClass::query()
                ->where('gclid', $clickId)
                ->orWhere('gbraid', $clickId)
                ->orWhere('wbraid', $clickId)
                ->first();

            if (! $lead) {
                $lead = new $modelClass;
                // Identify default column to assign clickId
                if (isset($leadData['gbraid'])) {
                    $lead->setGbraid($clickId);
                } elseif (isset($leadData['wbraid'])) {
                    $lead->setWbraid($clickId);
                } else {
                    $lead->setGclid($clickId);
                }
            }

            if ($leadData) {
                $lead->fillTrackingData($leadData);
            }

            $cached = Cache::pull(self::CACHE_PREFIX.$clickId);

            if (! empty($cached)) {
                $existing = $lead->getConversions();

                foreach ($cached as $entry) {
                    $duplicate = $existing->contains(
                        fn ($item) => ($item['event'] ?? null) === $entry['event']
                            && ($item['timestamp'] ?? null) === $entry['timestamp']
                            && ($item['order_id'] ?? null) === ($entry['order_id'] ?? null),
                    );

                    if (! $duplicate) {
                        $existing->push($entry);
                    }
                }

                $lead->setConversions($existing);
            }

            if ($lead->isModified()) {
                $lead->persist();
            }

            $synced[] = $clickId;
        }

        // Clean up dirty set safely
        $currentDirty = Cache::get(self::DIRTY_SET_KEY, []);
        if (is_array($currentDirty)) {
            $remaining = array_values(array_diff($currentDirty, $synced));
            if (empty($remaining)) {
                Cache::forget(self::DIRTY_SET_KEY);
            } else {
                Cache::put(self::DIRTY_SET_KEY, $remaining, now()->addDays(self::BUFFER_TTL_DAYS));
            }
        }

        Log::info('[GoogleAdsConversions] Synced '.count($synced).' leads/conversions to database.');

        ConversionsSynced::dispatch($synced);
    }

    /**
     * GDPR Right to Erasure: Permanently delete all leads for a given visitor ID.
     */
    public function forgetVisitor(string $visitorId): int
    {
        return (int) $this->modelClass()::query()
            ->where('visitor_id', $visitorId)
            ->delete();
    }

    /**
     * Find a click identifier for the current request.
     */
    protected function resolveIdentifier(string $type): ?string
    {
        $sessionKeys = (array) config('google-ads-conversions.session_keys', [
            'gclid' => 'google_ads_gclid',
            'gbraid' => 'google_ads_gbraid',
            'wbraid' => 'google_ads_wbraid',
        ]);
        $cookieConfig = (array) config('google-ads-conversions.cookies');

        $sessionKey = $sessionKeys[$type] ?? config('google-ads-conversions.session_key', 'google_ads_gclid');
        $cookieKey = $cookieConfig[$type] ?? 'google_ads_'.$type;
        $visitorCookie = $cookieConfig['visitor_id'] ?? 'google_ads_visitor_id';

        if ($val = Session::get($sessionKey)) {
            return $val;
        }

        $request = request();

        if ($val = $request->cookie($cookieKey)) {
            return $val;
        }

        if ($visitorId = $request->cookie($visitorCookie)) {
            $lead = $this->modelClass()::query()
                ->where('visitor_id', $visitorId)
                ->whereNotNull($type)
                ->latest()
                ->first();

            if ($lead instanceof HasConversions) {
                return match ($type) {
                    'gbraid' => $lead->getGbraid(),
                    'wbraid' => $lead->getWbraid(),
                    default => $lead->getGclid(),
                };
            }
        }

        return null;
    }

    protected function pushToCache(string $clickId, array $conversion): void
    {
        $key = self::CACHE_PREFIX.$clickId;
        $pending = Cache::get($key, []);

        if (! is_array($pending)) {
            $pending = [];
        }

        $pending[] = $conversion;

        Cache::put($key, $pending, now()->addDays(self::BUFFER_TTL_DAYS));

        $this->markDirty($clickId);

        Log::info("[GoogleAdsConversions] Cached conversion '{$conversion['event']}' for click ID '{$clickId}'");
    }

    protected function markDirty(string $clickId): void
    {
        $dirty = Cache::get(self::DIRTY_SET_KEY, []);

        if (! is_array($dirty)) {
            $dirty = [];
        }

        if (! in_array($clickId, $dirty, true)) {
            $dirty[] = $clickId;
            Cache::put(self::DIRTY_SET_KEY, $dirty, now()->addDays(self::BUFFER_TTL_DAYS));
        }
    }

    /**
     * @return class-string<HasConversions&Model>
     */
    protected function modelClass(): string
    {
        return config('google-ads-conversions.model', Lead::class);
    }
}
