<?php

namespace ElectricTomCat\GoogleAdsConversions\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversionRecorded
{
    use Dispatchable, SerializesModels;

    /**
     * @param  string  $clickId  (gclid, gbraid, or wbraid)
     * @param  array<string, mixed>  $conversion
     */
    public function __construct(
        public string $clickId,
        public array $conversion,
    ) {}
}
