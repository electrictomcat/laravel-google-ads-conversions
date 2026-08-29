<?php

use ElectricTomCat\GoogleAdsConversions\Models\Lead;

return [

    /*
    |--------------------------------------------------------------------------
    | Google Ads API Credentials
    |--------------------------------------------------------------------------
    |
    | Generate the OAuth client and refresh token using Google's offline
    | conversion authentication flow:
    | https://developers.google.com/google-ads/api/docs/oauth/cloud-project
    |
    | If your OAuth refresh token belongs to a Manager Account (MCC) that
    | manages client sub-accounts, set `login_customer_id` to your MCC ID.
    |
    */

    'developer_token' => env('GOOGLE_ADS_DEVELOPER_TOKEN'),
    'client_id' => env('GOOGLE_ADS_CLIENT_ID'),
    'client_secret' => env('GOOGLE_ADS_CLIENT_SECRET'),
    'refresh_token' => env('GOOGLE_ADS_REFRESH_TOKEN'),
    'customer_id' => str_replace('-', '', (string) env('GOOGLE_ADS_CUSTOMER_ID', '')),
    'login_customer_id' => env('GOOGLE_ADS_LOGIN_CUSTOMER_ID')
        ? str_replace('-', '', (string) env('GOOGLE_ADS_LOGIN_CUSTOMER_ID'))
        : null,

    /*
    |--------------------------------------------------------------------------
    | Dry-Run / Validation Mode
    |--------------------------------------------------------------------------
    |
    | When enabled (validate_only = true), API requests are validated by Google
    | Ads without creating real conversion records. Useful in staging or tests.
    |
    */

    'validate_only' => (bool) env('GOOGLE_ADS_VALIDATE_ONLY', false),

    /*
    |--------------------------------------------------------------------------
    | Batch Size
    |--------------------------------------------------------------------------
    |
    | Maximum number of conversions uploaded per Google Ads API request.
    | Google Ads API supports up to 2,000 conversions per batch.
    |
    */

    'batch_size' => (int) env('GOOGLE_ADS_BATCH_SIZE', 2000),

    /*
    |--------------------------------------------------------------------------
    | Lead model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model used to persist conversions. Ships with a sensible
    | default; replace with your own model if you'd rather bring your own
    | (it must implement ElectricTomCat\GoogleAdsConversions\Contracts\HasConversions
    | -- the easiest way is to `use HasConversionsTrait`).
    |
    */

    'model' => Lead::class,

    /*
    |--------------------------------------------------------------------------
    | Table name
    |--------------------------------------------------------------------------
    |
    | The table the default Lead model uses. Override only if you've published
    | the migration and renamed the table; ignored entirely if you point
    | `model` at a class that sets its own $table.
    |
    */

    'table' => 'leads',

    /*
    |--------------------------------------------------------------------------
    | Upload delay
    |--------------------------------------------------------------------------
    |
    | Google Ads requires offline conversions to be at least a few hours old
    | before they show up in reporting. Conversions younger than this are
    | held back and uploaded on a later run.
    |
    */

    'upload_delay_hours' => env('GOOGLE_ADS_UPLOAD_DELAY_HOURS', 6),

    /*
    |--------------------------------------------------------------------------
    | Default currency
    |--------------------------------------------------------------------------
    |
    | Used when neither the call site nor the per-event config specifies one.
    | Must be a valid ISO 4217 3-letter currency code (e.g. 'USD', 'EUR').
    |
    */

    'default_currency' => env('GOOGLE_ADS_DEFAULT_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Unmapped Events Fallback
    |--------------------------------------------------------------------------
    |
    | When true, if an event name passed to record() is not mapped in the `events`
    | array below, the event name itself will be used as the Google Ads conversion
    | action name. If false, unmapped events are skipped.
    |
    */

    'allow_unmapped_events' => (bool) env('GOOGLE_ADS_ALLOW_UNMAPPED_EVENTS', true),

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    |
    | Map your internal event names to Google Ads conversion actions. Each
    | event may be either:
    |
    |   - A string -- the conversion action name (or full resource path
    |     "customers/{id}/conversionActions/{actionId}")
    |
    |   - An array with these keys:
    |       'action'   => string  (required) the action name or resource path
    |       'value'    => float   (optional) default value if call site omits one
    |       'currency' => string  (optional) default currency if call site omits one
    |
    | Both the call site and this config can supply a value/currency. The
    | call site always wins; this config is the fallback. If neither
    | provides a value, the conversion is uploaded without a value.
    |
    | Event names beginning with "Page Navigation: " match the "Page
    | Navigation" event by prefix. Use this for per-URL micro-conversions
    | that all roll up into a single Google Ads conversion action.
    |
    */

    'events' => [

        // 'Quote Form'    => 'Quote Submission',
        // 'Phone Call'    => env('GOOGLE_ADS_PHONE_ACTION', 'Call Clicks'),
        // 'Demo Booked'   => [
        //     'action'   => 'Demo Booked',
        //     'value'    => 250.00,
        //     'currency' => 'USD',
        // ],
        // 'Page Navigation' => 'Page Navigation', // catches "Page Navigation: /path"

    ],

    /*
    |--------------------------------------------------------------------------
    | European & UK Privacy Controls (GDPR / ePrivacy)
    |--------------------------------------------------------------------------
    |
    | Configures privacy guards for cookie dropping and data retention:
    |
    | - `cookie_consent`:
    |     'auto'   => Check incoming request cookies for common CMP consent
    |                 (Cookiebot, OneTrust, Spatie, etc.) before dropping cookies.
    |     'always' => Always queue persistent cookies on landing (standard US mode).
    |     'never'  => Never queue persistent marketing cookies (session-only).
    |
    | - `retention_days`:
    |     Number of days to keep lead records in the database. Eloquent's
    |     prunable command (php artisan model:prune) will clean up older leads.
    |
    */

    'privacy' => [
        'cookie_consent' => env('GOOGLE_ADS_COOKIE_CONSENT', 'always'),
        'consent_cookie_names' => [
            'cookie_consent_marketing',
            'cookie_consent',
            'CookieConsent',
            'laravel_cookie_consent',
        ],
        'retention_days' => (int) env('GOOGLE_ADS_RETENTION_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Consent Mode v2 Signals
    |--------------------------------------------------------------------------
    |
    | Consent signals attached to uploaded click conversions for Google Ads API.
    | Set to 'GRANTED', 'DENIED', or null (omits the field).
    |
    */

    'consent' => [
        'ad_user_data' => env('GOOGLE_ADS_CONSENT_AD_USER_DATA', null),
        'ad_personalization' => env('GOOGLE_ADS_CONSENT_AD_PERSONALIZATION', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Enhanced Conversions for Leads (First-Party User Data)
    |--------------------------------------------------------------------------
    |
    | When enabled, optional first-party data (email, phone) passed to record()
    | will be SHA-256 hashed and uploaded alongside the click conversion to
    | improve match rates. Disabled by default for data minimization.
    |
    */

    'enhanced_conversions' => [
        'enabled' => (bool) env('GOOGLE_ADS_ENHANCED_CONVERSIONS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cookies and session
    |--------------------------------------------------------------------------
    |
    | Names used by the CaptureGclid middleware.
    |
    */

    'cookies' => [
        'gclid' => 'google_ads_gclid',
        'gbraid' => 'google_ads_gbraid',
        'wbraid' => 'google_ads_wbraid',
        'visitor_id' => 'google_ads_visitor_id',
        'lifetime_minutes' => 60 * 24 * 30, // 30 days
        'domain' => null, // null = current host; set to ".example.com" for cross-subdomain
        'secure' => env('SESSION_SECURE_COOKIE', null),
        'http_only' => false,
        'same_site' => 'Lax',
    ],

    'session_key' => 'google_ads_gclid',
    'session_keys' => [
        'gclid' => 'google_ads_gclid',
        'gbraid' => 'google_ads_gbraid',
        'wbraid' => 'google_ads_wbraid',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracking data
    |--------------------------------------------------------------------------
    |
    | Query parameters the middleware harvests from the landing URL and
    | tries to persist on the model (via fillTrackingData). Only columns
    | listed in your model's $fillable will actually be written.
    |
    */

    'tracked_query_parameters' => [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'gad_source',
        'gad_campaignid',
    ],

];
