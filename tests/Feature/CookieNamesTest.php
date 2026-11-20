<?php

namespace ElectricTomCat\GoogleAdsConversions\Tests\Feature;

use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use ElectricTomCat\GoogleAdsConversions\Tests\TestCase;
use Illuminate\Container\Container;

class CookieNamesTest extends TestCase
{
    public function test_cookie_names_returns_all_configured_cookie_keys(): void
    {
        $this->assertSame([
            'google_ads_gclid',
            'google_ads_gbraid',
            'google_ads_wbraid',
            'google_ads_visitor_id',
        ], GoogleAdsConversions::cookieNames());

        config()->set('google-ads-conversions.cookies', [
            'gclid' => 'custom_gclid',
            'gbraid' => 'custom_gbraid',
            'wbraid' => 'custom_wbraid',
            'visitor_id' => 'custom_visitor_id',
        ]);

        $this->assertSame([
            'custom_gclid',
            'custom_gbraid',
            'custom_wbraid',
            'custom_visitor_id',
        ], GoogleAdsConversions::cookieNames());
    }

    public function test_cookie_names_returns_defaults_when_config_is_not_bound(): void
    {
        $container = new Container;
        Container::setInstance($container);

        try {
            $this->assertSame([
                'google_ads_gclid',
                'google_ads_gbraid',
                'google_ads_wbraid',
                'google_ads_visitor_id',
            ], GoogleAdsConversions::cookieNames());
        } finally {
            Container::setInstance($this->app);
        }
    }
}
