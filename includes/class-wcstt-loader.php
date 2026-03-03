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
