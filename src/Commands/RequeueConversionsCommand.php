<?php

namespace ElectricTomCat\GoogleAdsConversions\Commands;

use ElectricTomCat\GoogleAdsConversions\Contracts\HasConversions;
use ElectricTomCat\GoogleAdsConversions\ConversionUploader;
use ElectricTomCat\GoogleAdsConversions\Models\Lead;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class RequeueConversionsCommand extends Command
{
    protected $signature = 'ad-conversions:requeue
                            {--status=* : Statuses to reset back to pending (defaults to failed,rejected; pass --status=uploaded to recover falsely uploaded conversions)}
                            {--since= : Only reset conversions recorded or updated on/after this date/time}
                            {--until= : Only reset conversions recorded or updated on/before this date/time}
                            {--event=* : Only reset conversions matching specific event name(s)}
                            {--lead=* : Only reset conversions on specific lead ID(s)}
                            {--upload : Immediately upload pending conversions after resetting}
                            {--dry-run : Preview how many conversions would be reset without saving changes}';

    protected $description = 'Reset failed, rejected, or falsely uploaded conversions back to pending so they can be re-uploaded';

    /** @var array<int, string> */
    protected $aliases = ['google-ads:requeue'];

    public function handle(ConversionUploader $uploader): int
    {
        $statusOptions = array_values(array_filter(array_map(
            fn ($v) => is_scalar($v) ? trim((string) $v) : '',
            (array) $this->option('status'),
        )));
        $targetStatuses = $statusOptions !== [] ? $statusOptions : ['failed', 'rejected'];

        $eventFilters = array_values(array_filter(array_map(
            fn ($v) => is_scalar($v) ? trim((string) $v) : '',
            (array) $this->option('event'),
        )));
        $leadIds = array_values(array_filter((array) $this->option('lead'), fn ($v) => $v !== null && $v !== ''));

        $sinceOption = $this->option('since');
        $untilOption = $this->option('until');
        $sinceTimestamp = is_string($sinceOption) && trim($sinceOption) !== ''
            ? Carbon::parse($sinceOption)->timestamp
            : null;
        $untilTimestamp = is_string($untilOption) && trim($untilOption) !== ''
            ? Carbon::parse($untilOption)->timestamp
            : null;

        $dryRun = (bool) $this->option('dry-run');
        $shouldUpload = (bool) $this->option('upload');

        /** @var class-string<Model> $modelClass */
        $modelClass = config('google-ads-conversions.model', Lead::class);

        $query = $modelClass::query()->whereNotNull('conversions');

        if ($leadIds !== []) {
            $query->whereKey($leadIds);
        }

        $requeuedConversions = 0;
        $affectedLeads = 0;

        $query->chunkById(100, function ($leads) use (
            $targetStatuses,
            $eventFilters,
            $sinceTimestamp,
            $untilTimestamp,
            $dryRun,
            &$requeuedConversions,
            &$affectedLeads,
        ) {
            foreach ($leads as $lead) {
                if (! $lead instanceof HasConversions) {
                    continue;
                }

                $conversions = $lead->getConversions()->toArray();
                $modified = false;

                foreach ($conversions as $index => $conversion) {
                    $status = (string) ($conversion['status'] ?? '');

                    if (! in_array($status, $targetStatuses, true)) {
                        continue;
                    }

                    if ($eventFilters !== [] && ! in_array((string) ($conversion['event'] ?? ''), $eventFilters, true)) {
                        continue;
                    }

                    $recordedAt = (int) ($conversion['timestamp'] ?? 0);
                    $statusAt = (int) ($conversion['uploaded_at'] ?? $conversion['failed_at'] ?? $conversion['rejected_at'] ?? $recordedAt);

                    if ($sinceTimestamp !== null && max($recordedAt, $statusAt) < $sinceTimestamp) {
                        continue;
                    }

                    if ($untilTimestamp !== null && min($recordedAt ?: $statusAt, $statusAt ?: $recordedAt) > $untilTimestamp) {
                        continue;
                    }

                    $conversions[$index]['status'] = 'pending';
                    unset(
                        $conversions[$index]['uploaded_at'],
                        $conversions[$index]['failed_at'],
                        $conversions[$index]['rejected_at'],
                        $conversions[$index]['error'],
                        $conversions[$index]['retry_count'],
                    );

                    $modified = true;
                    $requeuedConversions++;
                }

                if ($modified) {
                    $affectedLeads++;

                    if (! $dryRun) {
                        $lead->setConversions($conversions);
                        $lead->persist();
                    }
                }
            }
        });

        if ($dryRun) {
            $this->info("Dry run: {$requeuedConversions} conversion(s) across {$affectedLeads} lead(s) would be reset to pending.");

            return self::SUCCESS;
        }

        $this->info("Reset {$requeuedConversions} conversion(s) across {$affectedLeads} lead(s) back to pending.");

        if ($shouldUpload && $requeuedConversions > 0) {
            $this->info('Uploading pending conversions...');
            $uploaded = $uploader->uploadPendingConversions(0, false);
            $this->info("Completed! Uploaded {$uploaded} conversion(s).");
        }

        return self::SUCCESS;
    }
}
