<?php

if (! defined('ABSPATH')) {
    exit;
}

class OmniSignal_WooCommerce
{
    public static function init(): void
    {
        add_action('woocommerce_payment_complete', [__CLASS__, 'on_payment_complete']);
        add_action('woocommerce_thankyou', [__CLASS__, 'on_thankyou'], 10, 1);
    }

    public static function on_payment_complete(int $order_id): void
    {
        self::process_order($order_id);
    }

    public static function on_thankyou(int $order_id): void
    {
        if (! $order_id) {
            return;
        }

        self::process_order($order_id);
    }

    private static function process_order(int $order_id): void
    {
        // Avoid duplicate triggers
        if (get_post_meta($order_id, '_omnisignal_uploaded', true)) {
            return;
        }

        $order = wc_get_order($order_id);
        if (! $order) {
            return;
        }

        $clicks = OmniSignal_Tracker::get_click_ids();

        $payload = [
            'event_name' => 'Purchase',
            'order_id' => $order->get_id(),
            'value' => (float) $order->get_total(),
            'currency' => $order->get_currency(),
            'email' => $order->get_billing_email(),
            'phone' => $order->get_billing_phone(),
            'gclid' => $clicks['gclid'],
            'gbraid' => $clicks['gbraid'],
            'wbraid' => $clicks['wbraid'],
            'fbclid' => $clicks['fbclid'],
            'msclkid' => $clicks['msclkid'],
            'ttclid' => $clicks['ttclid'],
            'li_fat_id' => $clicks['li_fat_id'],
        ];

        OmniSignal_API::send_conversion($payload);

        update_post_meta($order_id, '_omnisignal_uploaded', time());
    }
}
