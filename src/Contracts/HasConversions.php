<?php

namespace ElectricTomCat\GoogleAdsConversions\Contracts;

use Illuminate\Support\Collection;

interface HasConversions
{
    public function getGclid(): ?string;

    public function setGclid(string $gclid): void;

    public function getVisitorId(): ?string;

    public function setVisitorId(?string $visitorId): void;

    public function getConversions(): Collection;

    public function setConversions(Collection|array $conversions): void;

    public function fillTrackingData(array $data): void;

    public function persist(): bool;

    public function isModified(): bool;
}
