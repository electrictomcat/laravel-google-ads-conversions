<?php

namespace ElectricTomCat\GoogleAdsConversions\Tests\Feature;

use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use ElectricTomCat\GoogleAdsConversions\Models\Lead;
use ElectricTomCat\GoogleAdsConversions\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LegacySchemaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('leads');
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('gclid')->unique();
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
    }

    protected function tearDown(): void
    {
        // Restore default full schema for other tests
        Schema::dropIfExists('leads');
        $migration = include __DIR__.'/../../database/migrations/create_leads_table.php.stub';
        $migration->up();
        GoogleAdsConversions::flushColumnCache();

        parent::tearDown();
    }

    public function test_it_syncs_a_gclid_conversion_to_a_legacy_database_table_without_gbraid_wbraid_columns(): void
    {
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
        $this->assertNotNull($lead);
        $this->assertSame($gclid, $lead->getGclid());
        $this->assertCount(1, $lead->getConversions());
    }

    public function test_it_syncs_a_gbraid_click_to_gclid_column_when_gbraid_column_does_not_exist(): void
    {
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
        $this->assertNotNull($lead);
        $this->assertSame($gbraid, $lead->getGclid());
    }

    public function test_it_resolves_visitor_history_on_a_legacy_table_without_gbraid_or_wbraid_columns(): void
    {
        $visitorId = (string) Str::uuid();
        $gclid = 'gclid-legacy-123';

        Lead::create([
            'visitor_id' => $visitorId,
            'gclid' => $gclid,
        ]);

        request()->cookies->set('google_ads_visitor_id', $visitorId);

        $tracker = app(GoogleAdsConversions::class);
        $this->assertSame($gclid, $tracker->gclid());
    }

    public function test_it_warns_about_missing_gbraid_wbraid_columns_and_non_nullable_gclid_in_diagnose_command(): void
    {
        // Create a lead so reportLocalData runs
        Lead::create([
            'gclid' => 'gclid-diag-123',
            'created_at' => now(),
        ]);

        $this->artisan('ad-conversions:diagnose')
            ->expectsOutputToContain('Table is missing gbraid/wbraid columns.')
            ->expectsOutputToContain("Table column 'gclid' is NOT NULL.")
            ->assertExitCode(1);
    }

    public function test_it_adds_gbraid_and_wbraid_columns_and_makes_gclid_nullable_via_migration(): void
    {
        $tracker = app(GoogleAdsConversions::class);

        $this->assertFalse(Schema::hasColumn('leads', 'gbraid'));
        $this->assertFalse(Schema::hasColumn('leads', 'wbraid'));
        $this->assertFalse($tracker->modelColumnIsNullable('gclid'));

        $migration = include __DIR__.'/../../database/migrations/add_gbraid_and_wbraid_to_leads_table.php.stub';
        $migration->up();

        GoogleAdsConversions::flushColumnCache();

        $this->assertTrue(Schema::hasColumn('leads', 'gbraid'));
        $this->assertTrue(Schema::hasColumn('leads', 'wbraid'));
        $this->assertTrue($tracker->modelColumnIsNullable('gclid'));

        // Can now insert a lead with only gbraid and no gclid without SQL integrity constraint violation
        $lead = Lead::create([
            'visitor_id' => (string) Str::uuid(),
            'gbraid' => '0AAAAA_test_braid',
        ]);
        $this->assertSame('0AAAAA_test_braid', $lead->getGbraid());
        $this->assertNull($lead->getGclid());

        // Running again does not throw or fail (idempotent)
        $migration->up();
        $this->assertTrue(Schema::hasColumn('leads', 'gbraid'));
        $this->assertTrue($tracker->modelColumnIsNullable('gclid'));

        // Down drops braid columns
        $migration->down();
        $this->assertFalse(Schema::hasColumn('leads', 'gbraid'));
        $this->assertFalse(Schema::hasColumn('leads', 'wbraid'));
    }

    public function test_it_safely_syncs_a_gbraid_click_when_gbraid_column_exists_but_gclid_is_not_null(): void
    {
        // Manually add gbraid column without making gclid nullable
        Schema::table('leads', function (Blueprint $table) {
            $table->string('gbraid')->nullable()->index();
        });
        GoogleAdsConversions::flushColumnCache();

        $tracker = app(GoogleAdsConversions::class);
        $this->assertTrue($tracker->modelHasColumn('gbraid'));
        $this->assertFalse($tracker->modelColumnIsNullable('gclid'));

        $gbraid = '0AAAAA_non_nullable_gclid_test';

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

        // syncToDatabase must not throw SQL integrity constraint violation for gclid cannot be null
        $tracker->syncToDatabase();

        $lead = Lead::where('gclid', $gbraid)->first();
        $this->assertNotNull($lead);
        $this->assertSame($gbraid, $lead->getGbraid());
        $this->assertSame($gbraid, $lead->getGclid());
    }
}
