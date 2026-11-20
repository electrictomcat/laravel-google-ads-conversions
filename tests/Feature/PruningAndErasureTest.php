<?php

namespace ElectricTomCat\GoogleAdsConversions\Tests\Feature;

use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use ElectricTomCat\GoogleAdsConversions\Models\Lead;
use ElectricTomCat\GoogleAdsConversions\Tests\TestCase;

class PruningAndErasureTest extends TestCase
{
    public function test_it_identifies_prunable_leads_older_than_retention_days(): void
    {
        config()->set('google-ads-conversions.privacy.retention_days', 90);

        $oldLead = Lead::create(['gclid' => 'gclid-old']);
        $oldLead->timestamps = false;
        $oldLead->created_at = now()->subDays(91);
        $oldLead->save();

        $newLead = Lead::create(['gclid' => 'gclid-new']);

        $prunableIds = (new Lead)->prunable()->pluck('id')->all();

        $this->assertContains($oldLead->id, $prunableIds);
        $this->assertNotContains($newLead->id, $prunableIds);
    }

    public function test_it_erases_visitor_data_via_forget_visitor_for_gdpr_right_to_erasure(): void
    {
        $visitorId = 'uuid-gdpr-erasure-123';

        Lead::create(['gclid' => 'gclid-gdpr-1', 'visitor_id' => $visitorId]);
        Lead::create(['gclid' => 'gclid-gdpr-2', 'visitor_id' => $visitorId]);
        Lead::create(['gclid' => 'gclid-other', 'visitor_id' => 'uuid-other']);

        $deletedCount = app(GoogleAdsConversions::class)->forgetVisitor($visitorId);

        $this->assertSame(2, $deletedCount);
        $this->assertSame(0, Lead::where('visitor_id', $visitorId)->count());
        $this->assertSame(1, Lead::where('visitor_id', 'uuid-other')->count());
    }
}
