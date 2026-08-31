<?php

namespace ElectricTomCat\GoogleAdsConversions\Tests\Fixtures;

use ElectricTomCat\GoogleAdsConversions\Models\Lead;

/**
 * A Lead whose writes always fail, standing in for a database outage,
 * deadlock, or constraint violation during a sync run.
 */
class ExplodingLead extends Lead
{
    public function persist(): bool
    {
        throw new \RuntimeException('simulated database outage');
    }
}
