<?php

namespace ElectricTomCat\GoogleAdsConversions\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void record(string $eventName, ?float $value = null, ?string $currency = null, ?string $gclid = null)
 * @method static string|null gclid()
 * @method static void forgetGclid()
 * @method static void bufferLeadData(string $gclid, array $data)
 * @method static void syncToDatabase()
 *
 * @see \ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions
 */
class GoogleAdsConversions extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions::class;
    }
}
