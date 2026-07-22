# Changelog

All notable changes to `laravel-google-ads-conversions` will be documented in this file.

## v0.2.0 Memoized gclid() accessor - 2026-07-22

### Added
- `GoogleAdsConversions::gclid(): ?string` — public accessor for the current visitor's GCLID, using the same session → cookie → visitor-history resolution as `record()`. Memoized per request so repeated call sites don't re-run the visitor-history database lookup.
- `GoogleAdsConversions::forgetGclid()` — clears the memo (tests, long-running workers).
- `@method static` docblocks on the facade.

**Full Changelog**: https://github.com/electrictomcat/laravel-google-ads-conversions/compare/v0.1.0...v0.2.0

## v0.1.0 Port Code from Source Project - 2026-05-03

**Full Changelog**: https://github.com/electrictomcat/laravel-google-ads-conversions/commits/v0.1.0
