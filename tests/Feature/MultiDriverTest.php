<?php

use ElectricTomCat\GoogleAdsConversions\ConversionManager;
use ElectricTomCat\GoogleAdsConversions\DTO\ConversionPayload;
use Illuminate\Support\Facades\Http;

it('fans out conversion payload across configured drivers', function () {
    config()->set('google-ads-conversions.meta.pixel_id', '123456');
    config()->set('google-ads-conversions.meta.access_token', 'TOKEN_123');

    Http::fake([
        'https://graph.facebook.com/*' => Http::response(['events_received' => 1], 200),
    ]);

    $manager = app(ConversionManager::class);

    $payload = new ConversionPayload(
        eventName: 'Lead',
        value: 50.0,
        currency: 'USD',
        userData: ['email' => 'lead@example.com'],
    );

    $results = $manager->fanOut($payload, ['meta']);

    expect($results)->toHaveKey('meta')
        ->and($results['meta']['success'])->toBeTrue();
});
