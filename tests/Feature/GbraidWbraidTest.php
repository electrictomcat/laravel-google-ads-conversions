<?php

namespace ElectricTomCat\GoogleAdsConversions\Tests\Feature;

use ElectricTomCat\GoogleAdsConversions\ConversionUploader;
use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use ElectricTomCat\GoogleAdsConversions\Http\Middleware\CaptureGclid;
use ElectricTomCat\GoogleAdsConversions\Models\Lead;
use ElectricTomCat\GoogleAdsConversions\Support\ConsentManager;
use ElectricTomCat\GoogleAdsConversions\Support\EventResolver;
use ElectricTomCat\GoogleAdsConversions\Support\UserDataHasher;
use ElectricTomCat\GoogleAdsConversions\Tests\TestCase;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Mockery;

class GbraidWbraidTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware([
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            CaptureGclid::class,
        ])->get('/gbraid-landing', fn () => 'ok');
    }

    public function test_it_captures_and_buffers_gbraid_and_wbraid_with_distinct_columns_in_database_after_sync(): void
    {
        $gbraid = 'gbraid-test-'.Str::random(6);
        $wbraid = 'wbraid-test-'.Str::random(6);

        $this->get("/gbraid-landing?gbraid={$gbraid}")->assertOk();
        app(GoogleAdsConversions::class)->syncToDatabase();

        $leadGbraid = Lead::where('gbraid', $gbraid)->first();
        $this->assertNotNull($leadGbraid);
        $this->assertSame($gbraid, $leadGbraid->gbraid);

        $this->get("/gbraid-landing?wbraid={$wbraid}")->assertOk();
        app(GoogleAdsConversions::class)->syncToDatabase();

        $leadWbraid = Lead::where('wbraid', $wbraid)->first();
        $this->assertNotNull($leadWbraid);
        $this->assertSame($wbraid, $leadWbraid->wbraid);
    }

    public function test_it_resolves_gbraid_and_wbraid_independently_from_gclid(): void
    {
        $conversions = app(GoogleAdsConversions::class);

        session(['google_ads_gbraid' => 'session-gbraid-123']);
        session(['google_ads_wbraid' => 'session-wbraid-456']);

        $this->assertSame('session-gbraid-123', $conversions->gbraid());
        $this->assertSame('session-wbraid-456', $conversions->wbraid());
        $this->assertSame('session-gbraid-123', $conversions->clickId());
    }

    public function test_it_constructs_click_conversion_with_set_gbraid_and_set_wbraid_respectively(): void
    {
        config()->set('google-ads-conversions.events', [
            'Quote Form' => 'customers/1234567890/conversionActions/111111',
        ]);

        Lead::create([
            'gbraid' => 'gbraid-val-123',
            'conversions' => [[
                'event' => 'Quote Form',
                'timestamp' => now()->subHours(8)->timestamp,
                'status' => 'pending',
            ]],
        ]);

        $uploader = Mockery::mock(ConversionUploader::class.'[uploadBatch]', [
            app(EventResolver::class),
            app(ConsentManager::class),
            app(UserDataHasher::class),
        ]);

        $uploader->shouldReceive('uploadBatch')
            ->once()
            ->andReturnUsing(function ($batchItems, $clicks) {
                $this->assertSame('gbraid-val-123', $clicks[0]->getGbraid());
                $this->assertSame('', $clicks[0]->getGclid());

                return 1;
            });

        $uploader->uploadPendingConversions();
    }
}
