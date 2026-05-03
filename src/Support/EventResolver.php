<?php

namespace ElectricTomCat\GoogleAdsConversions\Support;

/**
 * Resolves an event name into its configured Google Ads action, value,
 * and currency. Supports two config shapes:
 *
 *   'Quote Form' => 'Quote Submission'
 *   'Demo Booked' => ['action' => 'Demo Booked', 'value' => 250, 'currency' => 'USD']
 *
 * And supports prefix matching: an event named "Page Navigation: /pricing"
 * matches a config entry "Page Navigation".
 */
class EventResolver
{
    /**
     * Find the action string (name or full resource path) for an event.
     */
    public function action(string $event): ?string
    {
        $entry = $this->findEntry($event);

        if ($entry === null) {
            return null;
        }

        return is_array($entry) ? ($entry['action'] ?? null) : $entry;
    }

    /**
     * Resolve the value for an event. Call-site value wins; otherwise the
     * config default; otherwise null (the upload will omit a value).
     */
    public function value(string $event, ?float $callSiteValue): ?float
    {
        if ($callSiteValue !== null) {
            return $callSiteValue;
        }

        $entry = $this->findEntry($event);

        if (is_array($entry) && isset($entry['value'])) {
            return (float) $entry['value'];
        }

        return null;
    }

    /**
     * Resolve the currency for an event. Call-site value wins; otherwise the
     * per-event config; otherwise the package default.
     */
    public function currency(string $event, ?string $callSiteCurrency): string
    {
        if ($callSiteCurrency !== null) {
            return $callSiteCurrency;
        }

        $entry = $this->findEntry($event);

        if (is_array($entry) && isset($entry['currency'])) {
            return (string) $entry['currency'];
        }

        return (string) config('google-ads-conversions.default_currency', 'USD');
    }

    /**
     * Look up the config entry for an event, supporting prefix matching
     * for events shaped like "Prefix: anything".
     *
     * @return string|array<string, mixed>|null
     */
    protected function findEntry(string $event): string|array|null
    {
        $events = (array) config('google-ads-conversions.events', []);

        if (array_key_exists($event, $events)) {
            return $events[$event];
        }

        foreach ($events as $key => $value) {
            if (str_starts_with($event, $key.': ')) {
                return $value;
            }
        }

        return null;
    }
}
