<?php

namespace ElectricTomCat\GoogleAdsConversions\Tests\Feature;

use ElectricTomCat\GoogleAdsConversions\ConversionUploader;
use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use ElectricTomCat\GoogleAdsConversions\Models\Lead;
use ElectricTomCat\GoogleAdsConversions\Tests\TestCase;
use Mockery;

class CommandsTest extends TestCase
{
    public function test_it_runs_google_ads_sync_to_flush_the_cache_buffer(): void
    {
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

        $this->assertTrue(Lead::where('gclid', $gclid)->exists());
    }

    public function test_it_runs_google_ads_upload_with_dry_run_and_force_options(): void
    {
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
    }

    public function test_it_fails_google_ads_test_connection_when_customer_id_is_missing(): void
    {
        config()->set('google-ads-conversions.customer_id', null);

        $this->artisan('google-ads:test-connection')
            ->expectsOutputToContain('Google Ads Customer ID is not set')
            ->assertFailed();
    }
}
