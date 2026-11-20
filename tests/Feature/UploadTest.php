<?php

namespace ElectricTomCat\GoogleAdsConversions\Tests\Feature;

use ElectricTomCat\GoogleAdsConversions\ConversionUploader;
use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use ElectricTomCat\GoogleAdsConversions\Jobs\UploadPendingConversions;
use ElectricTomCat\GoogleAdsConversions\Models\Lead;
use ElectricTomCat\GoogleAdsConversions\Support\ConsentManager;
use ElectricTomCat\GoogleAdsConversions\Support\EventResolver;
use ElectricTomCat\GoogleAdsConversions\Support\UserDataHasher;
use ElectricTomCat\GoogleAdsConversions\Tests\TestCase;
use Illuminate\Support\Str;
use Mockery;

class UploadTest extends TestCase
{
    public function test_it_runs_sync_before_upload_from_the_queued_job(): void
    {
        $tracker = Mockery::mock(GoogleAdsConversions::class);
        $uploader = Mockery::mock(ConversionUploader::class);

        $tracker->shouldReceive('syncToDatabase')->once()->ordered();
        $uploader->shouldReceive('uploadPendingConversions')->once()->ordered();

        (new UploadPendingConversions)->handle($tracker, $uploader);
    }

    public function test_it_uploads_only_conversions_older_than_the_configured_delay(): void
    {
        config()->set('google-ads-conversions.upload_delay_hours', 6);
        config()->set('google-ads-conversions.events', [
            'Quote Form' => 'customers/1234567890/conversionActions/111111',
            'Phone Call' => 'customers/1234567890/conversionActions/222222',
        ]);

        $gclid = 'gclid-delay-'.Str::random(8);

        $lead = Lead::create([
            'gclid' => $gclid,
            'conversions' => [
                [
                    'event' => 'Quote Form',
                    'timestamp' => now()->subHours(7)->timestamp, // older than delay
                    'value' => 100.0,
                    'currency' => 'USD',
                    'status' => 'pending',
                ],
                [
                    'event' => 'Phone Call',
                    'timestamp' => now()->subHours(1)->timestamp, // newer than delay
                    'value' => 50.0,
                    'currency' => 'USD',
                    'status' => 'pending',
                ],
            ],
        ]);

        $uploader = Mockery::mock(ConversionUploader::class.'[uploadBatch]', [
            app(EventResolver::class),
            app(ConsentManager::class),
            app(UserDataHasher::class),
        ]);

        $uploader->shouldReceive('uploadBatch')
            ->once()
            ->andReturnUsing(function ($batchItems, $clicks, $validateOnly = null) {
                foreach ($batchItems as $item) {
                    $l = $item['lead'];
                    $i = $item['index'];
                    $conversions = $l->getConversions()->toArray();
                    $conversions[$i]['status'] = 'uploaded';
                    $l->setConversions($conversions);
                    $l->persist();
                }

                return count($clicks);
            });

        $uploader->uploadPendingConversions();

        $lead->refresh();

        $this->assertSame('uploaded', $lead->getConversions()[0]['status']);
        $this->assertSame('pending', $lead->getConversions()[1]['status']);
    }
}
