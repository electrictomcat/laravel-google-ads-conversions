<?php

namespace ElectricTomCat\GoogleAdsConversions\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'google-ads:install';

    protected $description = 'Publish Google Ads Conversions config and migration, then verify setup';

    /** @var array<int, string> */
    protected $aliases = ['ad-conversions:install'];

    public function handle(): int
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

        $missing = [];
        foreach ($keys as $configKey => $envVar) {
            $val = config('google-ads-conversions.' . $configKey);
            if (empty($val)) {
                $missing[] = $envVar;
                $this->line("  ✗ <error>{$envVar}</error> is missing");
            } else {
                $this->line("  ✓ <info>{$envVar}</info> configured");
            }
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
}
