<?php

namespace ElectricTomCat\GoogleAdsConversions\Tests\Feature;

use ElectricTomCat\GoogleAdsConversions\ConversionUploader;
use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use ElectricTomCat\GoogleAdsConversions\Http\Middleware\CaptureGclid;
use ElectricTomCat\GoogleAdsConversions\Models\Lead;
use ElectricTomCat\GoogleAdsConversions\Tests\Fixtures\ExplodingLead;
use ElectricTomCat\GoogleAdsConversions\Tests\Fixtures\RecordingUploader;
use ElectricTomCat\GoogleAdsConversions\Tests\TestCase;
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
use ReflectionMethod;

/*
|--------------------------------------------------------------------------
| Regressions for the conversion-loss and availability bugs found in audit.
|--------------------------------------------------------------------------
*/

class DataLossRegressionTest extends TestCase
{
    private function tracker(): GoogleAdsConversions
    {
        return app(GoogleAdsConversions::class);
    }

    private function makePartialFailure(array $indexToMessage, string $fieldName = 'conversions'): Status
    {
        $errors = [];

        foreach ($indexToMessage as $index => $message) {
            $errors[] = (new GoogleAdsError)
                ->setMessage($message)
                ->setErrorCode(new ErrorCode)
                ->setLocation((new ErrorLocation)->setFieldPathElements([
                    (new FieldPathElement)->setFieldName($fieldName)->setIndex($index),
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
    private function seedPendingLead(string $gclid = 'gclid-regression'): Lead
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

    public function test_it_does_not_blow_up_when_a_click_identifier_arrives_as_an_array(): void
    {
        Route::middleware(['web', CaptureGclid::class])->get('/probe', fn () => 'ok');

        $this->withoutExceptionHandling()
            ->get('/probe?gclid[]=abc')
            ->assertOk();
    }

    public function test_it_ignores_a_click_identifier_longer_than_the_column_can_hold(): void
    {
        Route::middleware(['web', CaptureGclid::class])->get('/probe', fn () => 'ok');

        $long = str_repeat('a', CaptureGclid::MAX_CLICK_ID_LENGTH + 1);

        $this->withoutExceptionHandling()->get('/probe?gclid='.$long)->assertOk();

        $this->assertEmpty($this->tracker()->pendingClickIds());
    }

    public function test_it_does_not_blow_up_when_the_visitor_cookie_arrives_as_an_array(): void
    {
        Route::middleware(['web', CaptureGclid::class])->get('/probe', fn () => 'ok');

        // Cookies can be arrays too. This is the same defect as the query-string
        // case, in the one place it was still reachable.
        $this->withoutExceptionHandling()
            ->withUnencryptedCookies(['google_ads_visitor_id' => ['a', 'b']])
            ->get('/probe?gclid=cookie-array-probe')
            ->assertOk();

        $this->assertContains('cookie-array-probe', $this->tracker()->pendingClickIds());
    }

    public function test_it_ignores_an_oversized_visitor_cookie(): void
    {
        Route::middleware(['web', CaptureGclid::class])->get('/probe', fn () => 'ok');

        $this->withoutExceptionHandling()
            ->withUnencryptedCookies([
                'google_ads_visitor_id' => str_repeat('a', CaptureGclid::MAX_CLICK_ID_LENGTH + 1),
            ])
            ->get('/probe?gclid=oversized-cookie-probe')
            ->assertOk();
    }

    public function test_it_ignores_array_valued_tracked_query_parameters(): void
    {
        Route::middleware(['web', CaptureGclid::class])->get('/probe', fn () => 'ok');

        $this->withoutExceptionHandling()
            ->get('/probe?gclid=good-click&utm_source[]=x&utm_campaign[]=y')
            ->assertOk();

        $buffered = Cache::get(GoogleAdsConversions::LEAD_DATA_PREFIX.'good-click');

        $this->assertNull($buffered['utm_source']);
        $this->assertNull($buffered['utm_campaign']);
    }

    // ---------------------------------------------------------------- PKG-02

    public function test_it_keeps_the_buffer_when_the_database_write_fails_so_nothing_is_lost(): void
    {
        $tracker = $this->tracker();
        $tracker->bufferLeadData('click-x', ['gclid' => 'click-x']);
        $tracker->record('Quote Form', 10.0, 'USD', gclid: 'click-x');

        config()->set('google-ads-conversions.model', ExplodingLead::class);

        $tracker->syncToDatabase();

        $this->assertTrue(Cache::has(GoogleAdsConversions::CACHE_PREFIX.'click-x'));
        $this->assertTrue(Cache::has(GoogleAdsConversions::LEAD_DATA_PREFIX.'click-x'));
        $this->assertContains('click-x', $tracker->pendingClickIds());

        // And the retry succeeds once the database recovers.
        config()->set('google-ads-conversions.model', Lead::class);
        $tracker->syncToDatabase();

        $this->assertTrue(Lead::where('gclid', 'click-x')->exists());
        $this->assertFalse(Cache::has(GoogleAdsConversions::CACHE_PREFIX.'click-x'));
        $this->assertNotContains('click-x', $tracker->pendingClickIds());
    }

    public function test_it_does_not_let_one_failing_lead_abort_the_rest_of_the_sweep(): void
    {
        $tracker = $this->tracker();

        foreach (['ok-1', 'ok-2'] as $id) {
            $tracker->bufferLeadData($id, ['gclid' => $id]);
            $tracker->record('Quote Form', 5.0, 'USD', gclid: $id);
        }

        $this->assertCount(2, $tracker->pendingClickIds());

        $tracker->syncToDatabase();

        $this->assertSame(2, Lead::count());
    }

    // ---------------------------------------------------------------- PKG-03

    public function test_it_keeps_every_click_identifier_when_the_dirty_set_is_written_concurrently(): void
    {
        $tracker = $this->tracker();

        // 200 distinct click IDs land across the shard buckets; none may be lost.
        $ids = [];
        for ($i = 0; $i < 200; $i++) {
            $id = 'click-'.$i;
            $ids[] = $id;
            $tracker->bufferLeadData($id, ['gclid' => $id]);
        }

        $pending = $tracker->pendingClickIds();

        $this->assertCount(200, $pending);
        foreach ($ids as $id) {
            $this->assertContains($id, $pending);
        }
    }

    public function test_it_still_drains_a_dirty_set_written_by_a_pre_sharding_release(): void
    {
        $tracker = $this->tracker();

        Cache::put(GoogleAdsConversions::LEAD_DATA_PREFIX.'legacy-click', ['gclid' => 'legacy-click']);
        Cache::put(GoogleAdsConversions::DIRTY_SET_KEY, ['legacy-click']);

        $this->assertContains('legacy-click', $tracker->pendingClickIds());

        $tracker->syncToDatabase();

        $this->assertTrue(Lead::where('gclid', 'legacy-click')->exists());
        $this->assertNotContains('legacy-click', $tracker->pendingClickIds());
    }

    // ---------------------------------------------------------------- PKG-04

    public function test_it_leaves_conversions_pending_after_a_dry_run(): void
    {
        $lead = $this->seedPendingLead('gclid-dryrun');

        $uploader = app(RecordingUploader::class);
        $count = $uploader->uploadPendingConversions(0, true);

        $this->assertSame(1, $count);
        $this->assertTrue($uploader->lastRequestWasValidateOnly());

        $lead->refresh();
        $this->assertSame('pending', $lead->getConversions()[0]['status']);
        $this->assertArrayNotHasKey('uploaded_at', $lead->getConversions()[0]);
    }

    public function test_it_marks_conversions_uploaded_on_a_real_run(): void
    {
        $lead = $this->seedPendingLead('gclid-real');

        app(RecordingUploader::class)->uploadPendingConversions(0, false);

        $lead->refresh();
        $this->assertSame('uploaded', $lead->getConversions()[0]['status']);
        $this->assertArrayHasKey('uploaded_at', $lead->getConversions()[0]);
    }

    // ---------------------------------------------------------------- PKG-05

    public function test_it_leaves_a_google_rejected_conversion_pending_rather_than_marking_it_uploaded(): void
    {
        $lead = $this->seedPendingLead('gclid-rejected');

        $uploader = app(RecordingUploader::class);
        $uploader->stubbedResponse = (new UploadClickConversionsResponse)
            ->setPartialFailureError($this->makePartialFailure([0 => 'Conversion action not found.']));

        $count = $uploader->uploadPendingConversions(0, false);

        $this->assertSame(0, $count);

        $lead->refresh();
        $conversion = $lead->getConversions()[0];

        $this->assertSame('failed', $conversion['status']);
        $this->assertStringContainsString('Conversion action not found', $conversion['error']);
    }

    public function test_it_uploads_the_accepted_rows_of_a_partially_failed_batch_and_retries_only_the_rejected_one(): void
    {
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
            ->setPartialFailureError($this->makePartialFailure([1 => 'Invalid gclid.']));

        $count = $uploader->uploadPendingConversions(0, false);

        $this->assertSame(1, $count);

        $this->assertSame('uploaded', $accepted->fresh()->getConversions()[0]['status']);
        $this->assertSame('failed', $rejected->fresh()->getConversions()[0]['status']);
        $this->assertSame(1, $rejected->fresh()->getConversions()[0]['retry_count']);

        // Second run: Google now accepts the retried conversion.
        // The accepted lead is not re-uploaded; only the rejected lead is retried.
        $uploader->stubbedResponse = new UploadClickConversionsResponse;
        $count2 = $uploader->uploadPendingConversions(0, false);

        $this->assertSame(1, $count2);
        $this->assertSame('uploaded', $rejected->fresh()->getConversions()[0]['status']);
    }

    public function test_it_treats_the_whole_batch_as_unsent_when_the_failure_detail_cannot_be_decoded(): void
    {
        $lead = $this->seedPendingLead('gclid-undecodable');

        $any = new Any;
        $any->setTypeUrl('type.googleapis.com/google.ads.googleads.v23.errors.GoogleAdsFailure');
        $any->setValue('not-a-valid-protobuf-payload');

        $uploader = app(RecordingUploader::class);
        $uploader->stubbedResponse = (new UploadClickConversionsResponse)
            ->setPartialFailureError((new Status)->setCode(3)->setMessage('boom')->setDetails([$any]));

        $uploader->uploadPendingConversions(0, false);

        $this->assertSame('failed', $lead->fresh()->getConversions()[0]['status']);
    }

    public function test_it_detects_partial_failure_errors_reported_at_conversions_index_and_never_marks_them_uploaded(): void
    {
        $lead = $this->seedPendingLead('gclid-conversions-field');

        $deprecationError = 'New integrations for uploading click conversions should use the Data Manager API. '
            .'Usage of ConversionUploadService.UploadClickConversions is limited to existing users.';

        $uploader = app(RecordingUploader::class);
        $uploader->stubbedResponse = (new UploadClickConversionsResponse)
            ->setPartialFailureError($this->makePartialFailure([0 => $deprecationError], 'conversions'));

        $count = $uploader->uploadPendingConversions(0, false);

        $this->assertSame(0, $count);

        $conv = $lead->fresh()->getConversions()[0];
        $this->assertSame('failed', $conv['status']);
        $this->assertArrayNotHasKey('uploaded_at', $conv);
        $this->assertStringContainsString('Data Manager API', $conv['error']);
    }

    public function test_it_fails_closed_when_a_partial_failure_error_has_no_field_path_location(): void
    {
        $lead = $this->seedPendingLead('gclid-unattributed-error');

        $failure = (new GoogleAdsFailure)->setErrors([
            (new GoogleAdsError)->setMessage('Account-level upload error with no location'),
        ]);

        $any = new Any;
        $any->setTypeUrl('type.googleapis.com/google.ads.googleads.v23.errors.GoogleAdsFailure');
        $any->setValue($failure->serializeToString());

        $uploader = app(RecordingUploader::class);
        $uploader->stubbedResponse = (new UploadClickConversionsResponse)
            ->setPartialFailureError((new Status)->setCode(3)->setMessage('partial failure')->setDetails([$any]));

        $count = $uploader->uploadPendingConversions(0, false);

        $this->assertSame(0, $count);

        $conv = $lead->fresh()->getConversions()[0];
        $this->assertSame('failed', $conv['status']);
        $this->assertStringContainsString('Account-level upload error with no location', $conv['error']);
    }

    // ---------------------------------------------------------------- PKG-11

    public function test_it_purges_the_cache_buffer_when_a_visitor_exercises_the_right_to_erasure(): void
    {
        $tracker = $this->tracker();
        $visitorId = (string) Str::uuid();

        Lead::create(['gclid' => 'click-erase', 'visitor_id' => $visitorId]);

        $tracker->bufferLeadData('click-erase', ['gclid' => 'click-erase', 'visitor_id' => $visitorId]);
        $tracker->record('Quote Form', 10.0, 'USD', gclid: 'click-erase');

        $this->assertSame(1, $tracker->forgetVisitor($visitorId));

        $this->assertFalse(Cache::has(GoogleAdsConversions::CACHE_PREFIX.'click-erase'));
        $this->assertFalse(Cache::has(GoogleAdsConversions::LEAD_DATA_PREFIX.'click-erase'));
        $this->assertNotContains('click-erase', $tracker->pendingClickIds());

        // The erased visitor must not reappear on the next sync.
        $tracker->syncToDatabase();
        $this->assertFalse(Lead::where('visitor_id', $visitorId)->exists());
    }

    // ---------------------------------------------------------------- PKG-10

    public function test_it_does_not_prune_a_lead_whose_conversion_has_not_been_uploaded_yet(): void
    {
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

        $this->assertContains('gclid-stale-uploaded', $prunable);
        $this->assertNotContains('gclid-stale-pending', $prunable);
    }

    // ---------------------------------------------------------------- PKG-16

    public function test_it_reports_whether_a_conversion_could_be_attributed_at_all(): void
    {
        $this->assertFalse($this->tracker()->record('Quote Form', 10.0));
        $this->assertTrue($this->tracker()->record('Quote Form', 10.0, 'USD', gclid: 'has-a-click'));
    }

    public function test_it_escapes_backslashes_as_well_as_quotes_in_conversion_action_lookups(): void
    {
        $uploader = app(ConversionUploader::class);

        $resolve = new ReflectionMethod($uploader, 'resolveActionResourceName');

        // A trailing backslash previously escaped the closing quote of the GAQL
        // literal. Resolution fails here (no credentials), but it must fail by
        // returning null rather than by producing a broken query.
        $this->assertNull($resolve->invoke($uploader, 'Quote\\'));
    }
}
