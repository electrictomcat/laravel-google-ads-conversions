<?php

use ElectricTomCat\GoogleAdsConversions\ConversionUploader;
use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use ElectricTomCat\GoogleAdsConversions\Models\Lead;

it('runs google-ads:sync to flush the cache buffer', function () {
    $gclid = 'gclid-artisan-sync';
    cache([GoogleAdsConversions::CACHE_PREFIX.$gclid => [[
        'event' => 'Quote Form',
        'timestamp' => now()->timestamp,
        'value' => 50.0,
        'currency' => 'USD',
        'status' => 'pending',
    ]]]);
    cache([GoogleAdsConversions::DIRTY_SET_KEY => [$gclid]]);

    $this->artisan('google-ads:sync')
        ->expectsOutput('Flushing cache buffer to database...')
        ->expectsOutput('Sync complete.')
        ->assertSuccessful();

    expect(Lead::where('gclid', $gclid)->exists())->toBeTrue();
});

it('runs google-ads:upload with dry-run and force options', function () {
    $uploader = Mockery::mock(ConversionUploader::class);
    $uploader->shouldReceive('uploadPendingConversions')
        ->with(0, true)
        ->once()
        ->andReturn(5);

    $this->app->instance(ConversionUploader::class, $uploader);

    $this->artisan('google-ads:upload --dry-run --force')
        ->expectsOutputToContain('DRY-RUN mode')
        ->expectsOutput('Completed! Processed 5 conversion(s).')
        ->assertSuccessful();
});

it('fails google-ads:test-connection when customer id is missing', function () {
    config()->set('google-ads-conversions.customer_id', null);

    $this->artisan('google-ads:test-connection')
        ->expectsOutputToContain('Google Ads Customer ID is not set')
        ->assertFailed();
});
