<?php

use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use ElectricTomCat\GoogleAdsConversions\Models\Lead;
use ElectricTomCat\GoogleAdsConversions\Support\ClickIdentifier;
use ElectricTomCat\GoogleAdsConversions\Tests\Fixtures\RecordingUploader;
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

function pendingLead(array $attributes): Lead
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

function uploadedClick(Lead $lead): ClickConversion
{
    $uploader = app(RecordingUploader::class);
    $uploader->uploadPendingConversions(0, false);

    return $uploader->sentRequests[0]->getConversions()[0];
}

// ------------------------------------------------------------- precedence

it('prefers the gclid when a lead holds more than one identifier', function () {
    // A visitor seen on Search and later from an app campaign accumulates
    // both. The gclid attributes to a single click; a braid is the coarser
    // privacy-preserving fallback, so sending it instead loses precision.
    $click = uploadedClick(pendingLead([
        'gclid' => 'Cj0KCQjwSTRONG',
        'gbraid' => '0AAAAAcoarse',
    ]));

    expect($click->getGclid())->toBe('Cj0KCQjwSTRONG')
        ->and($click->getGbraid())->toBe('');
});

it('falls back to gbraid, then wbraid, when there is no gclid', function () {
    expect(uploadedClick(pendingLead(['gbraid' => '0AAAAAbraid']))->getGbraid())->toBe('0AAAAAbraid');

    Lead::query()->delete();

    expect(uploadedClick(pendingLead(['wbraid' => '0AAAAAweb']))->getWbraid())->toBe('0AAAAAweb');
});

// ------------------------------------------------------------- misfiling

it('moves a braid-shaped value out of the gclid field', function () {
    // Exactly the production case: a gbraid stored in the gclid column.
    $click = uploadedClick(pendingLead(['gclid' => '0AAAAAok5kLjuoMSU60CSS9zVi6vsXg1G6']));

    expect($click->getGclid())->toBe('')
        ->and($click->getGbraid())->toBe('0AAAAAok5kLjuoMSU60CSS9zVi6vsXg1G6');
});

it('leaves the value alone when autocorrect is switched off', function () {
    config()->set('google-ads-conversions.click_identifiers.autocorrect', false);

    $click = uploadedClick(pendingLead(['gclid' => '0AAAAAok5kLjuoMSU60CSS9zVi6vsXg1G6']));

    expect($click->getGclid())->toBe('0AAAAAok5kLjuoMSU60CSS9zVi6vsXg1G6');
});

it('does not mistake a real gclid for a braid', function () {
    foreach (['Cj0KCQjw3ZY3BhCnARIsAFbK', 'EAIaIQobChMI7fWm2Yb', 'CjwKCAjw_e6HBhAiEiwA'] as $gclid) {
        expect(ClickIdentifier::looksLikeBraid($gclid))->toBeFalse();
    }

    expect(ClickIdentifier::looksLikeBraid('0AAAAAok5kLjuoMSU60CSS9zVi6vsXg1G6'))->toBeTrue();
});

// ------------------------------------------------------- typed identifier

it('carries the type when an identifier is stored and passed back', function () {
    // The pattern the README used to encourage - store clickId(), pass it
    // back as gclid: - is how a gbraid ends up misfiled. A ClickIdentifier
    // survives the round trip.
    Session::put('google_ads_gbraid', '0AAAAAfrom-an-app-campaign');

    $identifier = app(GoogleAdsConversions::class)->clickIdentifier();

    expect($identifier?->type)->toBe(ClickIdentifier::GBRAID);

    $restored = ClickIdentifier::fromString((string) $identifier);

    expect($restored?->type)->toBe(ClickIdentifier::GBRAID)
        ->and($restored?->value)->toBe('0AAAAAfrom-an-app-campaign');
});

it('routes a stored identifier to the correct field when recording', function () {
    $tracker = app(GoogleAdsConversions::class);

    expect($tracker->record('Quote Form', 100.0, 'USD',
        gclid: ClickIdentifier::gbraid('0AAAAAstored')))->toBeTrue();

    $tracker->syncToDatabase();

    $lead = Lead::where('gbraid', '0AAAAAstored')->first();

    // Recorded under gbraid, not smuggled into the gclid column.
    expect($lead)->not->toBeNull()
        ->and($lead->getGclid())->toBeNull();
});

it('files a gbraid correctly when the middleware buffer has expired', function () {
    // The production path. The middleware buffers tracking data for two days;
    // a visitor who lands from an app campaign and converts on day three has
    // no buffer left, and the identifier used to default into the gclid
    // column regardless of what it was.
    $tracker = app(GoogleAdsConversions::class);

    $tracker->record('Quote Form', 500.0, 'USD', gclid: null, gbraid: '0AAAAAok5kLjuoMSU60CSS9zVi6vsXg1G6');
    $tracker->syncToDatabase();

    $lead = Lead::first();

    expect($lead->getGbraid())->toBe('0AAAAAok5kLjuoMSU60CSS9zVi6vsXg1G6')
        ->and($lead->getGclid())->toBeNull();
});

it('does not file a braid-shaped identifier as a gclid even with no other signal', function () {
    // Nothing records the type - a legacy row, or a caller that only had the
    // raw value. Filing it as a gclid guarantees Google refuses it.
    $tracker = app(GoogleAdsConversions::class);

    $tracker->bufferLeadData('0AAAAAno-type-recorded', []);
    $tracker->syncToDatabase();

    $lead = Lead::first();

    expect($lead->getGclid())->toBeNull()
        ->and($lead->getGbraid())->toBe('0AAAAAno-type-recorded');
});

it('still accepts a plain string gclid', function () {
    $tracker = app(GoogleAdsConversions::class);

    expect($tracker->record('Quote Form', 100.0, 'USD', gclid: 'Cj0KCQjwPLAIN'))->toBeTrue();

    $tracker->syncToDatabase();

    expect(Lead::where('gclid', 'Cj0KCQjwPLAIN')->exists())->toBeTrue();
});

// ------------------------------------------------------------ the window

it('keeps the click identifier for the full window Google accepts', function () {
    // Google takes offline conversions for 90 days after the click. A 30-day
    // cookie meant anything with a longer sales cycle had no identifier left
    // by the time it converted.
    $lifetime = (int) config('google-ads-conversions.cookies.lifetime_minutes');

    expect($lifetime)->toBeGreaterThanOrEqual(60 * 24 * 90);
});
