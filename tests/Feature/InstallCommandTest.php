<?php

it('publishes config and reports Google Ads setup status', function () {
    $this->artisan('google-ads:install')
        ->expectsOutputToContain('Installing Google Ads Offline Conversions')
        ->expectsOutputToContain('GOOGLE_ADS_DEVELOPER_TOKEN')
        ->expectsOutputToContain('OmniSignal Pro')
        ->assertSuccessful();
});
