<?php

namespace ElectricTomCat\GoogleAdsConversions\DTO;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ConversionPayload
{
    /**
     * @param  array{email?: string|null, phone?: string|null, first_name?: string|null, last_name?: string|null, city?: string|null, state?: string|null, postal_code?: string|null, country?: string|null, client_ip?: string|null, client_user_agent?: string|null}  $userData
     * @param  array{ad_user_data?: string|bool|null, ad_personalization?: string|bool|null}|bool|null  $consent
     * @param  array<string, mixed>  $customData
     */
    public function __construct(
        public string $eventName,
        public ?float $value = null,
        public ?string $currency = 'USD',
        public ?string $orderId = null,
        public int $timestamp = 0,
        public ?string $gclid = null,
        public ?string $gbraid = null,
        public ?string $wbraid = null,
        public ?string $fbclid = null,
        public ?string $fbc = null,
        public ?string $fbp = null,
        public ?string $msclkid = null,
        public ?string $ttclid = null,
        public ?string $liFatId = null,
        public array $userData = [],
        public array|bool|null $consent = null,
        public array $customData = [],
        public string $actionSource = 'website',
        public ?string $eventSourceUrl = null,
    ) {
        if ($this->timestamp === 0) {
            $this->timestamp = now()->timestamp;
        }

        if ($this->currency !== null) {
            $this->currency = strtoupper(trim($this->currency));
        }
    }

    /**
     * Build from array or parameters.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $timestamp = $data['timestamp'] ?? now()->timestamp;
        if ($timestamp instanceof DateTimeInterface) {
            $timestamp = $timestamp->getTimestamp();
        } elseif (is_string($timestamp) && ! is_numeric($timestamp)) {
            $timestamp = Carbon::parse($timestamp)->getTimestamp();
        }

        return new self(
            eventName: (string) ($data['event'] ?? $data['event_name'] ?? 'Conversion'),
            value: isset($data['value']) ? (float) $data['value'] : null,
            currency: $data['currency'] ?? config('google-ads-conversions.default_currency', 'USD'),
            orderId: $data['order_id'] ?? $data['orderId'] ?? null,
            timestamp: (int) $timestamp,
            gclid: $data['gclid'] ?? null,
            gbraid: $data['gbraid'] ?? null,
            wbraid: $data['wbraid'] ?? null,
            fbclid: $data['fbclid'] ?? null,
            fbc: $data['fbc'] ?? null,
            fbp: $data['fbp'] ?? null,
            msclkid: $data['msclkid'] ?? null,
            ttclid: $data['ttclid'] ?? null,
            liFatId: $data['li_fat_id'] ?? $data['liFatId'] ?? null,
            userData: (array) ($data['user_data'] ?? $data['user'] ?? $data['user_identifiers'] ?? []),
            consent: $data['consent'] ?? null,
            customData: (array) ($data['custom_data'] ?? []),
            actionSource: (string) ($data['action_source'] ?? 'website'),
            eventSourceUrl: $data['event_source_url'] ?? $data['landing_page'] ?? null,
        );
    }

    /**
     * Populate missing client IP, User-Agent, and URL from request.
     */
    public function withRequest(Request $request): self
    {
        if (empty($this->userData['client_ip'])) {
            $this->userData['client_ip'] = $request->ip();
        }

        if (empty($this->userData['client_user_agent'])) {
            $this->userData['client_user_agent'] = $request->userAgent();
        }

        if (empty($this->eventSourceUrl)) {
            $this->eventSourceUrl = $request->fullUrl();
        }

        return $this;
    }

    /**
     * Get any active click identifier.
     */
    public function primaryClickId(): ?string
    {
        return $this->gclid
            ?? $this->gbraid
            ?? $this->wbraid
            ?? $this->fbclid
            ?? $this->msclkid
            ?? $this->ttclid
            ?? $this->liFatId;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'event' => $this->eventName,
            'value' => $this->value,
            'currency' => $this->currency,
            'order_id' => $this->orderId,
            'timestamp' => $this->timestamp,
            'gclid' => $this->gclid,
            'gbraid' => $this->gbraid,
            'wbraid' => $this->wbraid,
            'fbclid' => $this->fbclid,
            'fbc' => $this->fbc,
            'fbp' => $this->fbp,
            'msclkid' => $this->msclkid,
            'ttclid' => $this->ttclid,
            'li_fat_id' => $this->liFatId,
            'user_data' => $this->userData,
            'consent' => $this->consent,
            'custom_data' => $this->customData,
            'action_source' => $this->actionSource,
            'event_source_url' => $this->eventSourceUrl,
        ];
    }
}
