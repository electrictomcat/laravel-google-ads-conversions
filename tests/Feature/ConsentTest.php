<?php

namespace ElectricTomCat\GoogleAdsConversions\Tests\Feature;

use ElectricTomCat\GoogleAdsConversions\Http\Middleware\CaptureGclid;
use ElectricTomCat\GoogleAdsConversions\Support\ConsentManager;
use ElectricTomCat\GoogleAdsConversions\Tests\TestCase;
use Google\Ads\GoogleAds\V23\Enums\ConsentStatusEnum\ConsentStatus;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

class ConsentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware([
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            CaptureGclid::class,
        ])->get('/consent-landing', fn () => 'ok');

        ConsentManager::resetResolvers();
    }

    public function test_it_does_not_drop_persistent_cookies_when_cookie_consent_is_never(): void
    {
        config()->set('google-ads-conversions.privacy.cookie_consent', 'never');

        $response = $this->get('/consent-landing?gclid=test-gclid-privacy');

        $response->assertOk();
        $response->assertCookieMissing('google_ads_gclid');
        $response->assertCookieMissing('google_ads_visitor_id');
    }

    public function test_it_drops_persistent_cookies_when_cookie_consent_is_always(): void
    {
        config()->set('google-ads-conversions.privacy.cookie_consent', 'always');

        $response = $this->get('/consent-landing?gclid=test-gclid-always');

        $response->assertOk();
        $response->assertCookie('google_ads_gclid');
    }

    public function test_it_drops_persistent_cookies_in_auto_mode_only_when_consent_cookie_is_present(): void
    {
        config()->set('google-ads-conversions.privacy.cookie_consent', 'auto');
        config()->set('google-ads-conversions.privacy.consent_cookie_names', ['cookie_consent_marketing']);

        // Without consent cookie
        $responseWithout = $this->get('/consent-landing?gclid=test-gclid-auto1');
        $responseWithout->assertCookieMissing('google_ads_gclid');

        // With consent cookie
        $responseWith = $this->withUnencryptedCookie('cookie_consent_marketing', 'true')
            ->get('/consent-landing?gclid=test-gclid-auto2');
        $responseWith->assertCookie('google_ads_gclid');
    }

    public function test_it_supports_custom_closure_consent_resolvers(): void
    {
        config()->set('google-ads-conversions.privacy.cookie_consent', 'auto');

        ConsentManager::determineCookieConsentUsing(function (Request $request) {
            return $request->has('user_has_opted_in');
        });

        $res1 = $this->get('/consent-landing?gclid=custom-1');
        $res1->assertCookieMissing('google_ads_gclid');

        $res2 = $this->get('/consent-landing?gclid=custom-2&user_has_opted_in=1');
        $res2->assertCookie('google_ads_gclid');
    }

    public function test_it_maps_consent_strings_and_booleans_to_google_ads_consent_status_enums(): void
    {
        $manager = new ConsentManager;

        $this->assertSame(ConsentStatus::GRANTED, $manager->mapToConsentStatus('GRANTED'));
        $this->assertSame(ConsentStatus::DENIED, $manager->mapToConsentStatus('DENIED'));
        $this->assertSame(ConsentStatus::GRANTED, $manager->mapToConsentStatus(true));
        $this->assertSame(ConsentStatus::DENIED, $manager->mapToConsentStatus(false));
    }

    public function test_it_resolves_consent_protobuf_object_with_ad_user_data_and_ad_personalization(): void
    {
        $manager = new ConsentManager;

        $consent = $manager->resolveConsentObject([
            'ad_user_data' => 'GRANTED',
            'ad_personalization' => 'DENIED',
        ]);

        $this->assertNotNull($consent);
        $this->assertSame(ConsentStatus::GRANTED, $consent->getAdUserData());
        $this->assertSame(ConsentStatus::DENIED, $consent->getAdPersonalization());
    }
}
