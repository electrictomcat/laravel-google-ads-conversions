<?php

namespace ElectricTomCat\GoogleAdsConversions\Tests\Feature;

use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use ElectricTomCat\GoogleAdsConversions\Models\Lead;
use ElectricTomCat\GoogleAdsConversions\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class GclidResolutionTest extends TestCase
{
    public function test_it_exposes_the_resolved_gclid_from_the_session(): void
    {
        Session::put('google_ads_gclid', 'gclid-from-session');

        $this->assertSame('gclid-from-session', app(GoogleAdsConversions::class)->gclid());
    }

    public function test_it_falls_back_to_the_visitor_history_lookup(): void
    {
        Lead::create(['gclid' => 'gclid-from-history', 'visitor_id' => 'visitor-abc']);

        request()->cookies->set('google_ads_visitor_id', 'visitor-abc');

        $this->assertSame('gclid-from-history', app(GoogleAdsConversions::class)->gclid());
    }

    public function test_it_returns_null_when_nothing_resolves(): void
    {
        $this->assertNull(app(GoogleAdsConversions::class)->gclid());
    }

    public function test_it_memoizes_the_visitor_history_lookup_for_the_request(): void
    {
        Lead::create(['gclid' => 'gclid-memo', 'visitor_id' => 'visitor-memo']);

        request()->cookies->set('google_ads_visitor_id', 'visitor-memo');

        $conversions = app(GoogleAdsConversions::class);

        // Prime the memo, then count queries on the repeat calls.
        $this->assertSame('gclid-memo', $conversions->gclid());

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $this->assertSame('gclid-memo', $conversions->gclid());
        $this->assertSame('gclid-memo', $conversions->gclid());
        $this->assertSame(0, $queries);
    }

    public function test_it_memoizes_a_null_result_so_a_missing_gclid_is_not_looked_up_repeatedly(): void
    {
        request()->cookies->set('google_ads_visitor_id', 'visitor-unknown');

        $conversions = app(GoogleAdsConversions::class);

        $this->assertNull($conversions->gclid());

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $this->assertNull($conversions->gclid());
        $this->assertSame(0, $queries);
    }

    public function test_it_resolves_again_after_forget_gclid(): void
    {
        $conversions = app(GoogleAdsConversions::class);

        $this->assertNull($conversions->gclid());

        Session::put('google_ads_gclid', 'gclid-after-forget');
        $conversions->forgetGclid();

        $this->assertSame('gclid-after-forget', $conversions->gclid());
    }
}
