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
}
