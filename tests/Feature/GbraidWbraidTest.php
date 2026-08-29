<?php

use ElectricTomCat\GoogleAdsConversions\ConversionUploader;
use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use ElectricTomCat\GoogleAdsConversions\Http\Middleware\CaptureGclid;
use ElectricTomCat\GoogleAdsConversions\Models\Lead;
use ElectricTomCat\GoogleAdsConversions\Support\ConsentManager;
use ElectricTomCat\GoogleAdsConversions\Support\EventResolver;
use ElectricTomCat\GoogleAdsConversions\Support\UserDataHasher;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

beforeEach(function () {
    Route::middleware([
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        CaptureGclid::class,
    ])->get('/gbraid-landing', fn () => 'ok');
});

it('captures and buffers gbraid and wbraid with distinct columns in database after sync', function () {
    $gbraid = 'gbraid-test-'.Str::random(6);
    $wbraid = 'wbraid-test-'.Str::random(6);

    $this->get("/gbraid-landing?gbraid={$gbraid}")->assertOk();
    app(GoogleAdsConversions::class)->syncToDatabase();

    $leadGbraid = Lead::where('gbraid', $gbraid)->first();
    expect($leadGbraid)->not->toBeNull()
        ->and($leadGbraid->gbraid)->toBe($gbraid);

    $this->get("/gbraid-landing?wbraid={$wbraid}")->assertOk();
    app(GoogleAdsConversions::class)->syncToDatabase();

    $leadWbraid = Lead::where('wbraid', $wbraid)->first();
    expect($leadWbraid)->not->toBeNull()
        ->and($leadWbraid->wbraid)->toBe($wbraid);
});

it('resolves gbraid and wbraid independently from gclid', function () {
    $conversions = app(GoogleAdsConversions::class);

    session(['google_ads_gbraid' => 'session-gbraid-123']);
    session(['google_ads_wbraid' => 'session-wbraid-456']);

    expect($conversions->gbraid())->toBe('session-gbraid-123')
        ->and($conversions->wbraid())->toBe('session-wbraid-456')
        ->and($conversions->clickId())->toBe('session-gbraid-123');
});

it('constructs ClickConversion with setGbraid and setWbraid respectively', function () {
    config()->set('google-ads-conversions.events', [
        'Quote Form' => 'customers/1234567890/conversionActions/111111',
    ]);

    $gbraidLead = Lead::create([
        'gbraid' => 'gbraid-val-123',
        'conversions' => [[
            'event' => 'Quote Form',
            'timestamp' => now()->subHours(8)->timestamp,
            'status' => 'pending',
        ]],
    ]);

    $uploader = Mockery::mock(ConversionUploader::class.'[uploadBatch]', [
        app(EventResolver::class),
        app(ConsentManager::class),
        app(UserDataHasher::class),
    ]);

    $uploader->shouldReceive('uploadBatch')
        ->once()
        ->andReturnUsing(function ($batchItems, $clicks) {
            expect($clicks[0]->getGbraid())->toBe('gbraid-val-123')
                ->and($clicks[0]->getGclid())->toBe('');

            return 1;
        });

    $uploader->uploadPendingConversions();
});
