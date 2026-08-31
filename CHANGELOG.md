# Changelog

All notable changes to `laravel-google-ads-conversions` will be documented in this file.

## v2.0.0 - Unreleased

The first release of the multi-channel engine, and a set of fixes for ways the
previous behaviour could silently lose conversions.

### Fixed — conversion loss

These are the reasons to upgrade. Each one lost data without reporting anything.

- **`--dry-run` no longer destroys the pending queue.** A validate-only run marked
  every conversion `uploaded`, so the documented safe way to test your setup was
  the one action guaranteeing those conversions were never really sent. Dry runs
  now leave stored state untouched.
- **Conversions Google rejects are retried instead of retired.** With
  `partial_failure` enabled, rows Google refused were still marked `uploaded`.
  The per-index errors are now decoded: rejected rows are marked `failed` with
  the reason attached, and picked up by the next run. An undecodable failure is
  treated as the whole batch failing, so nothing is retired on an assumption.
- **The cache buffer survives a failed database write.** `syncToDatabase()` used
  `Cache::pull()` — a read that deletes — before persisting, so a deadlock or
  outage during the save destroyed the conversion permanently. Buffers are now
  discarded only after the row is written, and one failing lead no longer aborts
  the sweep.
- **Concurrent requests no longer drop click identifiers.** The dirty set was a
  single shared array updated with a non-atomic read-modify-write, so two
  simultaneous requests silently erased each other's entry. Mutations are now
  lock-guarded and sharded across buckets. A cache store implementing
  `LockProvider` (redis, memcached, database, array) is recommended; the file
  driver has no lock primitive and falls back to an unguarded write.
- **Retention no longer deletes undelivered conversions.** `Lead::prunable()`
  filtered on age alone, so a lead whose upload had been failing was hard-deleted
  with its conversion inside. Rows holding a pending conversion are held back;
  set `privacy.prune_pending` to restore the old behaviour.
- **Right to erasure actually erases.** `forgetVisitor()` deleted rows but left
  the cache buffer, so the next sync recreated the record. It now purges the
  buffered lead data, conversions and dirty-set entries first.

### Fixed — availability and correctness

- **A crafted URL no longer 500s every page.** `?gclid[]=x` reached a
  string-typed parameter and threw a `TypeError` on every request of any app
  running `CaptureGclid` on its web group. Click identifiers and tracked query
  parameters are now type-checked and length-capped.
- **The dashboard is no longer public by default.** `/ad-conversions` was
  registered on the `web` group with no authentication and no mention in the
  README, so installing the package published lead counts, click identifiers and
  attributed revenue. It is now disabled by default and defaults to
  `['web', 'auth']` when switched on.
- **The Google driver honours the conversions it is given.** `upload()` ignored
  its argument and swept the entire database, so `fanOut()` on a single payload
  triggered a full queue flush. Use `uploadPending()` for the sweep.
- **Microsoft Advertising works at all.** The driver posted JSON to a
  CustomerManagement path that does not exist and omitted the required
  `CustomerAccountId` header. It now uses Campaign Management v13
  `OfflineConversions/Apply`, reports `PartialErrors` per item, and needs the new
  `microsoft.account_id` config key.
- **LinkedIn sends the conversion value.** It was sent as `totalBudget`, a
  campaign field this endpoint ignores; it is now `conversionValue` with a string
  `amount`. The API version is configurable via `linkedin.version` and an expired
  version (HTTP 426) is reported as such instead of failing opaquely.
- **`ad-conversions:test` tests the connection.** For four of five channels it
  previously checked that config values were non-empty and reported success, so a
  revoked token looked identical to a working setup. Every channel now makes a
  real authenticated call.
- **Identifier hashing matches what the platforms expect.** Gmail dots and
  `+suffixes` are collapsed. A phone number that cannot be resolved to E.164 is
  dropped rather than hashed under a guessed country — the old code turned
  `555-123-4567` into `+5551234567`, a well-formed hash matching nobody. Set
  `privacy.default_calling_code` for national-format numbers.
- Backslashes are escaped in GAQL conversion-action lookups, not just quotes.
- `UploadPendingConversions` is `ShouldBeUnique` with `tries`, `backoff` and
  `timeout`; two overlapping runs previously double-uploaded.
- The Google Ads client is memoized rather than rebuilt for every batch and
  action lookup.
- An unrecognised consent value now maps to `DENIED` rather than `UNSPECIFIED`.
  Set `consent.unknown_maps_to` to restore the old behaviour.
- `record()` returns `bool`, and can record against hashed identifiers alone when
  no click identifier is available and enhanced conversions are enabled.
- Ungrouped `orWhere` in the lead lookup no longer escapes global scopes such as
  `SoftDeletes`.
- One query resolves all three click identifiers for a visitor; the three
  accessors previously issued one each on every request.

### Added

- **Multi-channel drivers**: Google Ads, Meta CAPI, Microsoft Advertising,
  LinkedIn and TikTok, behind `ConversionManager` with `fanOut()` and
  `getConfiguredDrivers()`.
- `ConversionPayload` DTO for channel-agnostic conversions.
- **GBRAID & WBRAID support** for iOS ATT app and web campaigns.
- **GDPR & ePrivacy cookie consent gating** with a prior-consent strategy and a
  custom resolver hook.
- **Google Consent Mode v2** signals on uploaded conversions.
- **Enhanced Conversions for Leads**, off by default.
- **Retention and erasure**: `Prunable` on the lead model, plus
  `forgetVisitor()`.
- **Testing fake** — `GoogleAdsConversions::fake()` with assertion helpers.
- **Blade directives** `@googleAdsClickInputs` and `@googleAdsGclid`.
- **Domain events**: `ConversionRecorded`, `ConversionsSynced`,
  `ConversionsUploaded`, `ConversionUploadFailed`.
- **Manager account (MCC) authentication** via `login_customer_id`.
- `pendingClickIds()` for inspecting what is waiting to be flushed.

### Changed

- Commands are now `ad-conversions:install|upload|sync|test`. The previous
  `google-ads:*` names remain as aliases.
- `minimum-stability` is `stable`.
- The package no longer ships marketing views. `resources/views/landing.blade.php`
  and `docs.blade.php` have been removed; only the dashboard view remains.
- PHPStan runs at level 6 with an empty baseline, and on pull requests as well as
  pushes.

## v0.2.0 - 2026-05-03

Initial Google Ads offline conversion support.

## v0.1.0 - 2026-05-03

First tagged release.
