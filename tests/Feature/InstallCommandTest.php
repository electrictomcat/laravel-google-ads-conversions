<?php

use Illuminate\Support\ServiceProvider;

afterEach(function () {
    foreach (glob(database_path('migrations/*_create_leads_table.php')) ?: [] as $file) {
        @unlink($file);
    }
    foreach (glob(database_path('migrations/*_add_gbraid_and_wbraid_to_leads_table.php')) ?: [] as $file) {
        @unlink($file);
    }
    if (file_exists(config_path('google-ads-conversions.php'))) {
        @unlink(config_path('google-ads-conversions.php'));
    }
});

it('publishes config and reports Google Ads setup status', function () {
    $this->artisan('google-ads:install')
        ->expectsOutputToContain('Installing Google Ads Offline Conversions')
        ->expectsOutputToContain('GOOGLE_ADS_DEVELOPER_TOKEN')
        ->expectsOutputToContain('OmniSignal Pro')
        ->assertSuccessful();
});

it('verifies publishable groups for migrations and config', function () {
    $groups = ServiceProvider::publishableGroups();
    expect($groups)->toContain('laravel-google-ads-conversions-migrations')
        ->and($groups)->toContain('google-ads-conversions-migrations')
        ->and($groups)->toContain('laravel-google-ads-conversions-config')
        ->and($groups)->toContain('google-ads-conversions-config');
});

it('publishes migrations via laravel-google-ads-conversions-migrations tag', function () {
    $this->artisan('vendor:publish', [
        '--tag' => 'laravel-google-ads-conversions-migrations',
    ])->assertSuccessful();

    $published = glob(database_path('migrations/*_add_gbraid_and_wbraid_to_leads_table.php'));
    expect($published)->not->toBeEmpty();
});
