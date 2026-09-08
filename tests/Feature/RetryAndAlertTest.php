<?php

use ElectricTomCat\GoogleAdsConversions\Events\ConversionRejected;
use ElectricTomCat\GoogleAdsConversions\Events\ConversionUploadFailed;
use ElectricTomCat\GoogleAdsConversions\Mail\ConversionAlertMail;
use ElectricTomCat\GoogleAdsConversions\Models\Lead;
use ElectricTomCat\GoogleAdsConversions\Tests\Fixtures\RecordingUploader;
use Google\Ads\GoogleAds\V23\Errors\ErrorCode;
use Google\Ads\GoogleAds\V23\Errors\ErrorLocation;
use Google\Ads\GoogleAds\V23\Errors\ErrorLocation\FieldPathElement;
use Google\Ads\GoogleAds\V23\Errors\GoogleAdsError;
use Google\Ads\GoogleAds\V23\Errors\GoogleAdsFailure;
use Google\Ads\GoogleAds\V23\Services\UploadClickConversionsResponse;
use Google\Protobuf\Any;
use Google\Rpc\Status;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

function makeFailureStatus(array $indexToMessage): Status
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

it('retries failed conversions up to max_retries and transitions to rejected', function () {
    Event::fake([ConversionUploadFailed::class, ConversionRejected::class]);

    config()->set('google-ads-conversions.max_retries', 3);

    $lead = Lead::create([
        'gclid' => 'gclid-retry-test',
        'conversions' => [[
            'event' => 'Quote Form',
            'timestamp' => now()->subDays(2)->timestamp,
            'value' => 10.0,
            'currency' => 'USD',
            'status' => 'pending',
        ]],
    ]);

    $uploader = app(RecordingUploader::class);
    $uploader->stubbedResponse = (new UploadClickConversionsResponse)
        ->setPartialFailureError(makeFailureStatus([0 => 'Temporary error 1']));

    // Attempt 1: fails, transitions to 'failed', retry_count = 1
    $count = $uploader->uploadPendingConversions(0, false);
    expect($count)->toBe(0);

    $conv = $lead->fresh()->getConversions()[0];
    expect($conv['status'])->toBe('failed')
        ->and($conv['retry_count'])->toBe(1)
        ->and($conv['error'])->toBe('Temporary error 1');

    Event::assertDispatched(ConversionUploadFailed::class);
    Event::assertNotDispatched(ConversionRejected::class);

    // Attempt 2: fails again, retry_count = 2, still 'failed'
    $uploader->stubbedResponse = (new UploadClickConversionsResponse)
        ->setPartialFailureError(makeFailureStatus([0 => 'Temporary error 2']));

    $count = $uploader->uploadPendingConversions(0, false);
    expect($count)->toBe(0);

    $conv = $lead->fresh()->getConversions()[0];
    expect($conv['status'])->toBe('failed')
        ->and($conv['retry_count'])->toBe(2);

    // Attempt 3: reaches max_retries (3), transitions to 'rejected'
    $uploader->stubbedResponse = (new UploadClickConversionsResponse)
        ->setPartialFailureError(makeFailureStatus([0 => 'Temporary error 3']));

    $count = $uploader->uploadPendingConversions(0, false);
    expect($count)->toBe(0);

    $conv = $lead->fresh()->getConversions()[0];
    expect($conv['status'])->toBe('rejected')
        ->and($conv['retry_count'])->toBe(3)
        ->and($conv)->toHaveKey('rejected_at');

    Event::assertDispatched(ConversionRejected::class, function ($event) {
        return $event->clickId === 'gclid-retry-test'
            && $event->retryCount === 3
            && $event->errorMessage === 'Temporary error 3';
    });

    // Attempt 4: swept again, but since it is 'rejected', it is skipped entirely
    $uploader->stubbedResponse = new UploadClickConversionsResponse;
    $count = $uploader->uploadPendingConversions(0, false);
    expect($count)->toBe(0);
});

it('sends webhook alerts when conversion is permanently rejected', function () {
    Http::fake();

    config()->set('google-ads-conversions.max_retries', 1);
    config()->set('google-ads-conversions.alerts.webhook_url', 'https://hooks.slack.com/services/T00/B00/X00');
    config()->set('google-ads-conversions.alerts.alert_on_rejected', true);

    $lead = Lead::create([
        'gclid' => 'gclid-alert-webhook',
        'conversions' => [[
            'event' => 'Quote Form',
            'timestamp' => now()->subDays(1)->timestamp,
            'value' => 50.0,
            'currency' => 'USD',
            'status' => 'pending',
        ]],
    ]);

    $uploader = app(RecordingUploader::class);
    $uploader->stubbedResponse = (new UploadClickConversionsResponse)
        ->setPartialFailureError(makeFailureStatus([0 => 'Customer not found']));

    $uploader->uploadPendingConversions(0, false);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'hooks.slack.com')
            && str_contains($request['text'], 'Customer not found')
            && str_contains($request['text'], 'gclid-alert-webhook');
    });
});

it('sends email alerts when mail_to is configured', function () {
    Mail::fake();

    config()->set('google-ads-conversions.max_retries', 1);
    config()->set('google-ads-conversions.alerts.mail_to', 'dev@example.com');
    config()->set('google-ads-conversions.alerts.alert_on_rejected', true);

    $lead = Lead::create([
        'gclid' => 'gclid-alert-mail',
        'conversions' => [[
            'event' => 'Quote Form',
            'timestamp' => now()->subDays(1)->timestamp,
            'value' => 50.0,
            'currency' => 'USD',
            'status' => 'pending',
        ]],
    ]);

    $uploader = app(RecordingUploader::class);
    $uploader->stubbedResponse = (new UploadClickConversionsResponse)
        ->setPartialFailureError(makeFailureStatus([0 => 'Account suspended']));

    $uploader->uploadPendingConversions(0, false);

    Mail::assertSent(ConversionAlertMail::class, function ($mail) {
        return $mail->type === 'rejected'
            && $mail->clickId === 'gclid-alert-mail'
            && $mail->errorMessage === 'Account suspended';
    });
});

it('respects retry backoff delay before re-attempting failed conversions', function () {
    config()->set('google-ads-conversions.upload_delay_hours', 0);
    config()->set('google-ads-conversions.retry_delay_hours', 2);

    $lead = Lead::create([
        'gclid' => 'gclid-backoff-test',
        'conversions' => [[
            'event' => 'Quote Form',
            'timestamp' => now()->subHours(5)->timestamp,
            'value' => 10.0,
            'currency' => 'USD',
            'status' => 'failed',
            'failed_at' => now()->subMinutes(30)->timestamp, // Failed only 30 mins ago (< 2h retry_delay_hours)
            'retry_count' => 1,
        ]],
    ]);

    $uploader = app(RecordingUploader::class);
    $uploader->stubbedResponse = new UploadClickConversionsResponse;

    // Normal sweep without forceDelayHours (respects retry_delay_hours)
    $count = $uploader->uploadPendingConversions();

    // Skipped because 2 hours have not passed since failed_at
    expect($count)->toBe(0)
        ->and($lead->fresh()->getConversions()[0]['status'])->toBe('failed');
});

it('rejects conversions older than 90 days', function () {
    Event::fake([ConversionRejected::class]);

    $lead = Lead::create([
        'gclid' => 'gclid-stale-91-days',
        'conversions' => [[
            'event' => 'Quote Form',
            'timestamp' => now()->subDays(91)->timestamp,
            'value' => 10.0,
            'currency' => 'USD',
            'status' => 'pending',
        ]],
    ]);

    $uploader = app(RecordingUploader::class);
    $uploader->stubbedResponse = new UploadClickConversionsResponse;

    $count = $uploader->uploadPendingConversions(0, false);

    expect($count)->toBe(0);

    $conv = $lead->fresh()->getConversions()[0];
    expect($conv['status'])->toBe('rejected')
        ->and($conv['error'])->toContain('older than 90 days');

    Event::assertDispatched(ConversionRejected::class);
});

it('protects failed conversions from being pruned but allows pruning once rejected', function () {
    // 1. Lead with 'failed' conversion awaiting retry (95 days old)
    $failedLead = Lead::create([
        'gclid' => 'gclid-failed-prune',
        'conversions' => [[
            'event' => 'Quote Form',
            'timestamp' => now()->subDays(80)->timestamp,
            'status' => 'failed',
            'retry_count' => 1,
        ]],
    ]);
    $failedLead->timestamps = false;
    $failedLead->created_at = now()->subDays(95);
    $failedLead->save();

    // 2. Lead with 'rejected' conversion (95 days old)
    $rejectedLead = Lead::create([
        'gclid' => 'gclid-rejected-prune',
        'conversions' => [[
            'event' => 'Quote Form',
            'timestamp' => now()->subDays(95)->timestamp,
            'status' => 'rejected',
            'retry_count' => 5,
        ]],
    ]);
    $rejectedLead->timestamps = false;
    $rejectedLead->created_at = now()->subDays(95);
    $rejectedLead->save();

    $prunableIds = (new Lead)->prunable()->pluck('id')->all();

    // The failed lead is awaiting retry and must NOT be pruned
    expect($prunableIds)->not->toContain($failedLead->id)
        // The rejected lead reached terminal state and IS prunable
        ->and($prunableIds)->toContain($rejectedLead->id);
});

it('reports failed and rejected conversions in diagnose command', function () {
    Lead::create([
        'gclid' => 'gclid-diag-failed',
        'created_at' => now(),
        'conversions' => [[
            'event' => 'Quote Form',
            'timestamp' => now()->timestamp,
            'status' => 'failed',
            'error' => 'API rate limit exceeded',
        ]],
    ]);

    Lead::create([
        'gclid' => 'gclid-diag-rejected',
        'created_at' => now(),
        'conversions' => [[
            'event' => 'Quote Form',
            'timestamp' => now()->timestamp,
            'status' => 'rejected',
            'error' => 'Invalid customer ID',
        ]],
    ]);

    $this->artisan('ad-conversions:diagnose')
        ->expectsOutputToContain('1 conversion(s) were rejected by Google and are awaiting retry:')
        ->expectsOutputToContain('1 conversion(s) permanently rejected (exhausted max retries):')
        ->assertExitCode(1);
});
