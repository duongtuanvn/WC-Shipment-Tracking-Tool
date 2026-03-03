<?php
/**
 * Display tracking information on the My Account → View Order page.
 *
 * @package WCSTT
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

class WCSTT_Frontend_Tracking {

    public function __construct() {
        add_action(
            'woocommerce_order_details_after_order_table',
            [$this, 'display_tracking_info'],
            10,
            1
        );
    }

    /**
     * Render the tracking table below the order-items table.
     */
    public function display_tracking_info(\WC_Order $order): void {
        $items = WCSTT_Tracking_Repository::get_tracking_items($order->get_id());
        if (empty($items)) {
            return;
        }

        // Resolve URLs for predefined providers.
        $items = array_map(static function (array $item): array {
            if (empty($item['custom_tracking_link']) && ! empty($item['tracking_provider'])) {
                $item['custom_tracking_link'] = WCSTT_Providers::get_tracking_url(
                    $item['tracking_provider'],
                    $item['tracking_number'] ?? ''
                );
            }
            return $item;
        }, $items);

        ?>
        <section class="woocommerce-shipment-tracking">
            <h2><?php esc_html_e('Tracking Information', 'wc-shipment-tracking-tool'); ?></h2>

            <table class="woocommerce-table woocommerce-table--shipment-tracking shop_table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Provider', 'wc-shipment-tracking-tool'); ?></th>
                        <th><?php esc_html_e('Tracking Number', 'wc-shipment-tracking-tool'); ?></th>
                        <th><?php esc_html_e('Date Shipped', 'wc-shipment-tracking-tool'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item) : ?>
                        <tr>
                            <td>
                                <?php echo esc_html($item['custom_tracking_provider'] ?: $item['tracking_provider']); ?>
                            </td>
                            <td>
                                <?php if (! empty($item['custom_tracking_link'])) : ?>
                                    <a href="<?php echo esc_url($item['custom_tracking_link']); ?>"
                                       target="_blank" rel="noopener">
                                        <?php echo esc_html($item['tracking_number']); ?>
                                    </a>
                                <?php else : ?>
                                    <?php echo esc_html($item['tracking_number']); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                if (! empty($item['date_shipped'])) {
                                    echo esc_html(date_i18n(get_option('date_format'), (int) $item['date_shipped']));
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
        <?php
    }
}
