<?php

use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use ElectricTomCat\GoogleAdsConversions\Support\UserDataHasher;

it('hashes email and phone numbers to SHA-256 with normalization', function () {
    $hasher = new UserDataHasher;

    $rawEmail = '  Test.User@Example.COM ';
    $expectedEmailHash = hash('sha256', 'test.user@example.com');

    expect($hasher->hashEmail($rawEmail))->toBe($expectedEmailHash);

    $rawPhone = '(555) 123-4567';
    $expectedPhoneHash = hash('sha256', '+5551234567');

    expect($hasher->hashPhone($rawPhone))->toBe($expectedPhoneHash);
});

it('does not attach user identifiers if enhanced_conversions is disabled', function () {
    config()->set('google-ads-conversions.enhanced_conversions.enabled', false);

    $conversions = app(GoogleAdsConversions::class);
    $conversions->record(
        eventName: 'Quote Form',
        value: 100.0,
        gclid: 'gclid-test-ec',
        userIdentifiers: ['email' => 'customer@example.com'],
    );

    $cached = cache(GoogleAdsConversions::CACHE_PREFIX.'gclid-test-ec');
    expect($cached[0])->not->toHaveKey('user_identifiers');
});

it('attaches user identifiers when enhanced_conversions is enabled', function () {
    config()->set('google-ads-conversions.enhanced_conversions.enabled', true);

    $conversions = app(GoogleAdsConversions::class);
    $conversions->record(
        eventName: 'Quote Form',
        value: 100.0,
        gclid: 'gclid-test-ec-enabled',
        userIdentifiers: ['email' => 'customer@example.com'],
    );

    $cached = cache(GoogleAdsConversions::CACHE_PREFIX.'gclid-test-ec-enabled');
    expect($cached[0])->toHaveKey('user_identifiers')
        ->and($cached[0]['user_identifiers']['email'])->toBe('customer@example.com');
});
