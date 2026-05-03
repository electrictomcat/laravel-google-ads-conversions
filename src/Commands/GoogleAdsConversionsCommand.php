<?php

namespace ElectricTomCat\GoogleAdsConversions\Commands;

use Illuminate\Console\Command;

class GoogleAdsConversionsCommand extends Command
{
    public $signature = 'laravel-google-ads-conversions';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
