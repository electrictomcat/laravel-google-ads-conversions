<?php

namespace ElectricTomCat\GoogleAdsConversions\Support;

use Google\Ads\GoogleAds\V23\Common\UserIdentifier;
use Illuminate\Support\Facades\Log;

/**
 * Normalises and hashes first-party identifiers for enhanced conversions.
 *
 * Every ad platform expects the same normalisation before SHA-256: lowercase,
 * trimmed, Gmail dots and +suffixes removed, phone numbers in full E.164. Get
 * it wrong and the hash is well-formed but matches nothing, which is worse
 * than sending nothing — it looks like it worked.
 */
class UserDataHasher
{
    /**
     * Domains that ignore dots and +suffixes in the local part.
     *
     * @var array<int, string>
     */
    protected const DOT_INSENSITIVE_DOMAINS = ['gmail.com', 'googlemail.com'];

    /**
     * Create UserIdentifier protobuf objects for Enhanced Conversions.
     *
     * @param  array{email?: string|null, phone?: string|null, phone_number?: string|null}  $userData
     * @return array<int, UserIdentifier>
     */
    public function hashUserIdentifiers(array $userData): array
    {
        $identifiers = [];

        if (! empty($userData['email'])) {
            $hashedEmail = $this->hashEmail((string) $userData['email']);
            if ($hashedEmail) {
                $identifier = new UserIdentifier;
                $identifier->setHashedEmail($hashedEmail);
                $identifiers[] = $identifier;
            }
        }

        $phone = $userData['phone'] ?? $userData['phone_number'] ?? null;
        if (! empty($phone)) {
            $hashedPhone = $this->hashPhone((string) $phone);
            if ($hashedPhone) {
                $identifier = new UserIdentifier;
                $identifier->setHashedPhoneNumber($hashedPhone);
                $identifiers[] = $identifier;
            }
        }

        return $identifiers;
    }

    /**
     * Normalize and SHA-256 hash an email address.
     */
    public function hashEmail(string $email): ?string
    {
        $normalized = $this->normalizeEmail($email);

        return $normalized ? hash('sha256', $normalized) : null;
    }

    /**
     * Apply the normalisation ad platforms expect before hashing.
     */
    public function normalizeEmail(string $email): ?string
    {
        $normalized = strtolower(trim($email));

        if (! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        [$local, $domain] = explode('@', $normalized, 2);

        if (in_array($domain, self::DOT_INSENSITIVE_DOMAINS, true)) {
            // Gmail treats a.b+tag@gmail.com and ab@gmail.com as one mailbox;
            // Google's own matching expects the canonical form.
            $local = str_replace('.', '', explode('+', $local, 2)[0]);

            if ($local === '') {
                return null;
            }
        }

        return $local.'@'.$domain;
    }

    /**
     * Normalize and SHA-256 hash a phone number to E.164.
     *
     * Returns null rather than guessing when the number cannot be resolved to
     * E.164. A wrong country code produces a hash that matches nobody, and
     * silently sending one is indistinguishable from success.
     */
    public function hashPhone(string $phone): ?string
    {
        $normalized = $this->normalizePhone($phone);

        return $normalized ? hash('sha256', $normalized) : null;
    }

    /**
     * Reduce a phone number to E.164, or null if that isn't possible.
     */
    public function normalizePhone(string $phone): ?string
    {
        $trimmed = trim($phone);
        $hadPlus = str_starts_with($trimmed, '+');

        $digits = preg_replace('/\D/', '', $trimmed) ?? '';

        if ($digits === '') {
            return null;
        }

        if ($hadPlus) {
            // Already international.
            return $this->validE164('+'.$digits);
        }

        $callingCode = trim((string) config('google-ads-conversions.privacy.default_calling_code', ''));

        if ($callingCode === '') {
            Log::warning(
                '[GoogleAdsConversions] Dropped a phone number with no country code. Set '
                .'GOOGLE_ADS_DEFAULT_CALLING_CODE (e.g. 1 for the US, 44 for the UK) or store numbers in E.164.'
            );

            return null;
        }

        $callingCode = ltrim($callingCode, '+');

        // A number already carrying the country code should not gain a second.
        if (str_starts_with($digits, $callingCode) && strlen($digits) > strlen($callingCode) + 6) {
            return $this->validE164('+'.$digits);
        }

        // Strip a national trunk prefix ("0") before prepending the code.
        $digits = ltrim($digits, '0');

        if ($digits === '') {
            return null;
        }

        return $this->validE164('+'.$callingCode.$digits);
    }

    /**
     * E.164 allows at most 15 digits, and no real number has fewer than 7.
     */
    protected function validE164(string $candidate): ?string
    {
        return preg_match('/^\+[1-9]\d{6,14}$/', $candidate) === 1 ? $candidate : null;
    }
}
