<?php
/**
 * REST API controller — /wc-shipment-tracking/v3/orders/{order_id}/shipment-trackings.
 *
 * Compatible with the official WooCommerce Shipment Tracking extension namespace.
 * Uses WooCommerce consumer-key / secret authentication automatically.
 *
 * @package WCSTT
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

class WCSTT_REST_Controller extends \WP_REST_Controller {

    protected $namespace = 'wc-shipment-tracking/v3';
    protected $rest_base = 'orders';

    /* ------------------------------------------------------------------
     * Route registration
     * ----------------------------------------------------------------*/

    public function register_routes(): void {
        // Collection: list + create.
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<order_id>\d+)/shipment-trackings', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_items'],
                'permission_callback' => [$this, 'read_permission_check'],
                'args'                => [
                    'order_id' => ['type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint'],
                ],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'create_item'],
                'permission_callback' => [$this, 'write_permission_check'],
                'args'                => $this->get_create_args(),
            ],
            'schema' => [$this, 'get_public_item_schema'],
        ]);

        // Single: retrieve + delete.
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<order_id>\d+)/shipment-trackings/(?P<tracking_number>[^/]+)', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_item'],
                'permission_callback' => [$this, 'read_permission_check'],
            ],
            [
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'delete_item'],
                'permission_callback' => [$this, 'write_permission_check'],
            ],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
    }

    /* ------------------------------------------------------------------
     * Permission callbacks
     * ----------------------------------------------------------------*/

    public function read_permission_check(\WP_REST_Request $request): bool {
        return current_user_can('read_shop_orders');
    }

    public function write_permission_check(\WP_REST_Request $request): bool {
        return current_user_can('manage_woocommerce');
    }

    /* ------------------------------------------------------------------
     * GET collection
     * ----------------------------------------------------------------*/

    public function get_items($request): \WP_REST_Response|\WP_Error {
        $order_id = (int) $request->get_param('order_id');
        $order    = wc_get_order($order_id);

        if (! $order) {
            return new \WP_Error('wcstt_order_not_found', __('Order not found.', 'wc-shipment-tracking-tool'), ['status' => 404]);
        }

        $items = WCSTT_Tracking_Repository::get_tracking_items($order_id);
        $data  = array_map([$this, 'prepare_item'], $items);

        return rest_ensure_response($data);
    }

    /* ------------------------------------------------------------------
     * POST create
     * ----------------------------------------------------------------*/

    public function create_item($request): \WP_REST_Response|\WP_Error {
        $order_id = (int) $request->get_param('order_id');

        $result = WCSTT_Tracking_Repository::add_tracking_item(
            $order_id,
            sanitize_text_field($request->get_param('tracking_number') ?? ''),
            sanitize_text_field($request->get_param('tracking_provider') ?? ''),
            sanitize_text_field($request->get_param('custom_tracking_provider') ?? ''),
            esc_url_raw($request->get_param('custom_tracking_link') ?? ''),
            $this->parse_date($request->get_param('date_shipped'))
        );

        if (is_wp_error($result)) {
            $result->add_data(['status' => 400]);
            return $result;
        }

        $response = rest_ensure_response($this->prepare_item($result));
        $response->set_status(201);

        return $response;
    }

    /* ------------------------------------------------------------------
     * GET single
     * ----------------------------------------------------------------*/

    public function get_item($request): \WP_REST_Response|\WP_Error {
        $order_id        = (int) $request->get_param('order_id');
        $tracking_number = sanitize_text_field(urldecode($request->get_param('tracking_number') ?? ''));

        $order = wc_get_order($order_id);
        if (! $order) {
            return new \WP_Error('wcstt_order_not_found', __('Order not found.', 'wc-shipment-tracking-tool'), ['status' => 404]);
        }

        $items      = WCSTT_Tracking_Repository::get_tracking_items($order_id);
        $normalized = strtoupper(trim($tracking_number));

        foreach ($items as $item) {
            if (strtoupper(trim($item['tracking_number'] ?? '')) === $normalized) {
                return rest_ensure_response($this->prepare_item($item));
            }
        }

        return new \WP_Error('wcstt_tracking_not_found', __('Tracking number not found.', 'wc-shipment-tracking-tool'), ['status' => 404]);
    }

    /* ------------------------------------------------------------------
     * DELETE single
     * ----------------------------------------------------------------*/

    public function delete_item($request): \WP_REST_Response|\WP_Error {
        $order_id        = (int) $request->get_param('order_id');
        $tracking_number = sanitize_text_field(urldecode($request->get_param('tracking_number') ?? ''));

        $result = WCSTT_Tracking_Repository::remove_tracking_item($order_id, $tracking_number);

        if (is_wp_error($result)) {
            $result->add_data(['status' => 404]);
            return $result;
        }

        return rest_ensure_response([
            'deleted'         => true,
            'tracking_number' => $tracking_number,
        ]);
    }

    /* ------------------------------------------------------------------
     * Schema
     * ----------------------------------------------------------------*/

    public function get_public_item_schema(): array {
        if ($this->schema) {
            return $this->schema;
        }

        $this->schema = [
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'shipment_tracking',
            'type'       => 'object',
            'properties' => [
                'tracking_number'          => ['description' => 'Carrier tracking number', 'type' => 'string'],
                'tracking_provider'        => ['description' => 'Provider slug (predefined)', 'type' => 'string'],
                'custom_tracking_provider' => ['description' => 'Provider display name', 'type' => 'string'],
                'custom_tracking_link'     => ['description' => 'Tracking page URL', 'type' => 'string', 'format' => 'uri'],
                'tracking_link'            => ['description' => 'Resolved tracking URL (read-only)', 'type' => 'string', 'format' => 'uri', 'readonly' => true],
                'date_shipped'             => ['description' => 'Ship date (ISO 8601 or Unix)', 'type' => 'string'],
            ],
        ];

        return $this->schema;
    }

    /* ------------------------------------------------------------------
     * Helpers
     * ----------------------------------------------------------------*/

    private function get_create_args(): array {
        return [
            'order_id'                 => ['type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint'],
            'tracking_number'          => ['type' => 'string',  'required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            'tracking_provider'        => ['type' => 'string',  'default' => '',    'sanitize_callback' => 'sanitize_text_field'],
            'custom_tracking_provider' => ['type' => 'string',  'default' => '',    'sanitize_callback' => 'sanitize_text_field'],
            'custom_tracking_link'     => ['type' => 'string',  'default' => '',    'format' => 'uri'],
            'date_shipped'             => ['type' => 'string',  'default' => ''],
        ];
    }

    /**
     * Format a single tracking item for REST response.
     */
    private function prepare_item(array $item): array {
        // Resolve tracking link.
        $link = $item['custom_tracking_link'] ?? '';
        if ($link === '' && ! empty($item['tracking_provider'])) {
            $link = WCSTT_Providers::get_tracking_url(
                $item['tracking_provider'],
                $item['tracking_number'] ?? ''
            );
        }

        $date_shipped = '';
        if (! empty($item['date_shipped'])) {
            $date_shipped = gmdate('c', (int) $item['date_shipped']);
        }

        return [
            'tracking_number'          => $item['tracking_number'] ?? '',
            'tracking_provider'        => $item['tracking_provider'] ?? '',
            'custom_tracking_provider' => $item['custom_tracking_provider'] ?? '',
            'custom_tracking_link'     => $item['custom_tracking_link'] ?? '',
            'tracking_link'            => $link,
            'date_shipped'             => $date_shipped,
        ];
    }

    /**
     * Parse a date string into a Unix timestamp, or null.
     */
    private function parse_date(mixed $value): ?int {
        if (empty($value)) {
            return null;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        $ts = strtotime((string) $value);
        return $ts !== false ? $ts : null;
    }
}
