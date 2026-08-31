<?php

namespace ElectricTomCat\GoogleAdsConversions\Support;

use Closure;
use Google\Ads\GoogleAds\V23\Common\Consent;
use Google\Ads\GoogleAds\V23\Enums\ConsentStatusEnum\ConsentStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ConsentManager
{
    /**
     * @var (Closure(Request): bool)|null
     */
    protected static ?Closure $cookieConsentResolver = null;

    /**
     * Custom resolver for determining cookie consent.
     *
     * @param  Closure(Request): bool  $resolver
     */
    public static function determineCookieConsentUsing(Closure $resolver): void
    {
        static::$cookieConsentResolver = $resolver;
    }

    /**
     * Reset custom resolver.
     */
    public static function resetResolvers(): void
    {
        static::$cookieConsentResolver = null;
    }

    /**
     * Check whether marketing cookies may be queued on the given request.
     */
    public function hasCookieConsent(Request $request): bool
    {
        if (static::$cookieConsentResolver !== null) {
            return (bool) call_user_func(static::$cookieConsentResolver, $request);
        }

        $strategy = config('google-ads-conversions.privacy.cookie_consent', 'always');

        if ($strategy === 'always') {
            return true;
        }

        if ($strategy === 'never' || $strategy === 'disabled') {
            return false;
        }

        // 'auto' strategy: inspect common consent cookies and sessions
        $cookieNames = (array) config('google-ads-conversions.privacy.consent_cookie_names', []);

        foreach ($cookieNames as $name) {
            $val = $request->cookie($name) ?? session($name);

            if ($val !== null && $this->isConsentTruthy($val)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve Google Ads API Consent object from array or config.
     *
     * @param  array{ad_user_data?: string|bool|null, ad_personalization?: string|bool|null}|null  $consentData
     */
    public function resolveConsentObject(?array $consentData = null): ?Consent
    {
        $userDataStatus = $consentData['ad_user_data']
            ?? config('google-ads-conversions.consent.ad_user_data');

        $personalizationStatus = $consentData['ad_personalization']
            ?? config('google-ads-conversions.consent.ad_personalization');

        if ($userDataStatus === null && $personalizationStatus === null) {
            return null;
        }

        $consent = new Consent;

        if ($userDataStatus !== null) {
            $consent->setAdUserData($this->mapToConsentStatus($userDataStatus));
        }

        if ($personalizationStatus !== null) {
            $consent->setAdPersonalization($this->mapToConsentStatus($personalizationStatus));
        }

        return $consent;
    }

    /**
     * Map string/boolean input to Google Ads ConsentStatus enum int.
     *
     * A value matching neither vocabulary is a configuration or integration
     * mistake, not a signal. It maps to DENIED by default so an unreadable
     * consent string fails closed — set `consent.unknown_maps_to` to
     * 'unspecified' to restore the older, permissive behaviour.
     */
    public function mapToConsentStatus(string|bool $status): int
    {
        if (is_bool($status)) {
            return $status ? ConsentStatus::GRANTED : ConsentStatus::DENIED;
        }

        $normalized = strtoupper(trim($status));

        return match ($normalized) {
            'GRANTED', 'TRUE', '1', 'ALLOW', 'ACCEPTED' => ConsentStatus::GRANTED,
            'DENIED', 'FALSE', '0', 'REJECT', 'DISALLOW' => ConsentStatus::DENIED,
            default => $this->unknownConsentStatus($normalized),
        };
    }

    protected function unknownConsentStatus(string $original): int
    {
        Log::warning("[GoogleAdsConversions] Unrecognised consent value '{$original}'; treating it as configured fallback.");

        return strtolower((string) config('google-ads-conversions.consent.unknown_maps_to', 'denied')) === 'unspecified'
            ? ConsentStatus::UNSPECIFIED
            : ConsentStatus::DENIED;
    }

    /**
     * Determine if a cookie/session consent value is truthy.
     */
    protected function isConsentTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if (in_array($normalized, ['true', '1', 'yes', 'granted', 'accepted', 'all'], true)) {
                return true;
            }

            // Check JSON-encoded consent payloads (e.g. {"marketing":true})
            if (str_starts_with($normalized, '{') && str_ends_with($normalized, '}')) {
                $decoded = json_decode($normalized, true);
                if (is_array($decoded)) {
                    return ! empty($decoded['marketing'])
                        || ! empty($decoded['ad_storage'])
                        || ! empty($decoded['analytics'])
                        || ! empty($decoded['all']);
                }
            }
        }

        return false;
    }
}
