<?php

namespace ElectricTomCat\GoogleAdsConversions\Tests\Feature;

use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use ElectricTomCat\GoogleAdsConversions\Http\Middleware\CaptureGclid;
use ElectricTomCat\GoogleAdsConversions\Models\Lead;
use ElectricTomCat\GoogleAdsConversions\Tests\TestCase;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

class MiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware([
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            CaptureGclid::class,
        ])->get('/landing', fn () => 'ok');
    }

    public function test_it_buffers_gclid_and_tracking_data_in_cache_not_in_the_database(): void
    {
        $gclid = 'gclid-mw-buffer';

        $this->get('/landing?gclid='.$gclid.'&utm_source=google&utm_campaign=spring')
            ->assertOk();

        $this->assertFalse(Lead::where('gclid', $gclid)->exists());
        $this->assertContains($gclid, app(GoogleAdsConversions::class)->pendingClickIds());

        $buffered = Cache::get(GoogleAdsConversions::LEAD_DATA_PREFIX.$gclid);

        $this->assertSame('google', $buffered['utm_source']);
        $this->assertSame('spring', $buffered['utm_campaign']);
        $this->assertSame('/landing', $buffered['landing_page']);
    }

    public function test_it_persists_buffered_data_into_the_database_after_sync(): void
    {
        $gclid = 'gclid-mw-persist';

        $this->get('/landing?gclid='.$gclid.'&utm_source=google');

        app(GoogleAdsConversions::class)->syncToDatabase();

        $lead = Lead::where('gclid', $gclid)->first();

        $this->assertNotNull($lead);
        $this->assertSame('google', $lead->utm_source);
        $this->assertNotNull($lead->visitor_id);
    }

    public function test_it_also_captures_gbraid_as_a_gclid_alternative(): void
    {
        $this->get('/landing?gbraid=test-gbraid');

        $this->assertContains('test-gbraid', app(GoogleAdsConversions::class)->pendingClickIds());
    }

    public function test_it_does_not_buffer_when_no_click_id_is_on_the_url(): void
    {
        $this->get('/landing?utm_source=organic');

        $this->assertEmpty(app(GoogleAdsConversions::class)->pendingClickIds());
    }
}
