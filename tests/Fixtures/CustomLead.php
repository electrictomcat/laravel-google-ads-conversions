<?php

namespace ElectricTomCat\GoogleAdsConversions\Tests\Fixtures;

use ElectricTomCat\GoogleAdsConversions\Contracts\HasConversions;
use ElectricTomCat\GoogleAdsConversions\Models\Concerns\HasConversionsTrait;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Model;

class CustomLead extends Model implements HasConversions
{
    use HasConversionsTrait;

    protected $table = 'custom_leads';

    protected $fillable = [
        'gclid',
        'visitor_id',
        'conversions',
        'utm_source',
    ];

    protected $casts = [
        'conversions' => AsCollection::class,
    ];
}
