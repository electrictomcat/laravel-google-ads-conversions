<?php

it('publishes config and reports which channels are ready', function () {
    $this->artisan('ad-conversions:install')
        ->expectsOutputToContain('Installing OmniSignal conversion tracking')
        ->expectsOutputToContain('Channel status')
        // No credentials in the test environment, so every channel should say
        // so — and name the variables to set — rather than claiming it
        // "configured" anything.
        ->expectsOutputToContain('not configured')
        ->expectsOutputToContain('GOOGLE_ADS_DEVELOPER_TOKEN')
        ->assertSuccessful();
});

it('only reports the channels it was asked about', function () {
    $this->artisan('ad-conversions:install --channel=tiktok')
        ->expectsOutputToContain('TIKTOK_ACCESS_TOKEN')
        ->doesntExpectOutputToContain('LINKEDIN_ACCESS_TOKEN')
        ->assertSuccessful();
});
