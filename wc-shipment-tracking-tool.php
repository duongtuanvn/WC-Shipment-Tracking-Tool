<?php
/**
 * Plugin Name: WC Shipment Tracking Tool
 * Plugin URI:  https://github.com/your-org/wc-shipment-tracking-tool
 * Description: Shipment tracking for WooCommerce — compatible with the official Shipment Tracking extension data format.
 * Version:     1.0.0
 * Author:      Your Name
 * Author URI:  https://example.com
 * License:     GPL-2.0-or-later
 * Text Domain: wc-shipment-tracking-tool
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Requires Plugins: woocommerce
 * WC requires at least: 8.0
 * WC tested up to: 9.6
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('WCSTT_VERSION', '1.0.0');
define('WCSTT_FILE', __FILE__);
define('WCSTT_PATH', plugin_dir_path(__FILE__));
define('WCSTT_URL', plugin_dir_url(__FILE__));

// Declare HPOS compatibility before WooCommerce initializes.
add_action('before_woocommerce_init', static function (): void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
});

// Boot plugin after WooCommerce is fully loaded.
add_action('woocommerce_loaded', static function (): void {
    require_once WCSTT_PATH . 'includes/class-wcstt-loader.php';
    WCSTT_Loader::instance()->init();
});

/**
 * Global helper — add a tracking number to an order.
 *
 * @param int         $order_id        WooCommerce order ID.
 * @param string      $tracking_number Carrier tracking code.
 * @param string      $provider        Provider slug (predefined) or empty for custom.
 * @param int|null    $date_shipped    Unix timestamp, defaults to now.
 * @param string      $custom_url      Custom tracking URL (required when provider is custom).
 * @param string      $custom_provider Custom provider display name.
 * @return array|WP_Error New tracking item on success.
 */
function wcstt_add_tracking_number(
    int $order_id,
    string $tracking_number,
    string $provider = '',
    ?int $date_shipped = null,
    string $custom_url = '',
    string $custom_provider = ''
): array|\WP_Error {
    if (! class_exists('WCSTT_Tracking_Repository')) {
        require_once WCSTT_PATH . 'includes/class-wcstt-tracking-repository.php';
    }
    return WCSTT_Tracking_Repository::add_tracking_item(
        $order_id,
        $tracking_number,
        $provider,
        $custom_provider,
        $custom_url,
        $date_shipped
    );
}
