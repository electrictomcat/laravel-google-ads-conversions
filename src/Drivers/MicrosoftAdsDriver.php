<?php

namespace ElectricTomCat\GoogleAdsConversions\Drivers;

use ElectricTomCat\GoogleAdsConversions\Contracts\ConversionDriverInterface;
use ElectricTomCat\GoogleAdsConversions\DTO\ConversionPayload;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Microsoft Advertising offline conversions.
 *
 * Uses the Campaign Management v13 REST surface:
 *   POST /CampaignManagement/v13/OfflineConversions/Apply
 *
 * A previous release posted to a CustomerManagement path that does not exist,
 * and omitted the required CustomerAccountId header, so no conversion this
 * driver produced ever reached Microsoft.
 *
 * @see https://learn.microsoft.com/en-us/advertising/campaign-management-service/applyofflineconversions?view=bingads-13
 */
class MicrosoftAdsDriver implements ConversionDriverInterface
{
    protected const APPLY_URL = 'https://campaign.api.bingads.microsoft.com/CampaignManagement/v13/OfflineConversions/Apply';

    protected const USER_QUERY_URL = 'https://clientcenter.api.bingads.microsoft.com/CustomerManagement/v13/User/Query';

    /** Microsoft accepts at most 1,000 offline conversions per request. */
    protected const MAX_PER_REQUEST = 1000;

    public function name(): string
    {
        return 'microsoft';
    }

    public function isConfigured(): bool
    {
        return ! empty(config('google-ads-conversions.microsoft.developer_token'))
            && ! empty(config('google-ads-conversions.microsoft.customer_id'))
            && ! empty(config('google-ads-conversions.microsoft.account_id'))
            && ! empty(config('google-ads-conversions.microsoft.access_token'));
    }

    /**
     * @param  array<int, ConversionPayload|array<string, mixed>>  $conversions
     * @return array{success: bool, count: int, errors: array<int, string>, raw_response: mixed}
     */
    public function upload(array $conversions, bool $validateOnly = false): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'count' => 0,
                'errors' => ['Microsoft Ads credentials are not configured (developer_token, customer_id, account_id, access_token).'],
                'raw_response' => null,
            ];
        }

        $items = [];

        foreach ($conversions as $item) {
            $payload = $item instanceof ConversionPayload ? $item : ConversionPayload::fromArray((array) $item);

            if (! $payload->msclkid) {
                continue;
            }

            $entry = [
                'MicrosoftClickId' => $payload->msclkid,
                'ConversionName' => $payload->eventName,
                // Microsoft requires UTC with a Z suffix, not an offset.
                'ConversionTime' => gmdate('Y-m-d\TH:i:s\Z', $payload->timestamp),
            ];

            if ($payload->value !== null) {
                $entry['ConversionValue'] = (float) $payload->value;
                $entry['ConversionCurrencyCode'] = $payload->currency ?? config('google-ads-conversions.default_currency', 'USD');
            }

            $items[] = $entry;
        }

        if ($items === []) {
            return ['success' => true, 'count' => 0, 'errors' => [], 'raw_response' => null];
        }

        if ($validateOnly) {
            // Microsoft has no validate-only mode for this operation. Report
            // what would have been sent rather than sending it.
            Log::info('[MicrosoftAds] validate_only: '.count($items).' conversion(s) built but not sent.');

            return ['success' => true, 'count' => count($items), 'errors' => [], 'raw_response' => null];
        }

        $uploaded = 0;
        $errors = [];
        $last = null;

        foreach (array_chunk($items, self::MAX_PER_REQUEST) as $chunk) {
            try {
                $response = $this->request()->post(self::APPLY_URL, ['OfflineConversions' => $chunk]);
                $last = $response->json();

                if (! $response->successful()) {
                    $errors[] = 'HTTP '.$response->status().': '.$response->body();

                    continue;
                }

                // Per-item failures come back in PartialErrors, each carrying
                // the index of the entry that produced it.
                $partial = $response->json('PartialErrors') ?? [];
                foreach ($partial as $error) {
                    $index = $error['Index'] ?? '?';
                    $message = $error['Message'] ?? ($error['ErrorCode'] ?? 'Unknown error');
                    $errors[] = "Item {$index}: {$message}";
                }

                $uploaded += count($chunk) - count($partial);
            } catch (\Throwable $e) {
                Log::error('[MicrosoftAds] Exception: '.$e->getMessage());
                $errors[] = $e->getMessage();
            }
        }

        if ($uploaded > 0) {
            Log::info("[MicrosoftAds] Uploaded {$uploaded} conversion(s).");
        }

        return [
            'success' => $errors === [],
            'count' => $uploaded,
            'errors' => $errors,
            'raw_response' => $last,
        ];
    }

    /**
     * Verify the credentials with a real authenticated call.
     *
     * GetUser needs only the token and developer token, which makes it the
     * cheapest way to prove the credentials are live.
     */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Missing Microsoft Ads credentials (developer_token, customer_id, account_id, or access_token).',
            ];
        }

        try {
            $response = Http::withToken(config('google-ads-conversions.microsoft.access_token'))
                ->withHeaders(['DeveloperToken' => config('google-ads-conversions.microsoft.developer_token')])
                ->timeout(10)
                ->post(self::USER_QUERY_URL, ['UserId' => null]);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'message' => 'Microsoft rejected the credentials (HTTP '.$response->status().'): '.$response->body(),
                ];
            }

            $userName = $response->json('User.Name.FirstName');

            return [
                'success' => true,
                'message' => 'Microsoft Ads authenticated'.($userName ? " as {$userName}" : '')
                    .' for account '.config('google-ads-conversions.microsoft.account_id'),
                'details' => [
                    'customer_id' => config('google-ads-conversions.microsoft.customer_id'),
                    'account_id' => config('google-ads-conversions.microsoft.account_id'),
                ],
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Microsoft Ads connection failed: '.$e->getMessage()];
        }
    }

    protected function request(): PendingRequest
    {
        return Http::withToken(config('google-ads-conversions.microsoft.access_token'))
            ->withHeaders([
                'DeveloperToken' => config('google-ads-conversions.microsoft.developer_token'),
                'CustomerId' => (string) config('google-ads-conversions.microsoft.customer_id'),
                'CustomerAccountId' => (string) config('google-ads-conversions.microsoft.account_id'),
            ])
            ->timeout(30);
    }
}
