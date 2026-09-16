# Laravel Google Ads Offline Conversions

[![Latest Version on Packagist](https://img.shields.io/packagist/v/electrictomcat/laravel-google-ads-conversions.svg?style=flat-square)](https://packagist.org/packages/electrictomcat/laravel-google-ads-conversions)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square)](LICENSE.md)

Production-ready, drop-in offline conversion tracking for Laravel applications. Captures Google Ads click identifiers (`gclid`, `gbraid`, `wbraid`), buffers conversions in cache with zero database lag, and uploads them to the Google Ads API v17/v18.

> ### ⚡ Need Meta CAPI, TikTok, LinkedIn, Microsoft Ads, or WooCommerce?
> If you need multi-channel server-to-server tracking across **Meta CAPI (v20.0)**, **TikTok Events API**, **LinkedIn CAPI**, **Microsoft Advertising**, an **in-app live reporting dashboard**, or turnkey **WordPress & WooCommerce** integration, check out [OmniSignal Pro](https://omnisignal.dev).

---

## Key Features

- 🎯 **Full Click Attribution**: Captures `gclid` (Search/Display) as well as `gbraid` and `wbraid` (iOS 14.5+ ATT app/web clicks) and maintains attribution across visitor sessions.
- ⚡ **One-Line Recording**: Record conversions from anywhere — controllers, Livewire, queued jobs, Eloquent observers (`GoogleAdsConversions::record(...)`).
- 🇪🇺 **GDPR, ePrivacy & Consent Mode v2 Ready**: Prior-consent cookie gating, automated 90-day retention pruning via `Prunable`, and explicit Google Consent Mode signals (`ad_user_data`, `ad_personalization`).
- 🔒 **Enhanced Conversions for Leads**: Strict data minimization with SHA-256 hashed email & phone numbers.
- 🧪 **First-Class Testing Support**: Built-in `GoogleAdsConversions::fake()` for easy test assertions in your application test suite.
- 🚀 **High Performance & Safe Batching**: Buffers in cache behind a sharded, lock-guarded dirty set, syncs to database, and uploads in batched requests of up to 2,000 conversions.
- 📦 **Bring Your Own Model**: Use the included `Lead` model or drop `HasConversionsTrait` onto your existing `User`, `Visitor`, or `Order` models.
- 🛠️ **Artisan Tooling**: Dedicated CLI commands for installing, testing credentials against live APIs, syncing cache, and running dry-run uploads.

Requires **PHP 8.3+** and **Laravel 11, 12, or 13**.

---

## Installation

```bash
composer require electrictomcat/laravel-google-ads-conversions
```

Publish configuration and database migration:

```bash
php artisan google-ads:install
```

Run migrations:

```bash
php artisan migrate
```

---

## Configuration

Add your Google Ads API credentials to your `.env` file:

```ini
GOOGLE_ADS_DEVELOPER_TOKEN="your-developer-token"
GOOGLE_ADS_CLIENT_ID="your-client-id.apps.googleusercontent.com"
GOOGLE_ADS_CLIENT_SECRET="your-client-secret"
GOOGLE_ADS_REFRESH_TOKEN="your-refresh-token"
GOOGLE_ADS_CUSTOMER_ID="1234567890" # without hyphens
```

---

## Usage

### 1. Register the Click Capture Middleware & Exempt Cookies

In your `bootstrap/app.php` (Laravel 11+), register the middleware and exempt the package's attribution cookies from encryption so client-side JavaScript can read them:

```php
use ElectricTomCat\GoogleAdsConversions\GoogleAdsConversions;
use ElectricTomCat\GoogleAdsConversions\Http\Middleware\CaptureGclid;

->withMiddleware(function (Middleware $middleware) {
    // Allow client-side scripts to read attribution cookies
    $middleware->encryptCookies(except: GoogleAdsConversions::cookieNames());

    $middleware->web(append: [
        CaptureGclid::class,
    ]);
})
```

### 2. Record a Conversion Event (Server-Side)

```php
use ElectricTomCat\GoogleAdsConversions\Facades\GoogleAdsConversions;

// Basic lead conversion
GoogleAdsConversions::record('Contact Form');

// Conversion with value and currency
GoogleAdsConversions::record('Demo Booked', 250.00, 'USD');

// Conversion with order ID and Enhanced Conversions (hashed customer data)
GoogleAdsConversions::record(
    eventName: 'Purchase',
    value: 99.00,
    currency: 'USD',
    orderId: 'ORD-9821',
    userIdentifiers: [
        'email' => 'customer@example.com',
        'phone' => '+15551234567',
    ]
);
```

### 3. Client-Side JavaScript Tracking

Add the `@googleAdsScript` directive to your layout (e.g. before `</head>` or `</body>`):

```blade
@googleAdsScript
```

This injects the global `window.trackGoogleAdsConversion(eventName, value, currency, orderId)` helper. It inspects client cookies (`google_ads_gclid`, `google_ads_gbraid`, `google_ads_wbraid`) and dispatches the conversion via `navigator.sendBeacon` (falling back to `fetch`) to the package's built-in endpoint:

```javascript
// Trigger a conversion from any frontend interaction or button click
window.trackGoogleAdsConversion('Add to Cart', 49.99, 'USD');
```

### 4. Navigation Micro-Conversions (Full-Page & SPA)

Track forward page navigation micro-conversions for ad-attributed visitors across full-page loads and SPA transitions (Livewire `wire:navigate`, Turbo, Inertia):

```blade
@googleAdsNavigationTracking
```

This directive only renders if the visitor has an active ad attribution (`GoogleAdsConversions::hasAttribution()`). It automatically posts navigation events (`Page Navigation: /path`) when visitors navigate forward.

### 5. Blade Directive for HTML Forms

Inject hidden click identifiers (`gclid`, `gbraid`, `wbraid`) into your contact or checkout forms:

```html
<form action="/contact" method="POST">
    @csrf
    @googleAdsClickInputs

    <input type="text" name="name" required>
    <button type="submit">Submit</button>
</form>
```

### 6. Built-In Route Configuration

The package automatically exposes a route for client-side conversions (`POST /api/google-ads/track-conversion`, named `google-ads-conversions.track`). You can customize the route in `config/google-ads-conversions.php`:

```php
'routes' => [
    'enabled' => true,
    'prefix' => 'api/google-ads',
    'middleware' => ['api'],
    'track_conversion_path' => 'track-conversion',
],
```

### 7. Testing with `fake()`

```php
use ElectricTomCat\GoogleAdsConversions\Facades\GoogleAdsConversions;

public function test_booking_records_conversion(): void
{
    GoogleAdsConversions::fake();

    $this->post('/book', ['email' => 'test@example.com']);

    GoogleAdsConversions::assertRecorded('Demo Booked', 250.00);
    GoogleAdsConversions::assertNotRecorded('Other Event');
}
```

---

## 💎 Need Multi-Platform Tracking?

For Meta CAPI (v20.0), TikTok Events API, LinkedIn Conversions API, Microsoft Advertising, and WooCommerce support, upgrade to **[OmniSignal Pro](https://omnisignal.dev)**.

---

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
