<?php

namespace ElectricTomCat\GoogleAdsConversions;

use ElectricTomCat\GoogleAdsConversions\Contracts\HasConversions;
use ElectricTomCat\GoogleAdsConversions\Events\ConversionsUploaded;
use ElectricTomCat\GoogleAdsConversions\Events\ConversionUploadFailed;
use ElectricTomCat\GoogleAdsConversions\Models\Lead;
use ElectricTomCat\GoogleAdsConversions\Support\ConsentManager;
use ElectricTomCat\GoogleAdsConversions\Support\EventResolver;
use ElectricTomCat\GoogleAdsConversions\Support\UserDataHasher;
use Google\Ads\GoogleAds\Lib\OAuth2TokenBuilder;
use Google\Ads\GoogleAds\Lib\V23\GoogleAdsClient;
use Google\Ads\GoogleAds\Lib\V23\GoogleAdsClientBuilder;
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
    public function __construct(
        protected EventResolver $events,
        protected ConsentManager $consentManager,
        protected UserDataHasher $hasher,
    ) {}

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
                /** @var HasConversions&Model $lead */
                foreach ($leads as $lead) {
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

            if ($hasPartialFailure) {
                Log::error('[GoogleAdsConversions] Partial failure in batch upload: '.$partialFailureMessage);
            }

            // Group conversions back by lead model to persist status updates in single transactions
            $leadsMap = [];
            $uploadedClickIds = [];

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

                $clickId = $lead->getGclid() ?? $lead->getGbraid() ?? $lead->getWbraid() ?? 'unknown';

                // In validate-only mode or successful request
                $leadsMap[$leadId]['conversions'][$index]['status'] = 'uploaded';
                $leadsMap[$leadId]['conversions'][$index]['uploaded_at'] = now()->timestamp;
                if ($isValidateOnly) {
                    $leadsMap[$leadId]['conversions'][$index]['validate_only'] = true;
                }

                $uploadedClickIds[] = $clickId;
            }

            foreach ($leadsMap as $entry) {
                /** @var HasConversions&Model $lead */
                $lead = $entry['lead'];
                $lead->setConversions($entry['conversions']);
                $lead->persist();
            }

            $count = count($clicks);
            Log::info("[GoogleAdsConversions] Successfully processed {$count} conversions".($isValidateOnly ? ' (validate_only)' : ''));

            ConversionsUploaded::dispatch($count, array_unique($uploadedClickIds));

            return $count;
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

        // Assign correct click identifier (gbraid / wbraid / gclid)
        $gbraid = $conversion['gbraid'] ?? $lead->getGbraid();
        $wbraid = $conversion['wbraid'] ?? $lead->getWbraid();
        $gclid = $conversion['gclid'] ?? $lead->getGclid();

        if ($gbraid) {
            $click->setGbraid($gbraid);
        } elseif ($wbraid) {
            $click->setWbraid($wbraid);
        } elseif ($gclid) {
            $click->setGclid($gclid);
        }

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

        // Attach Enhanced Conversions for Leads (hashed user identifiers) if enabled
        if (! empty($conversion['user_identifiers']) && config('google-ads-conversions.enhanced_conversions.enabled', false)) {
            $identifiers = $this->hasher->hashUserIdentifiers($conversion['user_identifiers']);
            if (! empty($identifiers)) {
                $click->setUserIdentifiers($identifiers);
            }
        }

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

            $escapedAction = str_replace("'", "\\'", $action);
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

    protected function client(): GoogleAdsClient
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
