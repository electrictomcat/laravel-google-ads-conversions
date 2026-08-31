<?php

namespace ElectricTomCat\GoogleAdsConversions\Tests;

/**
 * Boots the app with the analytics dashboard switched on.
 *
 * The route is registered during provider boot, so the config has to be in
 * place before the application starts — it cannot be flipped inside a test.
 * Auth is dropped here only because the package ships no auth scaffolding to
 * log into; DashboardDisabledTest covers the shipped defaults.
 */
class DashboardTestCase extends TestCase
{
    public function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        config()->set('google-ads-conversions.dashboard.enabled', true);
        config()->set('google-ads-conversions.dashboard.middleware', ['web']);
    }
}
