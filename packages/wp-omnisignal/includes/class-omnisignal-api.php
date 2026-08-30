<?php

if (! defined('ABSPATH')) {
    exit;
}

class OmniSignal_API
{
    /**
     * Dispatch an offline conversion payload to enabled channels.
     *
     * @param  array  $payload  Event data
     */
    public static function send_conversion(array $payload): void
    {
        $options = get_option('omnisignal_settings', []);

        // 1. Meta CAPI
        if (! empty($options['meta_pixel_id']) && ! empty($options['meta_access_token'])) {
            self::send_meta_capi($payload, $options);
        }

        // 2. TikTok Events API
        if (! empty($options['tiktok_pixel_code']) && ! empty($options['tiktok_access_token'])) {
            self::send_tiktok_api($payload, $options);
        }
    }

    private static function send_meta_capi(array $payload, array $options): void
    {
        $pixel_id = sanitize_text_field($options['meta_pixel_id']);
        $token = sanitize_text_field($options['meta_access_token']);

        $user_data = [];
        if (! empty($payload['email'])) {
            $user_data['em'] = [hash('sha256', strtolower(trim($payload['email'])))];
        }
        if (! empty($payload['phone'])) {
            $user_data['ph'] = [hash('sha256', preg_replace('/[^\d+]/', '', $payload['phone']))];
        }
        if (! empty($payload['fbclid'])) {
            $user_data['fbc'] = 'fb.1.'.time().'.'.$payload['fbclid'];
        }
        if (! empty($_SERVER['REMOTE_ADDR'])) {
            $user_data['client_ip_address'] = sanitize_text_field($_SERVER['REMOTE_ADDR']);
        }
        if (! empty($_SERVER['HTTP_USER_AGENT'])) {
            $user_data['client_user_agent'] = sanitize_text_field($_SERVER['HTTP_USER_AGENT']);
        }

        $event = [
            'event_name' => $payload['event_name'] ?? 'Purchase',
            'event_time' => time(),
            'action_source' => 'website',
            'user_data' => $user_data,
            'custom_data' => [
                'value' => (float) ($payload['value'] ?? 0),
                'currency' => $payload['currency'] ?? 'USD',
                'order_id' => (string) ($payload['order_id'] ?? ''),
            ],
        ];

        if (! empty($payload['order_id'])) {
            $event['event_id'] = 'ORDER_'.$payload['order_id'];
        }

        $body = ['data' => [$event]];
        if (! empty($options['meta_test_event_code'])) {
            $body['test_event_code'] = sanitize_text_field($options['meta_test_event_code']);
        }

        wp_remote_post("https://graph.facebook.com/v20.0/{$pixel_id}/events", [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
            'timeout' => 10,
        ]);
    }

    private static function send_tiktok_api(array $payload, array $options): void
    {
        $pixel_code = sanitize_text_field($options['tiktok_pixel_code']);
        $token = sanitize_text_field($options['tiktok_access_token']);

        $user = [];
        if (! empty($payload['email'])) {
            $user['email'] = hash('sha256', strtolower(trim($payload['email'])));
        }
        if (! empty($payload['phone'])) {
            $user['phone_number'] = hash('sha256', preg_replace('/[^\d+]/', '', $payload['phone']));
        }
        if (! empty($payload['ttclid'])) {
            $user['ttclid'] = $payload['ttclid'];
        }

        $event = [
            'event' => 'CompletePayment',
            'event_time' => time(),
            'user' => $user,
            'properties' => [
                'value' => (float) ($payload['value'] ?? 0),
                'currency' => $payload['currency'] ?? 'USD',
            ],
        ];

        if (! empty($payload['order_id'])) {
            $event['event_id'] = 'ORDER_'.$payload['order_id'];
        }

        wp_remote_post('https://business-api.tiktok.com/open_api/v1.3/event/track/', [
            'headers' => [
                'Access-Token' => $token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'pixel_code' => $pixel_code,
                'event_source' => 'web',
                'event_source_id' => $pixel_code,
                'data' => [$event],
            ]),
            'timeout' => 10,
        ]);
    }
}
