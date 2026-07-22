<?php

use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use ElectricTomCat\GoogleAdsConversions\Models\Lead;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

it('exposes the resolved gclid from the session', function () {
    Session::put('google_ads_gclid', 'gclid-from-session');

    expect(app(GoogleAdsConversions::class)->gclid())->toBe('gclid-from-session');
});

it('falls back to the visitor history lookup', function () {
    Lead::create(['gclid' => 'gclid-from-history', 'visitor_id' => 'visitor-abc']);

    request()->cookies->set('google_ads_visitor_id', 'visitor-abc');

    expect(app(GoogleAdsConversions::class)->gclid())->toBe('gclid-from-history');
});

it('returns null when nothing resolves', function () {
    expect(app(GoogleAdsConversions::class)->gclid())->toBeNull();
});

it('memoizes the visitor history lookup for the request', function () {
    Lead::create(['gclid' => 'gclid-memo', 'visitor_id' => 'visitor-memo']);

    request()->cookies->set('google_ads_visitor_id', 'visitor-memo');

    $conversions = app(GoogleAdsConversions::class);

    // Prime the memo, then count queries on the repeat calls.
    expect($conversions->gclid())->toBe('gclid-memo');

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    expect($conversions->gclid())->toBe('gclid-memo')
        ->and($conversions->gclid())->toBe('gclid-memo')
        ->and($queries)->toBe(0);
});

it('memoizes a null result so a missing gclid is not looked up repeatedly', function () {
    request()->cookies->set('google_ads_visitor_id', 'visitor-unknown');

    $conversions = app(GoogleAdsConversions::class);

    expect($conversions->gclid())->toBeNull();

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    expect($conversions->gclid())->toBeNull()
        ->and($queries)->toBe(0);
});

it('resolves again after forgetGclid()', function () {
    $conversions = app(GoogleAdsConversions::class);

    expect($conversions->gclid())->toBeNull();

    Session::put('google_ads_gclid', 'gclid-after-forget');
    $conversions->forgetGclid();

    expect($conversions->gclid())->toBe('gclid-after-forget');
});
