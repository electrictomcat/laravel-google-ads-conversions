<?php

use ElectricTomCat\GoogleAdsConversions\Support\EventResolver;

beforeEach(function () {
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

    $this->resolver = new EventResolver;
});

it('resolves a string event to its action name', function () {
    expect($this->resolver->action('Quote Form'))->toBe('Quote Submission');
});

it('resolves an array event to its action name', function () {
    expect($this->resolver->action('Demo Booked'))->toBe('Demo Booked Action');
});

it('returns null for an unmapped event', function () {
    expect($this->resolver->action('Unknown Event'))->toBeNull();
});

it('matches by prefix for "Prefix: ..." events', function () {
    expect($this->resolver->action('Page Navigation: /pricing'))->toBe('Page Navigation Action');
});

it('prefers the call-site value over the config default', function () {
    expect($this->resolver->value('Demo Booked', 99.0))->toBe(99.0);
});

it('falls back to the config default value', function () {
    expect($this->resolver->value('Demo Booked', null))->toBe(250.0);
});

it('returns null when neither call-site nor config provides a value', function () {
    expect($this->resolver->value('Quote Form', null))->toBeNull();
});

it('prefers the call-site currency over the config default', function () {
    expect($this->resolver->currency('Demo Booked', 'GBP'))->toBe('GBP');
});

it('falls back to the per-event config currency', function () {
    expect($this->resolver->currency('Demo Booked', null))->toBe('EUR');
});

it('falls back to the package default currency for events without one', function () {
    expect($this->resolver->currency('Quote Form', null))->toBe('USD');
});
