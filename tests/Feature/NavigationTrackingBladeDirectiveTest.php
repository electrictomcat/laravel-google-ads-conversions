<?php

namespace ElectricTomCat\GoogleAdsConversions\Tests\Feature;

use ElectricTomCat\GoogleAdsConversions\Facades\GoogleAdsConversions;
use ElectricTomCat\GoogleAdsConversions\Tests\TestCase;
use Illuminate\Support\Facades\Blade;

class NavigationTrackingBladeDirectiveTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        GoogleAdsConversions::forgetGclid();
        session()->flush();
    }

    public function test_directive_renders_nothing_when_visitor_has_no_attribution(): void
    {
        $rendered = Blade::render('@googleAdsNavigationTracking');

        $this->assertEmpty(trim($rendered));
    }

    public function test_directive_renders_script_when_gclid_present(): void
    {
        session(['google_ads_gclid' => 'gclid-directive-test']);

        $rendered = Blade::render('@googleAdsNavigationTracking');

        $this->assertStringContainsString('<script data-navigate-once>', $rendered);
        $this->assertStringContainsString('sendNavigationEvent', $rendered);
        $this->assertStringContainsString('google_ads_gclid', $rendered);
        $this->assertStringContainsString('Page Navigation:', $rendered);
    }

    public function test_directive_renders_script_when_gbraid_present(): void
    {
        session(['google_ads_gbraid' => 'gbraid-directive-test']);

        $rendered = Blade::render('@googleAdsNavigationTracking');

        $this->assertStringContainsString('<script data-navigate-once>', $rendered);
        $this->assertStringContainsString('sendNavigationEvent', $rendered);
        $this->assertStringContainsString('google_ads_gbraid', $rendered);
        $this->assertStringContainsString('Page Navigation:', $rendered);
    }

    public function test_directive_renders_script_when_wbraid_present(): void
    {
        session(['google_ads_wbraid' => 'wbraid-directive-test']);

        $rendered = Blade::render('@googleAdsNavigationTracking');

        $this->assertStringContainsString('<script data-navigate-once>', $rendered);
        $this->assertStringContainsString('sendNavigationEvent', $rendered);
        $this->assertStringContainsString('google_ads_wbraid', $rendered);
        $this->assertStringContainsString('Page Navigation:', $rendered);
    }

    public function test_google_ads_script_directive_renders_track_helper_function(): void
    {
        $rendered = Blade::render('@googleAdsScript');

        $this->assertStringContainsString('<script data-navigate-once>', $rendered);
        $this->assertStringContainsString('window.trackGoogleAdsConversion', $rendered);
        $this->assertStringContainsString('navigator.sendBeacon', $rendered);
    }
}
