<?php

if (! defined('ABSPATH')) {
    exit;
}

class OmniSignal_Admin
{
    public static function init(): void
    {
        add_action('admin_menu', [__CLASS__, 'add_menu_page']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    public static function add_menu_page(): void
    {
        add_submenu_page(
            'woocommerce',
            'OmniSignal CAPI',
            'OmniSignal ॐ',
            'manage_woocommerce',
            'omnisignal-settings',
            [__CLASS__, 'render_page']
        );
    }

    public static function register_settings(): void
    {
        register_setting('omnisignal_settings_group', 'omnisignal_settings');
    }

    public static function render_page(): void
    {
        $options = get_option('omnisignal_settings', []);
        ?>
        <div class="wrap" style="max-width: 850px; margin-top: 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
            <div style="background: #0b0f17; color: #fff; padding: 24px 30px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span style="font-size: 28px;">🕉️</span>
                        <div>
                            <h1 style="color: #fff; font-size: 22px; margin: 0; font-weight: 700;">OmniSignal &bull; Attribution Nirvana</h1>
                            <p style="color: #94a3b8; font-size: 13px; margin: 4px 0 0 0;">Server-side conversion tracking & CAPI for WooCommerce (<a href="https://omnisignal.dev" target="_blank" style="color: #34d399;">omnisignal.dev</a>)</p>
                        </div>
                    </div>
                    <span style="background: rgba(16, 185, 129, 0.15); color: #34d399; font-size: 11px; padding: 4px 10px; border-radius: 20px; font-weight: 600; border: 1px solid rgba(16, 185, 129, 0.3);">
                        v2.0 Active
                    </span>
                </div>
            </div>

            <form method="post" action="options.php" style="background: #fff; padding: 30px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <?php settings_fields('omnisignal_settings_group'); ?>

                <h2 style="font-size: 16px; margin-top: 0; padding-bottom: 12px; border-bottom: 1px solid #edf2f7;">🔵 Meta (Facebook & Instagram) CAPI</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="meta_pixel_id">Meta Pixel / Dataset ID</label></th>
                        <td>
                            <input type="text" id="meta_pixel_id" name="omnisignal_settings[meta_pixel_id]" value="<?php echo esc_attr($options['meta_pixel_id'] ?? ''); ?>" class="regular-text" placeholder="1234567890123456" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="meta_access_token">Conversions API Access Token</label></th>
                        <td>
                            <input type="password" id="meta_access_token" name="omnisignal_settings[meta_access_token]" value="<?php echo esc_attr($options['meta_access_token'] ?? ''); ?>" class="regular-text" />
                            <p class="description">System User permanent token from Meta Events Manager > Settings > Conversions API.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="meta_test_event_code">Test Event Code (Optional)</label></th>
                        <td>
                            <input type="text" id="meta_test_event_code" name="omnisignal_settings[meta_test_event_code]" value="<?php echo esc_attr($options['meta_test_event_code'] ?? ''); ?>" class="small-text" placeholder="TEST1234" />
                            <p class="description">Use to verify live test events in Meta Events Manager.</p>
                        </td>
                    </tr>
                </table>

                <h2 style="font-size: 16px; margin-top: 30px; padding-bottom: 12px; border-bottom: 1px solid #edf2f7;">🎵 TikTok Events API (Server-to-Server)</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="tiktok_pixel_code">TikTok Pixel Code</label></th>
                        <td>
                            <input type="text" id="tiktok_pixel_code" name="omnisignal_settings[tiktok_pixel_code]" value="<?php echo esc_attr($options['tiktok_pixel_code'] ?? ''); ?>" class="regular-text" placeholder="C1234567890ABC" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tiktok_access_token">TikTok Access Token</label></th>
                        <td>
                            <input type="password" id="tiktok_access_token" name="omnisignal_settings[tiktok_access_token]" value="<?php echo esc_attr($options['tiktok_access_token'] ?? ''); ?>" class="regular-text" />
                        </td>
                    </tr>
                </table>

                <p class="submit" style="margin-top: 25px;">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="Save OmniSignal Settings" style="background: #059669; border-color: #059669; padding: 4px 16px; font-weight: 600;" />
                </p>
            </form>
        </div>
        <?php
    }
}
