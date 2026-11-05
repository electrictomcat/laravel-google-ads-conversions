<?php

use ElectricTomCat\GoogleAdsConversions\Support\GoogleAdsSetup;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;

beforeEach(function () {
    $this->tempEnvPath = sys_get_temp_dir().'/google_ads_test_'.uniqid().'.env';
});

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
    if (isset($this->tempEnvPath) && file_exists($this->tempEnvPath)) {
        @unlink($this->tempEnvPath);
    }
});

it('publishes config and reports Google Ads setup status when skipping interactive setup', function () {
    $this->artisan('google-ads:install')
        ->expectsOutputToContain('Installing Google Ads Offline Conversions')
        ->expectsOutputToContain('GOOGLE_ADS_DEVELOPER_TOKEN')
        ->expectsConfirmation('Would you like to configure your Google Ads credentials interactively now?', 'no')
        ->expectsOutputToContain('OmniSignal Pro')
        ->assertSuccessful();
});

it('generates OAuth link, exchanges code for refresh token, discovers customer ID, and injects section into .env', function () {
    file_put_contents($this->tempEnvPath, "APP_NAME=Laravel\nAPP_ENV=local\n");

    Http::fake([
        GoogleAdsSetup::OAUTH_TOKEN_ENDPOINT => Http::response([
            'access_token' => 'ya29.fake-access-token',
            'refresh_token' => '1//0g-fake-refresh-token',
            'expires_in' => 3599,
            'token_type' => 'Bearer',
        ], 200),
        GoogleAdsSetup::ACCESSIBLE_CUSTOMERS_ENDPOINT => Http::response([
            'resourceNames' => [
                'customers/1234567890',
                'customers/9876543210',
            ],
        ], 200),
    ]);

    $this->artisan('google-ads:install', ['--env-path' => $this->tempEnvPath])
        ->expectsConfirmation('Would you like to configure your Google Ads credentials interactively now?', 'yes')
        ->expectsQuestion('Enter your Google OAuth Client ID (GOOGLE_ADS_CLIENT_ID)', 'my-client-id.apps.googleusercontent.com')
        ->expectsQuestion('Enter your Google OAuth Client Secret (GOOGLE_ADS_CLIENT_SECRET)', 'my-client-secret')
        ->expectsOutputToContain('https://accounts.google.com/o/oauth2/v2/auth?client_id=my-client-id.apps.googleusercontent.com')
        ->expectsQuestion(
            'Paste the authorization code (or full redirect URL from your browser), or press Enter to input a Refresh Token directly',
            'http://127.0.0.1:8085/?code=4%2F0AVGzR1-auth-code&scope=https://www.googleapis.com/auth/adwords'
        )
        ->expectsOutputToContain('Successfully exchanged authorization code for Refresh Token!')
        ->expectsQuestion('Enter your Google Ads Developer Token (GOOGLE_ADS_DEVELOPER_TOKEN)', 'dev-token-abc')
        ->expectsOutputToContain('Discovered accessible Google Ads Customer ID(s): 1234567890, 9876543210')
        ->expectsQuestion('Enter your 10-digit Google Ads Customer ID (GOOGLE_ADS_CUSTOMER_ID)', '123-456-7890')
        ->expectsQuestion('Enter your Manager (MCC) Login Customer ID if applicable (GOOGLE_ADS_LOGIN_CUSTOMER_ID, leave blank if none)', '999-888-7777')
        ->expectsOutputToContain('Injected Google Ads credentials section into .env successfully.')
        ->expectsOutputToContain('Google Ads is ready!')
        ->assertSuccessful();

    $envContents = file_get_contents($this->tempEnvPath);

    expect($envContents)
        ->toContain("APP_NAME=Laravel\n")
        ->toContain('# Google Ads Offline Conversions')
        ->toContain('GOOGLE_ADS_DEVELOPER_TOKEN="dev-token-abc"')
        ->toContain('GOOGLE_ADS_CLIENT_ID="my-client-id.apps.googleusercontent.com"')
        ->toContain('GOOGLE_ADS_CLIENT_SECRET="my-client-secret"')
        ->toContain('GOOGLE_ADS_REFRESH_TOKEN="1//0g-fake-refresh-token"')
        ->toContain('GOOGLE_ADS_CUSTOMER_ID="1234567890"')
        ->toContain('GOOGLE_ADS_LOGIN_CUSTOMER_ID="9998887777"');
});

it('updates existing .env keys in-place without duplicating the section when re-run with --setup', function () {
    $initialEnv = <<<'ENV'
APP_NAME=Laravel

# Google Ads Offline Conversions
GOOGLE_ADS_DEVELOPER_TOKEN="old-dev-token"
GOOGLE_ADS_CLIENT_ID="old-client-id"
GOOGLE_ADS_CLIENT_SECRET="old-secret"
GOOGLE_ADS_REFRESH_TOKEN="old-refresh"
GOOGLE_ADS_CUSTOMER_ID="1111111111"
ENV;

    file_put_contents($this->tempEnvPath, $initialEnv);

    $this->artisan('google-ads:install', [
        '--setup' => true,
        '--env-path' => $this->tempEnvPath,
    ])
        ->expectsQuestion('Enter your Google OAuth Client ID (GOOGLE_ADS_CLIENT_ID)', 'new-client-id')
        ->expectsQuestion('Enter your Google OAuth Client Secret (GOOGLE_ADS_CLIENT_SECRET)', 'new-secret')
        ->expectsQuestion(
            'Paste the authorization code (or full redirect URL from your browser), or press Enter to input a Refresh Token directly',
            ''
        )
        ->expectsQuestion('Enter your Google OAuth Refresh Token (GOOGLE_ADS_REFRESH_TOKEN)', 'new-refresh-token')
        ->expectsQuestion('Enter your Google Ads Developer Token (GOOGLE_ADS_DEVELOPER_TOKEN)', 'new-dev-token')
        ->expectsQuestion('Enter your 10-digit Google Ads Customer ID (GOOGLE_ADS_CUSTOMER_ID)', '222-333-4444')
        ->expectsQuestion('Enter your Manager (MCC) Login Customer ID if applicable (GOOGLE_ADS_LOGIN_CUSTOMER_ID, leave blank if none)', '')
        ->assertSuccessful();

    $envContents = (string) file_get_contents($this->tempEnvPath);

    expect(substr_count($envContents, '# Google Ads Offline Conversions'))->toBe(1)
        ->and($envContents)->toContain('GOOGLE_ADS_DEVELOPER_TOKEN="new-dev-token"')
        ->and($envContents)->toContain('GOOGLE_ADS_CLIENT_ID="new-client-id"')
        ->and($envContents)->toContain('GOOGLE_ADS_CLIENT_SECRET="new-secret"')
        ->and($envContents)->toContain('GOOGLE_ADS_REFRESH_TOKEN="new-refresh-token"')
        ->and($envContents)->toContain('GOOGLE_ADS_CUSTOMER_ID="2223334444"')
        ->and($envContents)->toContain('GOOGLE_ADS_LOGIN_CUSTOMER_ID=""')
        ->and($envContents)->not->toContain('old-dev-token');
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
