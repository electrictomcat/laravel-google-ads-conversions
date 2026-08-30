<?php

namespace ElectricTomCat\GoogleAdsConversions\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversionsSynced
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<int, string>  $syncedClickIds
     */
    public function __construct(
        public array $syncedClickIds,
    ) {}
}
