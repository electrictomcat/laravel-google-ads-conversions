<?php

namespace ElectricTomCat\GoogleAdsConversions\Contracts;

use Illuminate\Support\Collection;

interface HasConversions
{
    public function getGclid(): ?string;

    public function setGclid(?string $gclid): void;

    public function getGbraid(): ?string;

    public function setGbraid(?string $gbraid): void;

    public function getWbraid(): ?string;

    public function setWbraid(?string $wbraid): void;

    public function getVisitorId(): ?string;

    public function setVisitorId(?string $visitorId): void;

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getConversions(): Collection;

    /**
     * @param  Collection<int, array<string, mixed>>|array<int, array<string, mixed>>  $conversions
     */
    public function setConversions(Collection|array $conversions): void;

    /**
     * @param  array<string, mixed>  $data
     */
    public function fillTrackingData(array $data): void;

    public function persist(): bool;

    public function isModified(): bool;
}
