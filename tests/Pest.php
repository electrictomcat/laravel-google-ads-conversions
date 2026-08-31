<?php

use ElectricTomCat\GoogleAdsConversions\Tests\DashboardTestCase;
use ElectricTomCat\GoogleAdsConversions\Tests\TestCase;

uses(TestCase::class)->in(__DIR__.'/Feature', __DIR__.'/Unit');

// The dashboard route is registered during provider boot, so the enabled case
// needs its own booted application rather than a config tweak inside a test.
uses(DashboardTestCase::class)->in(__DIR__.'/Dashboard');
