<?php

use ElectricTomCat\GoogleAdsConversions\ConversionUploader;
use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use ElectricTomCat\GoogleAdsConversions\Jobs\UploadPendingConversions;
use ElectricTomCat\GoogleAdsConversions\Models\Lead;
use ElectricTomCat\GoogleAdsConversions\Support\EventResolver;
use Illuminate\Support\Str;

it('runs sync before upload from the queued job', function () {
    $tracker = Mockery::mock(GoogleAdsConversions::class);
    $uploader = Mockery::mock(ConversionUploader::class);

    $tracker->shouldReceive('syncToDatabase')->once()->ordered();
    $uploader->shouldReceive('uploadPendingConversions')->once()->ordered();

    (new UploadPendingConversions)->handle($tracker, $uploader);
});

it('uploads only conversions older than the configured delay', function () {
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
    ]);

    $uploader->shouldReceive('uploadBatch')
        ->once()
        ->andReturnUsing(function ($l, $batch, $indices) {
            $conversions = $l->getConversions()->toArray();
            foreach ($indices as $i) {
                $conversions[$i]['status'] = 'uploaded';
            }
            $l->setConversions($conversions);
            $l->persist();
        });

    $uploader->uploadPendingConversions();

    $lead->refresh();

    expect($lead->getConversions()[0]['status'])->toBe('uploaded')
        ->and($lead->getConversions()[1]['status'])->toBe('pending');
});
