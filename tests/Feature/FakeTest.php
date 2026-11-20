<?php

namespace ElectricTomCat\GoogleAdsConversions\Tests\Feature;

use ElectricTomCat\GoogleAdsConversions\Facades\GoogleAdsConversions;
use ElectricTomCat\GoogleAdsConversions\Tests\TestCase;

class FakeTest extends TestCase
{
    public function test_it_supports_google_ads_conversions_fake_for_application_testing(): void
    {
        GoogleAdsConversions::fake('fake-gclid-999');

        $this->assertSame('fake-gclid-999', GoogleAdsConversions::gclid());

        GoogleAdsConversions::record('Quote Form', 100.0, 'USD');

        GoogleAdsConversions::assertRecorded('Quote Form');
        GoogleAdsConversions::assertRecorded('Quote Form', 100.0);
        GoogleAdsConversions::assertNotRecorded('Phone Call');
        GoogleAdsConversions::assertRecordedCount(1);
    }

    public function test_it_supports_callable_assertions_in_google_ads_conversions_fake(): void
    {
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
    }

    public function test_it_asserts_nothing_recorded_when_no_conversions_fired(): void
    {
        GoogleAdsConversions::fake();

        GoogleAdsConversions::assertNothingRecorded();
    }
}
