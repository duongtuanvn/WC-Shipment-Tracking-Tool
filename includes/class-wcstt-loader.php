<?php
/**
 * Plugin loader — requires files and registers hooks.
 *
 * @package WCSTT
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

class WCSTT_Loader {

    private static ?self $instance = null;

    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Bootstrap all plugin components.
     */
    public function init(): void {
        $this->load_files();

        // Admin-only components.
        if (is_admin()) {
            new WCSTT_Admin_Meta_Box();
        }

        // Frontend (My Account order page).
        new WCSTT_Frontend_Tracking();

        // Email injection (runs on both admin and frontend contexts).
        new WCSTT_Email_Tracking();

        // REST API.
        add_action('rest_api_init', static function (): void {
            $controller = new WCSTT_REST_Controller();
            $controller->register_routes();
        });

        // Settings page (WooCommerce → Settings → Shipment Tracking).
        // WC_Settings_Page is only available in admin context.
        add_filter('woocommerce_get_settings_pages', static function (array $settings): array {
            require_once WCSTT_PATH . 'includes/class-wcstt-settings.php';
            $settings[] = new WCSTT_Settings();
            return $settings;
        });

        // Register settings AJAX handlers directly (WC_Settings_Page not available during AJAX).
        add_action('wp_ajax_wcstt_test_paypal', static function (): void {
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
            $base_url = $env === 'sandbox' ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';

            delete_transient('wcstt_paypal_token');

            $auth     = base64_encode($client_id . ':' . $client_secret);
            $response = wp_remote_post($base_url . '/v1/oauth2/token', [
                'headers' => [
                    'Authorization' => 'Basic ' . $auth,
                    'Content-Type'  => 'application/x-www-form-urlencoded',
                ],
                'body'    => 'grant_type=client_credentials',
                'timeout' => 30,
            ]);

            if (is_wp_error($response)) {
                if (function_exists('curl_init')) {
                    $ch = curl_init($base_url . '/v1/oauth2/token');
                    curl_setopt_array($ch, [
                        CURLOPT_POST           => true,
                        CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
                        CURLOPT_HTTPHEADER     => ['Authorization: Basic ' . $auth, 'Content-Type: application/x-www-form-urlencoded'],
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
                    wp_send_json_error(['message' => 'wp_remote_post failed: ' . $response->get_error_message()]);
                }
            } else {
                $code = wp_remote_retrieve_response_code($response);
                $data = json_decode(wp_remote_retrieve_body($response), true);
            }

            if ($code === 200 && ! empty($data['access_token'])) {
                $ttl = isset($data['expires_in']) ? max((int) $data['expires_in'] - 60, 60) : 3600;
                set_transient('wcstt_paypal_token', $data['access_token'], $ttl);

                wp_send_json_success([
                    'message' => sprintf(
                        'Connected! Environment: %s | App ID: %s | Token expires in %sh',
                        strtoupper($env),
                        $data['app_id'] ?? 'N/A',
                        round(($data['expires_in'] ?? 3600) / 3600, 1)
                    ),
                ]);
            }

            $err = $data['error_description'] ?? ($data['message'] ?? 'HTTP ' . $code);
            wp_send_json_error(['message' => 'Authentication failed: ' . $err]);
        });

        // PayPal tracking sync (only when enabled).
        if (get_option('wcstt_paypal_enabled') === 'yes') {
            new WCSTT_PayPal_Sync();
        }

        // Order notes parser (only when enabled).
        if (get_option('wcstt_notes_parser_enabled') === 'yes') {
            new WCSTT_Order_Notes_Parser();
        }
    }

    private function load_files(): void {
        $dir = WCSTT_PATH . 'includes/';

        require_once $dir . 'class-wcstt-providers.php';
        require_once $dir . 'class-wcstt-tracking-repository.php';
        require_once $dir . 'class-wcstt-admin-meta-box.php';
        require_once $dir . 'class-wcstt-email-tracking.php';
        require_once $dir . 'class-wcstt-frontend-tracking.php';
        require_once $dir . 'class-wcstt-rest-controller.php';
        require_once $dir . 'class-wcstt-paypal-sync.php';
        require_once $dir . 'class-wcstt-order-notes-parser.php';
    }

    private function __construct() {}
    private function __clone() {}
}
