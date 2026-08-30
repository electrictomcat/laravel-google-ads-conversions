<?php

namespace ElectricTomCat\GoogleAdsConversions\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversionUploadFailed
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $conversion
     */
    public function __construct(
        public string $clickId,
        public string $errorMessage,
        public array $conversion = [],
    ) {}
}
