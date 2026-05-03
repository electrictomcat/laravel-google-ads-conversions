<?php

use ElectricTomCat\GoogleAdsConversions\Contracts\HasConversions;
use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use ElectricTomCat\GoogleAdsConversions\Tests\Fixtures\CustomLead;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    Schema::dropIfExists('custom_leads');
    Schema::create('custom_leads', function (Blueprint $table) {
        $table->id();
        $table->string('gclid')->unique()->index();
        $table->uuid('visitor_id')->nullable()->index();
        $table->json('conversions')->nullable();
        $table->string('utm_source')->nullable();
        $table->timestamps();
    });

    config()->set('google-ads-conversions.model', CustomLead::class);
});

it('uses a consumer-supplied model that implements HasConversions', function () {
    $gclid = 'gclid-custom-'.Str::random(8);

    Cache::put(GoogleAdsConversions::CACHE_PREFIX.$gclid, [[
        'event' => 'Quote Form',
        'timestamp' => now()->timestamp,
        'value' => 75.0,
        'currency' => 'USD',
        'status' => 'pending',
    ]]);
    Cache::put(GoogleAdsConversions::DIRTY_SET_KEY, [$gclid]);

    app(GoogleAdsConversions::class)->syncToDatabase();

    $lead = CustomLead::where('gclid', $gclid)->first();

    expect($lead)->not->toBeNull()
        ->and($lead)->toBeInstanceOf(HasConversions::class)
        ->and($lead->getConversions())->toHaveCount(1)
        ->and($lead->getConversions()->first()['event'])->toBe('Quote Form');
});

it('confirms the trait gives bring-your-own models the full contract', function () {
    $lead = new CustomLead;

    expect(method_exists($lead, 'getGclid'))->toBeTrue()
        ->and(method_exists($lead, 'setConversions'))->toBeTrue()
        ->and(method_exists($lead, 'fillTrackingData'))->toBeTrue()
        ->and(method_exists($lead, 'persist'))->toBeTrue();
});
