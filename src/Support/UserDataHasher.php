<?php

namespace ElectricTomCat\GoogleAdsConversions\Support;

use Google\Ads\GoogleAds\V23\Common\UserIdentifier;

class UserDataHasher
{
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
            $hashedEmail = $this->hashEmail($userData['email']);
            if ($hashedEmail) {
                $identifier = new UserIdentifier;
                $identifier->setHashedEmail($hashedEmail);
                $identifiers[] = $identifier;
            }
        }

        $phone = $userData['phone'] ?? $userData['phone_number'] ?? null;
        if (! empty($phone)) {
            $hashedPhone = $this->hashPhone($phone);
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
        $normalized = strtolower(trim($email));

        if (! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return hash('sha256', $normalized);
    }

    /**
     * Normalize and SHA-256 hash a phone number (E.164 format).
     */
    public function hashPhone(string $phone): ?string
    {
        $clean = preg_replace('/[^\d+]/', '', trim($phone));

        if (empty($clean)) {
            return null;
        }

        if (! str_starts_with($clean, '+')) {
            $clean = '+'.$clean;
        }

        return hash('sha256', $clean);
    }
}
