<?php

namespace ElectricTomCat\GoogleAdsConversions\Testing;

use Closure;
use DateTimeInterface;
use PHPUnit\Framework\Assert as PHPUnit;

class GoogleAdsConversionsFake
{
    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $recorded = [];

    public function __construct(
        protected ?string $fakeGclid = 'fake-gclid-12345',
        protected ?string $fakeGbraid = null,
        protected ?string $fakeWbraid = null,
    ) {}

    /**
     * @param  array{ad_user_data?: string|bool|null, ad_personalization?: string|bool|null}|bool|null  $consent
     * @param  array{email?: string|null, phone?: string|null, phone_number?: string|null}  $userIdentifiers
     */
    public function record(
        string $eventName,
        ?float $value = null,
        ?string $currency = null,
        ?string $gclid = null,
        ?string $gbraid = null,
        ?string $wbraid = null,
        ?string $orderId = null,
        DateTimeInterface|int|string|null $conversionDateTime = null,
        array|bool|null $consent = null,
        array $userIdentifiers = [],
    ): void {
        $this->recorded[] = [
            'event' => $eventName,
            'value' => $value,
            'currency' => $currency,
            'gclid' => $gclid ?? $this->gclid(),
            'gbraid' => $gbraid ?? $this->gbraid(),
            'wbraid' => $wbraid ?? $this->wbraid(),
            'order_id' => $orderId,
            'conversion_date_time' => $conversionDateTime,
            'consent' => $consent,
            'user_identifiers' => $userIdentifiers,
        ];
    }

    public function gclid(): ?string
    {
        return $this->fakeGclid;
    }

    public function setFakeGclid(?string $gclid): self
    {
        $this->fakeGclid = $gclid;

        return $this;
    }

    public function gbraid(): ?string
    {
        return $this->fakeGbraid;
    }

    public function setFakeGbraid(?string $gbraid): self
    {
        $this->fakeGbraid = $gbraid;

        return $this;
    }

    public function wbraid(): ?string
    {
        return $this->fakeWbraid;
    }

    public function setFakeWbraid(?string $wbraid): self
    {
        $this->fakeWbraid = $wbraid;

        return $this;
    }

    public function clickId(): ?string
    {
        return $this->gclid() ?? $this->gbraid() ?? $this->wbraid();
    }

    public function forgetGclid(): void
    {
        $this->fakeGclid = null;
        $this->fakeGbraid = null;
        $this->fakeWbraid = null;
    }

    public function bufferLeadData(string $clickId, array $data): void {}

    public function syncToDatabase(): void {}

    public function forgetVisitor(string $visitorId): int
    {
        return 0;
    }

    /**
     * Assert that a conversion matching the given name or callback was recorded.
     */
    public function assertRecorded(string|Closure $eventName, ?float $value = null): void
    {
        if ($eventName instanceof Closure) {
            $matched = array_filter($this->recorded, $eventName);
            PHPUnit::assertTrue(
                count($matched) > 0,
                'Failed asserting that a matching Google Ads conversion was recorded.'
            );

            return;
        }

        $matched = array_filter($this->recorded, function ($entry) use ($eventName, $value) {
            if ($entry['event'] !== $eventName) {
                return false;
            }

            if ($value !== null && (float) ($entry['value'] ?? 0) !== (float) $value) {
                return false;
            }

            return true;
        });

        PHPUnit::assertTrue(
            count($matched) > 0,
            "Failed asserting that Google Ads conversion '{$eventName}' was recorded."
        );
    }

    /**
     * Assert that a conversion was NOT recorded.
     */
    public function assertNotRecorded(string|Closure $eventName): void
    {
        if ($eventName instanceof Closure) {
            $matched = array_filter($this->recorded, $eventName);
            PHPUnit::assertCount(
                0,
                $matched,
                'Failed asserting that no matching Google Ads conversion was recorded.'
            );

            return;
        }

        $matched = array_filter($this->recorded, fn ($entry) => $entry['event'] === $eventName);

        PHPUnit::assertCount(
            0,
            $matched,
            "Failed asserting that Google Ads conversion '{$eventName}' was not recorded."
        );
    }

    /**
     * Assert that zero conversions were recorded.
     */
    public function assertNothingRecorded(): void
    {
        PHPUnit::assertEmpty(
            $this->recorded,
            'Failed asserting that no Google Ads conversions were recorded.'
        );
    }

    /**
     * Assert the total count of recorded conversions.
     */
    public function assertRecordedCount(int $count): void
    {
        PHPUnit::assertCount(
            $count,
            $this->recorded,
            "Failed asserting that exactly {$count} Google Ads conversions were recorded."
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recorded(): array
    {
        return $this->recorded;
    }
}
