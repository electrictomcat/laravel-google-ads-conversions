<?php

namespace ElectricTomCat\GoogleAdsConversions\Contracts;

use ElectricTomCat\GoogleAdsConversions\DTO\ConversionPayload;

interface ConversionDriverInterface
{
    /**
     * Unique identifier for the driver (e.g. 'google', 'meta', 'microsoft', 'linkedin', 'tiktok').
     */
    public function name(): string;

    /**
     * Check if all required API credentials are configured for this driver.
     */
    public function isConfigured(): bool;

    /**
     * Upload a batch of conversion payloads to the advertising network.
     *
     * @param  array<int, ConversionPayload|array<string, mixed>>  $conversions
     * @return array{success: bool, count: int, errors: array<int, string>, raw_response: mixed}
     */
    public function upload(array $conversions, bool $validateOnly = false): array;

    /**
     * Test connection and credentials with the ad network.
     *
     * @return array{success: bool, message: string, details?: array<string, mixed>}
     */
    public function testConnection(): array;
}
