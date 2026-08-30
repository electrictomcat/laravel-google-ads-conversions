<?php

use ElectricTomCat\GoogleAdsConversions\Models\Lead;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Channel & Enabled Channels
    |--------------------------------------------------------------------------
    |
    | Supported channels: 'google', 'meta', 'microsoft', 'linkedin', 'tiktok'
    |
    */

    'default_channel' => env('AD_CONVERSIONS_DEFAULT_CHANNEL', 'google'),

    'enabled_channels' => [
        'google',
        'meta',
        'microsoft',
        'linkedin',
        'tiktok',
    ],

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
    | Meta (Facebook / Instagram) Conversions API (CAPI)
    |--------------------------------------------------------------------------
    */

    'meta' => [
        'pixel_id' => env('META_PIXEL_ID'),
        'access_token' => env('META_ACCESS_TOKEN'),
        'test_event_code' => env('META_TEST_EVENT_CODE'),
        'api_version' => env('META_API_VERSION', 'v20.0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Microsoft (Bing) Advertising Offline Conversions
    |--------------------------------------------------------------------------
    */

    'microsoft' => [
        'developer_token' => env('MICROSOFT_ADS_DEVELOPER_TOKEN'),
        'customer_id' => env('MICROSOFT_ADS_CUSTOMER_ID'),
        'access_token' => env('MICROSOFT_ADS_ACCESS_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | LinkedIn Conversions API
    |--------------------------------------------------------------------------
    */

    'linkedin' => [
        'access_token' => env('LINKEDIN_ACCESS_TOKEN'),
        'conversion_rule_id' => env('LINKEDIN_CONVERSION_RULE_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | TikTok Events API (Server-to-Server)
    |--------------------------------------------------------------------------
    */

    'tiktok' => [
        'access_token' => env('TIKTOK_ACCESS_TOKEN'),
        'pixel_code' => env('TIKTOK_PIXEL_CODE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | In-App Analytics & Reporting Dashboard
    |--------------------------------------------------------------------------
    |
    | Enables an embedded conversion reporting dashboard in your application.
    |
    */

    'dashboard' => [
        'enabled' => (bool) env('AD_CONVERSIONS_DASHBOARD_ENABLED', true),
        'path' => 'ad-conversions',
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dry-Run / Validation Mode
    |--------------------------------------------------------------------------
    */

    'validate_only' => (bool) env('GOOGLE_ADS_VALIDATE_ONLY', false),

    /*
    |--------------------------------------------------------------------------
    | Batch Size
    |--------------------------------------------------------------------------
    */

    'batch_size' => (int) env('GOOGLE_ADS_BATCH_SIZE', 2000),

    /*
    |--------------------------------------------------------------------------
    | Lead model
    |--------------------------------------------------------------------------
    */

    'model' => Lead::class,

    /*
    |--------------------------------------------------------------------------
    | Table name
    |--------------------------------------------------------------------------
    */

    'table' => 'leads',

    /*
    |--------------------------------------------------------------------------
    | Upload delay
    |--------------------------------------------------------------------------
    */

    'upload_delay_hours' => env('GOOGLE_ADS_UPLOAD_DELAY_HOURS', 6),

    /*
    |--------------------------------------------------------------------------
    | Default currency
    |--------------------------------------------------------------------------
    */

    'default_currency' => env('GOOGLE_ADS_DEFAULT_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Unmapped Events Fallback
    |--------------------------------------------------------------------------
    */

    'allow_unmapped_events' => (bool) env('GOOGLE_ADS_ALLOW_UNMAPPED_EVENTS', true),

    /*
    |--------------------------------------------------------------------------
    | Events mapping
    |--------------------------------------------------------------------------
    */

    'events' => [

        // 'Quote Form'    => 'Quote Submission',
        // 'Phone Call'    => env('GOOGLE_ADS_PHONE_ACTION', 'Call Clicks'),
        // 'Demo Booked'   => [
        //     'action'   => 'Demo Booked',
        //     'value'    => 250.00,
        //     'currency' => 'USD',
        // ],
        // 'Page Navigation' => 'Page Navigation',

    ],

    /*
    |--------------------------------------------------------------------------
    | European & UK Privacy Controls (GDPR / ePrivacy)
    |--------------------------------------------------------------------------
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
    */

    'consent' => [
        'ad_user_data' => env('GOOGLE_ADS_CONSENT_AD_USER_DATA', null),
        'ad_personalization' => env('GOOGLE_ADS_CONSENT_AD_PERSONALIZATION', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Enhanced Conversions for Leads (First-Party User Data)
    |--------------------------------------------------------------------------
    */

    'enhanced_conversions' => [
        'enabled' => (bool) env('GOOGLE_ADS_ENHANCED_CONVERSIONS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cookies and session
    |--------------------------------------------------------------------------
    */

    'cookies' => [
        'gclid' => 'google_ads_gclid',
        'gbraid' => 'google_ads_gbraid',
        'wbraid' => 'google_ads_wbraid',
        'fbclid' => 'meta_ads_fbclid',
        'msclkid' => 'ms_ads_msclkid',
        'ttclid' => 'tiktok_ads_ttclid',
        'li_fat_id' => 'linkedin_ads_lifatid',
        'visitor_id' => 'google_ads_visitor_id',
        'lifetime_minutes' => 60 * 24 * 30, // 30 days
        'domain' => null,
        'secure' => env('SESSION_SECURE_COOKIE', null),
        'http_only' => false,
        'same_site' => 'Lax',
    ],

    'session_key' => 'google_ads_gclid',
    'session_keys' => [
        'gclid' => 'google_ads_gclid',
        'gbraid' => 'google_ads_gbraid',
        'wbraid' => 'google_ads_wbraid',
        'fbclid' => 'meta_ads_fbclid',
        'msclkid' => 'ms_ads_msclkid',
        'ttclid' => 'tiktok_ads_ttclid',
        'li_fat_id' => 'linkedin_ads_lifatid',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracking data
    |--------------------------------------------------------------------------
    */

    'tracked_query_parameters' => [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'gad_source',
        'gad_campaignid',
        'fbclid',
        'msclkid',
        'ttclid',
        'li_fat_id',
    ],

];
