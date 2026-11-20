<?php

namespace ElectricTomCat\GoogleAdsConversions\Tests\Unit;

use ElectricTomCat\GoogleAdsConversions\Support\EventResolver;
use ElectricTomCat\GoogleAdsConversions\Tests\TestCase;

class EventResolverTest extends TestCase
{
    private EventResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('google-ads-conversions.events', [
            'Quote Form' => 'Quote Submission',
            'Demo Booked' => [
                'action' => 'Demo Booked Action',
                'value' => 250.00,
                'currency' => 'EUR',
            ],
            'Page Navigation' => 'Page Navigation Action',
        ]);
        config()->set('google-ads-conversions.default_currency', 'USD');
        config()->set('google-ads-conversions.allow_unmapped_events', false);

        $this->resolver = new EventResolver;
    }

    public function test_it_resolves_a_string_event_to_its_action_name(): void
    {
        $this->assertSame('Quote Submission', $this->resolver->action('Quote Form'));
    }

    public function test_it_resolves_an_array_event_to_its_action_name(): void
    {
        $this->assertSame('Demo Booked Action', $this->resolver->action('Demo Booked'));
    }

    public function test_it_returns_null_for_an_unmapped_event_when_allow_unmapped_events_is_false(): void
    {
        $this->assertNull($this->resolver->action('Unknown Event'));
    }

    public function test_it_returns_the_event_name_for_unmapped_events_when_allow_unmapped_events_is_true(): void
    {
        config()->set('google-ads-conversions.allow_unmapped_events', true);

        $this->assertSame('Direct Action Name', $this->resolver->action('Direct Action Name'));
    }

    public function test_it_matches_by_prefix_for_prefix_events(): void
    {
        $this->assertSame('Page Navigation Action', $this->resolver->action('Page Navigation: /pricing'));
    }

    public function test_it_prefers_the_call_site_value_over_the_config_default(): void
    {
        $this->assertSame(99.0, $this->resolver->value('Demo Booked', 99.0));
    }

    public function test_it_falls_back_to_the_config_default_value(): void
    {
        $this->assertSame(250.0, $this->resolver->value('Demo Booked', null));
    }

    public function test_it_returns_null_when_neither_call_site_nor_config_provides_a_value(): void
    {
        $this->assertNull($this->resolver->value('Quote Form', null));
    }

    public function test_it_prefers_the_call_site_currency_over_the_config_default(): void
    {
        $this->assertSame('GBP', $this->resolver->currency('Demo Booked', 'GBP'));
    }

    public function test_it_falls_back_to_the_per_event_config_currency(): void
    {
        $this->assertSame('EUR', $this->resolver->currency('Demo Booked', null));
    }

    public function test_it_falls_back_to_the_package_default_currency_for_events_without_one(): void
    {
        $this->assertSame('USD', $this->resolver->currency('Quote Form', null));
    }
}
