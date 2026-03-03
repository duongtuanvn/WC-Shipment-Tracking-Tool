<?php
/**
 * PayPal Tracking Sync — push tracking to PayPal when added/removed in WCSTT.
 *
 * Uses PayPal v1 Shipping Trackers Batch API.
 * Requires OAuth2 client credentials configured in WCSTT settings.
 *
 * @package WCSTT
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

class WCSTT_PayPal_Sync {

    /** PayPal carrier enum mapping from WCSTT provider slugs. */
    private const CARRIER_MAP = [
        'usps'             => 'USPS',
        'fedex'            => 'FEDEX',
        'ups'              => 'UPS',
        'dhl'              => 'DHL',
        'dhl_ecommerce'    => 'DHL_GLOBAL_ECOMMERCE',
        'amazon_logistics' => 'AMZN_US',
        'ontrac'           => 'ONTRAC',
        'lasership'        => 'LASERSHIP',
        'stamps_com'       => 'USPS',
    ];

    public function __construct() {
        add_action('wcstt_tracking_added', [$this, 'on_tracking_added'], 10, 2);
        add_action('wcstt_tracking_removed', [$this, 'on_tracking_removed'], 10, 2);
    }

    /* ------------------------------------------------------------------
     * Hook: tracking added → push SHIPPED to PayPal
     * ----------------------------------------------------------------*/

    /**
     * @param int   $order_id WooCommerce order ID.
     * @param array $item     Tracking item array.
     */
    public function on_tracking_added(int $order_id, array $item): void {
        $order = wc_get_order($order_id);
        if (! $order) {
            return;
        }

        $transaction_id = $order->get_transaction_id();
        if (empty($transaction_id)) {
            return; // Not a PayPal order.
        }

        $carrier = $this->map_carrier($item['tracking_provider'] ?? '');

        $payload = [
            'trackers' => [
                [
                    'transaction_id'  => $transaction_id,
                    'tracking_number' => $item['tracking_number'],
                    'status'          => 'SHIPPED',
                    'carrier'         => $carrier,
                ],
            ],
        ];

        $result = $this->api_request('POST', '/v1/shipping/trackers-batch', $payload);

        if (is_wp_error($result)) {
            $order->add_order_note(
                sprintf('PayPal sync failed for %s: %s', $item['tracking_number'], $result->get_error_message()),
                false // private note
            );
            return;
        }

        // Check for tracker-level errors in batch response.
        $errors = $this->extract_batch_errors($result);
        if (! empty($errors)) {
            $order->add_order_note(
                sprintf('PayPal sync error for %s: %s', $item['tracking_number'], implode('; ', $errors)),
                false
            );
            return;
        }

        $order->add_order_note(
            sprintf('PayPal tracking synced: %s (%s)', $item['tracking_number'], $carrier),
            false
        );
    }

    /* ------------------------------------------------------------------
     * Hook: tracking removed → cancel on PayPal
     * ----------------------------------------------------------------*/

    /**
     * @param int    $order_id        WooCommerce order ID.
     * @param string $tracking_number Removed tracking number.
     */
    public function on_tracking_removed(int $order_id, string $tracking_number): void {
        $order = wc_get_order($order_id);
        if (! $order) {
            return;
        }

        $transaction_id = $order->get_transaction_id();
        if (empty($transaction_id)) {
            return;
        }

        // PUT /v1/shipping/trackers/{transaction_id}-{tracking_number}
        $tracker_id = $transaction_id . '-' . $tracking_number;
        $result     = $this->api_request('PUT', '/v1/shipping/trackers/' . rawurlencode($tracker_id), [
            'transaction_id'  => $transaction_id,
            'tracking_number' => $tracking_number,
            'status'          => 'CANCELLED',
        ]);

        if (is_wp_error($result)) {
            $order->add_order_note(
                sprintf('PayPal cancel sync failed for %s: %s', $tracking_number, $result->get_error_message()),
                false
            );
            return;
        }

        $order->add_order_note(
            sprintf('PayPal tracking cancelled: %s', $tracking_number),
            false
        );
    }

    /* ------------------------------------------------------------------
     * PayPal API helpers
     * ----------------------------------------------------------------*/

    /**
     * Make an authenticated request to PayPal REST API.
     *
     * @return array|WP_Error Decoded response body or error.
     */
    private function api_request(string $method, string $endpoint, array $body = []): array|\WP_Error {
        $token = $this->get_access_token();
        if (is_wp_error($token)) {
            return $token;
        }

        $args = [
            'method'  => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 30,
        ];

        if (! empty($body)) {
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($this->get_base_url() . $endpoint, $args);

        if (is_wp_error($response)) {
            return new \WP_Error('wcstt_paypal_network', $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400) {
            $msg = $data['message'] ?? ($data['error_description'] ?? 'HTTP ' . $code);
            return new \WP_Error('wcstt_paypal_api', $msg);
        }

        return is_array($data) ? $data : [];
    }

    /**
     * Get OAuth2 access token, cached in a transient.
     *
     * @return string|WP_Error Access token or error.
     */
    private function get_access_token(): string|\WP_Error {
        $cached = get_transient('wcstt_paypal_token');
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $client_id     = get_option('wcstt_paypal_client_id', '');
        $client_secret = get_option('wcstt_paypal_client_secret', '');

        if ($client_id === '' || $client_secret === '') {
            return new \WP_Error('wcstt_paypal_config', 'PayPal Client ID or Secret is not configured.');
        }

        $response = wp_remote_post($this->get_base_url() . '/v1/oauth2/token', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'body'    => 'grant_type=client_credentials',
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return new \WP_Error('wcstt_paypal_auth', 'Token request failed: ' . $response->get_error_message());
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($data['access_token'])) {
            $err = $data['error_description'] ?? 'Invalid credentials';
            return new \WP_Error('wcstt_paypal_auth', 'OAuth2 failed: ' . $err);
        }

        // Cache for token lifetime minus 60s buffer.
        $ttl = isset($data['expires_in']) ? max((int) $data['expires_in'] - 60, 60) : 3600;
        set_transient('wcstt_paypal_token', $data['access_token'], $ttl);

        return $data['access_token'];
    }

    /**
     * Get PayPal API base URL based on environment setting.
     */
    private function get_base_url(): string {
        $env = get_option('wcstt_paypal_environment', 'live');
        return $env === 'sandbox'
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    /**
     * Map WCSTT provider slug to PayPal carrier enum.
     */
    private function map_carrier(string $provider_slug): string {
        return self::CARRIER_MAP[$provider_slug] ?? 'OTHER';
    }

    /**
     * Extract error messages from batch response.
     *
     * @return string[] Error messages, empty if all succeeded.
     */
    private function extract_batch_errors(array $response): array {
        $errors = [];

        // Batch endpoint returns "tracker_identifiers" on success and "errors" on failure.
        if (! empty($response['errors']) && is_array($response['errors'])) {
            foreach ($response['errors'] as $err) {
                $msg = $err['message'] ?? ($err['details'][0]['description'] ?? 'Unknown error');
                $errors[] = $msg;
            }
        }

        return $errors;
    }
}
