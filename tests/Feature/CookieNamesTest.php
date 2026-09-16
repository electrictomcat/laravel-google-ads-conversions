<?php

use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;

test('test_cookie_names_returns_all_configured_cookie_keys', function () {
    expect(GoogleAdsConversions::cookieNames())->toBe([
        'google_ads_gclid',
        'google_ads_gbraid',
        'google_ads_wbraid',
        'google_ads_visitor_id',
    ]);

    config()->set('google-ads-conversions.cookies', [
        'gclid' => 'custom_gclid',
        'gbraid' => 'custom_gbraid',
        'wbraid' => 'custom_wbraid',
        'visitor_id' => 'custom_visitor_id',
    ]);

    expect(GoogleAdsConversions::cookieNames())->toBe([
        'custom_gclid',
        'custom_gbraid',
        'custom_wbraid',
        'custom_visitor_id',
    ]);
});
