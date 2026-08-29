<?php

namespace ElectricTomCat\GoogleAdsConversions\Http\Controllers;

use ElectricTomCat\GoogleAdsConversions\Contracts\HasConversions;
use ElectricTomCat\GoogleAdsConversions\ConversionManager;
use ElectricTomCat\GoogleAdsConversions\Models\Lead;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    public function __invoke(ConversionManager $manager): View
    {
        /** @var class-string<HasConversions&Model> $modelClass */
        $modelClass = config('google-ads-conversions.model', Lead::class);

        $totalLeads = $modelClass::query()->count();

        $allLeadsWithConversions = $modelClass::query()
            ->whereNotNull('conversions')
            ->latest('updated_at')
            ->limit(100)
            ->get();

        $totalConversions = 0;
        $totalUploaded = 0;
        $totalPending = 0;
        $totalFailed = 0;
        $totalValue = 0.0;
        $recentConversions = [];

        foreach ($allLeadsWithConversions as $lead) {
            $conversions = $lead->getConversions();
            $clickId = $lead->getGclid() ?? $lead->getGbraid() ?? $lead->getWbraid() ?? 'N/A';

            foreach ($conversions as $conv) {
                $totalConversions++;
                $status = $conv['status'] ?? 'pending';

                if ($status === 'uploaded') {
                    $totalUploaded++;
                } elseif ($status === 'failed') {
                    $totalFailed++;
                } else {
                    $totalPending++;
                }

                if (isset($conv['value'])) {
                    $totalValue += (float) $conv['value'];
                }

                $recentConversions[] = [
                    'event' => $conv['event'] ?? 'Conversion',
                    'value' => $conv['value'] ?? 0.0,
                    'currency' => $conv['currency'] ?? 'USD',
                    'status' => $status,
                    'click_id' => $clickId,
                    'timestamp' => $conv['timestamp'] ?? now()->timestamp,
                    'order_id' => $conv['order_id'] ?? null,
                ];
            }
        }

        // Sort recent conversions descending by timestamp
        usort($recentConversions, fn ($a, $b) => ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0));
        $recentConversions = array_slice($recentConversions, 0, 25);

        // Match rate calculation
        $leadsWithClickId = $modelClass::query()
            ->where(function ($q) {
                $q->whereNotNull('gclid')
                    ->orWhereNotNull('gbraid')
                    ->orWhereNotNull('wbraid');
            })
            ->count();

        $matchRate = $totalLeads > 0 ? round(($leadsWithClickId / $totalLeads) * 100, 1) : 100.0;

        // Channel Statuses
        $channels = [];
        foreach (['google', 'meta', 'microsoft', 'linkedin', 'tiktok'] as $ch) {
            try {
                $driver = $manager->driver($ch);
                $isConfigured = $driver->isConfigured();
                $channels[$ch] = [
                    'name' => ucfirst($ch),
                    'configured' => $isConfigured,
                    'status' => $isConfigured ? 'Active' : 'Unconfigured',
                ];
            } catch (\Throwable $e) {
                $channels[$ch] = [
                    'name' => ucfirst($ch),
                    'configured' => false,
                    'status' => 'Disabled',
                ];
            }
        }

        return view('google-ads-conversions::dashboard', [
            'totalLeads' => $totalLeads,
            'totalConversions' => $totalConversions,
            'totalUploaded' => $totalUploaded,
            'totalPending' => $totalPending,
            'totalFailed' => $totalFailed,
            'totalValue' => $totalValue,
            'matchRate' => $matchRate,
            'recentConversions' => $recentConversions,
            'channels' => $channels,
            'defaultCurrency' => config('google-ads-conversions.default_currency', 'USD'),
        ]);
    }
}
