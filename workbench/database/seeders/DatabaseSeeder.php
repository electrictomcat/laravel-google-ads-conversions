<?php

namespace Workbench\Database\Seeders;

use ElectricTomCat\GoogleAdsConversions\Models\Lead;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Workbench\Database\Factories\UserFactory;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        UserFactory::new()->create([
            'name' => 'Demo Admin',
            'email' => 'admin@example.com',
        ]);

        // Create sample demo conversion leads for local preview
        Lead::create([
            'gclid' => 'gclid_search_campaign_'.Str::random(6),
            'visitor_id' => (string) Str::uuid(),
            'landing_page' => '/pricing',
            'source' => 'google_ads',
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'q3_enterprise_demo',
            'conversions' => [
                [
                    'event' => 'Demo Booked',
                    'value' => 350.00,
                    'currency' => 'USD',
                    'order_id' => 'DEMO-8001',
                    'status' => 'uploaded',
                    'timestamp' => now()->subHours(8)->timestamp,
                    'uploaded_at' => now()->subHours(2)->timestamp,
                ],
            ],
        ]);

        Lead::create([
            'gbraid' => 'gbraid_ios_campaign_'.Str::random(6),
            'visitor_id' => (string) Str::uuid(),
            'landing_page' => '/contact',
            'source' => 'google_ads',
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'ios_app_growth',
            'conversions' => [
                [
                    'event' => 'Quote Form',
                    'value' => 150.00,
                    'currency' => 'USD',
                    'order_id' => 'QUOTE-4029',
                    'status' => 'uploaded',
                    'timestamp' => now()->subHours(12)->timestamp,
                    'uploaded_at' => now()->subHours(4)->timestamp,
                ],
            ],
        ]);

        Lead::create([
            'wbraid' => 'wbraid_web_journey_'.Str::random(6),
            'visitor_id' => (string) Str::uuid(),
            'landing_page' => '/features',
            'source' => 'meta_ads',
            'utm_source' => 'facebook',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'retargeting_v2',
            'conversions' => [
                [
                    'event' => 'Checkout Completed',
                    'value' => 89.00,
                    'currency' => 'USD',
                    'order_id' => 'ORD-10932',
                    'status' => 'pending',
                    'timestamp' => now()->subMinutes(45)->timestamp,
                ],
            ],
        ]);
    }
}
