<?php

use ElectricTomCat\GoogleAdsConversions\Drivers\MetaCapiDriver;
use ElectricTomCat\GoogleAdsConversions\DTO\ConversionPayload;
use Illuminate\Support\Facades\Http;

it('formats and uploads Meta CAPI payloads with hashed user data', function () {
    config()->set('google-ads-conversions.meta.pixel_id', '1234567890');
    config()->set('google-ads-conversions.meta.access_token', 'META_TEST_TOKEN');

    Http::fake([
        'https://graph.facebook.com/*' => Http::response([
            'events_received' => 1,
            'messages' => [],
            'fbtrace_id' => 'abc123xyz',
        ], 200),
    ]);

    $driver = new MetaCapiDriver;
    expect($driver->isConfigured())->toBeTrue();

    $payload = new ConversionPayload(
        eventName: 'Purchase',
        value: 120.0,
        currency: 'USD',
        orderId: 'ORD-999',
        fbclid: 'test_fbclid_123',
        userData: [
            'email' => 'Customer@Example.com',
            'phone' => '555-123-4567',
            'first_name' => 'Alice',
            'last_name' => 'Smith',
        ],
    );

    $result = $driver->upload([$payload]);

    expect($result['success'])->toBeTrue()
        ->and($result['count'])->toBe(1);

    Http::assertSent(function ($request) {
        $data = $request['data'][0];

        return $data['event_name'] === 'Purchase'
            && $data['event_id'] === 'ORD-999'
            && $data['custom_data']['value'] === 120.0
            && $data['user_data']['em'][0] === hash('sha256', 'customer@example.com')
            && $data['user_data']['fn'][0] === hash('sha256', 'alice')
            && str_contains($data['user_data']['fbc'], 'test_fbclid_123');
    });
});
