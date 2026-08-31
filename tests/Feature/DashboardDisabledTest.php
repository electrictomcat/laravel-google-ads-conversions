<?php

it('does not expose the analytics dashboard by default', function () {
    // The dashboard shows lead counts, click identifiers and attributed
    // revenue. Installing the package must not publish that anonymously.
    $this->get('/ad-conversions')->assertNotFound();
});

it('requires authentication on the dashboard route when it is enabled', function () {
    expect(config('google-ads-conversions.dashboard.enabled'))->toBeFalse()
        ->and(config('google-ads-conversions.dashboard.middleware'))->toContain('auth');
});
