<?php
/**
 * Inject tracking information into WooCommerce customer emails.
 *
 * Tracking block appears in ALL customer emails once tracking data exists
 * on the order (matches official extension behavior).
 *
 * @package WCSTT
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

class WCSTT_Email_Tracking {

    public function __construct() {
        add_action(
            'woocommerce_email_before_order_table',
            [$this, 'display_tracking_in_email'],
            10,
            4
        );
    }

    /**
     * Hook callback — inject tracking block before the order table.
     *
     * @param \WC_Order  $order         Order object.
     * @param bool       $sent_to_admin Whether this is an admin email.
     * @param bool       $plain_text    Whether the email is plain-text.
     * @param \WC_Email  $email         Email object.
     */
    public function display_tracking_in_email($order, bool $sent_to_admin, bool $plain_text, $email): void {
        // Only customer emails.
        if ($sent_to_admin) {
            return;
        }

        $items = WCSTT_Tracking_Repository::get_tracking_items($order->get_id());
        if (empty($items)) {
            return;
        }

        // Ensure every item has a resolved tracking URL.
        $items = array_map([$this, 'resolve_url'], $items);

        if ($plain_text) {
            $this->render_plain_text($items);
        } else {
            $this->render_html($order, $items);
        }
    }

    /* ------------------------------------------------------------------
     * Renderers
     * ----------------------------------------------------------------*/

    private function render_html(\WC_Order $order, array $items): void {
        wc_get_template(
            'emails/tracking-info.php',
            [
                'order'          => $order,
                'tracking_items' => $items,
            ],
            '',
            WCSTT_PATH . 'templates/'
        );
    }

    private function render_plain_text(array $items): void {
        echo "\n" . esc_html__('Tracking Information', 'wc-shipment-tracking-tool') . "\n";
        echo str_repeat('─', 40) . "\n";

        foreach ($items as $item) {
            $provider = $item['custom_tracking_provider'] ?: $item['tracking_provider'];
            echo esc_html($provider) . ': ' . esc_html($item['tracking_number']) . "\n";

            if (! empty($item['custom_tracking_link'])) {
                echo esc_html__('Track', 'wc-shipment-tracking-tool') . ': '
                    . esc_url($item['custom_tracking_link']) . "\n";
            }

            if (! empty($item['date_shipped'])) {
                echo esc_html__('Shipped', 'wc-shipment-tracking-tool') . ': '
                    . date_i18n('M j, Y', (int) $item['date_shipped']) . "\n";
            }

            echo "\n";
        }
    }

    /* ------------------------------------------------------------------
     * Helpers
     * ----------------------------------------------------------------*/

    /**
     * If the item has a predefined provider but no link, build the link.
     */
    private function resolve_url(array $item): array {
        if (! empty($item['custom_tracking_link'])) {
            return $item;
        }
        if (! empty($item['tracking_provider'])) {
            $item['custom_tracking_link'] = WCSTT_Providers::get_tracking_url(
                $item['tracking_provider'],
                $item['tracking_number'] ?? ''
            );
        }
        return $item;
    }
}
