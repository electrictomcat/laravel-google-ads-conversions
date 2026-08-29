<?php

namespace ElectricTomCat\GoogleAdsConversions\Drivers;

use ElectricTomCat\GoogleAdsConversions\Contracts\ConversionDriverInterface;
use ElectricTomCat\GoogleAdsConversions\DTO\ConversionPayload;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaCapiDriver implements ConversionDriverInterface
{
    public function name(): string
    {
        return 'meta';
    }

    public function isConfigured(): bool
    {
        return ! empty(config('google-ads-conversions.meta.pixel_id'))
            && ! empty(config('google-ads-conversions.meta.access_token'));
    }

    public function upload(array $conversions, bool $validateOnly = false): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'count' => 0,
                'errors' => ['Meta Pixel ID or Access Token is not configured.'],
                'raw_response' => null,
            ];
        }

        if (empty($conversions)) {
            return ['success' => true, 'count' => 0, 'errors' => [], 'raw_response' => null];
        }

        $pixelId = config('google-ads-conversions.meta.pixel_id');
        $accessToken = config('google-ads-conversions.meta.access_token');
        $apiVersion = config('google-ads-conversions.meta.api_version', 'v20.0');

        $eventsData = [];

        foreach ($conversions as $item) {
            $payload = $item instanceof ConversionPayload ? $item : ConversionPayload::fromArray((array) $item);
            $eventsData[] = $this->formatMetaEvent($payload);
        }

        $body = ['data' => $eventsData];

        $testEventCode = config('google-ads-conversions.meta.test_event_code');
        if (! empty($testEventCode)) {
            $body['test_event_code'] = $testEventCode;
        }

        $url = "https://graph.facebook.com/{$apiVersion}/{$pixelId}/events";

        try {
            $response = Http::withToken($accessToken)
                ->timeout(15)
                ->post($url, $body);

            if ($response->successful()) {
                $count = count($eventsData);
                Log::info("[MetaCapi] Successfully posted {$count} event(s) to Meta Conversions API.");

                return [
                    'success' => true,
                    'count' => $count,
                    'errors' => [],
                    'raw_response' => $response->json(),
                ];
            }

            $errorMessage = $response->json('error.message') ?? $response->body();
            Log::error("[MetaCapi] API Error: {$errorMessage}");

            return [
                'success' => false,
                'count' => 0,
                'errors' => [$errorMessage],
                'raw_response' => $response->json(),
            ];
        } catch (\Throwable $e) {
            Log::error('[MetaCapi] Connection Exception: '.$e->getMessage());

            return [
                'success' => false,
                'count' => 0,
                'errors' => [$e->getMessage()],
                'raw_response' => null,
            ];
        }
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Missing Meta credentials (meta.pixel_id or meta.access_token in config).',
            ];
        }

        $pixelId = config('google-ads-conversions.meta.pixel_id');
        $accessToken = config('google-ads-conversions.meta.access_token');
        $apiVersion = config('google-ads-conversions.meta.api_version', 'v20.0');

        try {
            $response = Http::withToken($accessToken)
                ->get("https://graph.facebook.com/{$apiVersion}/{$pixelId}");

            if ($response->successful()) {
                $name = $response->json('name') ?? "Pixel ID {$pixelId}";

                return [
                    'success' => true,
                    'message' => "Successfully connected to Meta Pixel: {$name}",
                    'details' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'message' => 'Meta API returned error: '.($response->json('error.message') ?? $response->body()),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatMetaEvent(ConversionPayload $payload): array
    {
        $userData = [];
        $rawUser = $payload->userData;

        if (! empty($rawUser['email'])) {
            $userData['em'] = [hash('sha256', strtolower(trim($rawUser['email'])))];
        }

        $phone = $rawUser['phone'] ?? $rawUser['phone_number'] ?? null;
        if (! empty($phone)) {
            $cleanPhone = preg_replace('/[^\d+]/', '', trim($phone));
            $userData['ph'] = [hash('sha256', $cleanPhone)];
        }

        if (! empty($rawUser['first_name'])) {
            $userData['fn'] = [hash('sha256', strtolower(trim($rawUser['first_name'])))];
        }

        if (! empty($rawUser['last_name'])) {
            $userData['ln'] = [hash('sha256', strtolower(trim($rawUser['last_name'])))];
        }

        if (! empty($rawUser['client_ip'])) {
            $userData['client_ip_address'] = $rawUser['client_ip'];
        }

        if (! empty($rawUser['client_user_agent'])) {
            $userData['client_user_agent'] = $rawUser['client_user_agent'];
        }

        // Handle fbc / fbclid
        $fbc = $payload->fbc ?? $rawUser['fbc'] ?? null;
        if (! $fbc && $payload->fbclid) {
            $fbc = 'fb.1.'.$payload->timestamp.'.'.$payload->fbclid;
        }
        if ($fbc) {
            $userData['fbc'] = $fbc;
        }

        // Handle fbp
        $fbp = $payload->fbp ?? $rawUser['fbp'] ?? null;
        if ($fbp) {
            $userData['fbp'] = $fbp;
        }

        $customData = array_merge($payload->customData, array_filter([
            'value' => $payload->value,
            'currency' => $payload->currency,
        ]));

        $event = [
            'event_name' => $payload->eventName,
            'event_time' => $payload->timestamp,
            'action_source' => $payload->actionSource,
            'user_data' => $userData,
        ];

        if ($payload->orderId !== null) {
            $event['event_id'] = (string) $payload->orderId;
        }

        if ($payload->eventSourceUrl !== null) {
            $event['event_source_url'] = $payload->eventSourceUrl;
        }

        if (! empty($customData)) {
            $event['custom_data'] = $customData;
        }

        return $event;
    }
}
