# Laravel Google Ads Conversions

[![Latest Version on Packagist](https://img.shields.io/packagist/v/electrictomcat/laravel-google-ads-conversions.svg?style=flat-square)](https://packagist.org/packages/electrictomcat/laravel-google-ads-conversions)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/electrictomcat/laravel-google-ads-conversions/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/electrictomcat/laravel-google-ads-conversions/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/electrictomcat/laravel-google-ads-conversions/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/electrictomcat/laravel-google-ads-conversions/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/electrictomcat/laravel-google-ads-conversions.svg?style=flat-square)](https://packagist.org/packages/electrictomcat/laravel-google-ads-conversions)

Drop-in offline conversion tracking for Laravel apps using Google Ads.

- Captures `gclid` (and `gbraid`/`wbraid`) from incoming traffic and persists it across the visitor's session
- Records conversion events with a one-line API
- Buffers in cache, syncs to your database, and uploads to Google Ads in batched, queued jobs
- Honors Google Ads' minimum reporting delay (default 6 hours)
- Bring-your-own model — implement a small contract or use the included `Lead` model out of the box
- Supports both call-site values (`record('Event', 100)`) and config-mapped defaults

Requires PHP 8.3+ and Laravel 11, 12, or 13.

## Installation

```bash
composer require electrictomcat/laravel-google-ads-conversions
```

Publish the config and the migration:

```bash
php artisan vendor:publish --tag="laravel-google-ads-conversions-config"
php artisan vendor:publish --tag="laravel-google-ads-conversions-migrations"
php artisan migrate
```

Add these to your `.env` (see [Google's OAuth setup](https://developers.google.com/google-ads/api/docs/oauth/cloud-project) for how to mint the refresh token):

```env
GOOGLE_ADS_DEVELOPER_TOKEN=
GOOGLE_ADS_CLIENT_ID=
GOOGLE_ADS_CLIENT_SECRET=
GOOGLE_ADS_REFRESH_TOKEN=
GOOGLE_ADS_CUSTOMER_ID=123-456-7890
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

## Usage

### Recording a conversion

From anywhere in your app — controllers, jobs, Livewire components, observers:

```php
use ElectricTomCat\GoogleAdsConversions\Facades\GoogleAdsConversions;

GoogleAdsConversions::record('Quote Form', 100);
```

The first argument is your internal event name. The second is an optional value. The full signature is:

```php
GoogleAdsConversions::record(
    eventName: 'Quote Form',
    value: 100.0,        // optional — falls back to per-event config
    currency: 'USD',     // optional — falls back to per-event then package default
    gclid: null,         // optional — manually override the GCLID lookup
);
```

### Reading the current visitor's GCLID

If you store the GCLID on your own records, ask the package for it directly:

```php
$submission = ContactSubmission::create([
    'name' => $request->name,
    'gclid' => GoogleAdsConversions::gclid(),   // string|null
]);
```

`gclid()` runs the same session → cookie → visitor-history lookup that `record()` uses, and is memoized for the lifetime of the request, so calling it several times in one request won't repeat the visitor-history database query. Call `GoogleAdsConversions::forgetGclid()` to clear the memo (useful in tests or long-running workers).

### Mapping events to Google Ads conversion actions

Edit `config/google-ads-conversions.php`. Each event entry is either a **string** (just the action name) or an **array** with optional value/currency defaults:

```php
'events' => [

    // Simple: event name → Google Ads action name (or full resource path)
    'Quote Form' => 'Quote Submission',
    'Phone Call' => 'customers/1234567890/conversionActions/111111',

    // With per-event default value/currency that the call site can still override
    'Demo Booked' => [
        'action'   => 'Demo Booked',
        'value'    => 250.00,
        'currency' => 'USD',
    ],

    // Catches any event named "Page Navigation: /anything" by prefix
    'Page Navigation' => 'Page Navigation',

],
```

The call site always wins — `record('Demo Booked', 999)` overrides the config's `250.00`. Omit the value at the call site to use the config default.

### Bring your own model

The package ships a `Lead` model and matching migration that work out of the box. If you'd rather use your own model — say, you already have a `Visitor` table and want to track conversions there — implement the `HasConversions` contract.

The fastest path: drop the `HasConversionsTrait` onto your existing model:

```php
use ElectricTomCat\GoogleAdsConversions\Contracts\HasConversions;
use ElectricTomCat\GoogleAdsConversions\Models\Concerns\HasConversionsTrait;
use Illuminate\Database\Eloquent\Casts\AsCollection;

class Visitor extends Model implements HasConversions
{
    use HasConversionsTrait;

    protected $fillable = ['gclid', 'visitor_id', 'conversions', /* ... */];

    protected $casts = [
        'conversions' => AsCollection::class,
    ];
}
```

Make sure your table has at minimum these columns:
- `gclid` (string, unique, indexed)
- `visitor_id` (uuid, nullable)
- `conversions` (json, nullable)

Then point the package at it:

```php
// config/google-ads-conversions.php
'model' => \App\Models\Visitor::class,
```

For full control, implement `HasConversions` from scratch — see `src/Contracts/HasConversions.php` for the contract.

## How the pipeline works

1. **Middleware** (`CaptureGclid`) — runs on the landing request, extracts `gclid` from the URL, sets cookies + session, buffers a stub lead record in cache.
2. **Recording** (`GoogleAdsConversions::record()`) — pushes a conversion entry into a per-gclid cache bucket. Cheap. Fire from anywhere, including HTTP requests where the user has no `gclid` on the URL but does have one in their session/cookie.
3. **Sync** (`syncToDatabase()`) — flushes the cache buffer into the configured model's table. Runs as the first half of the queued job.
4. **Upload** (`uploadPendingConversions()`) — finds every pending conversion older than the delay window and ships eligible batches to Google Ads via `UploadClickConversions`. Marks each shipped conversion as `'uploaded'` with a timestamp.

## Testing

```bash
composer test
```

The suite uses Pest 4 + Orchestra Testbench against an in-memory SQLite database.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Contributing

Issues and pull requests welcome.

## Credits

- [Tom Michael](https://github.com/electrictomcat)
- Built on top of the [Spatie package skeleton](https://github.com/spatie/package-skeleton-laravel) and [`googleads/google-ads-php`](https://github.com/googleads/google-ads-php)

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
