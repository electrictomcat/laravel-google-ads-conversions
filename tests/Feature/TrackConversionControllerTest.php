<?php

use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    app(GoogleAdsConversions::class)->forgetGclid();
    session()->flush();
});

test('test_it_records_conversion_with_direct_gclid', function () {
    $response = $this->postJson(route('google-ads-conversions.track'), [
        'event' => 'Quote Submitted',
        'value' => 150.50,
        'currency' => 'USD',
        'gclid' => 'direct-gclid-12345',
    ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    expect(Cache::has(GoogleAdsConversions::CACHE_PREFIX.'direct-gclid-12345'))->toBeTrue();
    $cached = Cache::get(GoogleAdsConversions::CACHE_PREFIX.'direct-gclid-12345');
    expect($cached[0]['event'])->toBe('Quote Submitted')
        ->and($cached[0]['value'])->toBe(150.50)
        ->and($cached[0]['currency'])->toBe('USD')
        ->and($cached[0]['gclid'])->toBe('direct-gclid-12345');
});

test('test_it_records_conversion_with_direct_gbraid', function () {
    $response = $this->postJson(route('google-ads-conversions.track'), [
        'event' => 'App Install Form',
        'value' => 99.00,
        'currency' => 'USD',
        'gbraid' => '0AAAAAgbraid123',
    ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    expect(Cache::has(GoogleAdsConversions::CACHE_PREFIX.'0AAAAAgbraid123'))->toBeTrue();
    $cached = Cache::get(GoogleAdsConversions::CACHE_PREFIX.'0AAAAAgbraid123');
    expect($cached[0]['event'])->toBe('App Install Form')
        ->and($cached[0]['gbraid'])->toBe('0AAAAAgbraid123');
});

test('test_it_records_conversion_with_direct_wbraid', function () {
    $response = $this->postJson(route('google-ads-conversions.track'), [
        'event' => 'Web App Signup',
        'value' => 45.00,
        'currency' => 'EUR',
        'wbraid' => '0AAAAAwbraid456',
    ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    expect(Cache::has(GoogleAdsConversions::CACHE_PREFIX.'0AAAAAwbraid456'))->toBeTrue();
    $cached = Cache::get(GoogleAdsConversions::CACHE_PREFIX.'0AAAAAwbraid456');
    expect($cached[0]['event'])->toBe('Web App Signup')
        ->and($cached[0]['currency'])->toBe('EUR')
        ->and($cached[0]['wbraid'])->toBe('0AAAAAwbraid456');
});

test('test_it_falls_back_to_cookie_when_identifiers_omitted_in_body', function () {
    $response = $this->withCredentials()
        ->withUnencryptedCookie('google_ads_gclid', 'cookie-gclid-999')
        ->postJson(route('google-ads-conversions.track'), [
            'event' => 'Page Navigation: /contact',
            'value' => 1,
            'currency' => 'USD',
        ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    expect(Cache::has(GoogleAdsConversions::CACHE_PREFIX.'cookie-gclid-999'))->toBeTrue();
    $cached = Cache::get(GoogleAdsConversions::CACHE_PREFIX.'cookie-gclid-999');
    expect($cached[0]['event'])->toBe('Page Navigation: /contact');
});

test('test_it_validates_required_event_name', function () {
    $response = $this->postJson(route('google-ads-conversions.track'), [
        'value' => 10,
        'gclid' => 'direct-gclid-123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['event']);
});

test('it returns success false when conversion cannot be attributed', function () {
    $response = $this->postJson(route('google-ads-conversions.track'), [
        'event' => 'Unattributed Event',
    ]);

    $response->assertOk()
        ->assertJson(['success' => false]);
});
