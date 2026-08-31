<?php

use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use ElectricTomCat\GoogleAdsConversions\Support\UserDataHasher;

it('hashes email addresses to SHA-256 with normalization', function () {
    $hasher = new UserDataHasher;

    expect($hasher->hashEmail('  Test.User@Example.COM '))
        ->toBe(hash('sha256', 'test.user@example.com'));
});

it('applies Gmail dot and plus-suffix normalization before hashing', function () {
    $hasher = new UserDataHasher;

    // Google matches on the canonical mailbox, so all three are one person.
    $canonical = hash('sha256', 'testuser@gmail.com');

    expect($hasher->hashEmail('test.user@gmail.com'))->toBe($canonical)
        ->and($hasher->hashEmail('TestUser+promo@gmail.com'))->toBe($canonical)
        ->and($hasher->hashEmail('t.e.s.t.u.s.e.r@googlemail.com'))
        ->toBe(hash('sha256', 'testuser@googlemail.com'));

    // ...but dots are significant everywhere else.
    expect($hasher->hashEmail('test.user@example.com'))
        ->not->toBe($hasher->hashEmail('testuser@example.com'));
});

it('hashes a phone number that already carries a country code', function () {
    $hasher = new UserDataHasher;

    expect($hasher->hashPhone('+1 (555) 123-4567'))->toBe(hash('sha256', '+15551234567'))
        ->and($hasher->hashPhone('+44 20 7946 0958'))->toBe(hash('sha256', '+442079460958'));
});

it('applies the configured calling code to a national phone number', function () {
    config()->set('google-ads-conversions.privacy.default_calling_code', '1');

    $hasher = new UserDataHasher;

    expect($hasher->hashPhone('(555) 123-4567'))->toBe(hash('sha256', '+15551234567'));

    // A leading trunk zero is dropped rather than embedded in the E.164 number.
    config()->set('google-ads-conversions.privacy.default_calling_code', '44');
    expect($hasher->hashPhone('020 7946 0958'))->toBe(hash('sha256', '+442079460958'));
});

it('drops a phone number it cannot resolve to E.164 rather than guessing', function () {
    config()->set('google-ads-conversions.privacy.default_calling_code', null);

    $hasher = new UserDataHasher;

    // Without a country code, any hash produced would match nobody. Silently
    // sending a wrong one is worse than sending nothing.
    expect($hasher->hashPhone('(555) 123-4567'))->toBeNull()
        ->and($hasher->hashPhone('12345'))->toBeNull()
        ->and($hasher->hashPhone('not a phone number'))->toBeNull();
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
