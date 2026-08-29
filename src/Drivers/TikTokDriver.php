<?php

namespace ElectricTomCat\GoogleAdsConversions\Drivers;

use ElectricTomCat\GoogleAdsConversions\Contracts\ConversionDriverInterface;
use ElectricTomCat\GoogleAdsConversions\DTO\ConversionPayload;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TikTokDriver implements ConversionDriverInterface
{
    public function name(): string
    {
        return 'tiktok';
    }

    public function isConfigured(): bool
    {
        return ! empty(config('google-ads-conversions.tiktok.access_token'))
            && ! empty(config('google-ads-conversions.tiktok.pixel_code'));
    }

    public function upload(array $conversions, bool $validateOnly = false): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'count' => 0,
                'errors' => ['TikTok credentials are not configured.'],
                'raw_response' => null,
            ];
        }

        if (empty($conversions)) {
            return ['success' => true, 'count' => 0, 'errors' => [], 'raw_response' => null];
        }

        $accessToken = config('google-ads-conversions.tiktok.access_token');
        $pixelCode = config('google-ads-conversions.tiktok.pixel_code');

        $events = [];
        foreach ($conversions as $item) {
            $payload = $item instanceof ConversionPayload ? $item : ConversionPayload::fromArray((array) $item);

            $user = [];
            if (! empty($payload->userData['email'])) {
                $user['email'] = hash('sha256', strtolower(trim($payload->userData['email'])));
            }
            if (! empty($payload->userData['phone'])) {
                $cleanPhone = preg_replace('/[^\d+]/', '', trim($payload->userData['phone']));
                $user['phone_number'] = hash('sha256', $cleanPhone);
            }
            if ($payload->ttclid) {
                $user['ttclid'] = $payload->ttclid;
            }

            $eventItem = [
                'event' => $payload->eventName,
                'event_time' => $payload->timestamp,
                'user' => $user,
            ];

            if ($payload->orderId !== null) {
                $eventItem['event_id'] = (string) $payload->orderId;
            }

            if ($payload->value !== null) {
                $eventItem['properties'] = [
                    'value' => (float) $payload->value,
                    'currency' => $payload->currency ?? 'USD',
                ];
            }

            $events[] = $eventItem;
        }

        $body = [
            'pixel_code' => $pixelCode,
            'event_source' => 'web',
            'event_source_id' => $pixelCode,
            'data' => $events,
        ];

        try {
            $response = Http::withHeaders(['Access-Token' => $accessToken])
                ->post('https://business-api.tiktok.com/open_api/v1.3/event/track/', $body);

            if ($response->successful() && (int) $response->json('code') === 0) {
                $count = count($events);
                Log::info("[TikTok] Uploaded {$count} event(s) to TikTok Events API.");

                return ['success' => true, 'count' => $count, 'errors' => [], 'raw_response' => $response->json()];
            }

            $msg = $response->json('message') ?? $response->body();

            return ['success' => false, 'count' => 0, 'errors' => [$msg], 'raw_response' => $response->json()];
        } catch (\Throwable $e) {
            return ['success' => false, 'count' => 0, 'errors' => [$e->getMessage()], 'raw_response' => null];
        }
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Missing TikTok credentials (tiktok.access_token or tiktok.pixel_code).',
            ];
        }

        return [
            'success' => true,
            'message' => 'TikTok configured with Pixel Code: '.config('google-ads-conversions.tiktok.pixel_code'),
        ];
    }
}
