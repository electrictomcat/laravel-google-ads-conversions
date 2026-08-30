<?php

use ElectricTomCat\GoogleAdsConversions\ConversionUploader;
use ElectricTomCat\GoogleAdsConversions\Events\ConversionRecorded;
use ElectricTomCat\GoogleAdsConversions\Events\ConversionsSynced;
use ElectricTomCat\GoogleAdsConversions\Events\ConversionsUploaded;
use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use ElectricTomCat\GoogleAdsConversions\Models\Lead;
use ElectricTomCat\GoogleAdsConversions\Support\ConsentManager;
use ElectricTomCat\GoogleAdsConversions\Support\EventResolver;
use ElectricTomCat\GoogleAdsConversions\Support\UserDataHasher;
use Illuminate\Support\Facades\Event;

it('dispatches ConversionRecorded and ConversionsSynced events', function () {
    Event::fake([
        ConversionRecorded::class,
        ConversionsSynced::class,
    ]);

    $conversions = app(GoogleAdsConversions::class);
    $conversions->record('Quote Form', 100.0, 'USD', 'gclid-event-test');

    Event::assertDispatched(ConversionRecorded::class, function ($event) {
        return $event->clickId === 'gclid-event-test'
            && $event->conversion['event'] === 'Quote Form';
    });

    $conversions->syncToDatabase();

    Event::assertDispatched(ConversionsSynced::class, function ($event) {
        return in_array('gclid-event-test', $event->syncedClickIds, true);
    });
});

it('dispatches ConversionsUploaded on successful upload', function () {
    Event::fake([ConversionsUploaded::class]);

    config()->set('google-ads-conversions.events', [
        'Quote Form' => 'customers/1234567890/conversionActions/111111',
    ]);

    $lead = Lead::create([
        'gclid' => 'gclid-upload-event',
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
            ConversionsUploaded::dispatch(count($clicks), ['gclid-upload-event']);

            return count($clicks);
        });

    $uploader->uploadPendingConversions();

    Event::assertDispatched(ConversionsUploaded::class, function ($event) {
        return $event->count === 1 && in_array('gclid-upload-event', $event->clickIds, true);
    });
});
