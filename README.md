# Laravel Google Ads Offline Conversions

[![Latest Version on Packagist](https://img.shields.io/packagist/v/electrictomcat/laravel-google-ads-conversions.svg?style=flat-square)](https://packagist.org/packages/electrictomcat/laravel-google-ads-conversions)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/electrictomcat/laravel-google-ads-conversions/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/electrictomcat/laravel-google-ads-conversions/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/electrictomcat/laravel-google-ads-conversions/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/electrictomcat/laravel-google-ads-conversions/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/electrictomcat/laravel-google-ads-conversions.svg?style=flat-square)](https://packagist.org/packages/electrictomcat/laravel-google-ads-conversions)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square)](LICENSE.md)

Production-ready, drop-in offline conversion tracking for Laravel apps. Google Ads is the primary, most-featured channel — click attribution, Enhanced Conversions, GDPR retention — and the same recorded conversion can optionally also fan out to Meta, Microsoft Advertising, LinkedIn and TikTok.

- 🎯 **Full Click Attribution**: Captures `gclid` (Search/Display) as well as `gbraid` and `wbraid` (iOS 14.5+ ATT app/web clicks) and maintains attribution across visitor sessions.
- ⚡ **One-Line Recording**: Record conversions from anywhere — controllers, Livewire, queued jobs, Eloquent observers.
- 📡 **Multi-Channel Fan-Out**: Optionally broadcast the same conversion to Meta CAPI, Microsoft Advertising, LinkedIn Conversions API and TikTok Events API alongside Google Ads.
- 🇪🇺 **GDPR, ePrivacy & Consent Mode v2 Ready**: Prior-consent cookie gating, automated retention pruning via `Prunable`, and explicit Google Consent Mode signals (`ad_user_data`, `ad_personalization`).
- 🔒 **Privacy-First & Enhanced Conversions**: Strict data minimization by default. Optionally enable Enhanced Conversions for Leads (SHA-256 hashed email & phone).
- 🧪 **First-Class Testing Support**: Built-in `GoogleAdsConversions::fake()` for easy test assertions in your application test suite.
- 🚀 **High Performance & Safe Batching**: Buffers in cache behind a sharded, lock-guarded dirty set, syncs to database, and uploads in batched requests of up to 2,000 conversions with memory-safe chunking.
- 📦 **Bring Your Own Model**: Use the included `Lead` model or drop `HasConversionsTrait` onto your existing `User`, `Visitor`, or `Order` models.
- 🛠️ **Artisan Tooling**: Dedicated CLI commands for installing, testing credentials against live APIs, syncing cache, and running dry-run uploads.
- 📊 **Optional Dashboard**: An in-app reporting view, off by default and gated behind auth when enabled.

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
- [Multi-Channel Conversion Fan-Out](#multi-channel-conversion-fan-out)
- [Testing In Your Application](#testing-in-your-application)
- [European & UK Privacy (GDPR & Consent Mode v2)](#european--uk-privacy-gdpr--consent-mode-v2)
- [Enhanced Conversions for Leads](#enhanced-conversions-for-leads)
- [Bring Your Own Model](#bring-your-own-model)
- [Artisan CLI Commands](#artisan-cli-commands)
- [The Reporting Dashboard](#the-reporting-dashboard)
- [Concurrency & Cache Store Requirements](#concurrency--cache-store-requirements)
- [Laravel Domain Events](#laravel-domain-events)
- [Upgrade Guide](#upgrade-guide)

---

## Installation

```bash
composer require electrictomcat/laravel-google-ads-conversions
```

Run the install command — it publishes the config and migration, then reports which channels have credentials configured:

```bash
php artisan ad-conversions:install
```

Or publish manually:

```bash
php artisan vendor:publish --tag="laravel-google-ads-conversions-config"
php artisan vendor:publish --tag="laravel-google-ads-conversions-migrations"
php artisan migrate
```

Add your Google Ads credentials to `.env` (see [Google's OAuth setup](https://developers.google.com/google-ads/api/docs/oauth/cloud-project)):

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

The job flushes the cache buffer to the database and then uploads everything eligible to Google Ads. It is `ShouldBeUnique`: an overlapping run is skipped rather than reading the same pending rows twice, since Google only de-duplicates uploads that carry an order ID.

---

## Usage

### Recording a Conversion

From anywhere in your app:

```php
use ElectricTomCat\GoogleAdsConversions\Facades\GoogleAdsConversions;

// Simple event
GoogleAdsConversions::record('Quote Form', 100.0);
```

`record()` returns `bool`. It returns `false` — and logs a warning — when the conversion could not be attributed to anything: no `gclid`/`gbraid`/`wbraid` (override, session, cookie, or stored visitor history) and, if Enhanced Conversions is enabled, no hashed user identifiers to fall back on either. Check the return value if losing a conversion silently would matter to you.

#### Full Options:
```php
$recorded = GoogleAdsConversions::record(
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
    // Store the typed identifier, not clickId(). Google keeps gclid, gbraid
    // and wbraid in separate fields and rejects a value filed under the wrong
    // one — and clickId() cannot tell you which kind it returned.
    'click_id' => (string) GoogleAdsConversions::clickIdentifier(), // "gclid:Cj0KCQ..." 
]);
```

`pendingClickIds()` returns every click identifier (and visitor key) currently buffered in cache, awaiting the next `syncToDatabase()` — useful for diagnostics or a custom monitoring check:

```php
$pending = app(GoogleAdsConversions::class)->pendingClickIds(); // array<int, string>
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

## Multi-Channel Conversion Fan-Out

Beyond the primary Google Ads pipeline, the package ships a driver-based `ConversionManager` that can send the same conversion to other ad networks via their server-to-server conversion APIs. This is separate from `record()` / the `leads` table — it works with a `ConversionPayload` DTO and talks to each network directly, without buffering or the pending-queue/retry machinery Google Ads gets.

```php
use ElectricTomCat\GoogleAdsConversions\ConversionManager;
use ElectricTomCat\GoogleAdsConversions\DTO\ConversionPayload;

$payload = ConversionPayload::fromArray([
    'event' => 'Demo Booked',
    'value' => 250.0,
    'currency' => 'USD',
    'gclid' => GoogleAdsConversions::gclid(),
    'fbclid' => $request->cookie('meta_ads_fbclid'),
    'user_data' => ['email' => 'customer@example.com'],
])->withRequest($request); // fills client_ip, client_user_agent, event_source_url if missing

/** @var ConversionManager $manager */
$manager = app(ConversionManager::class);

// Send to every enabled_channels driver that has credentials configured
$results = $manager->fanOut($payload);

// Or restrict to a subset
$results = $manager->fanOut($payload, ['google', 'meta']);
```

`fanOut()` returns an array keyed by channel name, each entry shaped `array{success: bool, count: int, errors: array<int, string>, raw_response: mixed}`. A channel is silently skipped (absent from the result) if it isn't configured; a channel that throws is caught and reported as a failed result rather than aborting the others.

`getConfiguredDrivers()` returns just the drivers that currently have credentials set, keyed by name — handy for building your own status UI.

### Drivers and required config

| Channel | Driver | Required config keys |
| :--- | :--- | :--- |
| `google` | `GoogleAdsDriver` | `developer_token`, `client_id`, `client_secret`, `refresh_token`, `customer_id` |
| `meta` | `MetaCapiDriver` | `meta.pixel_id`, `meta.access_token` |
| `microsoft` | `MicrosoftAdsDriver` | `microsoft.developer_token`, `microsoft.customer_id`, `microsoft.account_id`, `microsoft.access_token` |
| `linkedin` | `LinkedInDriver` | `linkedin.access_token`, `linkedin.conversion_rule_id` |
| `tiktok` | `TikTokDriver` | `tiktok.access_token`, `tiktok.pixel_code` |

A driver's `isConfigured()` must return `true` for every key above to be non-empty before it participates in `fanOut()` or `getConfiguredDrivers()`. Which channels are attempted at all (subject to that check) is controlled by `enabled_channels` in the config file — trim it to the networks you actually use.

Note that Microsoft Advertising needs both a manager `customer_id` and an ad `account_id` — they are different IDs and `ApplyOfflineConversions` rejects a request missing either.

`linkedin.version` (`LINKEDIN_API_VERSION`, default `202608`) is the LinkedIn API version header. LinkedIn retires a version roughly a year after release; when calls start returning HTTP 426, roll this forward.

### `ConversionPayload`

`ConversionPayload` (`src/DTO/ConversionPayload.php`) is the channel-agnostic conversion representation each driver consumes. Build it with the constructor or `ConversionPayload::fromArray()` (accepts `event`/`event_name`, `value`, `currency`, `order_id`/`orderId`, `timestamp`, `gclid`, `gbraid`, `wbraid`, `fbclid`, `fbc`, `fbp`, `msclkid`, `ttclid`, `li_fat_id`/`liFatId`, `user_data`/`user`/`user_identifiers`, `consent`, `custom_data`, `action_source`, `event_source_url`/`landing_page`). `withRequest($request)` fills in `client_ip`, `client_user_agent` and `event_source_url` from the current request when they're not already set. `primaryClickId()` returns the first non-null click identifier across every supported network, in `gclid, gbraid, wbraid, fbclid, msclkid, ttclid, liFatId` order.

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

`fake()` swaps the facade for `GoogleAdsConversionsFake`, which records to an in-memory array instead of touching cache, the database, or Google — no `record()` call in the fake path makes an attribution decision or returns `false`.

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

    // Prune leads even when they still hold an unsent conversion. Off by
    // default: retention should not silently destroy undelivered data — a
    // row with a still-pending conversion is held back from pruning until
    // it either uploads or fails, unless this is true.
    'prune_pending' => false,

    // Country calling code assumed for phone numbers stored without one
    // (e.g. '1' for the US, '44' for the UK). See "Phone number handling" below.
    'default_calling_code' => env('GOOGLE_ADS_DEFAULT_CALLING_CODE'),
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

    // How a consent value that matches neither the granted nor the denied
    // vocabulary is read. 'denied' fails closed; 'unspecified' preserves the
    // pre-v2 behaviour of letting Google decide.
    'unknown_maps_to' => env('GOOGLE_ADS_CONSENT_UNKNOWN', 'denied'),
],
```

### 3. GDPR Data Retention & Right to Erasure
1. **Automated Pruning**: The default `Lead` model implements Laravel's `Prunable` trait. Schedule `php artisan model:prune` to remove records older than `retention_days`. Rows that still carry a `pending` conversion are excluded unless `privacy.prune_pending` is `true`.
2. **Right to Erasure**: Erase a visitor's stored tracking records — this also purges anything for that visitor still sitting in the cache buffer (both the lead-data buffer and the dirty-set entry), not just the database rows, so a queued `syncToDatabase()` run can't resurrect what you just erased:
   ```php
   GoogleAdsConversions::forgetVisitor($visitorId);
   ```

### Phone number handling

`UserDataHasher` normalizes identifiers before hashing so the hash actually matches something. Email addresses are lowercased and trimmed, and for `gmail.com`/`googlemail.com` the dot-insensitive local part and any `+tag` suffix are stripped, so `a.b+promo@gmail.com` and `ab@gmail.com` hash identically. Phone numbers are reduced to E.164: a number already starting with `+` is used as-is; a number with no country code is combined with `privacy.default_calling_code` if you've set one. **If no calling code is configured, a phone number without one is dropped (logged, not hashed) rather than guessed at** — a wrong-country hash matches nobody and looks identical to a successful match, which is worse than sending nothing.

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

## Diagnosing missing conversions

When conversions are not appearing in Google Ads:

```bash
php artisan ad-conversions:diagnose
```

It reports what Google itself says about your uploads — pulled from its
offline-conversion diagnostics, including a breakdown of what is being rejected
and why — then checks your own data for the shapes Google refuses or that never
get sent at all: leads with no click identifier, braid values filed as gclids,
conversions already older than Google's 90-day window, and settings that quietly
cost attribution.

### Click identifiers

`gclid`, `gbraid` and `wbraid` live in three separate fields, and Google rejects
a value placed in the wrong one:

> The imported gclid could not be decoded. Make sure you use the correct gclid
> format. `at conversions[0].gclid`

A gclid is long and begins `Cj`/`EAIaIQ`; a gbraid or wbraid is shorter and
begins `0AAAAA`. If you keep the identifier on your own records, store a
`ClickIdentifier` rather than the raw string so the type travels with it:

```php
use ElectricTomCat\GoogleAdsConversions\Support\ClickIdentifier;

// on capture
$submission->click_id = (string) GoogleAdsConversions::clickIdentifier();

// on conversion — routed to the right field automatically
GoogleAdsConversions::record('Quote Form', 250.0,
    gclid: ClickIdentifier::fromString($submission->click_id),
);
```

A value that reaches the gclid field but has a braid's shape is moved to the
correct field and logged, rather than being sent somewhere Google will certainly
refuse it. Set `click_identifiers.autocorrect` to `false` to disable that.

Two further rules the package now enforces for you: a `gclid` is always
preferred over a braid when a lead has both, and enhanced-conversion identifiers
are omitted when the click is a braid — Google rejects that combination outright.

---

## Artisan CLI Commands

| Command | Description |
| :--- | :--- |
| `php artisan ad-conversions:install` | Publish config/migration and report which channels have credentials configured. `--channel=` limits the check to specific channels. |
| `php artisan ad-conversions:upload` | Flush cache and upload pending conversions. |
| `php artisan ad-conversions:upload --dry-run` | Validate conversions with Google Ads API without recording them (`validate_only = true`). Stored state is left untouched — nothing is marked `uploaded`, so the pending queue survives a dry run. |
| `php artisan ad-conversions:upload --force` | Force upload immediately, ignoring the delay window. |
| `php artisan ad-conversions:sync` | Flush the cache buffer directly into the database. |
| `php artisan ad-conversions:test` | Make a real authenticated API call to every configured channel and report pass/fail per channel, then resolve every mapped Google Ads conversion action. `--channel=` limits the check to specific channels; `--skip-actions` skips the conversion-action resolution step. |

The old names `google-ads:upload`, `google-ads:sync` and `google-ads:test-connection` still work as aliases of the commands above, but are deprecated — prefer the `ad-conversions:*` names in new scripts and schedules.

Note that `ad-conversions:test` now makes live, authenticated calls against each network's API (it previously only echoed config values back). Running it exercises real credentials and, for Google Ads, a real read-only query — expect it to fail exactly like a production upload would if a token is invalid or expired.

---

## The Reporting Dashboard

An optional read-only dashboard (lead counts, upload/pending/failed conversion breakdown, attributed value, per-channel configuration status, and the 25 most recent conversions) is available at a configurable route.

**It is disabled by default.** The dashboard surfaces lead counts, click identifiers and attributed revenue — data that must never be reachable anonymously — so it does not register a route at all unless you opt in:

```env
AD_CONVERSIONS_DASHBOARD_ENABLED=true
AD_CONVERSIONS_DASHBOARD_PATH=ad-conversions
```

```php
// config/google-ads-conversions.php
'dashboard' => [
    'enabled' => env('AD_CONVERSIONS_DASHBOARD_ENABLED', false),
    'path' => env('AD_CONVERSIONS_DASHBOARD_PATH', 'ad-conversions'),
    'middleware' => ['web', 'auth'],
],
```

When enabled, the route is registered with `['web', 'auth']` middleware by default — an authenticated user, not an anonymous visitor, can view it. If your app's auth guard needs a different stack (a specific guard, a role/permission gate, IP allow-listing), replace the `middleware` array accordingly; do not remove `auth` without putting an equivalent restriction in its place.

---

## Concurrency & Cache Store Requirements

Every write to the cache buffer (pending conversions, buffered lead data, and the dirty-click-ID set) is a read-modify-write. When the configured cache store implements Laravel's `LockProvider` contract — **redis, memcached, database, and array** all do — these mutations take a short-lived lock (5s TTL, 3s wait) so two concurrent requests recording against the same click ID can't silently clobber one another's write.

**The `file` cache driver has no lock primitive.** Without `LockProvider`, mutations are applied unguarded — correct for a single request at a time, but a real race under concurrent traffic can drop a buffered conversion. Use `file` only for local development or genuinely low-concurrency deployments; for anything else, use redis, memcached, database, or array as your `CACHE_STORE`.

The dirty set that tracks which click IDs need flushing to the database is sharded across 16 buckets (keyed by `crc32($clickId) % 16`) rather than kept in one array, so a busy site doesn't serialize every buffered write through a single hot key. `GoogleAdsConversions::DIRTY_SET_KEY` is the pre-sharding, single-key format; nothing writes to it any more, but `syncToDatabase()` still drains it on each run so buffers left behind by an older release of this package aren't stranded after an upgrade.

---

## Laravel Domain Events

Hook into the conversion pipeline for notifications or telemetry:

- `ElectricTomCat\GoogleAdsConversions\Events\ConversionRecorded`
- `ElectricTomCat\GoogleAdsConversions\Events\ConversionsSynced`
- `ElectricTomCat\GoogleAdsConversions\Events\ConversionsUploaded`
- `ElectricTomCat\GoogleAdsConversions\Events\ConversionUploadFailed`

`ConversionUploadFailed` now also fires for a conversion Google rejects in a partial-failure response, not just for a hard exception. A rejected conversion is marked `failed` in the `conversions` column (with `failed_at` and an `error` message) and is retried on the next upload run rather than being silently marked `uploaded` — check this event, or the `error` key, if conversions seem to vanish without appearing in Google Ads.

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
