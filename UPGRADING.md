# Upgrade Guide

## Upgrading to v2.0 (GBRAID, WBRAID, GDPR & Consent Mode v2 Support)

Version 2.0 introduces native support for iOS app/web tracking (`gbraid`, `wbraid`), European/UK GDPR and ePrivacy consent controls, Google Consent Mode v2, Enhanced Conversions (hashed user identifiers), and batch performance improvements.

---

### 1. Database Schema Update

If you are using the default `leads` table, add the new `gbraid` and `wbraid` columns:

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('google-ads-conversions.table', 'leads'), function (Blueprint $table) {
            $table->string('gbraid')->nullable()->index()->after('gclid');
            $table->string('wbraid')->nullable()->index()->after('gbraid');
            // gclid can now be nullable if a visitor arrives via gbraid or wbraid
            $table->string('gclid')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table(config('google-ads-conversions.table', 'leads'), function (Blueprint $table) {
            $table->dropColumn(['gbraid', 'wbraid']);
        });
    }
};
```

---

### 2. If You Use a Custom Model (Bring Your Own Model)

#### If you use `HasConversionsTrait`:
No breaking code changes are required! The trait already implements the new contract methods (`getGbraid()`, `setGbraid()`, `getWbraid()`, `setWbraid()`). 

Simply ensure:
1. Your database table has nullable `gbraid` and `wbraid` columns.
2. If your model uses `$fillable`, add `'gbraid'` and `'wbraid'`:
   ```php
   protected $fillable = [
       'gclid',
       'gbraid',
       'wbraid',
       'visitor_id',
       'conversions',
       // ...
   ];
   ```

#### If you implement `HasConversions` manually:
Add the four new methods to your model:
```php
public function getGbraid(): ?string
{
    return $this->gbraid;
}

public function setGbraid(?string $gbraid): void
{
    $this->gbraid = $gbraid;
}

public function getWbraid(): ?string
{
    return $this->wbraid;
}

public function setWbraid(?string $wbraid): void
{
    $this->wbraid = $wbraid;
}
```

---

### 3. Configuration Updates

Run:
```bash
php artisan vendor:publish --tag="laravel-google-ads-conversions-config" --force
```
*(Or review the new `privacy`, `consent`, `enhanced_conversions`, and `login_customer_id` sections in `config/google-ads-conversions.php`).*

---

### 4. GDPR & Cookie Consent Gating

By default, the package now supports cookie consent gating for GDPR & ePrivacy compliance in the EU and UK:
- If you want the previous behavior where persistent cookies are always set immediately, ensure `'cookie_consent' => 'always'` in config (the default).
- For automatic cookie consent gating with tools like Cookiebot, OneTrust, or Spatie Cookie Consent, set:
  ```php
  'privacy' => [
      'cookie_consent' => 'auto',
  ],
  ```
