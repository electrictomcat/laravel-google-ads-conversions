<?php

namespace ElectricTomCat\GoogleAdsConversions\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions
 */
class GoogleAdsConversions extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions::class;
    }
}
