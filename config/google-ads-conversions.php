<?php

use ElectricTomCat\GoogleAdsConversions\Models\Lead;

return [

    /*
    |--------------------------------------------------------------------------
    | Google Ads API Credentials
    |--------------------------------------------------------------------------
    |
    | All four are required to upload conversions. Generate the OAuth client
    | and refresh token using Google's offline conversion authentication flow:
    | https://developers.google.com/google-ads/api/docs/oauth/cloud-project
    |
    */

    'developer_token' => env('GOOGLE_ADS_DEVELOPER_TOKEN'),
    'client_id' => env('GOOGLE_ADS_CLIENT_ID'),
    'client_secret' => env('GOOGLE_ADS_CLIENT_SECRET'),
    'refresh_token' => env('GOOGLE_ADS_REFRESH_TOKEN'),
    'customer_id' => str_replace('-', '', (string) env('GOOGLE_ADS_CUSTOMER_ID', '')),

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
    |
    */

    'default_currency' => env('GOOGLE_ADS_DEFAULT_CURRENCY', 'USD'),

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
    | Cookies and session
    |--------------------------------------------------------------------------
    |
    | Names used by the CaptureGclid middleware. The default cookie names
    | are namespaced to avoid collisions with anything else you might track.
    |
    */

    'cookies' => [
        'gclid' => 'google_ads_gclid',
        'visitor_id' => 'google_ads_visitor_id',
        'lifetime_minutes' => 60 * 24 * 30, // 30 days
        'domain' => null, // null = current host; set to ".example.com" for cross-subdomain
        'secure' => true,
        'http_only' => false,
        'same_site' => 'Lax',
    ],

    'session_key' => 'google_ads_gclid',

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
