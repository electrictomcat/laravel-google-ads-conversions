# Changelog

All notable changes to `laravel-google-ads-conversions` will be documented in this file.

## v2.0.0 - 2026-08-29

### Added
- **Application Testing Fake (`GoogleAdsConversions::fake()`)**: Full testing fake with assertion helpers (`assertRecorded()`, `assertNotRecorded()`, `assertNothingRecorded()`, `assertRecordedCount()`) for unit & feature testing in host Laravel applications.
- **Blade Form Directives**: `@googleAdsClickInputs` and `@googleAdsGclid` for easily injecting hidden input fields into HTML/Blade contact forms.
- **GBRAID & WBRAID Support**: Native capture, resolution, and API upload mapping via `setGbraid()` and `setWbraid()` for iOS ATT app and web campaigns.
- **GDPR & ePrivacy Cookie Consent Gating**: Prior-consent gate (`'cookie_consent' => 'auto'`) that checks common CMP consent cookies (Cookiebot, OneTrust, Spatie, etc.) or custom callbacks before setting persistent 30-day cookies.
- **Google Consent Mode v2**: Send `ad_user_data` and `ad_personalization` signals (`GRANTED` / `DENIED`) attached to Google Ads API click conversion payloads.
- **Enhanced Conversions for Leads (Optional & Configurable)**: Support for passing first-party user data (`email`, `phone`) that is automatically normalized and SHA-256 hashed. Disabled by default for strict data minimization.
- **GDPR Data Retention & Erasure**: Automated 90-day pruning via Laravel `Prunable` on `Lead` model, and `GoogleAdsConversions::forgetVisitor($visitorId)` for Right to Erasure requests.
- **Artisan CLI Commands**:
  - `php artisan google-ads:upload` (with `--dry-run`, `--force`, `--delay=`)
  - `php artisan google-ads:sync`
  - `php artisan google-ads:test-connection`
- **Laravel Domain Events**: `ConversionRecorded`, `ConversionsSynced`, `ConversionsUploaded`, `ConversionUploadFailed`.
- **Manager Account (MCC) Authentication**: Support for `login_customer_id`.
- **Order ID / Transaction Deduplication**: Pass optional `orderId` and custom `conversionDateTime` to `record()`.
- **Unmapped Events Fallback**: Option to allow direct conversion action names at call site without requiring pre-registration in config.

### Fixed
- Fixed bug where `gbraid` and `wbraid` parameters were uploaded into Google Ads' `gclid` field causing API rejections.
- Fixed partial failure handling so failed conversions are not erroneously stamped as uploaded.
- Fixed cache poisoning in conversion action resource name resolution on transient network errors.
- Fixed potential out-of-memory errors by chunking lead queries in chunks of 100.
- Fixed batching to upload across multiple leads in batches of up to 2,000 conversions per request.
- Fixed `fillTrackingData()` when custom Eloquent models use `$guarded = []`.

## v0.2.0 Memoized gclid() accessor - 2026-07-22

### Added
- `GoogleAdsConversions::gclid(): ?string` — public accessor for the current visitor's GCLID, using the same session → cookie → visitor-history resolution as `record()`.
- `GoogleAdsConversions::forgetGclid()` — clears the memo.
- `@method static` docblocks on the facade.

## v0.1.0 Port Code from Source Project - 2026-05-03

- Initial release.
