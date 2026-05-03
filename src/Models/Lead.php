<?php

namespace ElectricTomCat\GoogleAdsConversions\Models;

use ElectricTomCat\GoogleAdsConversions\Contracts\HasConversions;
use ElectricTomCat\GoogleAdsConversions\Models\Concerns\HasConversionsTrait;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $gclid
 * @property string|null $visitor_id
 * @property Collection|null $conversions
 * @property string|null $landing_page
 * @property string|null $source
 * @property string|null $utm_source
 * @property string|null $utm_medium
 * @property string|null $utm_campaign
 * @property string|null $utm_content
 * @property string|null $utm_term
 * @property string|null $gad_source
 * @property string|null $gad_campaignid
 */
class Lead extends Model implements HasConversions
{
    use HasConversionsTrait;

    protected $fillable = [
        'gclid',
        'visitor_id',
        'conversions',
        'landing_page',
        'source',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'gad_source',
        'gad_campaignid',
    ];

    protected $casts = [
        'conversions' => AsCollection::class,
    ];

    public function getTable()
    {
        return config('google-ads-conversions.table', parent::getTable());
    }
}
