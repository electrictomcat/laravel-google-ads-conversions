<?php

namespace ElectricTomCat\GoogleAdsConversions\Tests\Feature;

use ElectricTomCat\GoogleAdsConversions\ConversionUploader;
use ElectricTomCat\GoogleAdsConversions\DTO\ConversionPayload;
use ElectricTomCat\GoogleAdsConversions\Models\Lead;
use ElectricTomCat\GoogleAdsConversions\Support\GoogleAdsSetup;
use ElectricTomCat\GoogleAdsConversions\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class DataManagerUploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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
    }

    public function test_it_defaults_upload_api_to_data_manager_and_includes_datamanager_scope_in_oauth_url(): void
    {
        $this->assertSame('data_manager', config('google-ads-conversions.upload_api'));

        $setup = new GoogleAdsSetup;
        $url = rawurldecode($setup->buildAuthorizationUrl('client-123'));

        $this->assertStringContainsString(GoogleAdsSetup::ADWORDS_SCOPE, $url);
        $this->assertStringContainsString(GoogleAdsSetup::DATA_MANAGER_SCOPE, $url);
    }

    public function test_it_uploads_pending_conversions_to_google_data_manager_api_events_ingest_with_full_payload_mapping(): void
    {
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
                $this->assertTrue($request->hasHeader('Authorization', 'Bearer ya29.data-manager-token'));
                $capturedIngestPayload = $request->data();

                return Http::response(['requestId' => 'dm-req-123'], 200);
            },
        ]);

        $count = app(ConversionUploader::class)->uploadPendingConversions(0, false);

        $this->assertSame(1, $count);
        $this->assertNotNull($capturedIngestPayload);
        $this->assertFalse($capturedIngestPayload['validateOnly']);
        $this->assertSame('HEX', $capturedIngestPayload['encoding']);
        $this->assertCount(1, $capturedIngestPayload['destinations']);
        $this->assertEquals([
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
        ], $capturedIngestPayload['destinations'][0]);

        $event = $capturedIngestPayload['events'][0];
        $this->assertSame(['dest_1'], $event['destinationReferences']);
        $this->assertSame(date('Y-m-d\TH:i:sP', $timestamp), $event['eventTimestamp']);
        $this->assertSame('WEB', $event['eventSource']);
        $this->assertSame(['gclid' => 'EAIaIQobChMI-test-gclid'], $event['adIdentifiers']);
        $this->assertSame(20.0, $event['conversionValue']);
        $this->assertSame('USD', $event['currency']);
        $this->assertSame('977', $event['transactionId']);
        $this->assertSame([
            'adUserData' => 'CONSENT_GRANTED',
            'adPersonalization' => 'CONSENT_DENIED',
        ], $event['consent']);
        $this->assertSame([
            ['emailAddress' => hash('sha256', 'customer@example.com')],
            ['phoneNumber' => hash('sha256', '+14155552671')],
        ], $event['userData']['userIdentifiers']);

        $lead->refresh();
        $this->assertSame('uploaded', $lead->getConversions()[0]['status']);
        $this->assertArrayHasKey('uploaded_at', $lead->getConversions()[0]);
    }

    public function test_it_resolves_numeric_conversion_action_ids_directly_without_a_gaql_lookup(): void
    {
        Lead::create([
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

        $this->assertSame(1, $count);
        $this->assertSame('444555666', $capturedPayload['destinations'][0]['productDestinationId']);
        $this->assertSame('1234567890', $capturedPayload['destinations'][0]['operatingAccount']['accountId']);
    }

    public function test_it_isolates_a_violating_event_in_a_fast_fail_data_manager_batch_and_re_sends_the_valid_events(): void
    {
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
                    $this->assertCount(2, $events);

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
                $this->assertCount(1, $events);
                $this->assertSame('gclid-good-event', $events[0]['adIdentifiers']['gclid']);

                return Http::response(['requestId' => 'dm-retry-ok'], 200);
            },
        ]);

        $count = app(ConversionUploader::class)->uploadPendingConversions(0, false);

        $this->assertSame(1, $count);
        $this->assertSame(2, $ingestCalls);

        $this->assertSame('uploaded', $goodLead->fresh()->getConversions()[0]['status']);
        $this->assertSame('failed', $badLead->fresh()->getConversions()[0]['status']);
        $this->assertStringContainsString('The gclid could not be decoded.', $badLead->fresh()->getConversions()[0]['error']);
    }

    public function test_it_marks_all_batch_conversions_as_failed_with_actionable_guidance_when_oauth_token_lacks_datamanager_scope(): void
    {
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

        $this->assertSame(0, $count);

        $conv = $lead->fresh()->getConversions()[0];
        $this->assertSame('failed', $conv['status']);
        $this->assertStringContainsString('https://www.googleapis.com/auth/datamanager', $conv['error']);
        $this->assertStringContainsString('google-ads:install --setup', $conv['error']);
    }

    public function test_it_supports_upload_payloads_via_the_data_manager_api(): void
    {
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

        $this->assertSame(1, $result['count']);
        $this->assertEmpty($result['errors']);
    }

    public function test_it_requeues_falsely_uploaded_failed_or_rejected_conversions_back_to_pending_via_ad_conversions_requeue(): void
    {
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

        $this->assertSame('uploaded', $lead1->fresh()->getConversions()[0]['status']);
        $requeued2 = $lead2->fresh()->getConversions()[0];
        $this->assertSame('pending', $requeued2['status']);
        $this->assertArrayNotHasKey('rejected_at', $requeued2);
        $this->assertArrayNotHasKey('error', $requeued2);
        $this->assertArrayNotHasKey('retry_count', $requeued2);

        // 2. Dry-run with --status=uploaded previews without persisting
        $this->artisan('ad-conversions:requeue', ['--status' => ['uploaded'], '--dry-run' => true])
            ->expectsOutput('Dry run: 1 conversion(s) across 1 lead(s) would be reset to pending.')
            ->assertSuccessful();

        $this->assertSame('uploaded', $lead1->fresh()->getConversions()[0]['status']);

        // 3. Real run with --status=uploaded resets the falsely uploaded conversion back to pending
        $this->artisan('google-ads:requeue', ['--status' => ['uploaded']])
            ->expectsOutput('Reset 1 conversion(s) across 1 lead(s) back to pending.')
            ->assertSuccessful();

        $requeued1 = $lead1->fresh()->getConversions()[0];
        $this->assertSame('pending', $requeued1['status']);
        $this->assertArrayNotHasKey('uploaded_at', $requeued1);
    }
}
