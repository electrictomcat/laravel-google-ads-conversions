<?php

namespace ElectricTomCat\GoogleAdsConversions\Jobs;

use ElectricTomCat\GoogleAdsConversions\ConversionUploader;
use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use Illuminate\Contracts\Queue\ShouldBeUnique;
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
 *
 * The job is unique while it runs: two overlapping runs would read the same
 * pending rows before either wrote its results back, and upload them twice.
 * Google only de-duplicates when an order ID is set, so the second copy would
 * otherwise be counted as a second conversion.
 */
class UploadPendingConversions implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Attempts before the job is marked failed.
     */
    public int $tries = 3;

    /**
     * Seconds the job may run before it is considered timed out.
     *
     * A full sweep uploads in batches of up to 2,000 against a remote API, so
     * this is generous by design.
     */
    public int $timeout = 900;

    /**
     * Seconds to wait between retries.
     *
     * @var array<int, int>
     */
    public array $backoff = [60, 300];

    /**
     * How long the uniqueness lock is held if the job dies without releasing it.
     *
     * Comfortably longer than $timeout so a hung run cannot be overlapped, but
     * short enough that a lost lock self-heals within an hour.
     */
    public int $uniqueFor = 1800;

    public function handle(GoogleAdsConversions $tracker, ConversionUploader $uploader): void
    {
        $tracker->syncToDatabase();
        $uploader->uploadPendingConversions();
    }
}
