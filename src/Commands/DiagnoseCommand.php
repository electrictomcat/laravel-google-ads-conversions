<?php

namespace ElectricTomCat\GoogleAdsConversions\Commands;

use ElectricTomCat\GoogleAdsConversions\Contracts\HasConversions;
use ElectricTomCat\GoogleAdsConversions\ConversionUploader;
use ElectricTomCat\GoogleAdsConversions\Models\Lead;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Explains why conversions are not showing up in Google Ads.
 *
 * Two halves. The first reads what Google itself says about our uploads,
 * through its offline-conversion diagnostics; the second inspects the local
 * data for the shapes that get rejected or never sent at all.
 */
class DiagnoseCommand extends Command
{
    protected $signature = 'ad-conversions:diagnose
                            {--days=30 : How far back to look at local conversions}';

    protected $description = 'Diagnose attribution problems: what Google reports, and what our own data looks like';

    /** Google keeps click data for 90 days; nothing older can be attributed. */
    protected const CLICK_RETENTION_DAYS = 90;

    public function handle(ConversionUploader $uploader): int
    {
        $this->components->info('OmniSignal attribution diagnostics');

        $problems = 0;
        $problems += $this->reportGoogleDiagnostics($uploader);
        $problems += $this->reportLocalData();
        $problems += $this->reportConfiguration();

        $this->newLine();

        if ($problems > 0) {
            $this->components->warn("{$problems} thing(s) worth looking at above.");

            return self::FAILURE;
        }

        $this->components->info('Nothing obviously wrong.');

        return self::SUCCESS;
    }

    /**
     * What Google says about the uploads it has received from us.
     */
    protected function reportGoogleDiagnostics(ConversionUploader $uploader): int
    {
        $this->newLine();
        $this->components->info('What Google reports about our uploads');

        $summary = $uploader->uploadDiagnostics();

        if (! $summary['ok']) {
            $this->components->warn('Could not read Google\'s diagnostics: '.$summary['message']);
            $this->line('  <fg=gray>Fix the credentials first — run ad-conversions:test.</>');

            return 1;
        }

        if ($summary['rows'] === []) {
            $this->line('  <fg=gray>Google has no offline-conversion diagnostics for this account yet.</>');
            $this->line('  <fg=gray>That is normal if nothing has been uploaded in the last 90 days.</>');

            return 0;
        }

        $problems = 0;

        foreach ($summary['rows'] as $row) {
            $status = $row['status'];
            $success = $row['successful_count'];
            $failed = $row['failed_count'];
            $total = $success + $failed;

            $this->line(sprintf(
                '  Status <fg=%s>%s</>  •  %d of %d accepted%s',
                $status === 'EXCELLENT' ? 'green' : 'yellow',
                $status,
                $success,
                $total,
                $row['last_upload'] ? '  •  last upload '.$row['last_upload'] : '',
            ));

            if ($status !== 'EXCELLENT' || $failed > 0) {
                $problems++;
            }

            foreach ($row['alerts'] as $alert) {
                $this->line(sprintf(
                    '    <fg=red>%s</> — %s%% of uploads',
                    $alert['error'],
                    round(((float) $alert['rate']) * 100, 1),
                ));
                $this->line('      <fg=gray>'.$this->explain($alert['error']).'</>');
            }
        }

        return $problems;
    }

    /**
     * Shapes in our own data that Google will refuse, or that never reach it.
     */
    protected function reportLocalData(): int
    {
        $this->newLine();
        $this->components->info('Our own data');

        /** @var class-string<HasConversions&Model> $modelClass */
        $modelClass = config('google-ads-conversions.model', Lead::class);

        $days = max(1, (int) $this->option('days'));
        $since = now()->subDays($days);

        $total = $modelClass::query()->where('created_at', '>=', $since)->count();

        if ($total === 0) {
            $this->line("  <fg=gray>No leads recorded in the last {$days} days.</>");

            return 0;
        }

        $problems = 0;

        // Leads with no click identifier at all cannot be attributed.
        $unattributable = $modelClass::query()
            ->where('created_at', '>=', $since)
            ->whereNull('gclid')->whereNull('gbraid')->whereNull('wbraid')
            ->count();

        $rate = round(($total - $unattributable) / $total * 100, 1);
        $this->line('  Leads with a click identifier: <fg='.($rate < 50 ? 'yellow' : 'green').">{$rate}%</> ({$total} leads)");

        if ($unattributable > 0) {
            $this->line("    <fg=gray>{$unattributable} have none, so nothing can be uploaded for them.</>");
            $this->line('    <fg=gray>Usually auto-tagging off in Google Ads, or a redirect dropping the query string.</>');
            $problems++;
        }

        // Braid values sitting in the gclid column - Google refuses these.
        $misfiled = $modelClass::query()
            ->where('created_at', '>=', $since)
            ->whereNotNull('gclid')
            ->where('gclid', 'like', '0AAAAA%')
            ->count();

        if ($misfiled > 0) {
            $this->line("  <fg=red>{$misfiled} lead(s) have a gbraid/wbraid stored in the gclid column.</>");
            $this->line('    <fg=gray>Google answers "The imported gclid could not be decoded" for these.</>');
            $this->line('    <fg=gray>They are re-routed automatically on upload; store a ClickIdentifier to stop it recurring.</>');
            $problems++;
        }

        // Conversions Google will refuse as too old.
        $stale = 0;
        $pending = 0;

        $modelClass::query()
            ->whereNotNull('conversions')
            ->chunkById(200, function ($leads) use (&$stale, &$pending) {
                foreach ($leads as $lead) {
                    if (! $lead instanceof HasConversions) {
                        continue;
                    }

                    foreach ($lead->getConversions() as $conversion) {
                        if (($conversion['status'] ?? '') !== 'pending') {
                            continue;
                        }

                        $pending++;

                        $age = now()->diffInDays(now()->setTimestamp((int) ($conversion['timestamp'] ?? 0)), true);

                        if ($age > self::CLICK_RETENTION_DAYS) {
                            $stale++;
                        }
                    }
                }
            });

        $this->line("  Pending conversions: {$pending}");

        if ($stale > 0) {
            $this->line("    <fg=red>{$stale} are older than ".self::CLICK_RETENTION_DAYS.' days and can never be attributed.</>');
            $this->line('    <fg=gray>Google keeps click data for 90 days. Check the upload job is running.</>');
            $problems++;
        }

        // Conversions we already know Google refused.
        $failed = 0;
        $reasons = [];

        $modelClass::query()
            ->whereNotNull('conversions')
            ->chunkById(200, function ($leads) use (&$failed, &$reasons) {
                foreach ($leads as $lead) {
                    if (! $lead instanceof HasConversions) {
                        continue;
                    }

                    foreach ($lead->getConversions() as $conversion) {
                        if (($conversion['status'] ?? '') === 'failed') {
                            $failed++;
                            $reason = (string) ($conversion['error'] ?? 'Unknown');
                            $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
                        }
                    }
                }
            });

        if ($failed > 0) {
            $this->line("  <fg=yellow>{$failed} conversion(s) were rejected by Google and are awaiting retry:</>");

            arsort($reasons);
            foreach (array_slice($reasons, 0, 5, true) as $reason => $count) {
                $this->line("    <fg=gray>{$count}x</> ".mb_substr($reason, 0, 110));
            }

            $problems++;
        }

        return $problems;
    }

    /**
     * Settings that quietly cost attribution.
     */
    protected function reportConfiguration(): int
    {
        $this->newLine();
        $this->components->info('Configuration');

        $problems = 0;

        $cookieDays = (int) config('google-ads-conversions.cookies.lifetime_minutes', 0) / 60 / 24;

        if ($cookieDays < self::CLICK_RETENTION_DAYS) {
            $this->line("  <fg=yellow>Click identifiers are kept for {$cookieDays} days, but Google accepts conversions for ".self::CLICK_RETENTION_DAYS.'.</>');
            $this->line('    <fg=gray>Anything converting after '.$cookieDays.' days has no identifier left to attribute.</>');
            $problems++;
        } else {
            $this->line("  Cookie lifetime: {$cookieDays} days");
        }

        $delay = (int) config('google-ads-conversions.upload_delay_hours', 6);

        if ($delay < 6) {
            $this->line("  <fg=yellow>Upload delay is {$delay}h; Google refuses conversions whose click is under 6 hours old.</>");
            $problems++;
        } else {
            $this->line("  Upload delay: {$delay}h");
        }

        $consent = (string) config('google-ads-conversions.privacy.cookie_consent', 'always');
        $this->line("  Cookie consent mode: {$consent}");

        if ($consent === 'auto') {
            $this->line('    <fg=gray>Identifiers are only stored once a consent cookie is present. If the CMP</>');
            $this->line('    <fg=gray>is not detected, nothing is captured at all — check consent_cookie_names.</>');
        }

        $retention = (int) config('google-ads-conversions.privacy.retention_days', 90);

        if ($retention < self::CLICK_RETENTION_DAYS) {
            $this->line("  <fg=yellow>Retention prunes at {$retention} days, before Google's ".self::CLICK_RETENTION_DAYS.'-day window closes.</>');
            $problems++;
        }

        return $problems;
    }

    /**
     * Plain-English cause for the errors Google reports most often.
     */
    protected function explain(string $error): string
    {
        return match (true) {
            str_contains($error, 'EXPIRED') => 'The click was older than the conversion action\'s click-through window when uploaded.',
            str_contains($error, 'TOO_RECENT_CONVERSION_ACTION') => 'The conversion action was created less than 6 hours ago.',
            str_contains($error, 'TOO_RECENT') => 'The click was under 6 hours old. Raise upload_delay_hours.',
            str_contains($error, 'INVALID_CUSTOMER_FOR_CLICK') => 'The click belongs to a different Google Ads account than the one uploading.',
            str_contains($error, 'NO_CONVERSION_ACTION') => 'The conversion action does not exist, is not enabled, or is not visible to this account.',
            str_contains($error, 'INVALID_CONVERSION_ACTION_TYPE') => 'The conversion action is not an offline/import type.',
            str_contains($error, 'ORDER_ID') => 'A conversion with this order ID was already recorded.',
            str_contains($error, 'DECODE') || str_contains($error, 'GCLID') => 'A value in the gclid field is not a gclid — often a gbraid or wbraid.',
            str_contains($error, 'VALUE_MUST_BE_UNSET') => 'Enhanced-conversion identifiers were sent alongside a gbraid or wbraid, which Google does not allow.',
            default => 'See Google\'s ConversionUploadError reference for this code.',
        };
    }
}
