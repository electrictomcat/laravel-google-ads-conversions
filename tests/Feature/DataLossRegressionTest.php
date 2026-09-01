<?php

use ElectricTomCat\GoogleAdsConversions\ConversionUploader;
use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use ElectricTomCat\GoogleAdsConversions\Http\Middleware\CaptureGclid;
use ElectricTomCat\GoogleAdsConversions\Models\Lead;
use ElectricTomCat\GoogleAdsConversions\Tests\Fixtures\ExplodingLead;
use ElectricTomCat\GoogleAdsConversions\Tests\Fixtures\RecordingUploader;
use Google\Ads\GoogleAds\V23\Errors\ErrorCode;
use Google\Ads\GoogleAds\V23\Errors\ErrorLocation;
use Google\Ads\GoogleAds\V23\Errors\ErrorLocation\FieldPathElement;
use Google\Ads\GoogleAds\V23\Errors\GoogleAdsError;
use Google\Ads\GoogleAds\V23\Errors\GoogleAdsFailure;
use Google\Ads\GoogleAds\V23\Services\UploadClickConversionsResponse;
use Google\Protobuf\Any;
use Google\Rpc\Status;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Regressions for the conversion-loss and availability bugs found in audit.
|--------------------------------------------------------------------------
*/

function tracker(): GoogleAdsConversions
{
    return app(GoogleAdsConversions::class);
}

function makePartialFailure(array $indexToMessage): Status
{
    $errors = [];

    foreach ($indexToMessage as $index => $message) {
        $errors[] = (new GoogleAdsError)
            ->setMessage($message)
            ->setErrorCode(new ErrorCode)
            ->setLocation((new ErrorLocation)->setFieldPathElements([
                (new FieldPathElement)->setFieldName('operations')->setIndex($index),
            ]));
    }

    $failure = (new GoogleAdsFailure)->setErrors($errors);

    $any = new Any;
    $any->setTypeUrl('type.googleapis.com/google.ads.googleads.v23.errors.GoogleAdsFailure');
    $any->setValue($failure->serializeToString());

    return (new Status)->setCode(3)->setMessage('partial failure')->setDetails([$any]);
}

/**
 * Seed one lead with a single pending conversion old enough to upload.
 */
function seedPendingLead(string $gclid = 'gclid-regression'): Lead
{
    return Lead::create([
        'gclid' => $gclid,
        'conversions' => [[
            'event' => 'Quote Form',
            'timestamp' => now()->subDays(2)->timestamp,
            'value' => 100.0,
            'currency' => 'USD',
            'status' => 'pending',
        ]],
    ]);
}

// ---------------------------------------------------------------- PKG-01

it('does not blow up when a click identifier arrives as an array', function () {
    Route::middleware(['web', CaptureGclid::class])->get('/probe', fn () => 'ok');

    $this->withoutExceptionHandling()
        ->get('/probe?gclid[]=abc')
        ->assertOk();
});

it('ignores a click identifier longer than the column can hold', function () {
    Route::middleware(['web', CaptureGclid::class])->get('/probe', fn () => 'ok');

    $long = str_repeat('a', CaptureGclid::MAX_CLICK_ID_LENGTH + 1);

    $this->withoutExceptionHandling()->get('/probe?gclid='.$long)->assertOk();

    expect(tracker()->pendingClickIds())->toBeEmpty();
});

it('does not blow up when the visitor cookie arrives as an array', function () {
    Route::middleware(['web', CaptureGclid::class])->get('/probe', fn () => 'ok');

    // Cookies can be arrays too. This is the same defect as the query-string
    // case, in the one place it was still reachable.
    $this->withoutExceptionHandling()
        ->withUnencryptedCookies(['google_ads_visitor_id' => ['a', 'b']])
        ->get('/probe?gclid=cookie-array-probe')
        ->assertOk();

    expect(tracker()->pendingClickIds())->toContain('cookie-array-probe');
});

it('ignores an oversized visitor cookie', function () {
    Route::middleware(['web', CaptureGclid::class])->get('/probe', fn () => 'ok');

    $this->withoutExceptionHandling()
        ->withUnencryptedCookies([
            'google_ads_visitor_id' => str_repeat('a', CaptureGclid::MAX_CLICK_ID_LENGTH + 1),
        ])
        ->get('/probe?gclid=oversized-cookie-probe')
        ->assertOk();
});

it('ignores array-valued tracked query parameters', function () {
    Route::middleware(['web', CaptureGclid::class])->get('/probe', fn () => 'ok');

    $this->withoutExceptionHandling()
        ->get('/probe?gclid=good-click&utm_source[]=x&utm_campaign[]=y')
        ->assertOk();

    $buffered = Cache::get(GoogleAdsConversions::LEAD_DATA_PREFIX.'good-click');

    expect($buffered['utm_source'])->toBeNull()
        ->and($buffered['utm_campaign'])->toBeNull();
});

// ---------------------------------------------------------------- PKG-02

it('keeps the buffer when the database write fails, so nothing is lost', function () {
    $tracker = tracker();
    $tracker->bufferLeadData('click-x', ['gclid' => 'click-x']);
    $tracker->record('Quote Form', 10.0, 'USD', gclid: 'click-x');

    config()->set('google-ads-conversions.model', ExplodingLead::class);

    $tracker->syncToDatabase();

    expect(Cache::has(GoogleAdsConversions::CACHE_PREFIX.'click-x'))->toBeTrue()
        ->and(Cache::has(GoogleAdsConversions::LEAD_DATA_PREFIX.'click-x'))->toBeTrue()
        ->and($tracker->pendingClickIds())->toContain('click-x');

    // And the retry succeeds once the database recovers.
    config()->set('google-ads-conversions.model', Lead::class);
    $tracker->syncToDatabase();

    expect(Lead::where('gclid', 'click-x')->exists())->toBeTrue()
        ->and(Cache::has(GoogleAdsConversions::CACHE_PREFIX.'click-x'))->toBeFalse()
        ->and($tracker->pendingClickIds())->not->toContain('click-x');
});

it('does not let one failing lead abort the rest of the sweep', function () {
    $tracker = tracker();

    foreach (['ok-1', 'ok-2'] as $id) {
        $tracker->bufferLeadData($id, ['gclid' => $id]);
        $tracker->record('Quote Form', 5.0, 'USD', gclid: $id);
    }

    expect($tracker->pendingClickIds())->toHaveCount(2);

    $tracker->syncToDatabase();

    expect(Lead::count())->toBe(2);
});

// ---------------------------------------------------------------- PKG-03

it('keeps every click identifier when the dirty set is written concurrently', function () {
    $tracker = tracker();

    // 200 distinct click IDs land across the shard buckets; none may be lost.
    $ids = [];
    for ($i = 0; $i < 200; $i++) {
        $id = 'click-'.$i;
        $ids[] = $id;
        $tracker->bufferLeadData($id, ['gclid' => $id]);
    }

    $pending = $tracker->pendingClickIds();

    expect($pending)->toHaveCount(200);
    foreach ($ids as $id) {
        expect($pending)->toContain($id);
    }
});

it('still drains a dirty set written by a pre-sharding release', function () {
    $tracker = tracker();

    Cache::put(GoogleAdsConversions::LEAD_DATA_PREFIX.'legacy-click', ['gclid' => 'legacy-click']);
    Cache::put(GoogleAdsConversions::DIRTY_SET_KEY, ['legacy-click']);

    expect($tracker->pendingClickIds())->toContain('legacy-click');

    $tracker->syncToDatabase();

    expect(Lead::where('gclid', 'legacy-click')->exists())->toBeTrue()
        ->and($tracker->pendingClickIds())->not->toContain('legacy-click');
});

// ---------------------------------------------------------------- PKG-04

it('leaves conversions pending after a dry run', function () {
    $lead = seedPendingLead('gclid-dryrun');

    $uploader = app(RecordingUploader::class);
    $count = $uploader->uploadPendingConversions(0, true);

    expect($count)->toBe(1)
        ->and($uploader->lastRequestWasValidateOnly())->toBeTrue();

    $lead->refresh();
    expect($lead->getConversions()[0]['status'])->toBe('pending')
        ->and($lead->getConversions()[0])->not->toHaveKey('uploaded_at');
});

it('marks conversions uploaded on a real run', function () {
    $lead = seedPendingLead('gclid-real');

    app(RecordingUploader::class)->uploadPendingConversions(0, false);

    $lead->refresh();
    expect($lead->getConversions()[0]['status'])->toBe('uploaded')
        ->and($lead->getConversions()[0])->toHaveKey('uploaded_at');
});

// ---------------------------------------------------------------- PKG-05

it('leaves a Google-rejected conversion pending rather than marking it uploaded', function () {
    $lead = seedPendingLead('gclid-rejected');

    $uploader = app(RecordingUploader::class);
    $uploader->stubbedResponse = (new UploadClickConversionsResponse)
        ->setPartialFailureError(makePartialFailure([0 => 'Conversion action not found.']));

    $count = $uploader->uploadPendingConversions(0, false);

    expect($count)->toBe(0);

    $lead->refresh();
    $conversion = $lead->getConversions()[0];

    expect($conversion['status'])->toBe('failed')
        ->and($conversion['error'])->toContain('Conversion action not found');
});

it('uploads the accepted rows of a partially failed batch and retries only the rejected one', function () {
    $accepted = Lead::create([
        'gclid' => 'gclid-accepted',
        'conversions' => [[
            'event' => 'Quote Form',
            'timestamp' => now()->subDays(2)->timestamp,
            'value' => 1.0, 'currency' => 'USD', 'status' => 'pending',
        ]],
    ]);
    $rejected = Lead::create([
        'gclid' => 'gclid-refused',
        'conversions' => [[
            'event' => 'Quote Form',
            'timestamp' => now()->subDays(2)->timestamp,
            'value' => 2.0, 'currency' => 'USD', 'status' => 'pending',
        ]],
    ]);

    $uploader = app(RecordingUploader::class);
    // Batch order follows chunkById, so index 1 is the second lead.
    $uploader->stubbedResponse = (new UploadClickConversionsResponse)
        ->setPartialFailureError(makePartialFailure([1 => 'Invalid gclid.']));

    $count = $uploader->uploadPendingConversions(0, false);

    expect($count)->toBe(1);

    expect($accepted->fresh()->getConversions()[0]['status'])->toBe('uploaded')
        ->and($rejected->fresh()->getConversions()[0]['status'])->toBe('failed');
});

it('treats the whole batch as unsent when the failure detail cannot be decoded', function () {
    $lead = seedPendingLead('gclid-undecodable');

    $any = new Any;
    $any->setTypeUrl('type.googleapis.com/google.ads.googleads.v23.errors.GoogleAdsFailure');
    $any->setValue('not-a-valid-protobuf-payload');

    $uploader = app(RecordingUploader::class);
    $uploader->stubbedResponse = (new UploadClickConversionsResponse)
        ->setPartialFailureError((new Status)->setCode(3)->setMessage('boom')->setDetails([$any]));

    $uploader->uploadPendingConversions(0, false);

    expect($lead->fresh()->getConversions()[0]['status'])->toBe('failed');
});

// ---------------------------------------------------------------- PKG-11

it('purges the cache buffer when a visitor exercises the right to erasure', function () {
    $tracker = tracker();
    $visitorId = (string) Str::uuid();

    Lead::create(['gclid' => 'click-erase', 'visitor_id' => $visitorId]);

    $tracker->bufferLeadData('click-erase', ['gclid' => 'click-erase', 'visitor_id' => $visitorId]);
    $tracker->record('Quote Form', 10.0, 'USD', gclid: 'click-erase');

    expect($tracker->forgetVisitor($visitorId))->toBe(1);

    expect(Cache::has(GoogleAdsConversions::CACHE_PREFIX.'click-erase'))->toBeFalse()
        ->and(Cache::has(GoogleAdsConversions::LEAD_DATA_PREFIX.'click-erase'))->toBeFalse()
        ->and($tracker->pendingClickIds())->not->toContain('click-erase');

    // The erased visitor must not reappear on the next sync.
    $tracker->syncToDatabase();
    expect(Lead::where('visitor_id', $visitorId)->exists())->toBeFalse();
});

// ---------------------------------------------------------------- PKG-10

it('does not prune a lead whose conversion has not been uploaded yet', function () {
    config()->set('google-ads-conversions.privacy.retention_days', 90);

    $stale = Lead::create([
        'gclid' => 'gclid-stale-pending',
        'conversions' => [[
            'event' => 'Quote Form', 'timestamp' => now()->subDays(100)->timestamp,
            'value' => 1.0, 'currency' => 'USD', 'status' => 'pending',
        ]],
    ]);
    $stale->forceFill(['created_at' => now()->subDays(100)])->save();

    $delivered = Lead::create([
        'gclid' => 'gclid-stale-uploaded',
        'conversions' => [[
            'event' => 'Quote Form', 'timestamp' => now()->subDays(100)->timestamp,
            'value' => 1.0, 'currency' => 'USD', 'status' => 'uploaded',
        ]],
    ]);
    $delivered->forceFill(['created_at' => now()->subDays(100)])->save();

    $prunable = (new Lead)->prunable()->pluck('gclid')->all();

    expect($prunable)->toContain('gclid-stale-uploaded')
        ->and($prunable)->not->toContain('gclid-stale-pending');
});

// ---------------------------------------------------------------- PKG-16

it('reports whether a conversion could be attributed at all', function () {
    expect(tracker()->record('Quote Form', 10.0))->toBeFalse()
        ->and(tracker()->record('Quote Form', 10.0, 'USD', gclid: 'has-a-click'))->toBeTrue();
});

it('escapes backslashes as well as quotes in conversion action lookups', function () {
    $uploader = app(ConversionUploader::class);

    $resolve = new ReflectionMethod($uploader, 'resolveActionResourceName');

    // A trailing backslash previously escaped the closing quote of the GAQL
    // literal. Resolution fails here (no credentials), but it must fail by
    // returning null rather than by producing a broken query.
    expect($resolve->invoke($uploader, 'Quote\\'))->toBeNull();
});
