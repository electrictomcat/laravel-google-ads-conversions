<?php

namespace ElectricTomCat\GoogleAdsConversions\Commands;

use ElectricTomCat\GoogleAdsConversions\Contracts\ConversionDriverInterface;
use ElectricTomCat\GoogleAdsConversions\ConversionManager;
use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'ad-conversions:install
                            {--channel=* : Specific channels to configure}';

    protected $description = 'Interactive setup wizard for OmniTrack ad conversion tracking';

    public function handle(ConversionManager $manager): int
    {
        $this->info('====================================================');
        $this->info('  🚀 Welcome to OmniTrack Conversion Setup Wizard   ');
        $this->info('====================================================');
        $this->newLine();

        $channels = $this->choice(
            'Which advertising channels do you want to configure?',
            ['all', 'google', 'meta', 'microsoft', 'linkedin', 'tiktok'],
            0,
            null,
            true
        );

        if (in_array('all', $channels, true)) {
            $channels = ['google', 'meta', 'microsoft', 'linkedin', 'tiktok'];
        }

        $this->info('Configuring selected channels: '.implode(', ', $channels));
        $this->newLine();

        foreach ($channels as $channel) {
            $this->configureChannel($channel, $manager);
            $this->newLine();
        }

        $this->info('Publishing database migrations and configuration...');
        $this->callSilent('vendor:publish', ['--tag' => 'laravel-google-ads-conversions-config']);
        $this->callSilent('vendor:publish', ['--tag' => 'laravel-google-ads-conversions-migrations']);

        $this->info('✅ Installation and setup completed!');
        $this->info('Run "php artisan ad-conversions:test" or "php artisan google-ads:test-connection" anytime to verify your connections.');

        return self::SUCCESS;
    }

    protected function configureChannel(string $channel, ConversionManager $manager): void
    {
        $this->comment("--- Configuring {$channel} ---");

        switch ($channel) {
            case 'google':
                $this->line('Google Ads uses developer token, client ID, client secret, refresh token, and customer ID.');
                break;
            case 'meta':
                $this->line('Meta Conversions API requires your Meta Pixel ID and Conversions API Access Token.');
                break;
            case 'microsoft':
                $this->line('Microsoft Advertising requires developer token, customer ID, and OAuth access token.');
                break;
            case 'linkedin':
                $this->line('LinkedIn CAPI requires access token and partner conversion rule ID.');
                break;
            case 'tiktok':
                $this->line('TikTok Events API requires access token and pixel code.');
                break;
        }

        /** @var ConversionDriverInterface $driver */
        $driver = $manager->driver($channel);
        $status = $driver->testConnection();

        if ($status['success']) {
            $this->info("✓ {$channel} connection status: {$status['message']}");
        } else {
            $this->warn("! {$channel} status: {$status['message']}");
        }
    }
}
