<?php

use ElectricTomCat\GoogleAdsConversions\ConversionUploader;
use ElectricTomCat\GoogleAdsConversions\DTO\ConversionPayload;
use ElectricTomCat\GoogleAdsConversions\Models\Lead;
use ElectricTomCat\GoogleAdsConversions\Support\GoogleAdsSetup;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('google-ads-conversions.upload_api', 'data_manager');
    config()->set('google-ads-conversions.customer_id', '1234567890');
    config()->set('google-ads-conversions.login_customer_id', '9998887777');
    config()->set('google-ads-conversions.client_id', 'test-client-id');
    config()->set('google-ads-conversions.client_secret', 'test-client-secret');
    config()->set('google-ads-conversions.refresh_token', 'test-refresh-token');
    config()->set('google-ads-conversions.events', [
        'Purchase' => 'customers/1234567890/conversionActions/111222333',
        'Quote Form' => '444555666',
    ]);

    app(ConversionUploader::class)->forgetClient();
});

it('defaults upload_api to data_manager and includes datamanager scope in OAuth URL', function () {
    expect(config('google-ads-conversions.upload_api'))->toBe('data_manager');

    $setup = new GoogleAdsSetup;
    $url = $setup->buildAuthorizationUrl('client-123');

    expect(rawurldecode($url))
        ->toContain(GoogleAdsSetup::ADWORDS_SCOPE)
        ->toContain(GoogleAdsSetup::DATA_MANAGER_SCOPE);
});

it('uploads pending conversions to Google Data Manager API events:ingest with full payload mapping', function () {
    config()->set('google-ads-conversions.enhanced_conversions.enabled', true);

    $timestamp = now()->subHours(8)->timestamp;

    $lead = Lead::create([
        'gclid' => 'EAIaIQobChMI-test-gclid',
        'conversions' => [[
            'event' => 'Purchase',
            'timestamp' => $timestamp,
            'value' => 20.0,
            'currency' => 'USD',
            'order_id' => '977',
            'consent' => [
                'ad_user_data' => 'GRANTED',
                'ad_personalization' => 'DENIED',
            ],
            'user_identifiers' => [
                'email' => 'Customer@Example.com',
                'phone' => '+14155552671',
            ],
            'status' => 'pending',
        ]],
    ]);

    $capturedIngestPayload = null;

    Http::fake([
        GoogleAdsSetup::OAUTH_TOKEN_ENDPOINT => Http::response([
            'access_token' => 'ya29.data-manager-token',
            'expires_in' => 3599,
            'token_type' => 'Bearer',
        ], 200),
        ConversionUploader::DATA_MANAGER_INGEST_ENDPOINT => function (Request $request) use (&$capturedIngestPayload) {
            expect($request->hasHeader('Authorization', 'Bearer ya29.data-manager-token'))->toBeTrue();
            $capturedIngestPayload = $request->data();

            return Http::response(['requestId' => 'dm-req-123'], 200);
        },
    ]);

    $count = app(ConversionUploader::class)->uploadPendingConversions(0, false);

    expect($count)->toBe(1)
        ->and($capturedIngestPayload)->not->toBeNull()
        ->and($capturedIngestPayload['validateOnly'])->toBeFalse()
        ->and($capturedIngestPayload['encoding'])->toBe('HEX')
        ->and($capturedIngestPayload['destinations'])->toHaveCount(1)
        ->and($capturedIngestPayload['destinations'][0])->toMatchArray([
            'reference' => 'dest_1',
            'operatingAccount' => [
                'accountType' => 'GOOGLE_ADS',
                'accountId' => '1234567890',
            ],
            'loginAccount' => [
                'accountType' => 'GOOGLE_ADS',
                'accountId' => '9998887777',
            ],
            'productDestinationId' => '111222333',
        ]);

    $event = $capturedIngestPayload['events'][0];
    expect($event['destinationReferences'])->toBe(['dest_1'])
        ->and($event['eventTimestamp'])->toBe(date('Y-m-d\TH:i:sP', $timestamp))
        ->and($event['eventSource'])->toBe('WEB')
        ->and($event['adIdentifiers'])->toBe(['gclid' => 'EAIaIQobChMI-test-gclid'])
        ->and($event['conversionValue'])->toBe(20.0)
        ->and($event['currency'])->toBe('USD')
        ->and($event['transactionId'])->toBe('977')
        ->and($event['consent'])->toBe([
            'adUserData' => 'CONSENT_GRANTED',
            'adPersonalization' => 'CONSENT_DENIED',
        ])
        ->and($event['userData']['userIdentifiers'])->toBe([
            ['emailAddress' => hash('sha256', 'customer@example.com')],
            ['phoneNumber' => hash('sha256', '+14155552671')],
        ]);

    $lead->refresh();
    expect($lead->getConversions()[0]['status'])->toBe('uploaded')
        ->and($lead->getConversions()[0])->toHaveKey('uploaded_at');
});

it('resolves numeric conversion action IDs directly without a GAQL lookup', function () {
    $lead = Lead::create([
        'gclid' => 'gclid-numeric-action',
        'conversions' => [[
            'event' => 'Quote Form', // mapped to '444555666'
            'timestamp' => now()->subHours(8)->timestamp,
            'value' => 50.0,
            'currency' => 'USD',
            'status' => 'pending',
        ]],
    ]);

    $capturedPayload = null;

    Http::fake([
        GoogleAdsSetup::OAUTH_TOKEN_ENDPOINT => Http::response(['access_token' => 'ya29.token'], 200),
        ConversionUploader::DATA_MANAGER_INGEST_ENDPOINT => function (Request $request) use (&$capturedPayload) {
            $capturedPayload = $request->data();

            return Http::response(['requestId' => 'dm-req-456'], 200);
        },
    ]);

    $count = app(ConversionUploader::class)->uploadPendingConversions(0, false);

    expect($count)->toBe(1)
        ->and($capturedPayload['destinations'][0]['productDestinationId'])->toBe('444555666')
        ->and($capturedPayload['destinations'][0]['operatingAccount']['accountId'])->toBe('1234567890');
});

it('isolates a violating event in a fast-fail Data Manager batch and re-sends the valid events', function () {
    $goodLead = Lead::create([
        'gclid' => 'gclid-good-event',
        'conversions' => [[
            'event' => 'Purchase',
            'timestamp' => now()->subHours(8)->timestamp,
            'value' => 100.0,
            'currency' => 'USD',
            'status' => 'pending',
        ]],
    ]);

    $badLead = Lead::create([
        'gclid' => 'gclid-bad-event',
        'conversions' => [[
            'event' => 'Purchase',
            'timestamp' => now()->subHours(8)->timestamp,
            'value' => 200.0,
            'currency' => 'USD',
            'status' => 'pending',
        ]],
    ]);

    $ingestCalls = 0;

    Http::fake([
        GoogleAdsSetup::OAUTH_TOKEN_ENDPOINT => Http::response(['access_token' => 'ya29.token'], 200),
        ConversionUploader::DATA_MANAGER_INGEST_ENDPOINT => function (Request $request) use (&$ingestCalls) {
            $ingestCalls++;
            $events = $request->data()['events'] ?? [];

            if ($ingestCalls === 1) {
                expect($events)->toHaveCount(2);

                // First call fails fast because events[1] is invalid
                return Http::response([
                    'error' => [
                        'code' => 400,
                        'message' => 'There was a problem with the request.',
                        'status' => 'INVALID_ARGUMENT',
                        'details' => [
                            [
                                '@type' => 'type.googleapis.com/google.rpc.BadRequest',
                                'fieldViolations' => [
                                    [
                                        'field' => 'events.events[1].ad_identifiers.gclid',
                                        'description' => 'The gclid could not be decoded.',
                                        'reason' => 'INVALID_FORMAT',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], 400);
            }

            // Second call contains only the valid event (events[0])
            expect($events)->toHaveCount(1)
                ->and($events[0]['adIdentifiers']['gclid'])->toBe('gclid-good-event');

            return Http::response(['requestId' => 'dm-retry-ok'], 200);
        },
    ]);

    $count = app(ConversionUploader::class)->uploadPendingConversions(0, false);

    expect($count)->toBe(1)
        ->and($ingestCalls)->toBe(2);

    expect($goodLead->fresh()->getConversions()[0]['status'])->toBe('uploaded')
        ->and($badLead->fresh()->getConversions()[0]['status'])->toBe('failed')
        ->and($badLead->fresh()->getConversions()[0]['error'])->toContain('The gclid could not be decoded.');
});

it('marks all batch conversions as failed with actionable guidance when OAuth token lacks datamanager scope', function () {
    $lead = Lead::create([
        'gclid' => 'gclid-missing-scope',
        'conversions' => [[
            'event' => 'Purchase',
            'timestamp' => now()->subHours(8)->timestamp,
            'value' => 20.0,
            'currency' => 'USD',
            'status' => 'pending',
        ]],
    ]);

    Http::fake([
        GoogleAdsSetup::OAUTH_TOKEN_ENDPOINT => Http::response(['access_token' => 'ya29.adwords-only-token'], 200),
        ConversionUploader::DATA_MANAGER_INGEST_ENDPOINT => Http::response([
            'error' => [
                'code' => 403,
                'message' => 'Request had insufficient authentication scopes.',
                'status' => 'PERMISSION_DENIED',
                'details' => [
                    [
                        '@type' => 'type.googleapis.com/google.rpc.ErrorInfo',
                        'reason' => 'ACCESS_TOKEN_SCOPE_INSUFFICIENT',
                        'domain' => 'googleapis.com',
                    ],
                ],
            ],
        ], 403),
    ]);

    $count = app(ConversionUploader::class)->uploadPendingConversions(0, false);

    expect($count)->toBe(0);

    $conv = $lead->fresh()->getConversions()[0];
    expect($conv['status'])->toBe('failed')
        ->and($conv['error'])->toContain('https://www.googleapis.com/auth/datamanager')
        ->and($conv['error'])->toContain('google-ads:install --setup');
});

it('supports uploadPayloads via the Data Manager API', function () {
    Http::fake([
        GoogleAdsSetup::OAUTH_TOKEN_ENDPOINT => Http::response(['access_token' => 'ya29.token'], 200),
        ConversionUploader::DATA_MANAGER_INGEST_ENDPOINT => Http::response(['requestId' => 'dm-payload-ok'], 200),
    ]);

    $payload = new ConversionPayload(
        eventName: 'Purchase',
        value: 45.0,
        currency: 'USD',
        orderId: 'ORD-500',
        timestamp: now()->subHours(2)->timestamp,
        gclid: 'gclid-payload-dm',
    );

    $result = app(ConversionUploader::class)->uploadPayloads([$payload], validateOnly: true);

    expect($result['count'])->toBe(1)
        ->and($result['errors'])->toBeEmpty();
});

it('requeues falsely uploaded, failed, or rejected conversions back to pending via ad-conversions:requeue', function () {
    $lead1 = Lead::create([
        'gclid' => 'gclid-falsely-uploaded',
        'conversions' => [
            [
                'event' => 'Purchase',
                'timestamp' => now()->subDays(2)->timestamp,
                'value' => 20.0,
                'currency' => 'USD',
                'order_id' => '977',
                'status' => 'uploaded',
                'uploaded_at' => now()->subHours(3)->timestamp,
            ],
        ],
    ]);

    $lead2 = Lead::create([
        'gclid' => 'gclid-rejected-conv',
        'conversions' => [
            [
                'event' => 'Quote Form',
                'timestamp' => now()->subDays(3)->timestamp,
                'value' => 10.0,
                'currency' => 'USD',
                'status' => 'rejected',
                'rejected_at' => now()->subHours(2)->timestamp,
                'error' => 'Exceeded maximum retries (5).',
                'retry_count' => 5,
            ],
        ],
    ]);

    // 1. Default invocation resets failed & rejected, leaving uploaded untouched
    $this->artisan('ad-conversions:requeue')
        ->expectsOutput('Reset 1 conversion(s) across 1 lead(s) back to pending.')
        ->assertSuccessful();

    expect($lead1->fresh()->getConversions()[0]['status'])->toBe('uploaded');
    $requeued2 = $lead2->fresh()->getConversions()[0];
    expect($requeued2['status'])->toBe('pending')
        ->and($requeued2)->not->toHaveKey('rejected_at')
        ->and($requeued2)->not->toHaveKey('error')
        ->and($requeued2)->not->toHaveKey('retry_count');

    // 2. Dry-run with --status=uploaded previews without persisting
    $this->artisan('ad-conversions:requeue', ['--status' => ['uploaded'], '--dry-run' => true])
        ->expectsOutput('Dry run: 1 conversion(s) across 1 lead(s) would be reset to pending.')
        ->assertSuccessful();

    expect($lead1->fresh()->getConversions()[0]['status'])->toBe('uploaded');

    // 3. Real run with --status=uploaded resets the falsely uploaded conversion back to pending
    $this->artisan('google-ads:requeue', ['--status' => ['uploaded']])
        ->expectsOutput('Reset 1 conversion(s) across 1 lead(s) back to pending.')
        ->assertSuccessful();

    $requeued1 = $lead1->fresh()->getConversions()[0];
    expect($requeued1['status'])->toBe('pending')
        ->and($requeued1)->not->toHaveKey('uploaded_at');
});
