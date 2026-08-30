<?php

/**
 * Plugin Name: OmniSignal &bull; Server-Side Conversion Tracking & CAPI for WooCommerce
 * Plugin URI: https://omnisignal.dev
 * Description: Pure Signal. Zero Noise. Attribution Nirvana for WooCommerce. Recover lost ad conversions across Google Ads, Meta CAPI, TikTok, LinkedIn, and Microsoft Ads.
 * Version: 2.0.0
 * Author: OmniSignal
 * Author URI: https://omnisignal.dev
 * Text Domain: omnisignal
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * WC requires at least: 7.0
 * WC tested up to: 9.2
 * License: GPLv2 or later
 */
if (! defined('ABSPATH')) {
    exit;
}

define('OMNISIGNAL_VERSION', '2.0.0');
define('OMNISIGNAL_PATH', plugin_dir_path(__FILE__));
define('OMNISIGNAL_URL', plugin_dir_url(__FILE__));

require_once OMNISIGNAL_PATH.'includes/class-omnisignal-tracker.php';
require_once OMNISIGNAL_PATH.'includes/class-omnisignal-api.php';
require_once OMNISIGNAL_PATH.'includes/class-omnisignal-woocommerce.php';
require_once OMNISIGNAL_PATH.'includes/class-omnisignal-admin.php';

add_action('plugins_loaded', function () {
    OmniSignal_Tracker::init();
    OmniSignal_WooCommerce::init();
    if (is_admin()) {
        OmniSignal_Admin::init();
    }
});
