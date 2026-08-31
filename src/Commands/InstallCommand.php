<?php

namespace ElectricTomCat\GoogleAdsConversions\Commands;

use ElectricTomCat\GoogleAdsConversions\Contracts\ConversionDriverInterface;
use ElectricTomCat\GoogleAdsConversions\ConversionManager;
use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'ad-conversions:install
                            {--channel=* : Only check these channels}';

    protected $description = 'Publish the package config and migration, then report which channels are ready';

    /**
     * Credentials each channel needs: config key => the .env variable that
     * feeds it. Read through config rather than env() so the report is still
     * correct when the host app has cached its configuration.
     *
     * @var array<string, array<string, string>>
     */
    protected const CHANNEL_CREDENTIALS = [
        'google' => [
            'developer_token' => 'GOOGLE_ADS_DEVELOPER_TOKEN',
            'client_id' => 'GOOGLE_ADS_CLIENT_ID',
            'client_secret' => 'GOOGLE_ADS_CLIENT_SECRET',
            'refresh_token' => 'GOOGLE_ADS_REFRESH_TOKEN',
            'customer_id' => 'GOOGLE_ADS_CUSTOMER_ID',
        ],
        'meta' => [
            'meta.pixel_id' => 'META_PIXEL_ID',
            'meta.access_token' => 'META_ACCESS_TOKEN',
        ],
        'microsoft' => [
            'microsoft.developer_token' => 'MICROSOFT_ADS_DEVELOPER_TOKEN',
            'microsoft.customer_id' => 'MICROSOFT_ADS_CUSTOMER_ID',
            'microsoft.account_id' => 'MICROSOFT_ADS_ACCOUNT_ID',
            'microsoft.access_token' => 'MICROSOFT_ADS_ACCESS_TOKEN',
        ],
        'linkedin' => [
            'linkedin.access_token' => 'LINKEDIN_ACCESS_TOKEN',
            'linkedin.conversion_rule_id' => 'LINKEDIN_CONVERSION_RULE_ID',
        ],
        'tiktok' => [
            'tiktok.pixel_code' => 'TIKTOK_PIXEL_CODE',
            'tiktok.access_token' => 'TIKTOK_ACCESS_TOKEN',
        ],
    ];

    public function handle(ConversionManager $manager): int
    {
        $this->components->info('Installing OmniSignal conversion tracking');

        $this->components->task('Publishing config', fn () => $this->callSilent('vendor:publish', [
            '--tag' => 'laravel-google-ads-conversions-config',
        ]) === self::SUCCESS);

        $this->components->task('Publishing migration', fn () => $this->callSilent('vendor:publish', [
            '--tag' => 'laravel-google-ads-conversions-migrations',
        ]) === self::SUCCESS);

        $channels = (array) $this->option('channel');
        $channels = $channels === [] ? array_keys(self::CHANNEL_CREDENTIALS) : $channels;

        $this->newLine();
        $this->components->info('Channel status');

        $rows = [];
        $missingByChannel = [];
        $ready = 0;

        foreach ($channels as $channel) {
            $channel = strtolower(trim((string) $channel));

            if (! isset(self::CHANNEL_CREDENTIALS[$channel])) {
                $rows[] = [$channel, '<error>unknown</error>', ''];

                continue;
            }

            try {
                /** @var ConversionDriverInterface $driver */
                $driver = $manager->driver($channel);
            } catch (\Throwable $e) {
                $rows[] = [$channel, '<error>unavailable</error>', $e->getMessage()];

                continue;
            }

            if (! $driver->isConfigured()) {
                $missing = [];

                foreach (self::CHANNEL_CREDENTIALS[$channel] as $configKey => $envKey) {
                    if (blank(config("google-ads-conversions.{$configKey}"))) {
                        $missing[] = $envKey;
                    }
                }

                $rows[] = [$channel, '<comment>not configured</comment>', count($missing).' credential(s) missing'];
                $missingByChannel[$channel] = $missing;

                continue;
            }

            $rows[] = [$channel, '<info>configured</info>', 'Run ad-conversions:test to verify the credentials'];
            $ready++;
        }

        $this->table(['Channel', 'Status', 'Next step'], $rows);

        if ($missingByChannel !== []) {
            $this->newLine();
            $this->components->info('Add these to your .env');

            foreach ($missingByChannel as $channel => $missing) {
                $this->line("  <comment>{$channel}</comment>");

                foreach ($missing as $key) {
                    $this->line("    {$key}=");
                }
            }
        }

        $this->newLine();
        $this->components->info('Next steps');
        $this->components->bulletList([
            'Run "php artisan migrate" to create the leads table.',
            'Register the CaptureGclid middleware on your web group (see the README).',
            'Schedule the UploadPendingConversions job, and "model:prune" for retention.',
            $ready > 0
                ? 'Run "php artisan ad-conversions:test" to check the credentials against the live APIs.'
                : 'Add credentials to your .env, then run this command again.',
        ]);

        return self::SUCCESS;
    }
}
