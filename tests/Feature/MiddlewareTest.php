<?php

use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use ElectricTomCat\GoogleAdsConversions\Http\Middleware\CaptureGclid;
use ElectricTomCat\GoogleAdsConversions\Models\Lead;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware([
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        CaptureGclid::class,
    ])->get('/landing', fn () => 'ok');
});

it('buffers gclid + tracking data in cache, not in the database', function () {
    $gclid = 'gclid-mw-buffer';

    $this->get('/landing?gclid='.$gclid.'&utm_source=google&utm_campaign=spring')
        ->assertOk();

    expect(Lead::where('gclid', $gclid)->exists())->toBeFalse();
    expect(Cache::get(GoogleAdsConversions::DIRTY_SET_KEY))->toContain($gclid);

    $buffered = Cache::get(GoogleAdsConversions::LEAD_DATA_PREFIX.$gclid);

    expect($buffered['utm_source'])->toBe('google')
        ->and($buffered['utm_campaign'])->toBe('spring')
        ->and($buffered['landing_page'])->toBe('/landing');
});

it('persists buffered data into the database after sync', function () {
    $gclid = 'gclid-mw-persist';

    $this->get('/landing?gclid='.$gclid.'&utm_source=google');

    app(GoogleAdsConversions::class)->syncToDatabase();

    $lead = Lead::where('gclid', $gclid)->first();

    expect($lead)->not->toBeNull()
        ->and($lead->utm_source)->toBe('google')
        ->and($lead->visitor_id)->not->toBeNull();
});

it('also captures gbraid as a gclid alternative', function () {
    $this->get('/landing?gbraid=test-gbraid');

    expect(Cache::get(GoogleAdsConversions::DIRTY_SET_KEY))->toContain('test-gbraid');
});

it('does not buffer when no click ID is on the URL', function () {
    $this->get('/landing?utm_source=organic');

    expect(Cache::get(GoogleAdsConversions::DIRTY_SET_KEY, []))->toBeEmpty();
});
