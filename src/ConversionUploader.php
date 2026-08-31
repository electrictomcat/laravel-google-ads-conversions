<?php

namespace ElectricTomCat\GoogleAdsConversions;

use ElectricTomCat\GoogleAdsConversions\Contracts\HasConversions;
use ElectricTomCat\GoogleAdsConversions\DTO\ConversionPayload;
use ElectricTomCat\GoogleAdsConversions\Events\ConversionsUploaded;
use ElectricTomCat\GoogleAdsConversions\Events\ConversionUploadFailed;
use ElectricTomCat\GoogleAdsConversions\Models\Lead;
use ElectricTomCat\GoogleAdsConversions\Support\ClickIdentifier;
use ElectricTomCat\GoogleAdsConversions\Support\ConsentManager;
use ElectricTomCat\GoogleAdsConversions\Support\EventResolver;
use ElectricTomCat\GoogleAdsConversions\Support\UserDataHasher;
use Google\Ads\GoogleAds\Lib\OAuth2TokenBuilder;
use Google\Ads\GoogleAds\Lib\V23\GoogleAdsClient;
use Google\Ads\GoogleAds\Lib\V23\GoogleAdsClientBuilder;
use Google\Ads\GoogleAds\V23\Enums\OfflineConversionDiagnosticStatusEnum\OfflineConversionDiagnosticStatus;
use Google\Ads\GoogleAds\V23\Errors\GoogleAdsFailure;
use Google\Ads\GoogleAds\V23\Services\ClickConversion;
use Google\Ads\GoogleAds\V23\Services\SearchGoogleAdsRequest;
use Google\Ads\GoogleAds\V23\Services\UploadClickConversionsRequest;
use Google\Ads\GoogleAds\V23\Services\UploadClickConversionsResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Talks to the Google Ads API: builds the SDK client, batches pending
 * conversions across leads, and posts them via UploadClickConversions.
 */
class ConversionUploader
{
    protected ?GoogleAdsClient $client = null;

    public function __construct(
        protected EventResolver $events,
        protected ConsentManager $consentManager,
        protected UserDataHasher $hasher,
    ) {}

    /**
     * Drop the memoized API client. Useful in tests and long-running workers
     * where credentials may be swapped between runs.
     */
    public function forgetClient(): void
    {
        $this->client = null;
    }

    /**
     * Find every lead with at least one pending conversion older than
     * the configured delay, then upload in global batches.
     *
     * @param  int|null  $forceDelayHours  Override the upload delay window
     * @param  bool|null  $validateOnly  Override dry-run validation mode
     * @return int Number of uploaded conversions
     */
    public function uploadPendingConversions(?int $forceDelayHours = null, ?bool $validateOnly = null): int
    {
        $delayHours = $forceDelayHours ?? (int) config('google-ads-conversions.upload_delay_hours', 6);
        $threshold = now()->subHours($delayHours);
        $batchSize = (int) config('google-ads-conversions.batch_size', 2000);

        $modelClass = $this->modelClass();
        $batchItems = [];
        $totalUploaded = 0;

        $modelClass::query()
            ->whereNotNull('conversions')
            ->chunkById(100, function ($leads) use (&$batchItems, &$totalUploaded, $threshold, $batchSize, $validateOnly) {
                foreach ($leads as $lead) {
                    // A custom model may not implement the contract; skip
                    // rather than fatal halfway through a sweep.
                    if (! $lead instanceof HasConversions) {
                        continue;
                    }

                    $conversions = $lead->getConversions();

                    foreach ($conversions as $index => $conversion) {
                        if (($conversion['status'] ?? '') !== 'pending') {
                            continue;
                        }

                        if (($conversion['timestamp'] ?? 0) > $threshold->timestamp) {
                            continue;
                        }

                        $action = $this->events->action($conversion['event']);

                        if (! $action) {
                            Log::warning("[GoogleAdsConversions] No conversion action mapped for event: {$conversion['event']}");

                            continue;
                        }

                        $resourceName = $this->resolveActionResourceName($action);

                        if (! $resourceName) {
                            Log::warning("[GoogleAdsConversions] Could not resolve resource name for action: {$action}");

                            continue;
                        }

                        $click = $this->buildClickConversion($lead, $conversion, $resourceName);

                        // Google needs either a click identifier or hashed user
                        // identifiers. Sending a conversion with neither is a
                        // guaranteed rejection, so skip it here rather than
                        // burning a slot in the batch.
                        if (! $this->isAttributable($click)) {
                            Log::warning(
                                "[GoogleAdsConversions] Skipping '{$conversion['event']}': no click identifier and "
                                .'no user identifiers to attribute it with.'
                            );

                            continue;
                        }

                        $batchItems[] = [
                            'lead' => $lead,
                            'index' => $index,
                            'conversion' => $conversion,
                            'click' => $click,
                        ];

                        if (count($batchItems) >= $batchSize) {
                            $totalUploaded += $this->processBatch($batchItems, $validateOnly);
                            $batchItems = [];
                        }
                    }
                }
            });

        if (! empty($batchItems)) {
            $totalUploaded += $this->processBatch($batchItems, $validateOnly);
        }

        return $totalUploaded;
    }

    /**
     * Upload a specific set of payloads, without touching the pending queue.
     *
     * Used by the driver interface, where the caller has already decided which
     * conversions to send.
     *
     * @param  array<int, ConversionPayload>  $payloads
     * @return array{count: int, errors: array<int, string>}
     */
    public function uploadPayloads(array $payloads, bool $validateOnly = false): array
    {
        $clicks = [];
        $errors = [];

        foreach ($payloads as $payload) {
            $action = $this->events->action($payload->eventName);

            if (! $action) {
                $errors[] = "No conversion action mapped for event: {$payload->eventName}";

                continue;
            }

            $resourceName = $this->resolveActionResourceName($action);

            if (! $resourceName) {
                $errors[] = "Could not resolve resource name for action: {$action}";

                continue;
            }

            $click = $this->buildClickConversionFromPayload($payload, $resourceName);

            if (! $this->isAttributable($click)) {
                $errors[] = "Conversion '{$payload->eventName}' has no click identifier and no user identifiers.";

                continue;
            }

            $clicks[] = $click;
        }

        if ($clicks === []) {
            return ['count' => 0, 'errors' => $errors];
        }

        try {
            $service = $this->client()->getConversionUploadServiceClient();

            $request = UploadClickConversionsRequest::build($this->customerId(), $clicks, true);

            if ($validateOnly) {
                $request->setValidateOnly(true);
            }

            /** @var UploadClickConversionsResponse $response */
            $response = $service->uploadClickConversions($request);

            $rejected = $response->hasPartialFailureError()
                ? $this->rejectedIndexes($response, count($clicks))
                : [];

            foreach ($rejected as $message) {
                $errors[] = $message;
            }

            return ['count' => count($clicks) - count($rejected), 'errors' => $errors];
        } catch (\Throwable $e) {
            Log::error('[GoogleAdsConversions] Payload upload error: '.$e->getMessage());
            $errors[] = $e->getMessage();

            return ['count' => 0, 'errors' => $errors];
        }
    }

    /**
     * Make one cheap authenticated call to confirm the credentials work.
     *
     * @return array{success: bool, message: string, descriptive_name: ?string, currency: ?string, time_zone: ?string}
     */
    public function probeAccount(): array
    {
        try {
            $service = $this->client()->getGoogleAdsServiceClient();

            $query = 'SELECT customer.descriptive_name, customer.currency_code, customer.time_zone '
                   .'FROM customer LIMIT 1';

            $response = $service->search(SearchGoogleAdsRequest::build($this->customerId(), $query));

            foreach ($response->iterateAllElements() as $row) {
                $customer = $row->getCustomer();

                return [
                    'success' => true,
                    'message' => 'OK',
                    'descriptive_name' => $customer?->getDescriptiveName(),
                    'currency' => $customer?->getCurrencyCode(),
                    'time_zone' => $customer?->getTimeZone(),
                ];
            }

            return [
                'success' => false,
                'message' => 'Authenticated, but the account returned no rows.',
                'descriptive_name' => null,
                'currency' => null,
                'time_zone' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'descriptive_name' => null,
                'currency' => null,
                'time_zone' => null,
            ];
        }
    }

    /**
     * What Google reports about the offline conversions we have uploaded.
     *
     * Google exposes an account-level health summary rather than a per-
     * conversion outcome, so this answers "are our uploads landing" and "what
     * is being rejected", not "did this specific order attribute".
     *
     * @return array{ok: bool, message: string, rows: array<int, array{status: string, successful_count: int, failed_count: int, last_upload: string|null, alerts: array<int, array{error: string, rate: float}>}>}
     */
    public function uploadDiagnostics(): array
    {
        try {
            $service = $this->client()->getGoogleAdsServiceClient();

            $query = 'SELECT '
                .'offline_conversion_upload_client_summary.client, '
                .'offline_conversion_upload_client_summary.status, '
                .'offline_conversion_upload_client_summary.total_event_count, '
                .'offline_conversion_upload_client_summary.successful_event_count, '
                .'offline_conversion_upload_client_summary.pending_event_count, '
                .'offline_conversion_upload_client_summary.last_upload_date_time, '
                .'offline_conversion_upload_client_summary.alerts '
                .'FROM offline_conversion_upload_client_summary';

            $response = $service->search(SearchGoogleAdsRequest::build($this->customerId(), $query));

            $rows = [];

            foreach ($response->iterateAllElements() as $row) {
                $summary = $row->getOfflineConversionUploadClientSummary();

                if (! $summary) {
                    continue;
                }

                $total = (int) $summary->getTotalEventCount();
                $successful = (int) $summary->getSuccessfulEventCount();

                $alerts = [];

                foreach ($summary->getAlerts() as $alert) {
                    $alerts[] = [
                        'error' => $this->describeUploadError($alert),
                        'rate' => (float) $alert->getErrorPercentage(),
                    ];
                }

                $rows[] = [
                    'status' => $this->describeStatus($summary->getStatus()),
                    'successful_count' => $successful,
                    'failed_count' => max(0, $total - $successful),
                    'last_upload' => $summary->getLastUploadDateTime() ?: null,
                    'alerts' => $alerts,
                ];
            }

            return ['ok' => true, 'message' => 'OK', 'rows' => $rows];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'rows' => []];
        }
    }

    /**
     * The protobuf enums here vary by API version, so read them defensively -
     * a diagnostic that fatals is worse than one that says "UNKNOWN".
     */
    protected function describeStatus(mixed $status): string
    {
        try {
            if (is_int($status)) {
                return (string) OfflineConversionDiagnosticStatus::name($status);
            }

            return (string) $status;
        } catch (\Throwable) {
            return 'UNKNOWN';
        }
    }

    protected function describeUploadError(mixed $alert): string
    {
        try {
            $error = $alert->getError();

            foreach (['getCollectionSizeError', 'getConversionUploadError', 'getConversionAdjustmentUploadError'] as $accessor) {
                if (method_exists($error, $accessor) && $error->{$accessor}() !== null) {
                    return (string) $error->{$accessor}();
                }
            }

            return (string) json_encode($error);
        } catch (\Throwable) {
            return 'UNKNOWN_ERROR';
        }
    }

    /**
     * Process and upload an aggregate batch of conversions across leads.
     *
     * @param  array<int, array{lead: HasConversions&Model, index: int, conversion: array<string, mixed>, click: ClickConversion}>  $batchItems
     */
    public function processBatch(array $batchItems, ?bool $validateOnly = null): int
    {
        if (empty($batchItems)) {
            return 0;
        }

        $clicks = array_column($batchItems, 'click');

        return $this->uploadBatch($batchItems, $clicks, $validateOnly);
    }

    /**
     * Upload click conversions to Google Ads API.
     *
     * @param  array<int, array{lead: HasConversions&Model, index: int, conversion: array<string, mixed>, click: ClickConversion}>  $batchItems
     * @param  array<int, ClickConversion>  $clicks
     */
    public function uploadBatch(array $batchItems, array $clicks, ?bool $validateOnly = null): int
    {
        $isValidateOnly = $validateOnly ?? (bool) config('google-ads-conversions.validate_only', false);

        try {
            $client = $this->client();
            $service = $client->getConversionUploadServiceClient();

            $request = UploadClickConversionsRequest::build(
                $this->customerId(),
                $clicks,
                true, // partial_failure
            );

            if ($isValidateOnly) {
                $request->setValidateOnly(true);
            }

            /** @var UploadClickConversionsResponse $response */
            $response = $service->uploadClickConversions($request);

            $hasPartialFailure = $response->hasPartialFailureError();
            $partialFailureMessage = $hasPartialFailure
                ? $response->getPartialFailureError()->getMessage()
                : null;

            $rejected = $hasPartialFailure
                ? $this->rejectedIndexes($response, count($clicks))
                : [];

            if ($hasPartialFailure) {
                Log::error(
                    '[GoogleAdsConversions] Partial failure in batch upload ('.count($rejected).' of '
                    .count($clicks).' rejected): '.$partialFailureMessage
                );
            }

            // A validate-only run must not touch stored state. Marking these
            // 'uploaded' would retire conversions Google never actually
            // recorded, so the documented dry run would silently destroy the
            // pending queue.
            if ($isValidateOnly) {
                $accepted = count($clicks) - count($rejected);
                Log::info("[GoogleAdsConversions] Validated {$accepted} conversion(s) (validate_only; nothing persisted).");

                foreach ($rejected as $i => $reason) {
                    $item = $batchItems[$i] ?? null;
                    if ($item) {
                        ConversionUploadFailed::dispatch($this->clickIdFor($item['lead']), $reason, $item['conversion']);
                    }
                }

                return $accepted;
            }

            // Group conversions back by lead model to persist status updates in single transactions
            $leadsMap = [];
            $uploadedClickIds = [];
            $uploaded = 0;

            foreach ($batchItems as $i => $item) {
                /** @var HasConversions&Model $lead */
                $lead = $item['lead'];
                $index = $item['index'];
                $leadId = $lead->getKey() ?? spl_object_hash($lead);

                if (! isset($leadsMap[$leadId])) {
                    $leadsMap[$leadId] = [
                        'lead' => $lead,
                        'conversions' => $lead->getConversions()->toArray(),
                    ];
                }

                $clickId = $this->clickIdFor($lead);

                if (array_key_exists($i, $rejected)) {
                    // Google refused this row. Leave it pending so the next run
                    // retries it, and record why on the entry so the dashboard
                    // and the failure event can surface it.
                    $leadsMap[$leadId]['conversions'][$index]['status'] = 'failed';
                    $leadsMap[$leadId]['conversions'][$index]['failed_at'] = now()->timestamp;
                    $leadsMap[$leadId]['conversions'][$index]['error'] = $rejected[$i];

                    ConversionUploadFailed::dispatch($clickId, $rejected[$i], $item['conversion']);

                    continue;
                }

                $leadsMap[$leadId]['conversions'][$index]['status'] = 'uploaded';
                $leadsMap[$leadId]['conversions'][$index]['uploaded_at'] = now()->timestamp;

                $uploadedClickIds[] = $clickId;
                $uploaded++;
            }

            foreach ($leadsMap as $entry) {
                /** @var HasConversions&Model $lead */
                $lead = $entry['lead'];
                $lead->setConversions($entry['conversions']);
                $lead->persist();
            }

            Log::info("[GoogleAdsConversions] Successfully uploaded {$uploaded} conversion(s).");

            if ($uploaded > 0) {
                ConversionsUploaded::dispatch($uploaded, array_unique($uploadedClickIds));
            }

            return $uploaded;
        } catch (\Throwable $e) {
            Log::error('[GoogleAdsConversions] Batch API upload error: '.$e->getMessage());

            foreach ($batchItems as $item) {
                $clickId = $item['lead']->getGclid() ?? $item['lead']->getGbraid() ?? $item['lead']->getWbraid() ?? 'unknown';
                ConversionUploadFailed::dispatch($clickId, $e->getMessage(), $item['conversion']);
            }

            return 0;
        }
    }

    /**
     * Map a partial-failure response to the batch indexes Google rejected.
     *
     * `partial_failure_error` carries a GoogleAdsFailure whose errors each
     * point at the operation index that produced them. Without unpacking it,
     * a rejected conversion is indistinguishable from an accepted one.
     *
     * @return array<int, string> index => error message
     */
    protected function rejectedIndexes(UploadClickConversionsResponse $response, int $batchSize): array
    {
        $rejected = [];

        try {
            $status = $response->getPartialFailureError();

            foreach ($status->getDetails() as $detail) {
                $failure = new GoogleAdsFailure;
                $failure->mergeFromString($detail->getValue());

                foreach ($failure->getErrors() as $error) {
                    $message = $error->getMessage();
                    $matched = false;

                    foreach ($error->getLocation()?->getFieldPathElements() ?? [] as $element) {
                        if ($element->getFieldName() === 'operations') {
                            $rejected[(int) $element->getIndex()] = $message;
                            $matched = true;
                        }
                    }

                    if (! $matched) {
                        Log::error('[GoogleAdsConversions] Unattributed partial-failure error: '.$message);
                    }
                }
            }
        } catch (\Throwable $e) {
            // If the failure detail cannot be decoded we cannot tell which rows
            // Google kept. Treating the whole batch as failed is the safe
            // reading: those conversions stay pending and are retried, rather
            // than being retired on an assumption.
            Log::error('[GoogleAdsConversions] Could not decode partial failure detail: '.$e->getMessage());

            return array_fill(0, $batchSize, 'Undecodable partial failure; conversion left pending for retry.');
        }

        return $rejected;
    }

    /**
     * Build a ClickConversion from a standalone payload (driver interface).
     */
    protected function buildClickConversionFromPayload(ConversionPayload $payload, string $resourceName): ClickConversion
    {
        $click = new ClickConversion([
            'conversion_action' => $resourceName,
            'conversion_date_time' => date('Y-m-d H:i:sP', $payload->timestamp),
            'currency_code' => $payload->currency ?? config('google-ads-conversions.default_currency', 'USD'),
        ]);

        $identifierType = $this->assignClickIdentifier($click, $payload->gclid, $payload->gbraid, $payload->wbraid);

        if ($payload->value !== null) {
            $click->setConversionValue((float) $payload->value);
        }

        if ($payload->orderId !== null) {
            $click->setOrderId((string) $payload->orderId);
        }

        $consent = $this->consentManager->resolveConsentObject(
            is_array($payload->consent) ? $payload->consent : null
        );
        if ($consent !== null) {
            $click->setConsent($consent);
        }

        $this->attachUserIdentifiers($click, $payload->userData, $identifierType);

        return $click;
    }

    /**
     * Put the click identifier in the field Google expects.
     *
     * Two things go wrong here often enough to be worth handling explicitly.
     *
     * Precedence: a gclid identifies a single click and attributes precisely,
     * while gbraid and wbraid are the coarser privacy-preserving fallbacks for
     * iOS traffic. A lead can accumulate more than one - arriving on Search
     * once and from an app campaign later - and the gclid must win. This used
     * to prefer gbraid, quietly downgrading attribution for those visitors.
     *
     * Misfiling: an untyped accessor makes it easy to hand a gbraid to the
     * gclid argument, and Google answers "The imported gclid could not be
     * decoded". A braid-shaped value is therefore moved to the field it
     * belongs in rather than being sent somewhere it is certain to be
     * rejected. Set click_identifiers.autocorrect to false to send it as-is.
     */
    protected function assignClickIdentifier(
        ClickConversion $click,
        ?string $gclid,
        ?string $gbraid,
        ?string $wbraid,
    ): ?string {
        if ($gclid && ClickIdentifier::looksLikeBraid($gclid)) {
            Log::warning(
                "[GoogleAdsConversions] '{$gclid}' was supplied as a gclid but looks like a gbraid/wbraid. "
                .'Google rejects these. Store a ClickIdentifier rather than clickId() so the type travels '
                .'with the value.'
            );

            if (config('google-ads-conversions.click_identifiers.autocorrect', true)) {
                $gbraid ??= $gclid;
                $gclid = null;
            }
        }

        // gclid first: it is the most precise identifier Google accepts.
        // Exactly one is ever set - historically Google rejected a
        // ClickConversion carrying more than one.
        if ($gclid) {
            $click->setGclid($gclid);

            return ClickIdentifier::GCLID;
        }

        if ($gbraid) {
            $click->setGbraid($gbraid);

            return ClickIdentifier::GBRAID;
        }

        if ($wbraid) {
            $click->setWbraid($wbraid);

            return ClickIdentifier::WBRAID;
        }

        return null;
    }

    /**
     * Attach hashed identifiers for Enhanced Conversions for Leads.
     *
     * Google refuses these alongside a gbraid or wbraid - the combination
     * returns VALUE_MUST_BE_UNSET and the whole row is rejected - so the
     * identifiers are dropped rather than losing the conversion. The click
     * identifier is the stronger signal of the two, and it is the one Google
     * will actually attribute on.
     *
     * @param  array<string, mixed>  $userData
     */
    protected function attachUserIdentifiers(ClickConversion $click, array $userData, ?string $identifierType): void
    {
        if (empty($userData) || ! config('google-ads-conversions.enhanced_conversions.enabled', false)) {
            return;
        }

        if (in_array($identifierType, [ClickIdentifier::GBRAID, ClickIdentifier::WBRAID], true)) {
            Log::info(
                '[GoogleAdsConversions] Enhanced-conversion identifiers omitted: Google does not accept them '
                ."alongside a {$identifierType}. The {$identifierType} was sent on its own."
            );

            return;
        }

        $identifiers = $this->hasher->hashUserIdentifiers($userData);

        if (! empty($identifiers)) {
            $click->setUserIdentifiers($identifiers);
        }
    }

    protected function clickIdFor(HasConversions $lead): string
    {
        return $lead->getGclid() ?? $lead->getGbraid() ?? $lead->getWbraid() ?? 'unknown';
    }

    /**
     * Whether Google has anything to match this conversion against.
     */
    protected function isAttributable(ClickConversion $click): bool
    {
        return $click->getGclid() !== ''
            || $click->getGbraid() !== ''
            || $click->getWbraid() !== ''
            || $click->getUserIdentifiers()->count() > 0;
    }

    /**
     * Construct a Google Ads ClickConversion protobuf object from lead data.
     *
     * @param  array<string, mixed>  $conversion
     */
    protected function buildClickConversion(HasConversions $lead, array $conversion, string $resourceName): ClickConversion
    {
        $click = new ClickConversion([
            'conversion_action' => $resourceName,
            'conversion_date_time' => date('Y-m-d H:i:sP', $conversion['timestamp']),
            'currency_code' => $conversion['currency'] ?? config('google-ads-conversions.default_currency', 'USD'),
        ]);

        $identifierType = $this->assignClickIdentifier(
            $click,
            $conversion['gclid'] ?? $lead->getGclid(),
            $conversion['gbraid'] ?? $lead->getGbraid(),
            $conversion['wbraid'] ?? $lead->getWbraid(),
        );

        if (isset($conversion['value'])) {
            $click->setConversionValue((float) $conversion['value']);
        }

        if (! empty($conversion['order_id'])) {
            $click->setOrderId((string) $conversion['order_id']);
        }

        // Attach Google Consent Mode v2 signals if present or configured
        $consent = $this->consentManager->resolveConsentObject($conversion['consent'] ?? null);
        if ($consent !== null) {
            $click->setConsent($consent);
        }

        $this->attachUserIdentifiers($click, $conversion['user_identifiers'] ?? [], $identifierType);

        return $click;
    }

    /**
     * Translate a conversion-action name (or short ID) to its full
     * resource name. Caches only non-null results to prevent poisoning.
     */
    public function resolveActionResourceName(string $action): ?string
    {
        if (preg_match('/^customers\/\d+\/conversionActions\/\d+$/', $action) === 1) {
            return $action;
        }

        $customerId = $this->customerId();
        $cacheKey = "google_ads_conversion_action:{$customerId}:".md5($action);

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return (string) $cached;
        }

        try {
            $client = $this->client();
            $service = $client->getGoogleAdsServiceClient();

            // Backslash first, then the quote — escaping the quote first would
            // leave a trailing backslash able to escape its own escape and
            // break out of the literal.
            $escapedAction = str_replace(['\\', "'"], ['\\\\', "\\'"], $action);
            $query = 'SELECT conversion_action.resource_name '
                   .'FROM conversion_action '
                   ."WHERE conversion_action.name = '{$escapedAction}'";

            $response = $service->search(SearchGoogleAdsRequest::build($customerId, $query));

            foreach ($response->iterateAllElements() as $row) {
                $resourceName = $row->getConversionAction()->getResourceName();
                if ($resourceName) {
                    Cache::put($cacheKey, $resourceName, now()->addDays(7));

                    return $resourceName;
                }
            }
        } catch (\Throwable $e) {
            Log::error("[GoogleAdsConversions] Failed to resolve action '{$action}': ".$e->getMessage());
        }

        return null;
    }

    /**
     * The Google Ads client for this instance.
     *
     * Memoized because a batch run resolves one conversion action per distinct
     * event and then uploads, and each of those previously rebuilt the OAuth
     * credential and the whole client from scratch.
     */
    protected function client(): GoogleAdsClient
    {
        return $this->client ??= $this->buildClient();
    }

    protected function buildClient(): GoogleAdsClient
    {
        $oauth = (new OAuth2TokenBuilder)
            ->withClientId(config('google-ads-conversions.client_id'))
            ->withClientSecret(config('google-ads-conversions.client_secret'))
            ->withRefreshToken(config('google-ads-conversions.refresh_token'))
            ->build();

        $builder = (new GoogleAdsClientBuilder)
            ->withDeveloperToken(config('google-ads-conversions.developer_token'))
            ->withOAuth2Credential($oauth);

        $loginCustomerId = config('google-ads-conversions.login_customer_id')
            ?? config('google-ads-conversions.customer_id');

        if (! empty($loginCustomerId)) {
            $builder->withLoginCustomerId((int) str_replace('-', '', (string) $loginCustomerId));
        }

        return $builder->build();
    }

    protected function customerId(): string
    {
        return (string) config('google-ads-conversions.customer_id');
    }

    /**
     * @return class-string<HasConversions&Model>
     */
    protected function modelClass(): string
    {
        return config('google-ads-conversions.model', Lead::class);
    }
}
