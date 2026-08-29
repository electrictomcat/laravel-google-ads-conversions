<?php

use ElectricTomCat\GoogleAdsConversions\Http\Middleware\CaptureGclid;
use ElectricTomCat\GoogleAdsConversions\Support\ConsentManager;
use Google\Ads\GoogleAds\V23\Enums\ConsentStatusEnum\ConsentStatus;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware([
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        CaptureGclid::class,
    ])->get('/consent-landing', fn () => 'ok');

    ConsentManager::resetResolvers();
});

it('does not drop persistent cookies when cookie_consent is never', function () {
    config()->set('google-ads-conversions.privacy.cookie_consent', 'never');

    $response = $this->get('/consent-landing?gclid=test-gclid-privacy');

    $response->assertOk();
    $response->assertCookieMissing('google_ads_gclid');
    $response->assertCookieMissing('google_ads_visitor_id');
});

it('drops persistent cookies when cookie_consent is always', function () {
    config()->set('google-ads-conversions.privacy.cookie_consent', 'always');

    $response = $this->get('/consent-landing?gclid=test-gclid-always');

    $response->assertOk();
    $response->assertCookie('google_ads_gclid');
});

it('drops persistent cookies in auto mode only when consent cookie is present', function () {
    config()->set('google-ads-conversions.privacy.cookie_consent', 'auto');
    config()->set('google-ads-conversions.privacy.consent_cookie_names', ['cookie_consent_marketing']);

    // Without consent cookie
    $responseWithout = $this->get('/consent-landing?gclid=test-gclid-auto1');
    $responseWithout->assertCookieMissing('google_ads_gclid');

    // With consent cookie
    $responseWith = $this->withUnencryptedCookie('cookie_consent_marketing', 'true')
        ->get('/consent-landing?gclid=test-gclid-auto2');
    $responseWith->assertCookie('google_ads_gclid');
});

it('supports custom closure consent resolvers', function () {
    config()->set('google-ads-conversions.privacy.cookie_consent', 'auto');

    ConsentManager::determineCookieConsentUsing(function (Request $request) {
        return $request->has('user_has_opted_in');
    });

    $res1 = $this->get('/consent-landing?gclid=custom-1');
    $res1->assertCookieMissing('google_ads_gclid');

    $res2 = $this->get('/consent-landing?gclid=custom-2&user_has_opted_in=1');
    $res2->assertCookie('google_ads_gclid');
});

it('maps consent strings and booleans to Google Ads ConsentStatus enums', function () {
    $manager = new ConsentManager;

    expect($manager->mapToConsentStatus('GRANTED'))->toBe(ConsentStatus::GRANTED)
        ->and($manager->mapToConsentStatus('DENIED'))->toBe(ConsentStatus::DENIED)
        ->and($manager->mapToConsentStatus(true))->toBe(ConsentStatus::GRANTED)
        ->and($manager->mapToConsentStatus(false))->toBe(ConsentStatus::DENIED);
});

it('resolves Consent protobuf object with ad_user_data and ad_personalization', function () {
    $manager = new ConsentManager;

    $consent = $manager->resolveConsentObject([
        'ad_user_data' => 'GRANTED',
        'ad_personalization' => 'DENIED',
    ]);

    expect($consent)->not->toBeNull()
        ->and($consent->getAdUserData())->toBe(ConsentStatus::GRANTED)
        ->and($consent->getAdPersonalization())->toBe(ConsentStatus::DENIED);
});
