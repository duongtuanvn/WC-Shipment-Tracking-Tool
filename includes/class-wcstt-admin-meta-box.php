<?php
/**
 * Admin meta box on the order-edit screen — add / view / delete tracking items.
 *
 * @package WCSTT
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

class WCSTT_Admin_Meta_Box {

    public function __construct() {
        add_action('add_meta_boxes', [$this, 'register_meta_box']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        // AJAX handlers.
        add_action('wp_ajax_wcstt_add_tracking', [$this, 'ajax_add_tracking']);
        add_action('wp_ajax_wcstt_delete_tracking', [$this, 'ajax_delete_tracking']);
    }

    /* ------------------------------------------------------------------
     * Meta box registration (HPOS + legacy)
     * ----------------------------------------------------------------*/

    public function register_meta_box(): void {
        $screen = $this->get_order_screen_id();

        add_meta_box(
            'wcstt-shipment-tracking',
            __('Shipment Tracking', 'wc-shipment-tracking-tool'),
            [$this, 'render'],
            $screen,
            'side',
            'default'
        );
    }

    /* ------------------------------------------------------------------
     * Asset enqueue (only on order edit)
     * ----------------------------------------------------------------*/

    public function enqueue_assets(string $hook_suffix): void {
        $screen = get_current_screen();
        if (! $screen || ! $this->is_order_edit_screen($screen->id)) {
            return;
        }

        wp_enqueue_style(
            'wcstt-admin',
            WCSTT_URL . 'assets/css/wcstt-admin.css',
            [],
            WCSTT_VERSION
        );

        wp_enqueue_script(
            'wcstt-admin',
            WCSTT_URL . 'assets/js/wcstt-admin.js',
            ['jquery'],
            WCSTT_VERSION,
            true
        );

        wp_localize_script('wcstt-admin', 'wcsttAdmin', [
            'ajaxUrl'   => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce('wcstt_tracking'),
            'orderId'   => $this->get_current_order_id(),
            'providers' => WCSTT_Providers::get_providers_dropdown(),
            'i18n'      => [
                'confirmDelete' => __('Remove this tracking number?', 'wc-shipment-tracking-tool'),
                'error'         => __('An error occurred. Please try again.', 'wc-shipment-tracking-tool'),
            ],
        ]);
    }

    /* ------------------------------------------------------------------
     * Render
     * ----------------------------------------------------------------*/

    public function render($post_or_order): void {
        $order = ($post_or_order instanceof \WP_Post)
            ? wc_get_order($post_or_order->ID)
            : $post_or_order;

        if (! $order) {
            return;
        }

        $items     = WCSTT_Tracking_Repository::get_tracking_items($order->get_id());
        $providers = WCSTT_Providers::get_providers_dropdown();

        // --- Existing items ---
        echo '<div id="wcstt-items">';
        if (! empty($items)) {
            foreach ($items as $item) {
                $this->render_item_row($item);
            }
        } else {
            echo '<p class="wcstt-empty">' . esc_html__('No tracking numbers yet.', 'wc-shipment-tracking-tool') . '</p>';
        }
        echo '</div>';

        // --- Add-tracking form ---
        echo '<div id="wcstt-add-form">';
        echo '<p><strong>' . esc_html__('Add Tracking', 'wc-shipment-tracking-tool') . '</strong></p>';

        // Provider dropdown.
        echo '<select id="wcstt-provider" class="wcstt-field">';
        echo '<option value="">' . esc_html__('— Select provider —', 'wc-shipment-tracking-tool') . '</option>';
        foreach ($providers as $slug => $name) {
            echo '<option value="' . esc_attr($slug) . '">' . esc_html($name) . '</option>';
        }
        echo '</select>';

        // Custom provider fields (hidden by default).
        echo '<input type="text" id="wcstt-custom-provider" class="wcstt-field wcstt-custom-field" '
            . 'placeholder="' . esc_attr__('Custom provider name', 'wc-shipment-tracking-tool') . '" style="display:none;" />';
        echo '<input type="url" id="wcstt-custom-link" class="wcstt-field wcstt-custom-field" '
            . 'placeholder="' . esc_attr__('Tracking page URL', 'wc-shipment-tracking-tool') . '" style="display:none;" />';

        // Tracking number.
        echo '<input type="text" id="wcstt-number" class="wcstt-field" '
            . 'placeholder="' . esc_attr__('Tracking number', 'wc-shipment-tracking-tool') . '" />';

        // Date shipped.
        echo '<input type="date" id="wcstt-date" class="wcstt-field" value="' . esc_attr(wp_date('Y-m-d')) . '" />';

        // Submit button.
        echo '<button type="button" id="wcstt-add-btn" class="button button-primary wcstt-field">'
            . esc_html__('Add Tracking Number', 'wc-shipment-tracking-tool') . '</button>';

        echo '</div>'; // #wcstt-add-form
    }

    /* ------------------------------------------------------------------
     * AJAX: add tracking
     * ----------------------------------------------------------------*/

    public function ajax_add_tracking(): void {
        check_ajax_referer('wcstt_tracking', 'nonce');

        if (! current_user_can('edit_shop_orders')) {
            wp_send_json_error(['message' => __('Permission denied.', 'wc-shipment-tracking-tool')]);
        }

        $order_id         = absint($_POST['order_id'] ?? 0);
        $tracking_number  = sanitize_text_field(wp_unslash($_POST['tracking_number'] ?? ''));
        $provider         = sanitize_key($_POST['provider'] ?? '');
        $custom_provider  = sanitize_text_field(wp_unslash($_POST['custom_provider'] ?? ''));
        $custom_link      = esc_url_raw(wp_unslash($_POST['custom_link'] ?? ''));
        $date_raw         = sanitize_text_field(wp_unslash($_POST['date_shipped'] ?? ''));
        $date_shipped     = $date_raw !== '' ? (int) strtotime($date_raw) : null;

        $result = WCSTT_Tracking_Repository::add_tracking_item(
            $order_id,
            $tracking_number,
            $provider,
            $custom_provider,
            $custom_link,
            $date_shipped ?: null
        );

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        // Return rendered HTML for the new item row.
        ob_start();
        $this->render_item_row($result);
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }

    /* ------------------------------------------------------------------
     * AJAX: delete tracking
     * ----------------------------------------------------------------*/

    public function ajax_delete_tracking(): void {
        check_ajax_referer('wcstt_tracking', 'nonce');

        if (! current_user_can('edit_shop_orders')) {
            wp_send_json_error(['message' => __('Permission denied.', 'wc-shipment-tracking-tool')]);
        }

        $order_id        = absint($_POST['order_id'] ?? 0);
        $tracking_number = sanitize_text_field(wp_unslash($_POST['tracking_number'] ?? ''));

        $result = WCSTT_Tracking_Repository::remove_tracking_item($order_id, $tracking_number);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success();
    }

    /* ------------------------------------------------------------------
     * Helpers
     * ----------------------------------------------------------------*/

    /**
     * Render one tracking-item row (reused for initial render + AJAX response).
     */
    private function render_item_row(array $item): void {
        $provider_name = $item['custom_tracking_provider'] ?: $item['tracking_provider'];
        $link          = $item['custom_tracking_link'] ?? '';
        $number        = $item['tracking_number'] ?? '';
        $date          = ! empty($item['date_shipped'])
            ? date_i18n(get_option('date_format'), (int) $item['date_shipped'])
            : '';

        echo '<div class="wcstt-item">';
        echo '<span class="wcstt-item-provider">' . esc_html($provider_name) . '</span>';

        if ($link !== '') {
            echo ' <a href="' . esc_url($link) . '" target="_blank" rel="noopener">'
                . esc_html($number) . '</a>';
        } else {
            echo ' <span>' . esc_html($number) . '</span>';
        }

        if ($date !== '') {
            echo ' <span class="wcstt-item-date">(' . esc_html($date) . ')</span>';
        }

        echo ' <button type="button" class="wcstt-delete" data-number="'
            . esc_attr($number) . '" title="' . esc_attr__('Delete', 'wc-shipment-tracking-tool')
            . '">&times;</button>';
        echo '</div>';
    }

    /**
     * Get the correct screen ID for the order-edit page (HPOS vs legacy).
     */
    private function get_order_screen_id(): string {
        if (class_exists(\Automattic\WooCommerce\Utilities\OrderUtil::class)
            && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
            return wc_get_page_screen_id('shop-order');
        }
        return 'shop_order';
    }

    private function is_order_edit_screen(string $screen_id): bool {
        return $screen_id === $this->get_order_screen_id();
    }

    private function get_current_order_id(): int {
        // HPOS passes order ID via GET 'id'.
        if (isset($_GET['id'])) {
            return absint($_GET['id']);
        }
        // Legacy uses global $post.
        global $post;
        return $post ? (int) $post->ID : 0;
    }
}
