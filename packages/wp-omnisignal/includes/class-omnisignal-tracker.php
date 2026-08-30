<?php

if (! defined('ABSPATH')) {
    exit;
}

class OmniSignal_Tracker
{
    public static function init(): void
    {
        add_action('init', [__CLASS__, 'capture_click_ids']);
    }

    public static function capture_click_ids(): void
    {
        if (headers_sent()) {
            return;
        }

        $params = [
            'gclid' => 'omnisignal_gclid',
            'gbraid' => 'omnisignal_gbraid',
            'wbraid' => 'omnisignal_wbraid',
            'fbclid' => 'omnisignal_fbclid',
            'msclkid' => 'omnisignal_msclkid',
            'ttclid' => 'omnisignal_ttclid',
            'li_fat_id' => 'omnisignal_lifatid',
        ];

        $lifetime = time() + (30 * DAY_IN_SECONDS);
        $cookie_path = COOKIEPATH ? COOKIEPATH : '/';
        $cookie_domain = COOKIE_DOMAIN;

        foreach ($params as $param => $cookie_name) {
            if (! empty($_GET[$param])) {
                $val = sanitize_text_field(wp_unslash($_GET[$param]));
                setcookie($cookie_name, $val, $lifetime, $cookie_path, $cookie_domain, is_ssl(), false);
                $_COOKIE[$cookie_name] = $val;
            }
        }
    }

    public static function get_click_ids(): array
    {
        return [
            'gclid' => sanitize_text_field($_COOKIE['omnisignal_gclid'] ?? ''),
            'gbraid' => sanitize_text_field($_COOKIE['omnisignal_gbraid'] ?? ''),
            'wbraid' => sanitize_text_field($_COOKIE['omnisignal_wbraid'] ?? ''),
            'fbclid' => sanitize_text_field($_COOKIE['omnisignal_fbclid'] ?? ''),
            'msclkid' => sanitize_text_field($_COOKIE['omnisignal_msclkid'] ?? ''),
            'ttclid' => sanitize_text_field($_COOKIE['omnisignal_ttclid'] ?? ''),
            'li_fat_id' => sanitize_text_field($_COOKIE['omnisignal_lifatid'] ?? ''),
        ];
    }
}
