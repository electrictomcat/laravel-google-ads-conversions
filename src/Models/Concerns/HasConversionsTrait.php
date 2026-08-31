<?php

namespace ElectricTomCat\GoogleAdsConversions\Models\Concerns;

use Illuminate\Support\Collection;

/**
 * Default implementation of the HasConversions contract for Eloquent models.
 *
 * Assumes the model has the following columns:
 *   - gclid (string, nullable, unique)
 *   - gbraid (string, nullable, indexed)
 *   - wbraid (string, nullable, indexed)
 *   - visitor_id (uuid, nullable, indexed)
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

    public function setGclid(?string $gclid): void
    {
        $this->gclid = $gclid;
    }

    public function getGbraid(): ?string
    {
        return $this->gbraid;
    }

    public function setGbraid(?string $gbraid): void
    {
        $this->gbraid = $gbraid;
    }

    public function getWbraid(): ?string
    {
        return $this->wbraid;
    }

    public function setWbraid(?string $wbraid): void
    {
        $this->wbraid = $wbraid;
    }

    public function getVisitorId(): ?string
    {
        return $this->visitor_id;
    }

    public function setVisitorId(?string $visitorId): void
    {
        $this->visitor_id = $visitorId;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getConversions(): Collection
    {
        return $this->conversions ?? collect();
    }

    /**
     * @param  Collection<int, array<string, mixed>>|array<int, array<string, mixed>>  $conversions
     */
    public function setConversions(Collection|array $conversions): void
    {
        $this->conversions = $conversions instanceof Collection
            ? $conversions
            : collect($conversions);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function fillTrackingData(array $data): void
    {
        $fillable = $this->getFillable();

        if (! empty($fillable)) {
            $data = array_intersect_key($data, array_flip($fillable));
        }

        $this->fill($data);
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
