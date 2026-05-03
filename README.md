# Laravel Google Ads Conversions

[![Latest Version on Packagist](https://img.shields.io/packagist/v/electrictomcat/laravel-google-ads-conversions.svg?style=flat-square)](https://packagist.org/packages/electrictomcat/laravel-google-ads-conversions)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/electrictomcat/laravel-google-ads-conversions/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/electrictomcat/laravel-google-ads-conversions/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/electrictomcat/laravel-google-ads-conversions/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/electrictomcat/laravel-google-ads-conversions/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/electrictomcat/laravel-google-ads-conversions.svg?style=flat-square)](https://packagist.org/packages/electrictomcat/laravel-google-ads-conversions)

Drop-in offline conversion tracking for Laravel apps using Google Ads.

Captures `gclid` from incoming traffic, records conversion events with a one-line API, and uploads them to Google Ads in batched, queued jobs that respect Google's 6-hour reporting delay.

> **Status: pre-release.** Initial extraction in progress from a production Laravel app. Not yet on Packagist. The API and configuration shape below describe the planned interface and may change before 1.0.

## Planned features

- `CaptureGclid` middleware — extracts `gclid`, `gbraid`, `wbraid`, and UTM parameters from incoming requests and persists them across the visitor session
- Simple recording API — `GoogleAdsConversions::record('Quote Form', 100)` from anywhere in your app
- Queued batch upload — scheduled job uploads pending conversions to Google Ads on a configurable interval
- Built-in 6-hour delay handling per Google Ads' reporting requirements
- Configurable event-name → conversion-action mapping
- Publishable config and migrations

## Installation

> Not yet published to Packagist. Once released:

```bash
composer require electrictomcat/laravel-google-ads-conversions
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="laravel-google-ads-conversions-migrations"
php artisan migrate
```

Publish the config file:

```bash
php artisan vendor:publish --tag="laravel-google-ads-conversions-config"
```

## Usage

```php
use ElectricTomCat\GoogleAdsConversions\Facades\GoogleAdsConversions;

GoogleAdsConversions::record('Quote Form', 100);
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Issues and pull requests welcome.

## Credits

- [Tom Michael](https://github.com/electrictomcat)
- Built on top of the [Spatie package skeleton](https://github.com/spatie/package-skeleton-laravel) and [`googleads/google-ads-php`](https://github.com/googleads/google-ads-php)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
