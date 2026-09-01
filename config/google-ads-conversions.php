<?php

use ElectricTomCat\GoogleAdsConversions\Models\Lead;

return [

    /*
    |--------------------------------------------------------------------------
    | Google Ads API Credentials
    |--------------------------------------------------------------------------
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
    | Model Configuration
    |--------------------------------------------------------------------------
    |
    | The Eloquent model used to persist leads and their offline conversions.
    | To use your own model, implement the HasConversions contract and use
    | the HasConversionsTrait.
    |
    */

    'model' => Lead::class,

    /*
    |--------------------------------------------------------------------------
    | Buffer & Upload Settings
    |--------------------------------------------------------------------------
    */

    'buffer_ttl_days' => 2,
    'upload_delay_hours' => 6,
    'batch_size' => 2000,
    'retention_days' => 90,

    /*
    |--------------------------------------------------------------------------
    | Session & Cookie Settings
    |--------------------------------------------------------------------------
    */

    'session_keys' => [
        'gclid' => 'google_ads_gclid',
        'gbraid' => 'google_ads_gbraid',
        'wbraid' => 'google_ads_wbraid',
    ],

    'cookies' => [
        'gclid' => 'google_ads_gclid',
        'gbraid' => 'google_ads_gbraid',
        'wbraid' => 'google_ads_wbraid',
        'visitor_id' => 'google_ads_visitor_id',
        'lifetime_minutes' => 60 * 24 * 90, // 90 days
        'domain' => env('SESSION_DOMAIN'),
        'secure' => env('SESSION_SECURE_COOKIE', false),
        'same_site' => 'lax',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracked Query Parameters
    |--------------------------------------------------------------------------
    */

    'tracked_query_parameters' => [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'gad_source',
        'gad_campaignid',
    ],

    /*
    |--------------------------------------------------------------------------
    | Enhanced Conversions (First-Party Data Hashing)
    |--------------------------------------------------------------------------
    */

    'enhanced_conversions' => [
        'enabled' => env('GOOGLE_ADS_ENHANCED_CONVERSIONS_ENABLED', false),
        'default_country_calling_code' => env('GOOGLE_ADS_DEFAULT_COUNTRY_CALLING_CODE', '1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Consent Mode v2
    |--------------------------------------------------------------------------
    */

    'consent' => [
        'cookie_name' => 'cookie_consent',
        'ad_user_data' => 'GRANTED',
        'ad_personalization' => 'GRANTED',
    ],

    /*
    |--------------------------------------------------------------------------
    | Conversion Action Event Mapping
    |--------------------------------------------------------------------------
    */

    'events' => [
        // 'Contact Form' => 'Lead Form Submitted',
        // 'Purchase' => 'Ecommerce Purchase',
    ],

    'default_value' => 0.0,
    'default_currency' => 'USD',
    'allow_unmapped_events' => true,
];
