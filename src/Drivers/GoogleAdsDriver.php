<?php

namespace ElectricTomCat\GoogleAdsConversions\Drivers;

use ElectricTomCat\GoogleAdsConversions\Contracts\ConversionDriverInterface;
use ElectricTomCat\GoogleAdsConversions\ConversionUploader;

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

        $count = $this->uploader->uploadPendingConversions(null, $validateOnly);

        return [
            'success' => true,
            'count' => $count,
            'errors' => [],
            'raw_response' => null,
        ];
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Missing Google Ads credentials (developer_token, client_id, client_secret, refresh_token, or customer_id).',
            ];
        }

        $customerId = config('google-ads-conversions.customer_id');

        return [
            'success' => true,
            'message' => "Google Ads configured for Customer ID: {$customerId}",
            'details' => [
                'customer_id' => $customerId,
                'login_customer_id' => config('google-ads-conversions.login_customer_id') ?: '(direct)',
            ],
        ];
    }
}
