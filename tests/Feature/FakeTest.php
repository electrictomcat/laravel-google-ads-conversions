<?php

use ElectricTomCat\GoogleAdsConversions\Facades\GoogleAdsConversions;

it('supports GoogleAdsConversions::fake() for application testing', function () {
    $fake = GoogleAdsConversions::fake('fake-gclid-999');

    expect(GoogleAdsConversions::gclid())->toBe('fake-gclid-999');

    GoogleAdsConversions::record('Quote Form', 100.0, 'USD');

    GoogleAdsConversions::assertRecorded('Quote Form');
    GoogleAdsConversions::assertRecorded('Quote Form', 100.0);
    GoogleAdsConversions::assertNotRecorded('Phone Call');
    GoogleAdsConversions::assertRecordedCount(1);
});

it('supports callable assertions in GoogleAdsConversions::fake()', function () {
    GoogleAdsConversions::fake();

    GoogleAdsConversions::record(
        eventName: 'Demo Booked',
        value: 250.0,
        orderId: 'ORDER-12345',
    );

    GoogleAdsConversions::assertRecorded(function ($entry) {
        return $entry['event'] === 'Demo Booked'
            && $entry['value'] === 250.0
            && $entry['order_id'] === 'ORDER-12345';
    });
});

it('asserts nothing recorded when no conversions fired', function () {
    GoogleAdsConversions::fake();

    GoogleAdsConversions::assertNothingRecorded();
});
