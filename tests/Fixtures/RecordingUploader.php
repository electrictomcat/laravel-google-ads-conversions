<?php

namespace ElectricTomCat\GoogleAdsConversions\Tests\Fixtures;

use ElectricTomCat\GoogleAdsConversions\ConversionUploader;
use Google\Ads\GoogleAds\Lib\V23\GoogleAdsClient;
use Google\Ads\GoogleAds\V23\Services\Client\ConversionUploadServiceClient;
use Google\Ads\GoogleAds\V23\Services\UploadClickConversionsRequest;
use Google\Ads\GoogleAds\V23\Services\UploadClickConversionsResponse;
use Mockery;

/**
 * A ConversionUploader wired to a stubbed Google Ads client.
 *
 * Lets the upload path be exercised end to end — including validate-only and
 * partial-failure handling — without touching the network.
 */
class RecordingUploader extends ConversionUploader
{
    public ?UploadClickConversionsResponse $stubbedResponse = null;

    public ?string $resolvedResourceName = 'customers/1234567890/conversionActions/111';

    /** @var array<int, UploadClickConversionsRequest> */
    public array $sentRequests = [];

    protected function client(): GoogleAdsClient
    {
        $service = Mockery::mock(ConversionUploadServiceClient::class);
        $service->shouldReceive('uploadClickConversions')
            ->andReturnUsing(function (UploadClickConversionsRequest $request) {
                $this->sentRequests[] = $request;

                return $this->stubbedResponse ?? new UploadClickConversionsResponse;
            });

        $client = Mockery::mock(GoogleAdsClient::class);
        $client->shouldReceive('getConversionUploadServiceClient')->andReturn($service);

        return $client;
    }

    /**
     * Bypass the live GAQL lookup — conversion-action resolution is not what
     * these tests are about.
     */
    public function resolveActionResourceName(string $action): ?string
    {
        return $this->resolvedResourceName;
    }

    public function lastRequestWasValidateOnly(): bool
    {
        $last = end($this->sentRequests);

        return $last instanceof UploadClickConversionsRequest && $last->getValidateOnly();
    }
}
