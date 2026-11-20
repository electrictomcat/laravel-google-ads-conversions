<?php

namespace ElectricTomCat\GoogleAdsConversions\Tests\Feature;

use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use ElectricTomCat\GoogleAdsConversions\Models\Lead;
use ElectricTomCat\GoogleAdsConversions\Support\ClickIdentifier;
use ElectricTomCat\GoogleAdsConversions\Tests\Fixtures\RecordingUploader;
use ElectricTomCat\GoogleAdsConversions\Tests\TestCase;
use Google\Ads\GoogleAds\V23\Services\ClickConversion;
use Illuminate\Support\Facades\Session;

/*
|--------------------------------------------------------------------------
| Getting the click identifier into the right field.
|--------------------------------------------------------------------------
|
| Google keeps gclid, gbraid and wbraid separate and refuses a value filed
| under the wrong one:
|
|   The imported gclid could not be decoded. Make sure you use the correct
|   gclid format.  at conversions[0].gclid
|
| Observed in production against the value 0AAAAAok5kLjuoMSU60CSS9zVi6vsXg1G6
| - a gbraid that reached the gclid field.
|
*/

class ClickIdentifierTest extends TestCase
{
    private function pendingLead(array $attributes): Lead
    {
        return Lead::create(array_merge([
            'conversions' => [[
                'event' => 'Quote Form',
                'timestamp' => now()->subDays(2)->timestamp,
                'value' => 500.0,
                'currency' => 'USD',
                'status' => 'pending',
            ]],
        ], $attributes));
    }

    private function uploadedClick(Lead $lead): ClickConversion
    {
        $uploader = app(RecordingUploader::class);
        $uploader->uploadPendingConversions(0, false);

        return $uploader->sentRequests[0]->getConversions()[0];
    }

    // ------------------------------------------------------------- precedence

    public function test_it_prefers_the_gclid_when_a_lead_holds_more_than_one_identifier(): void
    {
        // A visitor seen on Search and later from an app campaign accumulates
        // both. The gclid attributes to a single click; a braid is the coarser
        // privacy-preserving fallback, so sending it instead loses precision.
        $click = $this->uploadedClick($this->pendingLead([
            'gclid' => 'Cj0KCQjwSTRONG',
            'gbraid' => '0AAAAAcoarse',
        ]));

        $this->assertSame('Cj0KCQjwSTRONG', $click->getGclid());
        $this->assertSame('', $click->getGbraid());
    }

    public function test_it_falls_back_to_gbraid_then_wbraid_when_there_is_no_gclid(): void
    {
        $this->assertSame('0AAAAAbraid', $this->uploadedClick($this->pendingLead(['gbraid' => '0AAAAAbraid']))->getGbraid());

        Lead::query()->delete();

        $this->assertSame('0AAAAAweb', $this->uploadedClick($this->pendingLead(['wbraid' => '0AAAAAweb']))->getWbraid());
    }

    // ------------------------------------------------------------- misfiling

    public function test_it_moves_a_braid_shaped_value_out_of_the_gclid_field(): void
    {
        // Exactly the production case: a gbraid stored in the gclid column.
        $click = $this->uploadedClick($this->pendingLead(['gclid' => '0AAAAAok5kLjuoMSU60CSS9zVi6vsXg1G6']));

        $this->assertSame('', $click->getGclid());
        $this->assertSame('0AAAAAok5kLjuoMSU60CSS9zVi6vsXg1G6', $click->getGbraid());
    }

    public function test_it_leaves_the_value_alone_when_autocorrect_is_switched_off(): void
    {
        config()->set('google-ads-conversions.click_identifiers.autocorrect', false);

        $click = $this->uploadedClick($this->pendingLead(['gclid' => '0AAAAAok5kLjuoMSU60CSS9zVi6vsXg1G6']));

        $this->assertSame('0AAAAAok5kLjuoMSU60CSS9zVi6vsXg1G6', $click->getGclid());
    }

    public function test_it_does_not_mistake_a_real_gclid_for_a_braid(): void
    {
        foreach (['Cj0KCQjw3ZY3BhCnARIsAFbK', 'EAIaIQobChMI7fWm2Yb', 'CjwKCAjw_e6HBhAiEiwA'] as $gclid) {
            $this->assertFalse(ClickIdentifier::looksLikeBraid($gclid));
        }

        $this->assertTrue(ClickIdentifier::looksLikeBraid('0AAAAAok5kLjuoMSU60CSS9zVi6vsXg1G6'));
    }

    // ------------------------------------------------------- typed identifier

    public function test_it_carries_the_type_when_an_identifier_is_stored_and_passed_back(): void
    {
        // The pattern the README used to encourage - store clickId(), pass it
        // back as gclid: - is how a gbraid ends up misfiled. A ClickIdentifier
        // survives the round trip.
        Session::put('google_ads_gbraid', '0AAAAAfrom-an-app-campaign');

        $identifier = app(GoogleAdsConversions::class)->clickIdentifier();

        $this->assertSame(ClickIdentifier::GBRAID, $identifier?->type);

        $restored = ClickIdentifier::fromString((string) $identifier);

        $this->assertSame(ClickIdentifier::GBRAID, $restored?->type);
        $this->assertSame('0AAAAAfrom-an-app-campaign', $restored?->value);
    }

    public function test_it_routes_a_stored_identifier_to_the_correct_field_when_recording(): void
    {
        $tracker = app(GoogleAdsConversions::class);

        $this->assertTrue($tracker->record('Quote Form', 100.0, 'USD',
            gclid: ClickIdentifier::gbraid('0AAAAAstored')));

        $tracker->syncToDatabase();

        $lead = Lead::where('gbraid', '0AAAAAstored')->first();

        // Recorded under gbraid, not smuggled into the gclid column.
        $this->assertNotNull($lead);
        $this->assertNull($lead->getGclid());
    }

    public function test_it_files_a_gbraid_correctly_when_the_middleware_buffer_has_expired(): void
    {
        // The production path. The middleware buffers tracking data for two days;
        // a visitor who lands from an app campaign and converts on day three has
        // no buffer left, and the identifier used to default into the gclid
        // column regardless of what it was.
        $tracker = app(GoogleAdsConversions::class);

        $tracker->record('Quote Form', 500.0, 'USD', gclid: null, gbraid: '0AAAAAok5kLjuoMSU60CSS9zVi6vsXg1G6');
        $tracker->syncToDatabase();

        $lead = Lead::first();

        $this->assertNotNull($lead);
        $this->assertSame('0AAAAAok5kLjuoMSU60CSS9zVi6vsXg1G6', $lead->getGbraid());
        $this->assertNull($lead->getGclid());
    }

    public function test_it_does_not_file_a_braid_shaped_identifier_as_a_gclid_even_with_no_other_signal(): void
    {
        // Nothing records the type - a legacy row, or a caller that only had the
        // raw value. Filing it as a gclid guarantees Google refuses it.
        $tracker = app(GoogleAdsConversions::class);

        $tracker->bufferLeadData('0AAAAAno-type-recorded', []);
        $tracker->syncToDatabase();

        $lead = Lead::first();

        $this->assertNotNull($lead);
        $this->assertNull($lead->getGclid());
        $this->assertSame('0AAAAAno-type-recorded', $lead->getGbraid());
    }

    public function test_it_still_accepts_a_plain_string_gclid(): void
    {
        $tracker = app(GoogleAdsConversions::class);

        $this->assertTrue($tracker->record('Quote Form', 100.0, 'USD', gclid: 'Cj0KCQjwPLAIN'));

        $tracker->syncToDatabase();

        $this->assertTrue(Lead::where('gclid', 'Cj0KCQjwPLAIN')->exists());
    }

    // ------------------------------------------------------------ the window

    public function test_it_keeps_the_click_identifier_for_the_full_window_google_accepts(): void
    {
        // Google takes offline conversions for 90 days after the click. A 30-day
        // cookie meant anything with a longer sales cycle had no identifier left
        // by the time it converted.
        $lifetime = (int) config('google-ads-conversions.cookies.lifetime_minutes');

        $this->assertGreaterThanOrEqual(60 * 24 * 90, $lifetime);
    }
}
