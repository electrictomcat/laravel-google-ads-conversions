<?php

use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use ElectricTomCat\GoogleAdsConversions\Models\Lead;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

it('records a conversion in the cache buffer', function () {
    $gclid = 'gclid-cache-'.Str::random(8);

    app(GoogleAdsConversions::class)->record('Quote Form', 100.0, 'USD', $gclid);

    expect(Cache::has(GoogleAdsConversions::CACHE_PREFIX.$gclid))->toBeTrue();

    $cached = Cache::get(GoogleAdsConversions::CACHE_PREFIX.$gclid);

    expect($cached[0]['event'])->toBe('Quote Form')
        ->and($cached[0]['value'])->toBe(100.0)
        ->and($cached[0]['currency'])->toBe('USD')
        ->and($cached[0]['status'])->toBe('pending');

    expect(Cache::get(GoogleAdsConversions::DIRTY_SET_KEY))->toContain($gclid);
});

it('flushes cached conversions to the database on sync', function () {
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

    expect($lead)->not->toBeNull()
        ->and($lead->getConversions())->toHaveCount(1)
        ->and($lead->getConversions()->first()['event'])->toBe('Quote Form');

    expect(Cache::has(GoogleAdsConversions::CACHE_PREFIX.$gclid))->toBeFalse()
        ->and(Cache::get(GoogleAdsConversions::DIRTY_SET_KEY, []))->not->toContain($gclid);
});

it('uses the per-event config default value when call site omits one', function () {
    config()->set('google-ads-conversions.events', [
        'Demo Booked' => ['action' => 'Demo Booked', 'value' => 250.0],
    ]);

    $gclid = 'gclid-default-'.Str::random(8);

    app(GoogleAdsConversions::class)->record('Demo Booked', null, null, $gclid);

    $cached = Cache::get(GoogleAdsConversions::CACHE_PREFIX.$gclid);

    expect($cached[0]['value'])->toBe(250.0);
});

it('lets the call site override the config default value', function () {
    config()->set('google-ads-conversions.events', [
        'Demo Booked' => ['action' => 'Demo Booked', 'value' => 250.0],
    ]);

    $gclid = 'gclid-override-'.Str::random(8);

    app(GoogleAdsConversions::class)->record('Demo Booked', 999.0, null, $gclid);

    $cached = Cache::get(GoogleAdsConversions::CACHE_PREFIX.$gclid);

    expect($cached[0]['value'])->toBe(999.0);
});

it('does not record when no GCLID is available', function () {
    app(GoogleAdsConversions::class)->record('Quote Form', 100.0);

    expect(Cache::get(GoogleAdsConversions::DIRTY_SET_KEY, []))->toBeEmpty();
});

it('deduplicates conversions on sync', function () {
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

    expect($lead->getConversions())->toHaveCount(1);
});
