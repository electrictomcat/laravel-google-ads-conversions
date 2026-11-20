<?php

namespace ElectricTomCat\GoogleAdsConversions\Tests\Feature;

use ElectricTomCat\GoogleAdsConversions\Contracts\HasConversions;
use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use ElectricTomCat\GoogleAdsConversions\Models\Concerns\HasConversionsTrait;
use ElectricTomCat\GoogleAdsConversions\Tests\Fixtures\CustomLead;
use ElectricTomCat\GoogleAdsConversions\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CustomModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('custom_leads');
        Schema::create('custom_leads', function (Blueprint $table) {
            $table->id();
            $table->string('gclid')->nullable()->unique();
            $table->string('gbraid')->nullable()->index();
            $table->string('wbraid')->nullable()->index();
            $table->uuid('visitor_id')->nullable()->index();
            $table->json('conversions')->nullable();
            $table->string('utm_source')->nullable();
            $table->timestamps();
        });

        config()->set('google-ads-conversions.model', CustomLead::class);
    }

    public function test_it_uses_a_consumer_supplied_model_that_implements_has_conversions(): void
    {
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

        $this->assertNotNull($lead);
        $this->assertInstanceOf(HasConversions::class, $lead);
        $this->assertCount(1, $lead->getConversions());
        $this->assertSame('Quote Form', $lead->getConversions()->first()['event']);
    }

    public function test_it_confirms_the_trait_gives_bring_your_own_models_the_full_contract(): void
    {
        $lead = new CustomLead;

        $this->assertTrue(method_exists($lead, 'getGclid'));
        $this->assertTrue(method_exists($lead, 'getGbraid'));
        $this->assertTrue(method_exists($lead, 'getWbraid'));
        $this->assertTrue(method_exists($lead, 'setConversions'));
        $this->assertTrue(method_exists($lead, 'fillTrackingData'));
        $this->assertTrue(method_exists($lead, 'persist'));
    }

    public function test_it_fills_tracking_data_correctly_even_when_custom_model_uses_guarded_empty_array(): void
    {
        $guardedModel = new class extends Model implements HasConversions
        {
            use HasConversionsTrait;

            protected $guarded = [];
        };

        $guardedModel->fillTrackingData(['utm_source' => 'google_cpc']);

        $this->assertSame('google_cpc', $guardedModel->utm_source);
    }
}
