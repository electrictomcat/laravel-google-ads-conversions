<?php

namespace ElectricTomCat\GoogleAdsConversions\Commands;

use ElectricTomCat\GoogleAdsConversions\Support\GoogleAdsSetup;
use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'google-ads:install
                            {--setup : Force interactive Google Ads credential and OAuth setup}
                            {--listen : Start a temporary local HTTP listener on the redirect URI to capture the OAuth code}
                            {--redirect-uri='.GoogleAdsSetup::DEFAULT_REDIRECT_URI.' : OAuth 2.0 redirect URI used to generate the authentication link}
                            {--env-path= : Optional custom path to the .env file}';

    protected $description = 'Publish Google Ads Conversions config and migration, authenticate with Google OAuth, and configure .env credentials';

    /** @var array<int, string> */
    protected $aliases = ['ad-conversions:install'];

    public function handle(GoogleAdsSetup $setup): int
    {
        $this->components->info('Installing Google Ads Offline Conversions package');

        $this->components->task('Publishing config', fn () => $this->callSilent('vendor:publish', [
            '--tag' => 'laravel-google-ads-conversions-config',
        ]) === self::SUCCESS);

        $this->components->task('Publishing migration', fn () => $this->callSilent('vendor:publish', [
            '--tag' => 'laravel-google-ads-conversions-migrations',
        ]) === self::SUCCESS);

        $this->newLine();
        $this->components->info('Checking Google Ads credentials in .env:');

        $keys = [
            'developer_token' => 'GOOGLE_ADS_DEVELOPER_TOKEN',
            'client_id' => 'GOOGLE_ADS_CLIENT_ID',
            'client_secret' => 'GOOGLE_ADS_CLIENT_SECRET',
            'refresh_token' => 'GOOGLE_ADS_REFRESH_TOKEN',
            'customer_id' => 'GOOGLE_ADS_CUSTOMER_ID',
        ];

        $missing = $this->checkMissingCredentials($keys, true);

        $forceSetup = (bool) $this->option('setup');
        $shouldRunSetup = $forceSetup;

        if (! $shouldRunSetup && ! empty($missing) && $this->input->isInteractive() && ! $this->option('no-interaction')) {
            $this->newLine();
            $shouldRunSetup = $this->confirm('Would you like to configure your Google Ads credentials interactively now?', true);
        }

        if ($shouldRunSetup) {
            $this->runInteractiveSetup($setup);
            $missing = $this->checkMissingCredentials($keys, false);
        }

        $this->newLine();
        if (empty($missing)) {
            $this->components->info('✨ Google Ads is ready! Run `php artisan google-ads:test-connection` to test API connectivity.');
        } else {
            $this->components->warn('Add the missing credentials to your .env file to begin uploading conversions.');
        }

        $this->newLine();
        $this->line('<fg=gray>─────────────────────────────────────────────────────────────────</>');
        $this->line('<fg=cyan>⚡ Need Meta CAPI, TikTok, LinkedIn, Microsoft Ads, and Live Analytics?</>');
        $this->line('<fg=gray>Upgrade to OmniSignal Pro at</> <fg=green;options=bold>https://omnisignal.dev</>');
        $this->line('<fg=gray>─────────────────────────────────────────────────────────────────</>');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $keys
     * @return array<int, string>
     */
    protected function checkMissingCredentials(array $keys, bool $outputLines): array
    {
        $missing = [];

        foreach ($keys as $configKey => $envVar) {
            $val = config('google-ads-conversions.'.$configKey);
            if (empty($val)) {
                $missing[] = $envVar;
                if ($outputLines) {
                    $this->line("  ✗ <error>{$envVar}</error> is missing");
                }
            } elseif ($outputLines) {
                $this->line("  ✓ <info>{$envVar}</info> configured");
            }
        }

        return $missing;
    }

    protected function runInteractiveSetup(GoogleAdsSetup $setup): void
    {
        $this->newLine();
        $this->line('<fg=yellow;options=bold>─── Step 1/4: Google Cloud OAuth 2.0 Credentials ───</>');
        $this->line('  Create or view your OAuth 2.0 Client ID (Desktop or Web application) at:');
        $this->line('  <fg=cyan>https://console.cloud.google.com/apis/credentials</>');
        $this->line('  Ensure both the Google Ads API and Google Data Manager API are enabled in your project:');
        $this->line('  <fg=cyan>https://console.cloud.google.com/apis/library/googleads.googleapis.com</>');
        $this->line('  <fg=cyan>https://console.cloud.google.com/apis/library/datamanager.googleapis.com</>');

        $currentClientId = (string) (config('google-ads-conversions.client_id') ?? '');
        $currentClientSecret = (string) (config('google-ads-conversions.client_secret') ?? '');
        $currentRefreshToken = (string) (config('google-ads-conversions.refresh_token') ?? '');
        $currentDeveloperToken = (string) (config('google-ads-conversions.developer_token') ?? '');
        $currentCustomerId = (string) (config('google-ads-conversions.customer_id') ?? '');
        $currentLoginCustomerId = (string) (config('google-ads-conversions.login_customer_id') ?? '');

        $clientId = trim((string) $this->ask(
            'Enter your Google OAuth Client ID (GOOGLE_ADS_CLIENT_ID)',
            $currentClientId !== '' ? $currentClientId : null
        ));

        $clientSecret = trim((string) $this->ask(
            'Enter your Google OAuth Client Secret (GOOGLE_ADS_CLIENT_SECRET)',
            $currentClientSecret !== '' ? $currentClientSecret : null
        ));

        $this->newLine();
        $this->line('<fg=yellow;options=bold>─── Step 2/4: Google OAuth Authentication (Refresh Token) ───</>');

        $refreshToken = $currentRefreshToken;
        $accessToken = null;
        $redirectUri = (string) ($this->option('redirect-uri') ?: GoogleAdsSetup::DEFAULT_REDIRECT_URI);

        if ($clientId !== '' && $clientSecret !== '') {
            $authUrl = $setup->buildAuthorizationUrl($clientId, $redirectUri);

            $this->line('  Open this link in your browser to authorize access to Google Ads:');
            $this->newLine();
            $this->line("  <fg=green;options=bold>{$authUrl}</>");
            $this->newLine();
            $this->line("  <fg=gray>(If using a Web Application OAuth client, ensure {$redirectUri} is in Authorized redirect URIs)</>");

            $codeInput = '';

            if ($this->option('listen')) {
                $this->components->info("Listening for OAuth callback on {$redirectUri}...");
                $capturedCode = $setup->listenForAuthorizationCode($redirectUri);
                if ($capturedCode !== null) {
                    $codeInput = $capturedCode;
                    $this->line('  ✓ <info>Captured authorization code from browser callback</info>');
                } else {
                    $this->components->warn('Could not capture callback automatically. You can paste the code or redirect URL below.');
                }
            }

            if ($codeInput === '') {
                $codeInput = trim((string) $this->ask(
                    'Paste the authorization code (or full redirect URL from your browser), or press Enter to input a Refresh Token directly'
                ));
            }

            if ($codeInput !== '') {
                $tokenResult = $setup->exchangeCodeForRefreshToken($clientId, $clientSecret, $codeInput, $redirectUri);

                if ($tokenResult['success'] && ! empty($tokenResult['refresh_token'])) {
                    $refreshToken = $tokenResult['refresh_token'];
                    $accessToken = $tokenResult['access_token'];
                    $this->line('  ✓ <info>Successfully exchanged authorization code for Refresh Token!</info>');
                } else {
                    $this->components->error('Could not exchange authorization code: '.($tokenResult['error'] ?? 'Unknown error'));
                    $refreshToken = trim((string) $this->ask(
                        'Enter your Google OAuth Refresh Token manually (GOOGLE_ADS_REFRESH_TOKEN)',
                        $currentRefreshToken !== '' ? $currentRefreshToken : null
                    ));
                }
            } else {
                $refreshToken = trim((string) $this->ask(
                    'Enter your Google OAuth Refresh Token (GOOGLE_ADS_REFRESH_TOKEN)',
                    $currentRefreshToken !== '' ? $currentRefreshToken : null
                ));
            }
        } else {
            $refreshToken = trim((string) $this->ask(
                'Enter your Google OAuth Refresh Token (GOOGLE_ADS_REFRESH_TOKEN)',
                $currentRefreshToken !== '' ? $currentRefreshToken : null
            ));
        }

        $this->newLine();
        $this->line('<fg=yellow;options=bold>─── Step 3/4: Google Ads Developer Token ───</>');
        $this->line('  Retrieve your Developer Token from your Google Ads Manager (MCC) account under Admin -> API Center:');
        $this->line('  <fg=cyan>https://ads.google.com/aw/apicenter</>');

        $developerToken = trim((string) $this->ask(
            'Enter your Google Ads Developer Token (GOOGLE_ADS_DEVELOPER_TOKEN)',
            $currentDeveloperToken !== '' ? $currentDeveloperToken : null
        ));

        $this->newLine();
        $this->line('<fg=yellow;options=bold>─── Step 4/4: Google Ads Account IDs ───</>');

        $defaultCustomerId = $currentCustomerId;

        if (is_string($accessToken) && $accessToken !== '' && $developerToken !== '') {
            $accessibleCustomers = $setup->listAccessibleCustomers($accessToken, $developerToken);
            if (! empty($accessibleCustomers)) {
                $this->line('  ✓ <info>Discovered accessible Google Ads Customer ID(s):</info> '.implode(', ', $accessibleCustomers));
                if ($defaultCustomerId === '') {
                    $defaultCustomerId = $accessibleCustomers[0];
                }
            }
        }

        $customerIdInput = trim((string) $this->ask(
            'Enter your 10-digit Google Ads Customer ID (GOOGLE_ADS_CUSTOMER_ID)',
            $defaultCustomerId !== '' ? $defaultCustomerId : null
        ));
        $customerId = str_replace('-', '', $customerIdInput);

        $loginCustomerIdInput = trim((string) $this->ask(
            'Enter your Manager (MCC) Login Customer ID if applicable (GOOGLE_ADS_LOGIN_CUSTOMER_ID, leave blank if none)',
            $currentLoginCustomerId !== '' ? $currentLoginCustomerId : ''
        ));
        $loginCustomerId = str_replace('-', '', $loginCustomerIdInput);

        $envPathOption = $this->option('env-path');
        $envPath = is_string($envPathOption) && $envPathOption !== '' ? $envPathOption : null;

        $written = $setup->writeEnvSection([
            'GOOGLE_ADS_DEVELOPER_TOKEN' => $developerToken,
            'GOOGLE_ADS_CLIENT_ID' => $clientId,
            'GOOGLE_ADS_CLIENT_SECRET' => $clientSecret,
            'GOOGLE_ADS_REFRESH_TOKEN' => $refreshToken,
            'GOOGLE_ADS_CUSTOMER_ID' => $customerId,
            'GOOGLE_ADS_LOGIN_CUSTOMER_ID' => $loginCustomerId,
        ], $envPath);

        config()->set('google-ads-conversions.developer_token', $developerToken !== '' ? $developerToken : null);
        config()->set('google-ads-conversions.client_id', $clientId !== '' ? $clientId : null);
        config()->set('google-ads-conversions.client_secret', $clientSecret !== '' ? $clientSecret : null);
        config()->set('google-ads-conversions.refresh_token', $refreshToken !== '' ? $refreshToken : null);
        config()->set('google-ads-conversions.customer_id', $customerId);
        config()->set('google-ads-conversions.login_customer_id', $loginCustomerId !== '' ? $loginCustomerId : null);

        $this->newLine();
        if ($written) {
            $this->components->info('Injected Google Ads credentials section into .env successfully.');
        } else {
            $this->components->error('Failed to write Google Ads credentials to .env file.');
        }
    }
}
