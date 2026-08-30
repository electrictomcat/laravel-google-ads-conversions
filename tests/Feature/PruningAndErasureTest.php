<?php

use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use ElectricTomCat\GoogleAdsConversions\Models\Lead;

it('identifies prunable leads older than retention days', function () {
    config()->set('google-ads-conversions.privacy.retention_days', 90);

    $oldLead = Lead::create(['gclid' => 'gclid-old']);
    $oldLead->timestamps = false;
    $oldLead->created_at = now()->subDays(91);
    $oldLead->save();

    $newLead = Lead::create(['gclid' => 'gclid-new']);

    $prunableIds = (new Lead)->prunable()->pluck('id')->all();

    expect($prunableIds)->toContain($oldLead->id)
        ->and($prunableIds)->not->toContain($newLead->id);
});

it('erases visitor data via forgetVisitor for GDPR right to erasure', function () {
    $visitorId = 'uuid-gdpr-erasure-123';

    Lead::create(['gclid' => 'gclid-gdpr-1', 'visitor_id' => $visitorId]);
    Lead::create(['gclid' => 'gclid-gdpr-2', 'visitor_id' => $visitorId]);
    Lead::create(['gclid' => 'gclid-other', 'visitor_id' => 'uuid-other']);

    $deletedCount = app(GoogleAdsConversions::class)->forgetVisitor($visitorId);

    expect($deletedCount)->toBe(2)
        ->and(Lead::where('visitor_id', $visitorId)->count())->toBe(0)
        ->and(Lead::where('visitor_id', 'uuid-other')->count())->toBe(1);
});
