<?php

namespace ElectricTomCat\GoogleAdsConversions\Drivers;

use ElectricTomCat\GoogleAdsConversions\Contracts\ConversionDriverInterface;
use ElectricTomCat\GoogleAdsConversions\DTO\ConversionPayload;
use ElectricTomCat\GoogleAdsConversions\Support\UserDataHasher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TikTokDriver implements ConversionDriverInterface
{
    public function __construct(protected ?UserDataHasher $hasher = null)
    {
        $this->hasher ??= new UserDataHasher;
    }

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
                // Shared normalisation: lowercase, trim, and Gmail dot/plus
                // handling, so the same person hashes identically everywhere.
                if ($hashed = $this->hasher->hashEmail((string) $payload->userData['email'])) {
                    $user['email'] = $hashed;
                }
            }
            if (! empty($payload->userData['phone'])) {
                if ($hashed = $this->hasher->hashPhone((string) $payload->userData['phone'])) {
                    $user['phone_number'] = $hashed;
                }
            }
            if ($payload->ttclid) {
                $user['ttclid'] = $payload->ttclid;
            }
            // TikTok weights IP and user agent heavily for match quality.
            if (! empty($payload->userData['client_ip'])) {
                $user['ip'] = $payload->userData['client_ip'];
            }
            if (! empty($payload->userData['client_user_agent'])) {
                $user['user_agent'] = $payload->userData['client_user_agent'];
            }

            $eventItem = [
                'event' => $payload->eventName,
                'event_time' => $payload->timestamp,
                'user' => $user,
            ];

            if ($payload->eventSourceUrl) {
                $eventItem['page'] = ['url' => $payload->eventSourceUrl];
            }

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

    /**
     * Verify the token with a real authenticated call.
     *
     * Reporting success because config values are non-empty told the user
     * nothing about whether a revoked or mistyped token would work.
     */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Missing TikTok credentials (tiktok.access_token or tiktok.pixel_code).',
            ];
        }

        $pixelCode = config('google-ads-conversions.tiktok.pixel_code');

        try {
            $response = Http::withHeaders(['Access-Token' => config('google-ads-conversions.tiktok.access_token')])
                ->timeout(10)
                ->get('https://business-api.tiktok.com/open_api/v1.3/user/info/');

            $code = (int) $response->json('code', -1);

            if (! $response->successful() || $code !== 0) {
                return [
                    'success' => false,
                    'message' => 'TikTok rejected the access token: '.($response->json('message') ?? $response->body()),
                ];
            }

            $displayName = $response->json('data.display_name');

            return [
                'success' => true,
                'message' => 'TikTok authenticated'.($displayName ? " as {$displayName}" : '')." for pixel {$pixelCode}",
                'details' => ['pixel_code' => $pixelCode],
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'TikTok connection failed: '.$e->getMessage()];
        }
    }
}
