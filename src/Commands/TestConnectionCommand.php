<?php

namespace ElectricTomCat\GoogleAdsConversions\Commands;

use ElectricTomCat\GoogleAdsConversions\Contracts\ConversionDriverInterface;
use ElectricTomCat\GoogleAdsConversions\ConversionManager;
use ElectricTomCat\GoogleAdsConversions\ConversionUploader;
use Illuminate\Console\Command;

class TestConnectionCommand extends Command
{
    protected $signature = 'ad-conversions:test
                            {--channel=* : Only test these channels}
                            {--skip-actions : Skip resolving configured conversion action names}';

    protected $description = 'Verify every configured channel\'s credentials against its live API';

    /** @var array<int, string> */
    protected $aliases = ['google-ads:test-connection'];

    public function handle(ConversionManager $manager, ConversionUploader $uploader): int
    {
        $channels = (array) $this->option('channel');
        $channels = $channels === []
            ? (array) config('google-ads-conversions.enabled_channels', ['google', 'meta', 'microsoft', 'linkedin', 'tiktok'])
            : $channels;

        $this->components->info('Testing channel credentials against the live APIs');

        $rows = [];
        $problems = [];
        $failed = 0;
        $tested = 0;

        foreach ($channels as $channel) {
            $channel = strtolower(trim((string) $channel));

            try {
                /** @var ConversionDriverInterface $driver */
                $driver = $manager->driver($channel);
            } catch (\Throwable $e) {
                $rows[] = [$channel, '<error>ERROR</error>', 'see below'];
                $problems[$channel] = $e->getMessage();
                $failed++;

                continue;
            }

            if (! $driver->isConfigured()) {
                $rows[] = [$channel, '<comment>SKIPPED</comment>', 'Not configured'];

                continue;
            }

            $tested++;
            $result = $driver->testConnection();

            if ($result['success']) {
                $rows[] = [$channel, '<info>OK</info>', $result['message']];
            } else {
                $rows[] = [$channel, '<error>FAILED</error>', 'see below'];
                $problems[$channel] = $result['message'];
                $failed++;
            }
        }

        $this->table(['Channel', 'Status', 'Detail'], $rows);

        // Printed in full underneath rather than squeezed into a table cell,
        // where the message that says what to fix gets truncated away.
        foreach ($problems as $channel => $message) {
            $this->newLine();
            $this->components->error("{$channel}: {$message}");
        }

        if ($tested === 0) {
            $this->components->warn('No channel is configured. Add credentials to your .env and try again.');

            return self::FAILURE;
        }

        if (! $this->option('skip-actions')) {
            $failed += $this->testConversionActions($uploader);
        }

        if ($failed > 0) {
            $this->components->error("{$failed} check(s) failed.");

            return self::FAILURE;
        }

        $this->components->info('All checks passed.');

        return self::SUCCESS;
    }

    /**
     * Resolve every configured event to a Google Ads conversion action.
     *
     * @return int number of failures
     */
    protected function testConversionActions(ConversionUploader $uploader): int
    {
        $events = (array) config('google-ads-conversions.events', []);

        if ($events === []) {
            $this->newLine();
            $this->components->warn('No events are mapped in config/google-ads-conversions.php.');

            return 0;
        }

        $this->newLine();
        $this->components->info('Resolving configured conversion actions');

        $rows = [];
        $failed = 0;

        foreach ($events as $event => $config) {
            $actionName = is_array($config) ? ($config['action'] ?? $event) : $config;
            $resolved = $uploader->resolveActionResourceName((string) $actionName);

            if ($resolved) {
                $rows[] = [$event, $actionName, "<info>{$resolved}</info>"];
            } else {
                $rows[] = [$event, $actionName, '<error>Could not resolve</error>'];
                $failed++;
            }
        }

        $this->table(['Event', 'Configured action', 'Google Ads resource name'], $rows);

        return $failed;
    }
}
