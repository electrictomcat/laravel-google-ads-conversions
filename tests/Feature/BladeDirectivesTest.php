<?php

namespace ElectricTomCat\GoogleAdsConversions\Tests\Feature;

use ElectricTomCat\GoogleAdsConversions\Facades\GoogleAdsConversions;
use ElectricTomCat\GoogleAdsConversions\Tests\TestCase;
use Illuminate\Support\Facades\Blade;

class BladeDirectivesTest extends TestCase
{
    public function test_it_renders_blade_directives_for_hidden_click_inputs(): void
    {
        GoogleAdsConversions::fake('gclid-blade-test');

        $rendered = Blade::render('@googleAdsGclid');

        $this->assertStringContainsString('<input type="hidden" name="gclid" value="gclid-blade-test">', $rendered);

        $renderedAll = Blade::render('@googleAdsClickInputs');
        $this->assertStringContainsString('<input type="hidden" name="gclid" value="gclid-blade-test">', $renderedAll);
    }
}
