<?php
/**
 * WooCommerce Settings tab for Shipment Tracking configuration.
 *
 * Adds a "Shipment Tracking" tab under WooCommerce → Settings
 * with sections for PayPal sync and Order Notes parser.
 *
 * @package WCSTT
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

class WCSTT_Settings extends WC_Settings_Page {

    public function __construct() {
        $this->id    = 'wcstt';
        $this->label = __('Shipment Tracking', 'wc-shipment-tracking-tool');

        add_action('wp_ajax_wcstt_test_paypal', [$this, 'ajax_test_paypal']);

        parent::__construct();
    }

    /**
     * Get settings array for the current section.
     *
     * @return array
     */
    public function get_settings_for_default_section(): array {
        return [
            // --- PayPal Tracking Sync ---
            [
                'title' => __('PayPal Tracking Sync', 'wc-shipment-tracking-tool'),
                'type'  => 'title',
                'desc'  => __('Automatically push tracking numbers to PayPal when added to an order. Requires PayPal REST API credentials.', 'wc-shipment-tracking-tool'),
                'id'    => 'wcstt_paypal_section',
            ],
            [
                'title'   => __('Enable PayPal Sync', 'wc-shipment-tracking-tool'),
                'id'      => 'wcstt_paypal_enabled',
                'type'    => 'checkbox',
                'default' => 'no',
                'desc'    => __('Push tracking info to PayPal when tracking is added or removed.', 'wc-shipment-tracking-tool'),
            ],
            [
                'title'   => __('Environment', 'wc-shipment-tracking-tool'),
                'id'      => 'wcstt_paypal_environment',
                'type'    => 'select',
                'default' => 'live',
                'options' => [
                    'live'    => __('Live', 'wc-shipment-tracking-tool'),
                    'sandbox' => __('Sandbox', 'wc-shipment-tracking-tool'),
                ],
            ],
            [
                'title'       => __('Client ID', 'wc-shipment-tracking-tool'),
                'id'          => 'wcstt_paypal_client_id',
                'type'        => 'text',
                'default'     => '',
                'desc_tip'    => __('PayPal REST API Client ID from developer.paypal.com', 'wc-shipment-tracking-tool'),
                'placeholder' => 'AX...',
            ],
            [
                'title'       => __('Client Secret', 'wc-shipment-tracking-tool'),
                'id'          => 'wcstt_paypal_client_secret',
                'type'        => 'password',
                'default'     => '',
                'desc_tip'    => __('PayPal REST API Client Secret from developer.paypal.com', 'wc-shipment-tracking-tool'),
            ],
            [
                'type' => 'sectionend',
                'id'   => 'wcstt_paypal_section',
            ],

            // --- Order Notes Parser ---
            [
                'title' => __('Order Notes Parser', 'wc-shipment-tracking-tool'),
                'type'  => 'title',
                'desc'  => __('Automatically detect and import tracking numbers from WooCommerce order notes.', 'wc-shipment-tracking-tool'),
                'id'    => 'wcstt_notes_section',
            ],
            [
                'title'   => __('Enable Notes Parser', 'wc-shipment-tracking-tool'),
                'id'      => 'wcstt_notes_parser_enabled',
                'type'    => 'checkbox',
                'default' => 'no',
                'desc'    => __('Parse new order notes for tracking numbers and auto-import them.', 'wc-shipment-tracking-tool'),
            ],
            [
                'type' => 'sectionend',
                'id'   => 'wcstt_notes_section',
            ],
        ];
    }

    /**
     * Output custom HTML after settings fields — test connection button.
     */
    public function output(): void {
        parent::output();

        // Only show test button on the default (main) section.
        if (method_exists($this, 'get_current_section') && $this->get_current_section() !== '') {
            return;
        }

        $nonce = wp_create_nonce('wcstt_test_paypal');
        ?>
        <div style="margin: -10px 0 20px; padding: 15px 20px; background: #f8f9fa; border: 1px solid #dcdcde; border-radius: 4px;">
            <h3 style="margin: 0 0 8px; font-size: 14px;">Test PayPal Connection</h3>
            <p style="margin: 0 0 10px; color: #646970; font-size: 13px;">
                Save settings first, then click to verify your PayPal API credentials.
            </p>
            <button type="button" id="wcstt-test-paypal" class="button button-secondary">
                Test Connection
            </button>
            <span id="wcstt-test-result" style="margin-left: 10px; font-size: 13px;"></span>
        </div>
        <script>
        jQuery(function($) {
            $('#wcstt-test-paypal').on('click', function() {
                var $btn = $(this);
                var $result = $('#wcstt-test-result');

                $btn.prop('disabled', true).text('Testing...');
                $result.html('').css('color', '');

                $.post(ajaxurl, {
                    action: 'wcstt_test_paypal',
                    nonce: '<?php echo esc_js($nonce); ?>'
                }).done(function(res) {
                    if (res.success) {
                        $result.css('color', '#00a32a').html('&#10004; ' + res.data.message);
                    } else {
                        $result.css('color', '#d63638').html('&#10008; ' + (res.data && res.data.message ? res.data.message : 'Connection failed'));
                    }
                }).fail(function() {
                    $result.css('color', '#d63638').html('&#10008; Network error');
                }).always(function() {
                    $btn.prop('disabled', false).text('Test Connection');
                });
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX handler — test PayPal API connection by requesting an OAuth2 token.
     */
    public function ajax_test_paypal(): void {
        check_ajax_referer('wcstt_test_paypal', 'nonce');

        if (! current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $client_id     = get_option('wcstt_paypal_client_id', '');
        $client_secret = get_option('wcstt_paypal_client_secret', '');

        if ($client_id === '' || $client_secret === '') {
            wp_send_json_error(['message' => 'Client ID and Client Secret are required. Save settings first.']);
        }

        $env      = get_option('wcstt_paypal_environment', 'live');
        $base_url = $env === 'sandbox'
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';

        // Clear cached token to force a fresh test.
        delete_transient('wcstt_paypal_token');

        $url   = $base_url . '/v1/oauth2/token';
        $auth  = base64_encode($client_id . ':' . $client_secret);
        $data  = null;
        $code  = 0;
        $via   = 'wp_remote_post';

        // Try wp_remote_post first.
        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Basic ' . $auth,
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'body'    => 'grant_type=client_credentials',
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            // Fallback: use PHP cURL directly (bypasses WP HTTP API filters/blocks).
            if (function_exists('curl_init')) {
                $via = 'php_curl_fallback';
                $ch  = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
                    CURLOPT_HTTPHEADER     => [
                        'Authorization: Basic ' . $auth,
                        'Content-Type: application/x-www-form-urlencoded',
                    ],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 30,
                    CURLOPT_SSL_VERIFYPEER => true,
                ]);
                $body = curl_exec($ch);
                $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $err  = curl_error($ch);
                curl_close($ch);

                if ($body === false || $code === 0) {
                    wp_send_json_error(['message' => 'cURL fallback failed: ' . $err]);
                }
                $data = json_decode($body, true);
            } else {
                wp_send_json_error([
                    'message' => 'wp_remote_post failed: ' . $response->get_error_message(),
                ]);
            }
        } else {
            $code = wp_remote_retrieve_response_code($response);
            $data = json_decode(wp_remote_retrieve_body($response), true);
        }

        if ($code === 200 && ! empty($data['access_token'])) {
            $app_id  = $data['app_id'] ?? 'N/A';
            $expires = isset($data['expires_in']) ? round((int) $data['expires_in'] / 3600, 1) : '?';

            // Cache the token since we got it.
            $ttl = isset($data['expires_in']) ? max((int) $data['expires_in'] - 60, 60) : 3600;
            set_transient('wcstt_paypal_token', $data['access_token'], $ttl);

            wp_send_json_success([
                'message' => sprintf(
                    'Connected! Environment: %s | App ID: %s | Token expires in %sh | via: %s',
                    strtoupper($env),
                    $app_id,
                    $expires,
                    $via
                ),
            ]);
        }

        $err = $data['error_description'] ?? ($data['message'] ?? 'HTTP ' . $code);
        wp_send_json_error(['message' => 'Authentication failed: ' . $err]);
    }
}
