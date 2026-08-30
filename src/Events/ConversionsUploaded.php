<?php

namespace ElectricTomCat\GoogleAdsConversions\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversionsUploaded
{
    use Dispatchable, SerializesModels;

    /**
     * @param  int  $count  Number of uploaded conversions
     * @param  array<int, string>  $clickIds
     */
    public function __construct(
        public int $count,
        public array $clickIds,
    ) {}
}
