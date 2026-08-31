<?php

namespace ElectricTomCat\GoogleAdsConversions\Drivers;

use ElectricTomCat\GoogleAdsConversions\Contracts\ConversionDriverInterface;
use ElectricTomCat\GoogleAdsConversions\ConversionUploader;
use ElectricTomCat\GoogleAdsConversions\DTO\ConversionPayload;
use Illuminate\Support\Facades\Log;

class GoogleAdsDriver implements ConversionDriverInterface
{
    public function __construct(protected ConversionUploader $uploader) {}

    public function name(): string
    {
        return 'google';
    }

    public function isConfigured(): bool
    {
        return ! empty(config('google-ads-conversions.developer_token'))
            && ! empty(config('google-ads-conversions.client_id'))
            && ! empty(config('google-ads-conversions.client_secret'))
            && ! empty(config('google-ads-conversions.refresh_token'))
            && ! empty(config('google-ads-conversions.customer_id'));
    }

    /**
     * Upload the given conversions — and only those.
     *
     * @param  array<int, ConversionPayload|array<string, mixed>>  $conversions
     * @return array{success: bool, count: int, errors: array<int, string>, raw_response: mixed}
     */
    public function upload(array $conversions, bool $validateOnly = false): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'count' => 0,
                'errors' => ['Google Ads credentials are not configured.'],
                'raw_response' => null,
            ];
        }

        if (empty($conversions)) {
            return ['success' => true, 'count' => 0, 'errors' => [], 'raw_response' => null];
        }

        $payloads = array_map(
            fn ($item) => $item instanceof ConversionPayload ? $item : ConversionPayload::fromArray((array) $item),
            $conversions,
        );

        try {
            $result = $this->uploader->uploadPayloads($payloads, $validateOnly);
        } catch (\Throwable $e) {
            Log::error('[GoogleAds] Upload failed: '.$e->getMessage());

            return ['success' => false, 'count' => 0, 'errors' => [$e->getMessage()], 'raw_response' => null];
        }

        return [
            'success' => $result['errors'] === [],
            'count' => $result['count'],
            'errors' => $result['errors'],
            'raw_response' => null,
        ];
    }

    /**
     * Flush every pending conversion in the database to Google Ads.
     *
     * This is the scheduled-sweep entry point. It is deliberately separate from
     * upload(), which handles only the payloads it is handed.
     */
    public function uploadPending(?int $forceDelayHours = null, ?bool $validateOnly = null): int
    {
        return $this->uploader->uploadPendingConversions($forceDelayHours, $validateOnly);
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Missing Google Ads credentials (developer_token, client_id, client_secret, refresh_token, or customer_id).',
            ];
        }

        $customerId = (string) config('google-ads-conversions.customer_id');

        // Actually call the API. Checking that config values are non-empty
        // proves nothing about whether the credentials still work.
        $probe = $this->uploader->probeAccount();

        if (! $probe['success']) {
            return [
                'success' => false,
                'message' => "Google Ads rejected the credentials for customer {$customerId}: ".$probe['message'],
            ];
        }

        return [
            'success' => true,
            'message' => "Google Ads authenticated as customer {$customerId}"
                .($probe['descriptive_name'] ? " ({$probe['descriptive_name']})" : ''),
            'details' => [
                'customer_id' => $customerId,
                'login_customer_id' => config('google-ads-conversions.login_customer_id') ?: '(direct)',
                'currency' => $probe['currency'],
                'time_zone' => $probe['time_zone'],
            ],
        ];
    }
}
