<?php
/**
 * Tracking data repository — CRUD for _wc_shipment_tracking_items order meta.
 *
 * All operations use WC_Order CRUD (HPOS-safe). No direct SQL.
 *
 * @package WCSTT
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

class WCSTT_Tracking_Repository {

    /** Official extension meta key — do NOT change. */
    const META_KEY = '_wc_shipment_tracking_items';

    /**
     * Get all tracking items for an order.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_tracking_items(int $order_id): array {
        $order = wc_get_order($order_id);
        if (! $order) {
            return [];
        }

        $items = $order->get_meta(self::META_KEY, true);
        return is_array($items) ? $items : [];
    }

    /**
     * Add a tracking item to an order.
     *
     * @return array|WP_Error New item on success.
     */
    public static function add_tracking_item(
        int $order_id,
        string $tracking_number,
        string $tracking_provider = '',
        string $custom_tracking_provider = '',
        string $custom_tracking_link = '',
        ?int $date_shipped = null
    ): array|\WP_Error {

        $tracking_number = sanitize_text_field(trim($tracking_number));
        if ($tracking_number === '') {
            return new \WP_Error(
                'wcstt_empty_tracking',
                __('Tracking number is required.', 'wc-shipment-tracking-tool')
            );
        }

        $order = wc_get_order($order_id);
        if (! $order) {
            return new \WP_Error(
                'wcstt_order_not_found',
                __('Order not found.', 'wc-shipment-tracking-tool')
            );
        }

        $items = $order->get_meta(self::META_KEY, true);
        if (! is_array($items)) {
            $items = [];
        }

        // Anti-duplicate: normalize and compare.
        $normalized = strtoupper(trim($tracking_number));
        foreach ($items as $existing) {
            if (strtoupper(trim($existing['tracking_number'] ?? '')) === $normalized) {
                return new \WP_Error(
                    'wcstt_duplicate_tracking',
                    /* translators: %s: tracking number */
                    sprintf(__('Tracking number %s already exists for this order.', 'wc-shipment-tracking-tool'), $tracking_number)
                );
            }
        }

        // Resolve provider display name + link.
        $tracking_provider        = sanitize_text_field($tracking_provider);
        $custom_tracking_provider = sanitize_text_field($custom_tracking_provider);
        $custom_tracking_link     = esc_url_raw($custom_tracking_link);

        if ($tracking_provider !== '' && $tracking_provider !== 'custom') {
            // Predefined provider — override display name + link from registry.
            $custom_tracking_provider = WCSTT_Providers::get_provider_name($tracking_provider);
            $custom_tracking_link     = WCSTT_Providers::get_tracking_url($tracking_provider, $tracking_number);
        }

        $new_item = [
            'tracking_provider'        => $tracking_provider,
            'custom_tracking_provider' => $custom_tracking_provider,
            'custom_tracking_link'     => $custom_tracking_link,
            'tracking_number'          => $tracking_number,
            'date_shipped'             => $date_shipped ?? time(),
        ];

        $items[] = $new_item;

        $order->update_meta_data(self::META_KEY, $items);
        $order->save();

        /**
         * Fires after a tracking item is added.
         *
         * @param int   $order_id Order ID.
         * @param array $new_item The tracking item that was added.
         */
        do_action('wcstt_tracking_added', $order_id, $new_item);

        return $new_item;
    }

    /**
     * Remove a tracking item by its tracking number.
     *
     * @return true|WP_Error
     */
    public static function remove_tracking_item(int $order_id, string $tracking_number): bool|\WP_Error {
        $order = wc_get_order($order_id);
        if (! $order) {
            return new \WP_Error('wcstt_order_not_found', __('Order not found.', 'wc-shipment-tracking-tool'));
        }

        $items = $order->get_meta(self::META_KEY, true);
        if (! is_array($items)) {
            return new \WP_Error('wcstt_tracking_not_found', __('Tracking number not found.', 'wc-shipment-tracking-tool'));
        }

        $normalized = strtoupper(trim($tracking_number));
        $found      = false;
        $items      = array_values(array_filter($items, static function (array $item) use ($normalized, &$found): bool {
            if (strtoupper(trim($item['tracking_number'] ?? '')) === $normalized) {
                $found = true;
                return false; // remove it
            }
            return true;
        }));

        if (! $found) {
            return new \WP_Error('wcstt_tracking_not_found', __('Tracking number not found.', 'wc-shipment-tracking-tool'));
        }

        $order->update_meta_data(self::META_KEY, $items);
        $order->save();

        /**
         * Fires after a tracking item is removed.
         *
         * @param int    $order_id        Order ID.
         * @param string $tracking_number The removed tracking number.
         */
        do_action('wcstt_tracking_removed', $order_id, $tracking_number);

        return true;
    }

    /**
     * Check whether a tracking number already exists on an order (normalized).
     */
    public static function tracking_exists(int $order_id, string $tracking_number): bool {
        $normalized = strtoupper(trim($tracking_number));
        foreach (self::get_tracking_items($order_id) as $item) {
            if (strtoupper(trim($item['tracking_number'] ?? '')) === $normalized) {
                return true;
            }
        }
        return false;
    }
}
