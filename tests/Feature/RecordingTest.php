<?php

namespace ElectricTomCat\GoogleAdsConversions\Tests\Feature;

use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use ElectricTomCat\GoogleAdsConversions\Models\Lead;
use ElectricTomCat\GoogleAdsConversions\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class RecordingTest extends TestCase
{
    public function test_it_records_a_conversion_in_the_cache_buffer(): void
    {
        $gclid = 'gclid-cache-'.Str::random(8);

        app(GoogleAdsConversions::class)->record('Quote Form', 100.0, 'USD', $gclid);

        $this->assertTrue(Cache::has(GoogleAdsConversions::CACHE_PREFIX.$gclid));

        $cached = Cache::get(GoogleAdsConversions::CACHE_PREFIX.$gclid);

        $this->assertSame('Quote Form', $cached[0]['event']);
        $this->assertSame(100.0, $cached[0]['value']);
        $this->assertSame('USD', $cached[0]['currency']);
        $this->assertSame('pending', $cached[0]['status']);

        $this->assertContains($gclid, app(GoogleAdsConversions::class)->pendingClickIds());
    }

    public function test_it_flushes_cached_conversions_to_the_database_on_sync(): void
    {
        $gclid = 'gclid-sync-'.Str::random(8);

        Cache::put(GoogleAdsConversions::CACHE_PREFIX.$gclid, [[
            'event' => 'Quote Form',
            'timestamp' => now()->timestamp,
            'value' => 50.0,
            'currency' => 'USD',
            'status' => 'pending',
        ]]);
        Cache::put(GoogleAdsConversions::DIRTY_SET_KEY, [$gclid]);

        app(GoogleAdsConversions::class)->syncToDatabase();

        $lead = Lead::where('gclid', $gclid)->first();

        $this->assertNotNull($lead);
        $this->assertCount(1, $lead->getConversions());
        $this->assertSame('Quote Form', $lead->getConversions()->first()['event']);

        $this->assertFalse(Cache::has(GoogleAdsConversions::CACHE_PREFIX.$gclid));
        $this->assertNotContains($gclid, app(GoogleAdsConversions::class)->pendingClickIds());
    }

    public function test_it_uses_the_per_event_config_default_value_when_call_site_omits_one(): void
    {
        config()->set('google-ads-conversions.events', [
            'Demo Booked' => ['action' => 'Demo Booked', 'value' => 250.0],
        ]);

        $gclid = 'gclid-default-'.Str::random(8);

        app(GoogleAdsConversions::class)->record('Demo Booked', null, null, $gclid);

        $cached = Cache::get(GoogleAdsConversions::CACHE_PREFIX.$gclid);

        $this->assertSame(250.0, $cached[0]['value']);
    }

    public function test_it_lets_the_call_site_override_the_config_default_value(): void
    {
        config()->set('google-ads-conversions.events', [
            'Demo Booked' => ['action' => 'Demo Booked', 'value' => 250.0],
        ]);

        $gclid = 'gclid-override-'.Str::random(8);

        app(GoogleAdsConversions::class)->record('Demo Booked', 999.0, null, $gclid);

        $cached = Cache::get(GoogleAdsConversions::CACHE_PREFIX.$gclid);

        $this->assertSame(999.0, $cached[0]['value']);
    }

    public function test_it_does_not_record_when_no_gclid_is_available(): void
    {
        app(GoogleAdsConversions::class)->record('Quote Form', 100.0);

        $this->assertEmpty(app(GoogleAdsConversions::class)->pendingClickIds());
    }

    public function test_it_deduplicates_conversions_on_sync(): void
    {
        $gclid = 'gclid-dedup-'.Str::random(8);
        $timestamp = now()->timestamp;

        Lead::create([
            'gclid' => $gclid,
            'conversions' => [[
                'event' => 'Quote Form',
                'timestamp' => $timestamp,
                'value' => 100.0,
                'currency' => 'USD',
                'status' => 'pending',
            ]],
        ]);

        Cache::put(GoogleAdsConversions::CACHE_PREFIX.$gclid, [[
            'event' => 'Quote Form',
            'timestamp' => $timestamp, // same timestamp = same conversion
            'value' => 100.0,
            'currency' => 'USD',
            'status' => 'pending',
        ]]);
        Cache::put(GoogleAdsConversions::DIRTY_SET_KEY, [$gclid]);

        app(GoogleAdsConversions::class)->syncToDatabase();

        $lead = Lead::where('gclid', $gclid)->first();

        $this->assertNotNull($lead);
        $this->assertCount(1, $lead->getConversions());
    }
}
