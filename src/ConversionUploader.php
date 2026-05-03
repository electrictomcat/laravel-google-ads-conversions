<?php

namespace ElectricTomCat\GoogleAdsConversions;

use ElectricTomCat\GoogleAdsConversions\Contracts\HasConversions;
use ElectricTomCat\GoogleAdsConversions\Models\Lead;
use ElectricTomCat\GoogleAdsConversions\Support\EventResolver;
use Google\Ads\GoogleAds\Lib\OAuth2TokenBuilder;
use Google\Ads\GoogleAds\Lib\V23\GoogleAdsClient;
use Google\Ads\GoogleAds\Lib\V23\GoogleAdsClientBuilder;
use Google\Ads\GoogleAds\V23\Services\ClickConversion;
use Google\Ads\GoogleAds\V23\Services\SearchGoogleAdsRequest;
use Google\Ads\GoogleAds\V23\Services\UploadClickConversionsRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Talks to the Google Ads API: builds the SDK client, batches pending
 * conversions per lead, and posts them via UploadClickConversions.
 */
class ConversionUploader
{
    public function __construct(protected EventResolver $events) {}

    /**
     * Find every lead with at least one pending conversion older than
     * the configured delay, then ship each lead's eligible batch.
     */
    public function uploadPendingConversions(): void
    {
        $delayHours = (int) config('google-ads-conversions.upload_delay_hours', 6);
        $threshold = now()->subHours($delayHours);

        $modelClass = $this->modelClass();

        $leads = $modelClass::query()->whereNotNull('conversions')->get();

        foreach ($leads as $lead) {
            if (! $lead instanceof HasConversions) {
                continue;
            }

            $conversions = $lead->getConversions();
            $batch = [];
            $indices = [];
            $hasPending = false;

            foreach ($conversions as $index => $conversion) {
                if (($conversion['status'] ?? '') !== 'pending') {
                    continue;
                }

                $hasPending = true;

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

                $click = new ClickConversion([
                    'conversion_action' => $resourceName,
                    'gclid' => $lead->getGclid(),
                    'conversion_date_time' => date('Y-m-d H:i:sP', $conversion['timestamp']),
                    'currency_code' => $conversion['currency'] ?? config('google-ads-conversions.default_currency', 'USD'),
                ]);

                if (isset($conversion['value'])) {
                    $click->setConversionValue((float) $conversion['value']);
                }

                $batch[] = $click;
                $indices[] = $index;
            }

            if (! $hasPending || $batch === []) {
                continue;
            }

            $this->uploadBatch($lead, $batch, $indices);
        }
    }

    /**
     * Upload a batch of click conversions for one lead, then mark
     * those conversion entries as 'uploaded' on the model.
     *
     * @param  array<int, ClickConversion>  $clickConversions
     * @param  array<int, int>  $indices
     */
    public function uploadBatch(HasConversions $lead, array $clickConversions, array $indices): void
    {
        try {
            $client = $this->client();
            $service = $client->getConversionUploadServiceClient();

            $request = UploadClickConversionsRequest::build(
                $this->customerId(),
                $clickConversions,
                true, // partial_failure
            );

            $response = $service->uploadClickConversions($request);

            if ($response->hasPartialFailureError()) {
                Log::error('[GoogleAdsConversions] Partial failure for GCLID '
                    .$lead->getGclid().': '
                    .$response->getPartialFailureError()->getMessage());
            }

            $conversions = $lead->getConversions()->toArray();

            foreach ($indices as $i) {
                $conversions[$i]['status'] = 'uploaded';
                $conversions[$i]['uploaded_at'] = now()->timestamp;
            }

            $lead->setConversions($conversions);
            $lead->persist();

            Log::info('[GoogleAdsConversions] Uploaded '.count($clickConversions)
                .' conversions for GCLID: '.$lead->getGclid());
        } catch (\Throwable $e) {
            Log::error('[GoogleAdsConversions] API error for GCLID '
                .$lead->getGclid().': '.$e->getMessage());
        }
    }

    /**
     * Translate a conversion-action name (or short ID) to its full
     * resource name. Caches the result so we don't query Google for
     * every upload run.
     */
    protected function resolveActionResourceName(string $action): ?string
    {
        if (preg_match('/^customers\/\d+\/conversionActions\/\d+$/', $action) === 1) {
            return $action;
        }

        $customerId = $this->customerId();
        $cacheKey = "google_ads_conversion_action:{$customerId}:".md5($action);

        return Cache::remember($cacheKey, now()->addDays(7), function () use ($action, $customerId): ?string {
            try {
                $client = $this->client();
                $service = $client->getGoogleAdsServiceClient();

                $query = 'SELECT conversion_action.resource_name '
                       .'FROM conversion_action '
                       ."WHERE conversion_action.name = '".addslashes($action)."'";

                $response = $service->search(SearchGoogleAdsRequest::build($customerId, $query));

                foreach ($response->iterateAllElements() as $row) {
                    return $row->getConversionAction()->getResourceName();
                }
            } catch (\Throwable $e) {
                Log::error("[GoogleAdsConversions] Failed to resolve action '{$action}': ".$e->getMessage());
            }

            return null;
        });
    }

    protected function client(): GoogleAdsClient
    {
        $oauth = (new OAuth2TokenBuilder)
            ->withClientId(config('google-ads-conversions.client_id'))
            ->withClientSecret(config('google-ads-conversions.client_secret'))
            ->withRefreshToken(config('google-ads-conversions.refresh_token'))
            ->build();

        return (new GoogleAdsClientBuilder)
            ->withDeveloperToken(config('google-ads-conversions.developer_token'))
            ->withLoginCustomerId((int) $this->customerId())
            ->withOAuth2Credential($oauth)
            ->build();
    }

    protected function customerId(): string
    {
        return (string) config('google-ads-conversions.customer_id');
    }

    /**
     * @return class-string<HasConversions&\Illuminate\Database\Eloquent\Model>
     */
    protected function modelClass(): string
    {
        return config('google-ads-conversions.model', Lead::class);
    }
}
