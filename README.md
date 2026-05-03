# Laravel Google Ads Conversions

Drop-in offline conversion tracking for Laravel apps using Google Ads.

Captures `gclid` from incoming traffic, records conversion events with a one-line API, and uploads them to Google Ads in batched, queued jobs that respect Google's 6-hour reporting delay.

> Status: pre-release. Initial extraction in progress from a production Laravel app. Not yet on Packagist.

## Planned features

- `CaptureGclid` middleware — extracts `gclid`, `gbraid`, `wbraid`, and UTM parameters from incoming requests and persists them across the visitor session
- Simple recording API — `Conversions::record('Quote Form', 100)` from anywhere in your app
- Queued batch upload — scheduled job uploads pending conversions to Google Ads on a configurable interval
- Built-in 6-hour delay handling per Google Ads' reporting requirements
- Configurable event-name → conversion-action mapping
- Publishable config, migrations, and middleware

## License

MIT
