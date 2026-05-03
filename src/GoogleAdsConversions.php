<?php

namespace ElectricTomCat\GoogleAdsConversions;

use ElectricTomCat\GoogleAdsConversions\Contracts\HasConversions;
use ElectricTomCat\GoogleAdsConversions\Models\Lead;
use ElectricTomCat\GoogleAdsConversions\Support\EventResolver;
use Illuminate\Database\Eloquent\Model;
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

    public function __construct(protected EventResolver $events) {}

    /**
     * Record a conversion event for the current visitor.
     *
     * Either $value or a per-event config default may supply the value;
     * the call-site argument always wins. If neither is set, the
     * conversion is uploaded without a value.
     */
    public function record(
        string $eventName,
        ?float $value = null,
        ?string $currency = null,
        ?string $gclid = null,
    ): void {
        $gclid = $gclid ?? $this->resolveGclid();

        if (! $gclid) {
            Log::warning("[GoogleAdsConversions] Failed to record '{$eventName}': no GCLID found in override, session, cookie, or visitor history.");

            return;
        }

        $resolvedValue = $this->events->value($eventName, $value);
        $resolvedCurrency = $this->events->currency($eventName, $currency);

        $this->pushToCache($gclid, [
            'event' => $eventName,
            'timestamp' => now()->timestamp,
            'value' => $resolvedValue,
            'currency' => $resolvedCurrency,
            'status' => 'pending',
        ]);
    }

    /**
     * Buffer creation/update data for a lead in cache, to be flushed
     * to the database by the next syncToDatabase() run.
     */
    public function bufferLeadData(string $gclid, array $data): void
    {
        Cache::put(
            self::LEAD_DATA_PREFIX.$gclid,
            $data,
            now()->addDays(self::BUFFER_TTL_DAYS),
        );

        $this->markDirty($gclid);
    }

    /**
     * Flush the cache buffer to the database, creating or updating
     * one model per dirty gclid.
     */
    public function syncToDatabase(): void
    {
        $dirty = Cache::get(self::DIRTY_SET_KEY, []);

        if (! is_array($dirty) || $dirty === []) {
            return;
        }

        $modelClass = $this->modelClass();

        foreach ($dirty as $gclid) {
            $leadData = Cache::pull(self::LEAD_DATA_PREFIX.$gclid);

            /** @var HasConversions $lead */
            $lead = $modelClass::query()->where('gclid', $gclid)->first()
                ?? new $modelClass(['gclid' => $gclid]);

            if ($leadData) {
                $lead->fillTrackingData($leadData);
            }

            $cached = Cache::pull(self::CACHE_PREFIX.$gclid);

            if (! empty($cached)) {
                $existing = $lead->getConversions();

                foreach ($cached as $entry) {
                    $duplicate = $existing->contains(
                        fn ($item) => ($item['event'] ?? null) === $entry['event']
                            && ($item['timestamp'] ?? null) === $entry['timestamp'],
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
        }

        Cache::forget(self::DIRTY_SET_KEY);

        Log::info('[GoogleAdsConversions] Synced '.count($dirty).' leads/conversions to database.');
    }

    /**
     * Find a GCLID for the current request, in priority order:
     *   1. Session     (set by the middleware on the landing request)
     *   2. Cookie      (persists across sessions, set by the middleware)
     *   3. Visitor ID  (look up the most recent lead with this visitor's UUID)
     */
    protected function resolveGclid(): ?string
    {
        $sessionKey = config('google-ads-conversions.session_key', 'google_ads_gclid');
        $gclidCookie = config('google-ads-conversions.cookies.gclid', 'google_ads_gclid');
        $visitorCookie = config('google-ads-conversions.cookies.visitor_id', 'google_ads_visitor_id');

        if ($gclid = Session::get($sessionKey)) {
            return $gclid;
        }

        $request = request();

        if ($gclid = $request->cookie($gclidCookie)) {
            return $gclid;
        }

        if ($visitorId = $request->cookie($visitorCookie)) {
            $lead = $this->modelClass()::query()
                ->where('visitor_id', $visitorId)
                ->latest()
                ->first();

            if ($lead instanceof HasConversions) {
                return $lead->getGclid();
            }
        }

        return null;
    }

    protected function pushToCache(string $gclid, array $conversion): void
    {
        $key = self::CACHE_PREFIX.$gclid;
        $pending = Cache::get($key, []);

        if (! is_array($pending)) {
            $pending = [];
        }

        $pending[] = $conversion;

        Cache::put($key, $pending, now()->addDays(self::BUFFER_TTL_DAYS));

        $this->markDirty($gclid);

        Log::info("[GoogleAdsConversions] Cached conversion '{$conversion['event']}' for GCLID '{$gclid}'");
    }

    protected function markDirty(string $gclid): void
    {
        $dirty = Cache::get(self::DIRTY_SET_KEY, []);

        if (! is_array($dirty)) {
            $dirty = [];
        }

        if (! in_array($gclid, $dirty, true)) {
            $dirty[] = $gclid;
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
