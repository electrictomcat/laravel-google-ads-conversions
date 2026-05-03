<?php

namespace ElectricTomCat\GoogleAdsConversions\Jobs;

use ElectricTomCat\GoogleAdsConversions\ConversionUploader;
use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * The two-step pipeline runner. Schedule this hourly (or to taste).
 *
 *   Schedule::job(UploadPendingConversions::class)->hourly();
 *
 * Step 1 flushes the cache buffer to the database, ensuring everything
 * recorded in the last interval has a row. Step 2 ships every eligible
 * (delay-aged) pending conversion up to Google Ads.
 */
class UploadPendingConversions implements ShouldQueue
{
    use Queueable;

    public function handle(GoogleAdsConversions $tracker, ConversionUploader $uploader): void
    {
        $tracker->syncToDatabase();
        $uploader->uploadPendingConversions();
    }
}
