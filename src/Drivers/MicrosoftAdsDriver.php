<?php

namespace ElectricTomCat\GoogleAdsConversions\Drivers;

use ElectricTomCat\GoogleAdsConversions\Contracts\ConversionDriverInterface;
use ElectricTomCat\GoogleAdsConversions\DTO\ConversionPayload;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MicrosoftAdsDriver implements ConversionDriverInterface
{
    public function name(): string
    {
        return 'microsoft';
    }

    public function isConfigured(): bool
    {
        return ! empty(config('google-ads-conversions.microsoft.developer_token'))
            && ! empty(config('google-ads-conversions.microsoft.customer_id'))
            && ! empty(config('google-ads-conversions.microsoft.access_token'));
    }

    public function upload(array $conversions, bool $validateOnly = false): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'count' => 0,
                'errors' => ['Microsoft Ads credentials are not configured.'],
                'raw_response' => null,
            ];
        }

        $items = [];
        foreach ($conversions as $item) {
            $payload = $item instanceof ConversionPayload ? $item : ConversionPayload::fromArray((array) $item);
            if ($payload->msclkid) {
                $items[] = [
                    'MicrosoftClickId' => $payload->msclkid,
                    'ConversionName' => $payload->eventName,
                    'ConversionTime' => date('c', $payload->timestamp),
                    'ConversionValue' => $payload->value,
                    'ConversionCurrencyCode' => $payload->currency,
                ];
            }
        }

        if (empty($items)) {
            return ['success' => true, 'count' => 0, 'errors' => [], 'raw_response' => null];
        }

        $developerToken = config('google-ads-conversions.microsoft.developer_token');
        $customerId = config('google-ads-conversions.microsoft.customer_id');
        $accessToken = config('google-ads-conversions.microsoft.access_token');

        $url = 'https://campaign.api.bingads.microsoft.com/Api/CustomerManagement/v13/OfflineConversions';

        try {
            $response = Http::withHeaders([
                'DeveloperToken' => $developerToken,
                'CustomerId' => $customerId,
                'AuthenticationToken' => $accessToken,
            ])->post($url, ['OfflineConversions' => $items]);

            if ($response->successful()) {
                $count = count($items);
                Log::info("[MicrosoftAds] Uploaded {$count} conversion(s) to Microsoft Ads.");

                return ['success' => true, 'count' => $count, 'errors' => [], 'raw_response' => $response->json()];
            }

            return ['success' => false, 'count' => 0, 'errors' => [$response->body()], 'raw_response' => $response->json()];
        } catch (\Throwable $e) {
            Log::error('[MicrosoftAds] Exception: '.$e->getMessage());

            return ['success' => false, 'count' => 0, 'errors' => [$e->getMessage()], 'raw_response' => null];
        }
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Missing Microsoft Ads credentials (developer_token, customer_id, or access_token).',
            ];
        }

        return [
            'success' => true,
            'message' => 'Microsoft Ads configured for Customer ID: '.config('google-ads-conversions.microsoft.customer_id'),
        ];
    }
}
