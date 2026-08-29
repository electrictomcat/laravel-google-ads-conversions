<?php

namespace ElectricTomCat\GoogleAdsConversions;

use ElectricTomCat\GoogleAdsConversions\Contracts\ConversionDriverInterface;
use ElectricTomCat\GoogleAdsConversions\Drivers\GoogleAdsDriver;
use ElectricTomCat\GoogleAdsConversions\Drivers\LinkedInDriver;
use ElectricTomCat\GoogleAdsConversions\Drivers\MetaCapiDriver;
use ElectricTomCat\GoogleAdsConversions\Drivers\MicrosoftAdsDriver;
use ElectricTomCat\GoogleAdsConversions\Drivers\TikTokDriver;
use ElectricTomCat\GoogleAdsConversions\DTO\ConversionPayload;
use Illuminate\Support\Manager;

class ConversionManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return config('google-ads-conversions.default_channel', 'google');
    }

    public function createGoogleDriver(): ConversionDriverInterface
    {
        return new GoogleAdsDriver($this->container->make(ConversionUploader::class));
    }

    public function createMetaDriver(): ConversionDriverInterface
    {
        return new MetaCapiDriver;
    }

    public function createMicrosoftDriver(): ConversionDriverInterface
    {
        return new MicrosoftAdsDriver;
    }

    public function createLinkedinDriver(): ConversionDriverInterface
    {
        return new LinkedInDriver;
    }

    public function createTiktokDriver(): ConversionDriverInterface
    {
        return new TikTokDriver;
    }

    /**
     * Broadcast a conversion to all configured channels (or specified list).
     *
     * @param  array<int, string>|null  $channels
     * @return array<string, array{success: bool, count: int, errors: array<int, string>, raw_response: mixed}>
     */
    public function fanOut(ConversionPayload $payload, ?array $channels = null): array
    {
        $targetChannels = $channels ?? (array) config('google-ads-conversions.enabled_channels', ['google', 'meta', 'microsoft', 'linkedin', 'tiktok']);
        $results = [];

        foreach ($targetChannels as $channel) {
            try {
                /** @var ConversionDriverInterface $driver */
                $driver = $this->driver($channel);
                if ($driver->isConfigured()) {
                    $results[$channel] = $driver->upload([$payload]);
                }
            } catch (\Throwable $e) {
                $results[$channel] = [
                    'success' => false,
                    'count' => 0,
                    'errors' => [$e->getMessage()],
                    'raw_response' => null,
                ];
            }
        }

        return $results;
    }

    /**
     * Get all configured and active drivers.
     *
     * @return array<string, ConversionDriverInterface>
     */
    public function getConfiguredDrivers(): array
    {
        $all = ['google', 'meta', 'microsoft', 'linkedin', 'tiktok'];
        $configured = [];

        foreach ($all as $name) {
            try {
                $driver = $this->driver($name);
                if ($driver->isConfigured()) {
                    $configured[$name] = $driver;
                }
            } catch (\Throwable $e) {
                // Ignore missing drivers
            }
        }

        return $configured;
    }
}
