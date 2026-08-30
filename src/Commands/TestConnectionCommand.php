<?php

namespace ElectricTomCat\GoogleAdsConversions\Commands;

use ElectricTomCat\GoogleAdsConversions\ConversionUploader;
use Illuminate\Console\Command;

class TestConnectionCommand extends Command
{
    protected $signature = 'google-ads:test-connection';

    protected $description = 'Verify Google Ads API credentials and test conversion action resolution';

    public function handle(ConversionUploader $uploader): int
    {
        $this->info('Testing Google Ads API configuration...');

        $customerId = config('google-ads-conversions.customer_id');
        $developerToken = config('google-ads-conversions.developer_token');
        $clientId = config('google-ads-conversions.client_id');
        $loginCustomerId = config('google-ads-conversions.login_customer_id');

        $this->table(['Setting', 'Value'], [
            ['Customer ID', $customerId ?: '<not set>'],
            ['Login Customer ID (MCC)', $loginCustomerId ?: '(same as customer_id)'],
            ['Developer Token', $developerToken ? 'Set ('.substr($developerToken, 0, 4).'***)' : '<not set>'],
            ['Client ID', $clientId ? 'Set ('.substr($clientId, 0, 8).'***)' : '<not set>'],
        ]);

        if (empty($customerId) || empty($developerToken) || empty($clientId)) {
            $this->error('Missing required Google Ads credentials in .env or config.');

            return self::FAILURE;
        }

        $events = (array) config('google-ads-conversions.events', []);

        if (empty($events)) {
            $this->warn('No events configured in config/google-ads-conversions.php.');
        } else {
            $this->info('Testing resolution of configured conversion actions:');
            $rows = [];

            foreach ($events as $event => $config) {
                $actionName = is_array($config) ? ($config['action'] ?? $event) : $config;
                $resolved = $uploader->resolveActionResourceName($actionName);

                $rows[] = [
                    $event,
                    $actionName,
                    $resolved ? "<info>{$resolved}</info>" : '<error>Failed to resolve</error>',
                ];
            }

            $this->table(['Event Name', 'Configured Action', 'Google Ads Resource Name'], $rows);
        }

        $this->info('Test complete.');

        return self::SUCCESS;
    }
}
