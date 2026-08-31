<?php

use ElectricTomCat\GoogleAdsConversions\Drivers\LinkedInDriver;
use ElectricTomCat\GoogleAdsConversions\Drivers\MicrosoftAdsDriver;
use ElectricTomCat\GoogleAdsConversions\Drivers\TikTokDriver;
use ElectricTomCat\GoogleAdsConversions\DTO\ConversionPayload;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| Regressions for drivers that reported success without delivering anything.
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    config()->set('google-ads-conversions.microsoft', [
        'developer_token' => 'dev-token',
        'customer_id' => '111',
        'account_id' => '222',
        'access_token' => 'access-token',
    ]);

    config()->set('google-ads-conversions.linkedin', [
        'access_token' => 'li-token',
        'conversion_rule_id' => '98765',
        'version' => '202608',
    ]);

    config()->set('google-ads-conversions.tiktok', [
        'access_token' => 'tt-token',
        'pixel_code' => 'PIXEL123',
    ]);
});

// ---------------------------------------------------------------- PKG-07

it('posts Microsoft conversions to the campaign management endpoint', function () {
    Http::fake([
        'campaign.api.bingads.microsoft.com/*' => Http::response(['PartialErrors' => []], 200),
    ]);

    $result = (new MicrosoftAdsDriver)->upload([
        new ConversionPayload(
            eventName: 'Purchase',
            value: 49.99,
            currency: 'GBP',
            timestamp: 1767225600,
            msclkid: 'MSCLK-1',
        ),
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['count'])->toBe(1);

    Http::assertSent(function ($request) {
        $body = $request->data();
        $entry = $body['OfflineConversions'][0];

        return $request->url() === 'https://campaign.api.bingads.microsoft.com/CampaignManagement/v13/OfflineConversions/Apply'
            && $request->hasHeader('DeveloperToken', 'dev-token')
            && $request->hasHeader('CustomerId', '111')
            // The account header was missing entirely before; without it
            // Microsoft rejects the request.
            && $request->hasHeader('CustomerAccountId', '222')
            && $entry['MicrosoftClickId'] === 'MSCLK-1'
            // UTC with a Z suffix, not a local offset.
            && $entry['ConversionTime'] === '2026-01-01T00:00:00Z'
            && $entry['ConversionCurrencyCode'] === 'GBP';
    });
});

it('reports Microsoft per-item failures instead of counting them as delivered', function () {
    Http::fake([
        'campaign.api.bingads.microsoft.com/*' => Http::response([
            'PartialErrors' => [
                ['Index' => 1, 'Message' => 'ConversionName does not match a goal.'],
            ],
        ], 200),
    ]);

    $result = (new MicrosoftAdsDriver)->upload([
        new ConversionPayload(eventName: 'Purchase', timestamp: 1767225600, msclkid: 'ok'),
        new ConversionPayload(eventName: 'Nonsense', timestamp: 1767225600, msclkid: 'bad'),
    ]);

    expect($result['count'])->toBe(1)
        ->and($result['success'])->toBeFalse()
        ->and($result['errors'][0])->toContain('does not match a goal');
});

it('refuses to report success when Microsoft is not fully configured', function () {
    config()->set('google-ads-conversions.microsoft.account_id', null);

    $result = (new MicrosoftAdsDriver)->upload([
        new ConversionPayload(eventName: 'Purchase', msclkid: 'x'),
    ]);

    expect($result['success'])->toBeFalse()->and($result['count'])->toBe(0);
});

// ---------------------------------------------------------------- PKG-08

it('sends the LinkedIn conversion value as conversionValue, not totalBudget', function () {
    Http::fake(['api.linkedin.com/*' => Http::response([], 201)]);

    $result = (new LinkedInDriver)->upload([
        new ConversionPayload(
            eventName: 'Lead',
            value: 250.0,
            currency: 'EUR',
            timestamp: 1767225600,
            userData: ['email' => 'buyer@example.com'],
        ),
    ]);

    expect($result['success'])->toBeTrue()->and($result['count'])->toBe(1);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return ! array_key_exists('totalBudget', $body)
            && $body['conversionValue']['currencyCode'] === 'EUR'
            // amount is a string in LinkedIn's schema.
            && $body['conversionValue']['amount'] === '250.00'
            && $body['conversionHappenedAt'] === 1767225600000
            && $request->hasHeader('LinkedIn-Version', '202608');
    });
});

it('surfaces an expired LinkedIn API version as an actionable error', function () {
    Http::fake(['api.linkedin.com/*' => Http::response(['message' => 'gone'], 426)]);

    $result = (new LinkedInDriver)->upload([
        new ConversionPayload(eventName: 'Lead', userData: ['email' => 'buyer@example.com']),
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['errors'][0])->toContain('LINKEDIN_API_VERSION');
});

it('does not call a run successful when only some LinkedIn events landed', function () {
    Http::fake([
        'api.linkedin.com/*' => Http::sequence()
            ->push([], 201)
            ->push(['message' => 'bad request'], 400),
    ]);

    $result = (new LinkedInDriver)->upload([
        new ConversionPayload(eventName: 'Lead', userData: ['email' => 'a@example.com']),
        new ConversionPayload(eventName: 'Lead', userData: ['email' => 'b@example.com']),
    ]);

    expect($result['count'])->toBe(1)
        ->and($result['success'])->toBeFalse();
});

// ---------------------------------------------------------------- PKG-09

it('fails a TikTok connection test when the token is rejected', function () {
    Http::fake([
        'business-api.tiktok.com/*' => Http::response(['code' => 40001, 'message' => 'Access token is invalid'], 200),
    ]);

    $result = (new TikTokDriver)->testConnection();

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('Access token is invalid');
});

it('passes a TikTok connection test when the token works', function () {
    Http::fake([
        'business-api.tiktok.com/*' => Http::response([
            'code' => 0,
            'data' => ['display_name' => 'OmniSignal'],
        ], 200),
    ]);

    $result = (new TikTokDriver)->testConnection();

    expect($result['success'])->toBeTrue()
        ->and($result['message'])->toContain('OmniSignal');
});

it('fails a Microsoft connection test when the credentials are rejected', function () {
    Http::fake([
        'clientcenter.api.bingads.microsoft.com/*' => Http::response('Unauthorized', 401),
    ]);

    expect((new MicrosoftAdsDriver)->testConnection()['success'])->toBeFalse();
});

it('fails a LinkedIn connection test when the token is rejected', function () {
    Http::fake(['api.linkedin.com/*' => Http::response(['message' => 'nope'], 401)]);

    expect((new LinkedInDriver)->testConnection()['success'])->toBeFalse();
});

// ---------------------------------------------------------------- PKG-15

it('normalizes identifiers consistently across channels', function () {
    Http::fake(['business-api.tiktok.com/*' => Http::response(['code' => 0], 200)]);

    config()->set('google-ads-conversions.privacy.default_calling_code', '1');

    (new TikTokDriver)->upload([
        new ConversionPayload(
            eventName: 'CompletePayment',
            timestamp: 1767225600,
            ttclid: 'TT-1',
            userData: [
                'email' => 'Test.User+promo@Gmail.com',
                'phone' => '(555) 123-4567',
                'client_ip' => '203.0.113.7',
                'client_user_agent' => 'Mozilla/5.0',
            ],
        ),
    ]);

    Http::assertSent(function ($request) {
        $user = $request->data()['data'][0]['user'];

        return $user['email'] === hash('sha256', 'testuser@gmail.com')
            && $user['phone_number'] === hash('sha256', '+15551234567')
            && $user['ip'] === '203.0.113.7'
            && $user['user_agent'] === 'Mozilla/5.0';
    });
});
