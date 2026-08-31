<?php

namespace ElectricTomCat\GoogleAdsConversions\Drivers;

use ElectricTomCat\GoogleAdsConversions\Contracts\ConversionDriverInterface;
use ElectricTomCat\GoogleAdsConversions\DTO\ConversionPayload;
use ElectricTomCat\GoogleAdsConversions\Support\UserDataHasher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * LinkedIn Conversions API.
 *
 * @see https://learn.microsoft.com/en-us/linkedin/marketing/integrations/ads-reporting/conversions-api-schema
 */
class LinkedInDriver implements ConversionDriverInterface
{
    /**
     * Default LinkedIn API version (YYYYMM).
     *
     * LinkedIn supports a version for a minimum of one year and then retires
     * it, at which point every call returns 426. This is configurable so it
     * can be rolled forward without a package release — a pinned literal is
     * how the previous version silently expired.
     */
    public const DEFAULT_VERSION = '202608';

    public function __construct(protected ?UserDataHasher $hasher = null)
    {
        $this->hasher ??= new UserDataHasher;
    }

    public function name(): string
    {
        return 'linkedin';
    }

    public function isConfigured(): bool
    {
        return ! empty(config('google-ads-conversions.linkedin.access_token'))
            && ! empty(config('google-ads-conversions.linkedin.conversion_rule_id'));
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
                'errors' => ['LinkedIn credentials are not configured.'],
                'raw_response' => null,
            ];
        }

        $conversionRuleId = config('google-ads-conversions.linkedin.conversion_rule_id');

        $uploaded = 0;
        $skipped = 0;
        $errors = [];

        foreach ($conversions as $item) {
            $payload = $item instanceof ConversionPayload ? $item : ConversionPayload::fromArray((array) $item);

            $userIds = $this->userIds($payload);

            if ($userIds === []) {
                $skipped++;

                continue;
            }

            $body = [
                'conversion' => "urn:lla:llaPartnerConversion:{$conversionRuleId}",
                'conversionHappenedAt' => $payload->timestamp * 1000,
                'user' => ['userIds' => $userIds],
            ];

            if ($payload->value !== null) {
                // conversionValue, not totalBudget — the latter is a campaign
                // budget field and is silently ignored here, so values were
                // never actually reaching LinkedIn. `amount` is a string.
                $body['conversionValue'] = [
                    'currencyCode' => $payload->currency ?? 'USD',
                    'amount' => number_format((float) $payload->value, 2, '.', ''),
                ];
            }

            if ($payload->orderId !== null) {
                $body['eventId'] = (string) $payload->orderId;
            }

            if ($validateOnly) {
                $uploaded++;

                continue;
            }

            try {
                $response = Http::withToken(config('google-ads-conversions.linkedin.access_token'))
                    ->withHeaders([
                        'X-Restli-Protocol-Version' => '2.0.0',
                        'LinkedIn-Version' => (string) config('google-ads-conversions.linkedin.version', self::DEFAULT_VERSION),
                    ])
                    ->timeout(15)
                    ->post('https://api.linkedin.com/rest/conversionEvents', $body);

                if ($response->successful()) {
                    $uploaded++;

                    continue;
                }

                if ($response->status() === 426) {
                    $errors[] = 'LinkedIn API version '
                        .config('google-ads-conversions.linkedin.version', self::DEFAULT_VERSION)
                        .' is no longer supported. Set LINKEDIN_API_VERSION to a current YYYYMM value.';

                    continue;
                }

                $errors[] = 'HTTP '.$response->status().': '.$response->body();
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ($skipped > 0) {
            $errors[] = "{$skipped} conversion(s) skipped: no hashed email or li_fat_id to identify the member.";
        }

        if ($uploaded > 0) {
            Log::info("[LinkedIn] Uploaded {$uploaded} conversion(s).");
        }

        return [
            // Only a clean run is a success. Reporting true because one event
            // out of a hundred landed hid real failures.
            'success' => $errors === [],
            'count' => $uploaded,
            'errors' => $errors,
            'raw_response' => null,
        ];
    }

    /**
     * @return array<int, array{idType: string, idValue: string}>
     */
    protected function userIds(ConversionPayload $payload): array
    {
        $userIds = [];

        if (! empty($payload->userData['email'])) {
            if ($hashed = $this->hasher->hashEmail((string) $payload->userData['email'])) {
                $userIds[] = ['idType' => 'SHA256_EMAIL', 'idValue' => $hashed];
            }
        }

        if ($payload->liFatId) {
            $userIds[] = [
                'idType' => 'LINKEDIN_FIRST_PARTY_ADS_TRACKING_UUID',
                'idValue' => $payload->liFatId,
            ];
        }

        return $userIds;
    }

    /**
     * Verify the token with a real authenticated call.
     */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Missing LinkedIn credentials (linkedin.access_token or linkedin.conversion_rule_id).',
            ];
        }

        $ruleId = config('google-ads-conversions.linkedin.conversion_rule_id');
        $version = (string) config('google-ads-conversions.linkedin.version', self::DEFAULT_VERSION);

        try {
            $response = Http::withToken(config('google-ads-conversions.linkedin.access_token'))
                ->withHeaders([
                    'X-Restli-Protocol-Version' => '2.0.0',
                    'LinkedIn-Version' => $version,
                ])
                ->timeout(10)
                ->get("https://api.linkedin.com/rest/conversions/{$ruleId}");

            if ($response->status() === 426) {
                return [
                    'success' => false,
                    'message' => "LinkedIn API version {$version} is no longer supported. Set LINKEDIN_API_VERSION to a current YYYYMM value.",
                ];
            }

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'message' => 'LinkedIn rejected the request (HTTP '.$response->status().'): '.$response->body(),
                ];
            }

            $name = $response->json('name');

            return [
                'success' => true,
                'message' => "LinkedIn authenticated for conversion rule {$ruleId}".($name ? " ({$name})" : ''),
                'details' => ['conversion_rule_id' => $ruleId, 'api_version' => $version],
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'LinkedIn connection failed: '.$e->getMessage()];
        }
    }
}
