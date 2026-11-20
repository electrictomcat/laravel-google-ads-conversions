<?php

namespace ElectricTomCat\GoogleAdsConversions\Tests\Feature;

use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use ElectricTomCat\GoogleAdsConversions\Support\UserDataHasher;
use ElectricTomCat\GoogleAdsConversions\Tests\TestCase;

class EnhancedConversionsTest extends TestCase
{
    public function test_it_hashes_email_addresses_to_sha_256_with_normalization(): void
    {
        $hasher = new UserDataHasher;

        $this->assertSame(
            hash('sha256', 'test.user@example.com'),
            $hasher->hashEmail('  Test.User@Example.COM ')
        );
    }

    public function test_it_applies_gmail_dot_and_plus_suffix_normalization_before_hashing(): void
    {
        $hasher = new UserDataHasher;

        // Google matches on the canonical mailbox, so all three are one person.
        $canonical = hash('sha256', 'testuser@gmail.com');

        $this->assertSame($canonical, $hasher->hashEmail('test.user@gmail.com'));
        $this->assertSame($canonical, $hasher->hashEmail('TestUser+promo@gmail.com'));
        $this->assertSame(
            hash('sha256', 'testuser@googlemail.com'),
            $hasher->hashEmail('t.e.s.t.u.s.e.r@googlemail.com')
        );

        // ...but dots are significant everywhere else.
        $this->assertNotSame(
            $hasher->hashEmail('testuser@example.com'),
            $hasher->hashEmail('test.user@example.com')
        );
    }

    public function test_it_hashes_a_phone_number_that_already_carries_a_country_code(): void
    {
        $hasher = new UserDataHasher;

        $this->assertSame(hash('sha256', '+15551234567'), $hasher->hashPhone('+1 (555) 123-4567'));
        $this->assertSame(hash('sha256', '+442079460958'), $hasher->hashPhone('+44 20 7946 0958'));
    }

    public function test_it_applies_the_configured_calling_code_to_a_national_phone_number(): void
    {
        config()->set('google-ads-conversions.privacy.default_calling_code', '1');

        $hasher = new UserDataHasher;

        $this->assertSame(hash('sha256', '+15551234567'), $hasher->hashPhone('(555) 123-4567'));

        // A leading trunk zero is dropped rather than embedded in the E.164 number.
        config()->set('google-ads-conversions.privacy.default_calling_code', '44');
        $this->assertSame(hash('sha256', '+442079460958'), $hasher->hashPhone('020 7946 0958'));
    }

    public function test_it_drops_a_phone_number_it_cannot_resolve_to_e164_rather_than_guessing(): void
    {
        config()->set('google-ads-conversions.privacy.default_calling_code', null);

        $hasher = new UserDataHasher;

        // Without a country code, any hash produced would match nobody. Silently
        // sending a wrong one is worse than sending nothing.
        $this->assertNull($hasher->hashPhone('(555) 123-4567'));
        $this->assertNull($hasher->hashPhone('12345'));
        $this->assertNull($hasher->hashPhone('not a phone number'));
    }

    public function test_it_does_not_attach_user_identifiers_if_enhanced_conversions_is_disabled(): void
    {
        config()->set('google-ads-conversions.enhanced_conversions.enabled', false);

        $conversions = app(GoogleAdsConversions::class);
        $conversions->record(
            eventName: 'Quote Form',
            value: 100.0,
            gclid: 'gclid-test-ec',
            userIdentifiers: ['email' => 'customer@example.com'],
        );

        $cached = cache(GoogleAdsConversions::CACHE_PREFIX.'gclid-test-ec');
        $this->assertArrayNotHasKey('user_identifiers', $cached[0]);
    }

    public function test_it_attaches_user_identifiers_when_enhanced_conversions_is_enabled(): void
    {
        config()->set('google-ads-conversions.enhanced_conversions.enabled', true);

        $conversions = app(GoogleAdsConversions::class);
        $conversions->record(
            eventName: 'Quote Form',
            value: 100.0,
            gclid: 'gclid-test-ec-enabled',
            userIdentifiers: ['email' => 'customer@example.com'],
        );

        $cached = cache(GoogleAdsConversions::CACHE_PREFIX.'gclid-test-ec-enabled');
        $this->assertArrayHasKey('user_identifiers', $cached[0]);
        $this->assertSame('customer@example.com', $cached[0]['user_identifiers']['email']);
    }
}
