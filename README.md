# Laravel Google Ads Offline Conversions

[![Latest Version on Packagist](https://img.shields.io/packagist/v/electrictomcat/laravel-google-ads-conversions.svg?style=flat-square)](https://packagist.org/packages/electrictomcat/laravel-google-ads-conversions)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/electrictomcat/laravel-google-ads-conversions/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/electrictomcat/laravel-google-ads-conversions/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/electrictomcat/laravel-google-ads-conversions/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/electrictomcat/laravel-google-ads-conversions/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/electrictomcat/laravel-google-ads-conversions.svg?style=flat-square)](https://packagist.org/packages/electrictomcat/laravel-google-ads-conversions)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square)](LICENSE.md)

Production-ready, drop-in offline conversion tracking for Laravel apps using the Google Ads API.

- 🎯 **Full Click Attribution**: Captures `gclid` (Search/Display) as well as `gbraid` and `wbraid` (iOS 14.5+ ATT app/web clicks) and maintains attribution across visitor sessions.
- ⚡ **One-Line Recording**: Record conversions from anywhere — controllers, Livewire, queued jobs, Eloquent observers.
- 🇪🇺 **GDPR, ePrivacy & Consent Mode v2 Ready**: Prior-consent cookie gating, automated 90-day retention pruning via `Prunable`, and explicit Google Consent Mode signals (`ad_user_data`, `ad_personalization`).
- 🔒 **Privacy-First & Enhanced Conversions**: Strict data minimization by default. Optionally enable Enhanced Conversions for Leads (SHA-256 hashed email & phone).
- 🧪 **First-Class Testing Support**: Built-in `GoogleAdsConversions::fake()` for easy test assertions in your application test suite.
- 🚀 **High Performance & Safe Batching**: Buffers in cache, syncs to database, and uploads in batched requests of up to 2,000 conversions with memory-safe chunking.
- 📦 **Bring Your Own Model**: Use the included `Lead` model or drop `HasConversionsTrait` onto your existing `User`, `Visitor`, or `Order` models.
- 🛠️ **Artisan Tooling**: Dedicated CLI commands for testing credentials, syncing cache, and running dry-run uploads.

Requires **PHP 8.3+** and **Laravel 11, 12, or 13**.

---

## Table of Contents
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
  - [Recording a Conversion](#recording-a-conversion)
  - [Accessing Click Identifiers](#accessing-click-identifiers)
  - [Blade Directives for HTML Forms](#blade-directives-for-html-forms)
  - [Mapping Conversion Events](#mapping-conversion-events)
- [Testing In Your Application](#testing-in-your-application)
- [European & UK Privacy (GDPR & Consent Mode v2)](#european--uk-privacy-gdpr--consent-mode-v2)
- [Enhanced Conversions for Leads](#enhanced-conversions-for-leads)
- [Bring Your Own Model](#bring-your-own-model)
- [Artisan CLI Commands](#artisan-cli-commands)
- [Laravel Domain Events](#laravel-domain-events)
- [Upgrade Guide](#upgrade-guide)

---

## Installation

```bash
composer require electrictomcat/laravel-google-ads-conversions
```

Publish the config and migrations:

```bash
php artisan vendor:publish --tag="laravel-google-ads-conversions-config"
php artisan vendor:publish --tag="laravel-google-ads-conversions-migrations"
php artisan migrate
```

Add your credentials to `.env` (see [Google's OAuth setup](https://developers.google.com/google-ads/api/docs/oauth/cloud-project)):

```env
GOOGLE_ADS_DEVELOPER_TOKEN=
GOOGLE_ADS_CLIENT_ID=
GOOGLE_ADS_CLIENT_SECRET=
GOOGLE_ADS_REFRESH_TOKEN=
GOOGLE_ADS_CUSTOMER_ID=123-456-7890

# Optional: If managing via a Manager Account (MCC)
GOOGLE_ADS_LOGIN_CUSTOMER_ID=
```

Register the middleware in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \ElectricTomCat\GoogleAdsConversions\Http\Middleware\CaptureGclid::class,
    ]);
})
```

Schedule the upload job in `routes/console.php`:

```php
use ElectricTomCat\GoogleAdsConversions\Jobs\UploadPendingConversions;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new UploadPendingConversions)->hourly();
```

---

## Usage

### Recording a Conversion

From anywhere in your app:

```php
use ElectricTomCat\GoogleAdsConversions\Facades\GoogleAdsConversions;

// Simple event
GoogleAdsConversions::record('Quote Form', 100.0);
```

#### Full Options:
```php
GoogleAdsConversions::record(
    eventName: 'Deal Closed',
    value: 1500.00,                      // Optional: conversion value
    currency: 'EUR',                     // Optional: ISO currency (falls back to config)
    gclid: null,                         // Optional: manual GCLID override
    gbraid: null,                        // Optional: manual GBRAID override
    wbraid: null,                        // Optional: manual WBRAID override
    orderId: 'INV-2026-0042',            // Optional: transaction ID for deduplication
    conversionDateTime: now()->subDay(), // Optional: custom timestamp
    consent: [                           // Optional: Consent Mode v2 signals
        'ad_user_data' => 'GRANTED',
        'ad_personalization' => 'GRANTED',
    ],
    userIdentifiers: [                   // Optional: Enhanced Conversions (if enabled)
        'email' => 'customer@example.com',
        'phone' => '+15555550199',
    ],
);
```

### Accessing Click Identifiers

If you store click IDs on your own CRM or form submission records:

```php
$submission = ContactSubmission::create([
    'name' => $request->name,
    'gclid' => GoogleAdsConversions::gclid(),     // string|null
    'gbraid' => GoogleAdsConversions::gbraid(),   // string|null
    'wbraid' => GoogleAdsConversions::wbraid(),   // string|null
    'click_id' => GoogleAdsConversions::clickId(), // any active click identifier
]);
```

### Blade Directives for HTML Forms

Automatically inject hidden `<input>` fields for active click identifiers into contact forms:

```blade
<form action="/lead" method="POST">
    @csrf
    @googleAdsClickInputs

    <input type="text" name="name" required>
    <button type="submit">Submit</button>
</form>
```

*(Or `@googleAdsGclid` for GCLID only).*

---

### Mapping Conversion Events

In `config/google-ads-conversions.php`:

```php
'events' => [
    // Direct string mapping
    'Quote Form' => 'Quote Submission',

    // Resource name mapping
    'Phone Call' => 'customers/1234567890/conversionActions/111111',

    // With defaults that call-site can override
    'Demo Booked' => [
        'action'   => 'Demo Booked',
        'value'    => 250.00,
        'currency' => 'USD',
    ],

    // Prefix matching: catches "Page Navigation: /pricing"
    'Page Navigation' => 'Page Navigation',
],
```

---

## Testing In Your Application

The package includes a full testing fake so you can easily write tests in your application:

```php
use ElectricTomCat\GoogleAdsConversions\Facades\GoogleAdsConversions;

public function test_booking_records_offline_conversion()
{
    GoogleAdsConversions::fake();

    $this->post('/book-demo', ['name' => 'Alice']);

    // Assert conversion was recorded
    GoogleAdsConversions::assertRecorded('Demo Booked', 250.0);

    // Or use custom closure assertions
    GoogleAdsConversions::assertRecorded(function ($entry) {
        return $entry['event'] === 'Demo Booked' && $entry['order_id'] === 'DEMO-123';
    });

    GoogleAdsConversions::assertNotRecorded('Unrelated Event');
    GoogleAdsConversions::assertRecordedCount(1);
}
```

---

## European & UK Privacy (GDPR & Consent Mode v2)

### 1. Cookie Consent Gating
Under ePrivacy / GDPR, marketing cookies cannot be dropped before consent is granted. The package provides three modes:

```php
// config/google-ads-conversions.php
'privacy' => [
    // 'auto'   => Only drop 30-day cookies if a consent cookie is detected
    // 'always' => Always drop cookies (default for non-EEA / standard US)
    // 'never'  => Ephemeral session-only tracking (no persistent cookies)
    'cookie_consent' => env('GOOGLE_ADS_COOKIE_CONSENT', 'always'),

    'consent_cookie_names' => [
        'cookie_consent_marketing',
        'cookie_consent',
        'CookieConsent',
        'laravel_cookie_consent',
    ],

    // Auto-prune leads after 90 days (Google's max attribution window)
    'retention_days' => 90,
],
```

You can also define custom consent logic in a service provider:
```php
use ElectricTomCat\GoogleAdsConversions\Support\ConsentManager;

ConsentManager::determineCookieConsentUsing(function ($request) {
    return $request->user()?->marketing_opt_in === true;
});
```

### 2. Google Consent Mode v2
Set default consent signals for uploaded conversions:
```php
'consent' => [
    'ad_user_data' => env('GOOGLE_ADS_CONSENT_AD_USER_DATA', null), // 'GRANTED' | 'DENIED' | null
    'ad_personalization' => env('GOOGLE_ADS_CONSENT_AD_PERSONALIZATION', null),
],
```

### 3. GDPR Data Retention & Right to Erasure
1. **Automated Pruning**: The default `Lead` model implements Laravel's `Prunable` trait. Schedule `php artisan model:prune` to remove records older than `retention_days`.
2. **Right to Erasure**: Erase a visitor's stored tracking records:
   ```php
   GoogleAdsConversions::forgetVisitor($visitorId);
   ```

---

## Enhanced Conversions for Leads

To improve attribution match rates when third-party cookies are degraded:

```php
// config/google-ads-conversions.php
'enhanced_conversions' => [
    'enabled' => env('GOOGLE_ADS_ENHANCED_CONVERSIONS', false), // default false
],
```

When enabled, passing `userIdentifiers: ['email' => '...', 'phone' => '...']` to `record()` will automatically normalize and SHA-256 hash the data before transmission to Google.

---

## Bring Your Own Model

Drop `HasConversionsTrait` onto any Eloquent model:

```php
use ElectricTomCat\GoogleAdsConversions\Contracts\HasConversions;
use ElectricTomCat\GoogleAdsConversions\Models\Concerns\HasConversionsTrait;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model implements HasConversions
{
    use HasConversionsTrait;

    protected $fillable = [
        'gclid',
        'gbraid',
        'wbraid',
        'visitor_id',
        'conversions',
        // ...
    ];

    protected $casts = [
        'conversions' => AsCollection::class,
    ];
}
```

Point the config at your model:
```php
// config/google-ads-conversions.php
'model' => \App\Models\Customer::class,
```

---

## Artisan CLI Commands

| Command | Description |
| :--- | :--- |
| `php artisan google-ads:upload` | Flush cache and upload pending conversions. |
| `php artisan google-ads:upload --dry-run` | Validate conversions with Google Ads API without recording them (`validate_only = true`). |
| `php artisan google-ads:upload --force` | Force upload immediately, ignoring the delay window. |
| `php artisan google-ads:sync` | Flush the cache buffer directly into the database. |
| `php artisan google-ads:test-connection` | Test API credentials and verify conversion action resolution. |

---

## Laravel Domain Events

Hook into the conversion pipeline for notifications or telemetry:

- `ElectricTomCat\GoogleAdsConversions\Events\ConversionRecorded`
- `ElectricTomCat\GoogleAdsConversions\Events\ConversionsSynced`
- `ElectricTomCat\GoogleAdsConversions\Events\ConversionsUploaded`
- `ElectricTomCat\GoogleAdsConversions\Events\ConversionUploadFailed`

---

## Upgrade Guide

See [UPGRADING.md](UPGRADING.md) for detailed migration steps from v1.x.

---

## Testing

```bash
composer test
```

---

## Credits

- [Tom Michael](https://github.com/electrictomcat)
- Built on top of the [Spatie package skeleton](https://github.com/spatie/package-skeleton-laravel) and [`googleads/google-ads-php`](https://github.com/googleads/google-ads-php)

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
