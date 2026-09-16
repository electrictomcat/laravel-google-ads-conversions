<?php

use ElectricTomCat\GoogleAdsConversions\Facades\GoogleAdsConversions;
use Illuminate\Support\Facades\Blade;

beforeEach(function () {
    GoogleAdsConversions::forgetGclid();
    session()->flush();
});

test('test_directive_renders_nothing_when_visitor_has_no_attribution', function () {
    $rendered = Blade::render('@googleAdsNavigationTracking');

    expect(trim($rendered))->toBeEmpty();
});

test('test_directive_renders_script_when_gclid_present', function () {
    session(['google_ads_gclid' => 'gclid-directive-test']);

    $rendered = Blade::render('@googleAdsNavigationTracking');

    expect($rendered)->toContain('<script data-navigate-once>')
        ->and($rendered)->toContain('sendNavigationEvent')
        ->and($rendered)->toContain('google_ads_gclid')
        ->and($rendered)->toContain('Page Navigation:');
});

test('test_directive_renders_script_when_gbraid_present', function () {
    session(['google_ads_gbraid' => 'gbraid-directive-test']);

    $rendered = Blade::render('@googleAdsNavigationTracking');

    expect($rendered)->toContain('<script data-navigate-once>')
        ->and($rendered)->toContain('sendNavigationEvent')
        ->and($rendered)->toContain('google_ads_gbraid')
        ->and($rendered)->toContain('Page Navigation:');
});

test('test_directive_renders_script_when_wbraid_present', function () {
    session(['google_ads_wbraid' => 'wbraid-directive-test']);

    $rendered = Blade::render('@googleAdsNavigationTracking');

    expect($rendered)->toContain('<script data-navigate-once>')
        ->and($rendered)->toContain('sendNavigationEvent')
        ->and($rendered)->toContain('google_ads_wbraid')
        ->and($rendered)->toContain('Page Navigation:');
});

test('google ads script directive renders track helper function', function () {
    $rendered = Blade::render('@googleAdsScript');

    expect($rendered)->toContain('<script data-navigate-once>')
        ->and($rendered)->toContain('window.trackGoogleAdsConversion')
        ->and($rendered)->toContain('navigator.sendBeacon');
});
