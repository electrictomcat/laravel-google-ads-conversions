<?php

namespace ElectricTomCat\GoogleAdsConversions;

use DateTimeInterface;
use ElectricTomCat\GoogleAdsConversions\Contracts\HasConversions;
use ElectricTomCat\GoogleAdsConversions\Events\ConversionRecorded;
use ElectricTomCat\GoogleAdsConversions\Events\ConversionsSynced;
use ElectricTomCat\GoogleAdsConversions\Models\Lead;
use ElectricTomCat\GoogleAdsConversions\Support\ClickIdentifier;
use ElectricTomCat\GoogleAdsConversions\Support\EventResolver;
use ElectricTomCat\GoogleAdsConversions\Support\UserDataHasher;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
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

    /**
     * Legacy single-key dirty set.
     *
     * Nothing writes here any more — writes are sharded across
     * {@see self::DIRTY_BUCKET_PREFIX} buckets — but syncToDatabase() still
     * drains it so buffers written by an older release are not stranded on
     * upgrade. Safe to remove one release after v2.
     */
    public const DIRTY_SET_KEY = 'google_ads_dirty_leads';

    public const DIRTY_BUCKET_PREFIX = 'google_ads_dirty_leads:';

    /**
     * Number of shards the dirty set is spread across.
     *
     * A single shared array meant every ad-referred pageview read, unserialised,
     * appended to and rewrote the entire set. Sharding keeps each array small
     * and cuts lock contention proportionally.
     */
    public const DIRTY_BUCKETS = 16;

    /**
     * Prefix for conversions recorded against a visitor rather than a click ID
     * (Enhanced Conversions for Leads, where hashed identifiers stand in for a
     * gclid).
     */
    public const VISITOR_KEY_PREFIX = 'visitor:';

    public const BUFFER_TTL_DAYS = 2;

    /** Seconds a buffer-mutation lock is held before it self-expires. */
    protected const LOCK_TTL = 5;

    /** Seconds to wait for a contended buffer lock before giving up. */
    protected const LOCK_WAIT = 3;

    protected ?string $memoizedGclid = null;

    protected ?string $memoizedGbraid = null;

    protected ?string $memoizedWbraid = null;

    protected bool $gclidMemoized = false;

    protected bool $gbraidMemoized = false;

    protected bool $wbraidMemoized = false;

    /**
     * Memoized result of the per-visitor history lookup.
     *
     * @var array{gclid: string|null, gbraid: string|null, wbraid: string|null}|null
     */
    protected ?array $visitorHistory = null;

    protected bool $visitorHistoryLoaded = false;

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
     * Any resolved click identifier, as an opaque string.
     *
     * @deprecated Prefer clickIdentifier(): this loses which of the three
     *             kinds the value is, and Google rejects a gbraid placed in
     *             the gclid field. Kept so existing call sites keep working.
     */
    public function clickId(): ?string
    {
        return $this->clickIdentifier()?->value;
    }

    /**
     * Any resolved click identifier, carrying its type.
     *
     * Store this rather than clickId() when you keep the identifier on your
     * own records: passing it back later tells record() which of Google's
     * three fields the value belongs in.
     */
    public function clickIdentifier(): ?ClickIdentifier
    {
        if ($gclid = $this->gclid()) {
            return ClickIdentifier::gclid($gclid);
        }

        if ($gbraid = $this->gbraid()) {
            return ClickIdentifier::gbraid($gbraid);
        }

        if ($wbraid = $this->wbraid()) {
            return ClickIdentifier::wbraid($wbraid);
        }

        return null;
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
        $this->visitorHistory = null;
        $this->visitorHistoryLoaded = false;
    }

    /**
     * Record a conversion event for the current visitor.
     *
     * Returns false when the conversion could not be attributed to anything —
     * no click identifier, and no hashed identifiers to fall back on.
     *
     * @param  string  $eventName  Internal event name or Google Ads action name
     * @param  float|null  $value  Monetary conversion value
     * @param  string|null  $currency  ISO 4217 currency code (e.g. 'USD', 'EUR')
     * @param  ClickIdentifier|string|null  $gclid  A GCLID, or a ClickIdentifier
     *                                              of any kind, which is routed
     *                                              to the correct field
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
        ClickIdentifier|string|null $gclid = null,
        ?string $gbraid = null,
        ?string $wbraid = null,
        ?string $orderId = null,
        DateTimeInterface|int|string|null $conversionDateTime = null,
        array|bool|null $consent = null,
        array $userIdentifiers = [],
    ): bool {
        // A ClickIdentifier knows which of Google's three fields it belongs
        // in, so unpack it rather than assuming the caller passed a gclid.
        if ($gclid instanceof ClickIdentifier) {
            ['gclid' => $gclid, 'gbraid' => $unpackedGbraid, 'wbraid' => $unpackedWbraid] = $gclid->toArguments();
            $gbraid ??= $unpackedGbraid;
            $wbraid ??= $unpackedWbraid;
        }

        $enhancedEnabled = (bool) config('google-ads-conversions.enhanced_conversions.enabled', false);
        $hasIdentifiers = $enhancedEnabled && ! empty($this->hasher->hashUserIdentifiers($userIdentifiers));

        $resolvedClickId = $gclid ?? $gbraid ?? $wbraid ?? $this->clickIdentifier()?->value;
        $bufferKey = $resolvedClickId;
        $identifierOnly = false;

        // Enhanced Conversions for Leads can be uploaded on hashed identifiers
        // alone. When there is no click ID but we do have identifiers and a
        // visitor to hang them off, record against the visitor instead of
        // dropping the conversion.
        if (! $bufferKey && $hasIdentifiers) {
            if ($visitorId = $this->currentVisitorId()) {
                $bufferKey = self::VISITOR_KEY_PREFIX.$visitorId;
                $identifierOnly = true;
            }
        }

        if (! $bufferKey) {
            Log::warning(
                "[GoogleAdsConversions] Failed to record '{$eventName}': no GCLID, GBRAID, or WBRAID found in "
                .'override, session, cookie, or visitor history, and no hashed user identifiers to fall back on.'
            );

            return false;
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
        if ($identifierOnly) {
            $conversionEntry['identifier_only'] = true;
        }

        if ($consent !== null) {
            $conversionEntry['consent'] = is_array($consent)
                ? $consent
                : ['ad_user_data' => $consent, 'ad_personalization' => $consent];
        }

        if (! empty($userIdentifiers) && $enhancedEnabled) {
            $conversionEntry['user_identifiers'] = $userIdentifiers;
        }

        $this->pushToCache($bufferKey, $conversionEntry);

        ConversionRecorded::dispatch($bufferKey, $conversionEntry);

        return true;
    }

    /**
     * Buffer creation/update data for a lead in cache, to be flushed
     * to the database by the next syncToDatabase() run.
     *
     * @param  array<string, mixed>  $data
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
     * Every click identifier currently awaiting a flush to the database.
     *
     * @return array<int, string>
     */
    public function pendingClickIds(): array
    {
        $keys = [];

        for ($bucket = 0; $bucket < self::DIRTY_BUCKETS; $bucket++) {
            $entries = Cache::get(self::DIRTY_BUCKET_PREFIX.$bucket, []);

            if (is_array($entries)) {
                $keys = array_merge($keys, $entries);
            }
        }

        // Drain anything left behind by a pre-sharding release.
        $legacy = Cache::get(self::DIRTY_SET_KEY, []);
        if (is_array($legacy)) {
            $keys = array_merge($keys, $legacy);
        }

        return array_values(array_unique($keys));
    }

    /**
     * Flush the cache buffer to the database, creating or updating
     * one model per dirty click identifier.
     *
     * Buffers are only discarded once the corresponding row has been written.
     * A failure on one lead leaves that lead's buffer intact for the next run
     * and does not abort the sweep.
     */
    public function syncToDatabase(): void
    {
        $pending = $this->pendingClickIds();

        if ($pending === []) {
            return;
        }

        $synced = [];
        $failed = 0;

        foreach ($pending as $clickId) {
            try {
                $this->syncOne($clickId);
                $synced[] = $clickId;
            } catch (\Throwable $e) {
                $failed++;
                Log::error("[GoogleAdsConversions] Failed to sync click ID '{$clickId}': ".$e->getMessage());
            }
        }

        foreach ($synced as $clickId) {
            $this->clearDirty($clickId);
        }

        $message = '[GoogleAdsConversions] Synced '.count($synced).' leads/conversions to database.';
        if ($failed > 0) {
            $message .= " {$failed} left buffered for the next run after errors.";
        }
        Log::info($message);

        ConversionsSynced::dispatch($synced);
    }

    /**
     * GDPR Right to Erasure: permanently delete all leads for a given visitor ID,
     * along with anything still buffered in cache for them.
     *
     * Deleting only the rows would let the next syncToDatabase() run recreate
     * the record from the buffer, so the buffer has to go first.
     */
    public function forgetVisitor(string $visitorId): int
    {
        $modelClass = $this->modelClass();

        $leads = $modelClass::query()
            ->where('visitor_id', $visitorId)
            ->get();

        $bufferKeys = [self::VISITOR_KEY_PREFIX.$visitorId];

        foreach ($leads as $lead) {
            if (! $lead instanceof HasConversions) {
                continue;
            }

            foreach ([$lead->getGclid(), $lead->getGbraid(), $lead->getWbraid()] as $clickId) {
                if ($clickId) {
                    $bufferKeys[] = $clickId;
                }
            }
        }

        foreach (array_unique($bufferKeys) as $key) {
            Cache::forget(self::CACHE_PREFIX.$key);
            Cache::forget(self::LEAD_DATA_PREFIX.$key);
            $this->clearDirty($key);
        }

        return (int) $modelClass::query()
            ->where('visitor_id', $visitorId)
            ->delete();
    }

    /**
     * Persist one buffered click identifier, then discard its buffers.
     *
     * @throws \Throwable when the row cannot be written — the caller leaves the
     *                    buffer in place so the conversion is retried.
     */
    protected function syncOne(string $clickId): void
    {
        $leadData = Cache::get(self::LEAD_DATA_PREFIX.$clickId);
        $cached = Cache::get(self::CACHE_PREFIX.$clickId);

        $modelClass = $this->modelClass();
        $isVisitorKey = str_starts_with($clickId, self::VISITOR_KEY_PREFIX);

        /** @var (HasConversions&Model)|null $lead */
        $lead = $isVisitorKey
            ? $modelClass::query()
                ->where('visitor_id', substr($clickId, strlen(self::VISITOR_KEY_PREFIX)))
                ->latest()
                ->first()
            : $modelClass::query()
                // Grouped so a global scope (SoftDeletes, tenancy) is not
                // escaped by the trailing OR conditions.
                ->where(function ($query) use ($clickId) {
                    $query->where('gclid', $clickId)
                        ->orWhere('gbraid', $clickId)
                        ->orWhere('wbraid', $clickId);
                })
                ->first();

        if (! $lead) {
            $lead = new $modelClass;

            if ($isVisitorKey) {
                $lead->setVisitorId(substr($clickId, strlen(self::VISITOR_KEY_PREFIX)));
            } else {
                match ($this->identifierTypeFor($clickId, $leadData, $cached)) {
                    ClickIdentifier::GBRAID => $lead->setGbraid($clickId),
                    ClickIdentifier::WBRAID => $lead->setWbraid($clickId),
                    default => $lead->setGclid($clickId),
                };
            }
        }

        if ($leadData) {
            $lead->fillTrackingData($leadData);
        }

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

        // Only now that the row is safely written do the buffers go.
        Cache::forget(self::LEAD_DATA_PREFIX.$clickId);
        Cache::forget(self::CACHE_PREFIX.$clickId);
    }

    /**
     * Work out which of Google's three columns a click identifier belongs in.
     *
     * This used to look only at the middleware's buffered tracking data and
     * default to gclid otherwise - so a gbraid recorded through record()
     * without the middleware, or after that buffer's two-day TTL had expired,
     * was written to the gclid column. Google then answers "The imported gclid
     * could not be decoded" and the conversion is lost.
     *
     * The recorded conversions carry the type too, so they are consulted
     * before falling back to the value's own shape.
     *
     * @param  array<string, mixed>|null  $leadData
     * @param  array<int, array<string, mixed>>|null  $cached
     */
    protected function identifierTypeFor(string $clickId, ?array $leadData, ?array $cached): string
    {
        foreach ([ClickIdentifier::GBRAID, ClickIdentifier::WBRAID, ClickIdentifier::GCLID] as $type) {
            if (isset($leadData[$type]) && $leadData[$type] === $clickId) {
                return $type;
            }
        }

        foreach ($cached ?? [] as $conversion) {
            foreach ([ClickIdentifier::GBRAID, ClickIdentifier::WBRAID, ClickIdentifier::GCLID] as $type) {
                if (isset($conversion[$type]) && $conversion[$type] === $clickId) {
                    return $type;
                }
            }
        }

        // Nothing said which it is. A braid-shaped value is certainly not a
        // gclid, so guessing gclid would guarantee a rejection.
        return ClickIdentifier::looksLikeBraid($clickId)
            ? ClickIdentifier::GBRAID
            : ClickIdentifier::GCLID;
    }

    /**
     * The visitor ID cookie for the current request, if any.
     */
    protected function currentVisitorId(): ?string
    {
        $cookieConfig = (array) config('google-ads-conversions.cookies');
        $name = $cookieConfig['visitor_id'] ?? 'google_ads_visitor_id';

        $value = request()->cookie($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Find a click identifier for the current request.
     *
     * Session and cookie are checked first; only if both miss do we fall back
     * to the visitor's stored history, and that lookup runs at most once per
     * instance no matter how many identifiers are asked for.
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

        if ($val = Session::get($sessionKey)) {
            return is_string($val) ? $val : null;
        }

        if ($val = request()->cookie($cookieKey)) {
            return is_string($val) ? $val : null;
        }

        return $this->visitorHistory()[$type] ?? null;
    }

    /**
     * The most recent stored click identifiers for the current visitor.
     *
     * One query resolves all three columns; previously each accessor issued its
     * own, so a page rendering @googleAdsClickInputs cost three queries just to
     * establish that a visitor had no attribution at all.
     *
     * @return array{gclid: string|null, gbraid: string|null, wbraid: string|null}
     */
    protected function visitorHistory(): array
    {
        if ($this->visitorHistoryLoaded) {
            return $this->visitorHistory ?? ['gclid' => null, 'gbraid' => null, 'wbraid' => null];
        }

        $this->visitorHistoryLoaded = true;
        $this->visitorHistory = ['gclid' => null, 'gbraid' => null, 'wbraid' => null];

        $visitorId = $this->currentVisitorId();

        if (! $visitorId) {
            return $this->visitorHistory;
        }

        /** @var (HasConversions&Model)|null $lead */
        $lead = $this->modelClass()::query()
            ->where('visitor_id', $visitorId)
            ->where(function ($query) {
                $query->whereNotNull('gclid')
                    ->orWhereNotNull('gbraid')
                    ->orWhereNotNull('wbraid');
            })
            ->latest()
            ->first();

        if ($lead instanceof HasConversions) {
            $this->visitorHistory = [
                'gclid' => $lead->getGclid(),
                'gbraid' => $lead->getGbraid(),
                'wbraid' => $lead->getWbraid(),
            ];
        }

        return $this->visitorHistory;
    }

    /**
     * @param  array<string, mixed>  $conversion
     */
    protected function pushToCache(string $clickId, array $conversion): void
    {
        $key = self::CACHE_PREFIX.$clickId;

        $this->mutate($key, function (mixed $pending) use ($conversion): array {
            $pending = is_array($pending) ? $pending : [];
            $pending[] = $conversion;

            return $pending;
        });

        $this->markDirty($clickId);

        Log::info("[GoogleAdsConversions] Cached conversion '{$conversion['event']}' for click ID '{$clickId}'");
    }

    protected function markDirty(string $clickId): void
    {
        $this->mutate($this->dirtyBucketKey($clickId), function (mixed $dirty) use ($clickId): array {
            $dirty = is_array($dirty) ? $dirty : [];

            if (! in_array($clickId, $dirty, true)) {
                $dirty[] = $clickId;
            }

            return $dirty;
        });
    }

    /**
     * Remove a click identifier from the dirty set once it has been persisted.
     */
    protected function clearDirty(string $clickId): void
    {
        $remove = function (mixed $dirty) use ($clickId): array {
            $dirty = is_array($dirty) ? $dirty : [];

            return array_values(array_filter($dirty, fn ($id) => $id !== $clickId));
        };

        $this->mutate($this->dirtyBucketKey($clickId), $remove);
        $this->mutate(self::DIRTY_SET_KEY, $remove);
    }

    protected function dirtyBucketKey(string $clickId): string
    {
        return self::DIRTY_BUCKET_PREFIX.(crc32($clickId) % self::DIRTY_BUCKETS);
    }

    /**
     * Read-modify-write a cache entry atomically where the store supports locks.
     *
     * Without this, two simultaneous requests read the same array, and the
     * second write erases the first — silently dropping a click ID and every
     * conversion buffered under it.
     *
     * @param  callable(mixed): array<mixed>  $mutator
     */
    protected function mutate(string $key, callable $mutator): void
    {
        $apply = function () use ($key, $mutator): void {
            $next = $mutator(Cache::get($key));

            if ($next === []) {
                Cache::forget($key);

                return;
            }

            Cache::put($key, $next, now()->addDays(self::BUFFER_TTL_DAYS));
        };

        if (! Cache::getStore() instanceof LockProvider) {
            // File and some third-party stores have no lock primitive. The
            // write is still correct single-threaded; concurrency is the
            // caller's problem, and the README says so.
            $apply();

            return;
        }

        try {
            Cache::lock('lock:'.$key, self::LOCK_TTL)->block(self::LOCK_WAIT, $apply);
        } catch (LockTimeoutException) {
            Log::warning("[GoogleAdsConversions] Timed out waiting on buffer lock for '{$key}'; writing unguarded.");
            $apply();
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
