<?php

namespace ElectricTomCat\GoogleAdsConversions\Commands;

use ElectricTomCat\GoogleAdsConversions\ConversionUploader;
use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use Illuminate\Console\Command;

class UploadConversionsCommand extends Command
{
    protected $signature = 'ad-conversions:upload
                            {--dry-run : Validate conversion uploads with Google Ads without committing them}
                            {--force : Force upload pending conversions immediately, ignoring the delay window}
                            {--delay= : Override the upload delay window in hours}';

    protected $description = 'Flush cached conversions to the database and upload pending conversions to Google Ads';

    /**
     * Kept so existing schedulers and runbooks keep working.
     *
     * @var array<int, string>
     */
    protected $aliases = ['google-ads:upload'];

    public function handle(GoogleAdsConversions $tracker, ConversionUploader $uploader): int
    {
        $this->info('Flushing cache buffer to database...');
        $tracker->syncToDatabase();

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $delayOption = $this->option('delay');

        $delayHours = $force ? 0 : ($delayOption !== null ? (int) $delayOption : null);

        if ($dryRun) {
            $this->warn('Running in DRY-RUN mode (validate_only = true). No actual conversions will be recorded.');
        }

        $this->info('Uploading eligible pending conversions to Google Ads API...');
        $count = $uploader->uploadPendingConversions($delayHours, $dryRun);

        $this->info("Completed! Processed {$count} conversion(s).");

        return self::SUCCESS;
    }
}
