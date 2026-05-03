<?php

namespace ElectricTomCat\GoogleAdsConversions\Models\Concerns;

use Illuminate\Support\Collection;

/**
 * Default implementation of the HasConversions contract for Eloquent models.
 *
 * Assumes the model has the following columns:
 *   - gclid (string, unique)
 *   - visitor_id (uuid, nullable)
 *   - conversions (jsonb, nullable, cast as Collection)
 *
 * And optionally any of the tracking columns the middleware tries to fill:
 *   landing_page, source, utm_source, utm_medium, utm_campaign,
 *   utm_content, utm_term, gad_source, gad_campaignid
 *
 * Override any of these methods if your schema differs.
 */
trait HasConversionsTrait
{
    public function getGclid(): ?string
    {
        return $this->gclid;
    }

    public function setGclid(string $gclid): void
    {
        $this->gclid = $gclid;
    }

    public function getVisitorId(): ?string
    {
        return $this->visitor_id;
    }

    public function setVisitorId(?string $visitorId): void
    {
        $this->visitor_id = $visitorId;
    }

    public function getConversions(): Collection
    {
        return $this->conversions ?? collect();
    }

    public function setConversions(Collection|array $conversions): void
    {
        $this->conversions = $conversions instanceof Collection
            ? $conversions
            : collect($conversions);
    }

    public function fillTrackingData(array $data): void
    {
        $this->fill(array_intersect_key($data, array_flip($this->getFillable())));
    }

    public function persist(): bool
    {
        return $this->save();
    }

    public function isModified(): bool
    {
        return $this->isDirty() || ! $this->exists;
    }
}
