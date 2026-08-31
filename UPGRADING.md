# Upgrading

## From v0.x to v2.0

Most of v2 is additive, but a few things changed behaviour deliberately — in
several cases because the old behaviour lost conversions. Work through the
checklist below; nothing here takes long.

### 1. The dashboard is now off by default

`/ad-conversions` used to be registered publicly on the `web` group. If you were
relying on it, switch it on explicitly:

```env
AD_CONVERSIONS_DASHBOARD_ENABLED=true
```

It now defaults to `['web', 'auth']` middleware. If your app has no `auth` route
to redirect to, override `dashboard.middleware` in the published config — for
example `['web', 'auth.basic']`.

> If you did **not** know this route existed, check whether it has been publicly
> reachable on your site.

### 2. `--dry-run` no longer marks conversions as uploaded

Previously a dry run retired everything it validated, so those conversions were
never really sent. If you have run `--dry-run` against production, look for
conversions marked `uploaded` with a `validate_only` key and reset them to
`pending` — they were never delivered:

```php
use App\Models\Lead; // or your configured model

Lead::whereNotNull('conversions')->chunkById(100, function ($leads) {
    foreach ($leads as $lead) {
        $conversions = $lead->getConversions()->map(function (array $entry) {
            if (! empty($entry['validate_only'])) {
                $entry['status'] = 'pending';
                unset($entry['validate_only'], $entry['uploaded_at']);
            }

            return $entry;
        });

        $lead->setConversions($conversions);
        $lead->save();
    }
});
```

### 3. Use a cache store that supports locks

The conversion buffer is mutated under a lock now. Redis, Memcached, DynamoDB,
the database store and the array store all provide one. The **file** driver does
not: it still works, but concurrent requests can drop a click identifier, which
is the bug this change exists to fix. If `CACHE_STORE=file`, move to `database`
at minimum.

### 4. Phone numbers need a country code

A number stored without one used to be hashed as `+` plus its digits, producing
a well-formed hash that matched nobody. Such numbers are now dropped and logged.
If you store national-format numbers, set the country they belong to:

```env
GOOGLE_ADS_DEFAULT_CALLING_CODE=1   # 1 = US/Canada, 44 = UK, 61 = AU
```

Storing numbers in E.164 (`+15551234567`) is better still and needs no setting.

### 5. Microsoft Advertising needs an extra credential

The driver now targets the correct endpoint and requires the ad account ID
alongside the manager ID:

```env
MICROSOFT_ADS_ACCOUNT_ID=
```

The channel previously posted to a URL that does not exist, so nothing it
reported was ever delivered. Run `php artisan ad-conversions:test` to confirm.

### 6. Commands were renamed

| Old | New |
| :-- | :-- |
| `google-ads:upload` | `ad-conversions:upload` |
| `google-ads:sync` | `ad-conversions:sync` |
| `google-ads:test-connection` | `ad-conversions:test` |

The old names still work as aliases, so scheduled tasks keep running. Update
them when convenient.

### 7. `record()` now returns a bool

It returns `false` when a conversion could not be attributed to anything. The
signature is otherwise unchanged, so existing calls that ignore the return value
need no edit — but this is the cheapest way to find out you are recording
conversions that will never be delivered.

```php
if (! GoogleAdsConversions::record('Quote Form', 100.0)) {
    Log::warning('Lead had no click identifier to attribute.');
}
```

### 8. Unrecognised consent values now fail closed

A consent string matching neither the granted nor the denied vocabulary maps to
`DENIED` instead of `UNSPECIFIED`. To restore the previous behaviour:

```env
GOOGLE_ADS_CONSENT_UNKNOWN=unspecified
```

### 9. Republish the config

Several keys were added: `microsoft.account_id`, `linkedin.version`,
`privacy.prune_pending`, `privacy.default_calling_code`,
`consent.unknown_maps_to`, `dashboard.path`.

```bash
php artisan vendor:publish --tag="laravel-google-ads-conversions-config" --force
```

Diff the result against your existing file before committing.

### 10. The dirty-set cache key changed shape

`GoogleAdsConversions::DIRTY_SET_KEY` is no longer written to — the set is
sharded across `DIRTY_BUCKET_PREFIX` buckets. The old key is still drained on
sync, so nothing buffered by v0.x is stranded during the upgrade. If you read
that constant directly, use `pendingClickIds()` instead.

### 11. Marketing views were removed

`resources/views/landing.blade.php` and `docs.blade.php` no longer ship with the
package. If you were rendering `google-ads-conversions::landing`, copy the file
from v0.2.0 into your own application's views.
