<?php

use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use ElectricTomCat\GoogleAdsConversions\Models\Lead;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    Schema::dropIfExists('leads');
    Schema::create('leads', function (Blueprint $table) {
        $table->id();
        $table->string('gclid')->nullable()->unique();
        $table->uuid('visitor_id')->nullable()->index();
        $table->json('conversions')->nullable();
        $table->text('landing_page')->nullable();
        $table->string('source')->nullable();
        $table->string('utm_source')->nullable();
        $table->string('utm_medium')->nullable();
        $table->string('utm_campaign')->nullable();
        $table->string('utm_content')->nullable();
        $table->string('utm_term')->nullable();
        $table->string('gad_source')->nullable();
        $table->string('gad_campaignid')->nullable();
        $table->timestamps();
    });

    GoogleAdsConversions::flushColumnCache();
});

afterEach(function () {
    // Restore default full schema for other tests
    Schema::dropIfExists('leads');
    $migration = include __DIR__.'/../../database/migrations/create_leads_table.php.stub';
    $migration->up();
    GoogleAdsConversions::flushColumnCache();
});

it('syncs a gclid conversion to a legacy database table without gbraid/wbraid columns', function () {
    $gclid = 'EAIaIQobChMI8NL3yPLUlgMVIzIIBR3GmS61EAAYASABEgIcIfD_BwE';

    Cache::put(GoogleAdsConversions::CACHE_PREFIX.$gclid, [[
        'event' => 'Quote Form',
        'timestamp' => now()->timestamp,
        'value' => 500.0,
        'currency' => 'USD',
        'status' => 'pending',
    ]]);
    Cache::put(GoogleAdsConversions::DIRTY_BUCKET_PREFIX.(crc32($gclid) % GoogleAdsConversions::DIRTY_BUCKETS), [$gclid]);

    app(GoogleAdsConversions::class)->syncToDatabase();

    $lead = Lead::where('gclid', $gclid)->first();
    expect($lead)->not->toBeNull()
        ->and($lead->getGclid())->toBe($gclid)
        ->and($lead->getConversions())->toHaveCount(1);
});

it('syncs a gbraid click to gclid column when gbraid column does not exist', function () {
    $gbraid = '0AAAAAtestgbraid12345';

    Cache::put(GoogleAdsConversions::CACHE_PREFIX.$gbraid, [[
        'event' => 'Lead Form',
        'timestamp' => now()->timestamp,
        'status' => 'pending',
    ]]);
    Cache::put(GoogleAdsConversions::LEAD_DATA_PREFIX.$gbraid, [
        'gbraid' => $gbraid,
        'landing_page' => '/contact',
    ]);
    Cache::put(GoogleAdsConversions::DIRTY_BUCKET_PREFIX.(crc32($gbraid) % GoogleAdsConversions::DIRTY_BUCKETS), [$gbraid]);

    app(GoogleAdsConversions::class)->syncToDatabase();

    $lead = Lead::where('gclid', $gbraid)->first();
    expect($lead)->not->toBeNull()
        ->and($lead->getGclid())->toBe($gbraid);
});

it('resolves visitor history on a legacy table without gbraid or wbraid columns', function () {
    $visitorId = (string) Str::uuid();
    $gclid = 'gclid-legacy-123';

    Lead::create([
        'visitor_id' => $visitorId,
        'gclid' => $gclid,
    ]);

    request()->cookies->set('google_ads_visitor_id', $visitorId);

    $tracker = app(GoogleAdsConversions::class);
    expect($tracker->gclid())->toBe($gclid);
});

it('warns about missing gbraid and wbraid columns in diagnose command', function () {
    // Create a lead so reportLocalData runs
    Lead::create([
        'gclid' => 'gclid-diag-123',
        'created_at' => now(),
    ]);

    $this->artisan('ad-conversions:diagnose')
        ->expectsOutputToContain('Table is missing gbraid/wbraid columns.')
        ->assertExitCode(1);
});

it('adds gbraid and wbraid columns via migration and is idempotent', function () {
    expect(Schema::hasColumn('leads', 'gbraid'))->toBeFalse()
        ->and(Schema::hasColumn('leads', 'wbraid'))->toBeFalse();

    $migration = include __DIR__.'/../../database/migrations/add_gbraid_and_wbraid_to_leads_table.php.stub';
    $migration->up();

    GoogleAdsConversions::flushColumnCache();

    expect(Schema::hasColumn('leads', 'gbraid'))->toBeTrue()
        ->and(Schema::hasColumn('leads', 'wbraid'))->toBeTrue();

    // Running again does not throw or fail (idempotent)
    $migration->up();
    expect(Schema::hasColumn('leads', 'gbraid'))->toBeTrue();

    // Down drops them
    $migration->down();
    expect(Schema::hasColumn('leads', 'gbraid'))->toBeFalse()
        ->and(Schema::hasColumn('leads', 'wbraid'))->toBeFalse();
});
