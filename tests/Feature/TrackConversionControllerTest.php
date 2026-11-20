<?php

namespace ElectricTomCat\GoogleAdsConversions\Tests\Feature;

use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use ElectricTomCat\GoogleAdsConversions\Tests\TestCase;
use Illuminate\Support\Facades\Cache;

class TrackConversionControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(GoogleAdsConversions::class)->forgetGclid();
        session()->flush();
    }

    public function test_it_records_conversion_with_direct_gclid(): void
    {
        $response = $this->postJson(route('google-ads-conversions.track'), [
            'event' => 'Quote Submitted',
            'value' => 150.50,
            'currency' => 'USD',
            'gclid' => 'direct-gclid-12345',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertTrue(Cache::has(GoogleAdsConversions::CACHE_PREFIX.'direct-gclid-12345'));
        $cached = Cache::get(GoogleAdsConversions::CACHE_PREFIX.'direct-gclid-12345');
        $this->assertSame('Quote Submitted', $cached[0]['event']);
        $this->assertSame(150.50, $cached[0]['value']);
        $this->assertSame('USD', $cached[0]['currency']);
        $this->assertSame('direct-gclid-12345', $cached[0]['gclid']);
    }

    public function test_it_records_conversion_with_direct_gbraid(): void
    {
        $response = $this->postJson(route('google-ads-conversions.track'), [
            'event' => 'App Install Form',
            'value' => 99.00,
            'currency' => 'USD',
            'gbraid' => '0AAAAAgbraid123',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertTrue(Cache::has(GoogleAdsConversions::CACHE_PREFIX.'0AAAAAgbraid123'));
        $cached = Cache::get(GoogleAdsConversions::CACHE_PREFIX.'0AAAAAgbraid123');
        $this->assertSame('App Install Form', $cached[0]['event']);
        $this->assertSame('0AAAAAgbraid123', $cached[0]['gbraid']);
    }

    public function test_it_records_conversion_with_direct_wbraid(): void
    {
        $response = $this->postJson(route('google-ads-conversions.track'), [
            'event' => 'Web App Signup',
            'value' => 45.00,
            'currency' => 'EUR',
            'wbraid' => '0AAAAAwbraid456',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertTrue(Cache::has(GoogleAdsConversions::CACHE_PREFIX.'0AAAAAwbraid456'));
        $cached = Cache::get(GoogleAdsConversions::CACHE_PREFIX.'0AAAAAwbraid456');
        $this->assertSame('Web App Signup', $cached[0]['event']);
        $this->assertSame('EUR', $cached[0]['currency']);
        $this->assertSame('0AAAAAwbraid456', $cached[0]['wbraid']);
    }

    public function test_it_falls_back_to_cookie_when_identifiers_omitted_in_body(): void
    {
        $response = $this->withCredentials()
            ->withUnencryptedCookie('google_ads_gclid', 'cookie-gclid-999')
            ->postJson(route('google-ads-conversions.track'), [
                'event' => 'Page Navigation: /contact',
                'value' => 1,
                'currency' => 'USD',
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertTrue(Cache::has(GoogleAdsConversions::CACHE_PREFIX.'cookie-gclid-999'));
        $cached = Cache::get(GoogleAdsConversions::CACHE_PREFIX.'cookie-gclid-999');
        $this->assertSame('Page Navigation: /contact', $cached[0]['event']);
    }

    public function test_it_validates_required_event_name(): void
    {
        $response = $this->postJson(route('google-ads-conversions.track'), [
            'value' => 10,
            'gclid' => 'direct-gclid-123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['event']);
    }

    public function test_it_returns_success_false_when_conversion_cannot_be_attributed(): void
    {
        $response = $this->postJson(route('google-ads-conversions.track'), [
            'event' => 'Unattributed Event',
        ]);

        $response->assertOk()
            ->assertJson(['success' => false]);
    }
}
