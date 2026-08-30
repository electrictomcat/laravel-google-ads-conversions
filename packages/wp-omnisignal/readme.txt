=== OmniSignal - Offline Ad Conversions & CAPI for WooCommerce ===
Contributors: omnisignal, electrictomcat
Tags: conversions api, capi, meta capi, google ads, tiktok events api, offline conversions, woocommerce
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Pure Signal. Zero Noise. Attribution Nirvana for WooCommerce. Recover lost ad conversions across Google Ads, Meta CAPI, and TikTok.

== Description ==

Stop losing 35-40% of WooCommerce ad attribution to iOS Safari ITP, ad-blockers, and browser noise.

OmniSignal automatically captures ad click identifiers (`gclid`, `gbraid`, `wbraid`, `fbclid`, `ttclid`, `msclkid`, `li_fat_id`) and uploads offline server-side conversions directly to your ad platforms on order completion.

### Key Features
* 🕉️ **Attribution Nirvana**: Captures landing click IDs and maintains them across sessions.
* ⚡ **WooCommerce Auto-Hooking**: Automatically sends offline conversion payloads on `woocommerce_payment_complete` and `woocommerce_thankyou`.
* 🔒 **Data Minimization & SHA-256 Hashing**: First-party customer data (email, phone) is normalized and SHA-256 hashed.
* 🌐 **Multi-Platform Support**: Google Ads, Meta Conversions API (CAPI v20.0), and TikTok Events API.

For advanced features and the Laravel SDK, visit [omnisignal.dev](https://omnisignal.dev).

== Installation ==

1. Upload the `wp-omnisignal` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to WooCommerce > OmniSignal ॐ to configure your API tokens.
