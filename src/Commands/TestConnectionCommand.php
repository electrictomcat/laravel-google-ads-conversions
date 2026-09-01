<?php

namespace ElectricTomCat\GoogleAdsConversions\Commands;

use ElectricTomCat\GoogleAdsConversions\ConversionUploader;
use Illuminate\Console\Command;

class TestConnectionCommand extends Command
{
    protected $signature = 'google-ads:test-connection
                            {--skip-actions : Skip resolving configured conversion action names}';

    protected $description = 'Verify Google Ads API credentials and conversion actions';

    /** @var array<int, string> */
    protected $aliases = ['ad-conversions:test'];

    public function handle(ConversionUploader $uploader): int
    {
        $customerId = config('google-ads-conversions.customer_id');

        if (empty($customerId)) {
            $this->components->error('Google Ads Customer ID is not set. Run php artisan google-ads:install or set GOOGLE_ADS_CUSTOMER_ID in your .env');

            return self::FAILURE;
        }

        $this->components->info('Testing Google Ads API connection for Customer ID: '.$customerId);

        try {
            $client = $uploader->getClient();
            if (! $client) {
                $this->components->error('Failed to instantiate Google Ads API Client. Check developer token and OAuth credentials.');

                return self::FAILURE;
            }

            $this->components->info('Google Ads API client instantiated successfully.');

            if (! $this->option('skip-actions')) {
                $actions = (array) config('google-ads-conversions.events', []);
                $actionNames = array_values(array_filter(array_map(function ($ev) {
                    return is_array($ev) ? ($ev['action'] ?? null) : $ev;
                }, $actions)));

                if (! empty($actionNames)) {
                    $this->line('Validating conversion action names against Google Ads account:');
                    foreach ($actionNames as $name) {
                        try {
                            $resourceName = $uploader->resolveConversionActionResourceName($name);
                            $this->line("  ✓ <info>{$name}</info> -> {$resourceName}");
                        } catch (\Throwable $e) {
                            $this->line("  ✗ <error>{$name}</error>: {$e->getMessage()}");
                        }
                    }
                }
            }

            $this->components->info('Google Ads connection verified successfully.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->components->error('Connection failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
