<?php

namespace ElectricTomCat\GoogleAdsConversions\Commands;

use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use Illuminate\Console\Command;

class SyncConversionsCommand extends Command
{
    protected $signature = 'google-ads:sync';

    protected $description = 'Flush cached conversions and visitor tracking data into the database';

    public function handle(GoogleAdsConversions $tracker): int
    {
        $this->info('Flushing cache buffer to database...');
        $tracker->syncToDatabase();
        $this->info('Sync complete.');

        return self::SUCCESS;
    }
}
