<?php

use ElectricTomCat\GoogleAdsConversions\ConversionUploader;
use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use ElectricTomCat\GoogleAdsConversions\Models\Lead;
use Illuminate\Support\Facades\Http;

it('runs ad-conversions:sync to flush the cache buffer', function () {
    $gclid = 'gclid-artisan-sync';
    cache([GoogleAdsConversions::CACHE_PREFIX.$gclid => [[
        'event' => 'Quote Form',
        'timestamp' => now()->timestamp,
        'value' => 50.0,
        'currency' => 'USD',
        'status' => 'pending',
    ]]]);
    cache([GoogleAdsConversions::DIRTY_SET_KEY => [$gclid]]);

    $this->artisan('ad-conversions:sync')
        ->expectsOutput('Flushing cache buffer to database...')
        ->expectsOutput('Sync complete.')
        ->assertSuccessful();

    expect(Lead::where('gclid', $gclid)->exists())->toBeTrue();
});

it('runs ad-conversions:upload with dry-run and force options', function () {
    $uploader = Mockery::mock(ConversionUploader::class);
    $uploader->shouldReceive('uploadPendingConversions')
        ->with(0, true)
        ->once()
        ->andReturn(5);

    $this->app->instance(ConversionUploader::class, $uploader);

    $this->artisan('ad-conversions:upload --dry-run --force')
        ->expectsOutputToContain('DRY-RUN mode')
        ->expectsOutput('Completed! Processed 5 conversion(s).')
        ->assertSuccessful();
});

it('fails ad-conversions:test when no channel is configured', function () {
    // Reporting success without having reached a single API was the whole
    // problem: a revoked token looked identical to a working setup.
    $this->artisan('ad-conversions:test')
        ->expectsOutputToContain('SKIPPED')
        ->expectsOutputToContain('No channel is configured')
        ->assertFailed();
});

it('reports a channel as failed when the API rejects its credentials', function () {
    Http::fake([
        'business-api.tiktok.com/*' => Http::response(
            ['code' => 40001, 'message' => 'Access token is invalid'], 200
        ),
    ]);

    config()->set('google-ads-conversions.tiktok.access_token', 'bad-token');
    config()->set('google-ads-conversions.tiktok.pixel_code', 'PIXEL');

    $this->artisan('ad-conversions:test --channel=tiktok --skip-actions')
        ->expectsOutputToContain('FAILED')
        ->expectsOutputToContain('Access token is invalid')
        ->assertFailed();
});

it('reports a channel as OK when the API accepts its credentials', function () {
    Http::fake([
        'business-api.tiktok.com/*' => Http::response(
            ['code' => 0, 'data' => ['display_name' => 'OmniSignal']], 200
        ),
    ]);

    config()->set('google-ads-conversions.tiktok.access_token', 'good-token');
    config()->set('google-ads-conversions.tiktok.pixel_code', 'PIXEL');

    $this->artisan('ad-conversions:test --channel=tiktok --skip-actions')
        ->expectsOutputToContain('OK')
        ->assertSuccessful();
});

it('keeps the legacy google-ads:* command names working', function () {
    $this->artisan('google-ads:sync')->assertSuccessful();
});
