<?php

use ElectricTomCat\GoogleAdsConversions\ConversionUploader;
use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use ElectricTomCat\GoogleAdsConversions\Models\Lead;

it('runs google-ads:sync command to flush cache buffer', function () {
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

it('runs google-ads:upload command with dry-run and force options', function () {
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

it('runs google-ads:test-connection command showing credential summary', function () {
    config()->set('google-ads-conversions.customer_id', '123-456-7890');
    config()->set('google-ads-conversions.developer_token', 'DEV_TOKEN_12345');
    config()->set('google-ads-conversions.client_id', 'CLIENT_ID_98765');
    config()->set('google-ads-conversions.events', [
        'Quote Form' => 'customers/1234567890/conversionActions/111111',
    ]);

    $this->artisan('google-ads:test-connection')
        ->expectsOutputToContain('Testing Google Ads API configuration...')
        ->expectsOutputToContain('1234567890')
        ->assertSuccessful();
});
