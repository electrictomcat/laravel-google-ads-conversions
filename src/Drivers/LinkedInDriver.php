<?php

namespace ElectricTomCat\GoogleAdsConversions\Drivers;

use ElectricTomCat\GoogleAdsConversions\Contracts\ConversionDriverInterface;
use ElectricTomCat\GoogleAdsConversions\DTO\ConversionPayload;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LinkedInDriver implements ConversionDriverInterface
{
    public function name(): string
    {
        return 'linkedin';
    }

    public function isConfigured(): bool
    {
        return ! empty(config('google-ads-conversions.linkedin.access_token'))
            && ! empty(config('google-ads-conversions.linkedin.conversion_rule_id'));
    }

    public function upload(array $conversions, bool $validateOnly = false): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'count' => 0,
                'errors' => ['LinkedIn credentials are not configured.'],
                'raw_response' => null,
            ];
        }

        $accessToken = config('google-ads-conversions.linkedin.access_token');
        $conversionRuleId = config('google-ads-conversions.linkedin.conversion_rule_id');

        $uploaded = 0;
        $errors = [];

        foreach ($conversions as $item) {
            $payload = $item instanceof ConversionPayload ? $item : ConversionPayload::fromArray((array) $item);

            $userIdentifiers = [];
            if (! empty($payload->userData['email'])) {
                $userIdentifiers[] = [
                    'idType' => 'SHA256_EMAIL',
                    'idValue' => hash('sha256', strtolower(trim($payload->userData['email']))),
                ];
            }
            if ($payload->liFatId) {
                $userIdentifiers[] = [
                    'idType' => 'LINKEDIN_FIRST_PARTY_ADS_TRACKING_UUID',
                    'idValue' => $payload->liFatId,
                ];
            }

            if (empty($userIdentifiers)) {
                continue;
            }

            $body = [
                'conversion' => "urn:lla:llaPartnerConversion:{$conversionRuleId}",
                'conversionHappenedAt' => $payload->timestamp * 1000,
                'user' => ['userIds' => $userIdentifiers],
            ];

            if ($payload->value !== null) {
                $body['totalBudget'] = [
                    'amount' => (string) $payload->value,
                    'currencyCode' => $payload->currency ?? 'USD',
                ];
            }

            try {
                $response = Http::withToken($accessToken)
                    ->withHeaders([
                        'X-Restli-Protocol-Version' => '2.0.0',
                        'LinkedIn-Version' => '202401',
                    ])
                    ->post('https://api.linkedin.com/rest/conversionEvents', $body);

                if ($response->successful() || $response->status() === 201) {
                    $uploaded++;
                } else {
                    $errors[] = $response->body();
                }
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        Log::info("[LinkedIn] Uploaded {$uploaded} conversion(s) to LinkedIn CAPI.");

        return [
            'success' => $uploaded > 0 || empty($errors),
            'count' => $uploaded,
            'errors' => $errors,
            'raw_response' => null,
        ];
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Missing LinkedIn credentials (linkedin.access_token or linkedin.conversion_rule_id).',
            ];
        }

        return [
            'success' => true,
            'message' => 'LinkedIn configured with Conversion Rule: '.config('google-ads-conversions.linkedin.conversion_rule_id'),
        ];
    }
}
