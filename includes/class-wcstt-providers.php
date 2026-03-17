<?php
/**
 * Provider registry — predefined US carriers + custom provider support.
 *
 * @package WCSTT
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

class WCSTT_Providers {

    /**
     * Get all registered providers (filterable).
     *
     * @return array<string, array{name: string, url: string}>
     */
    public static function get_providers(): array {
        $providers = [
            'usps'             => [
                'name' => 'USPS',
                'url'  => 'https://tools.usps.com/go/TrackConfirmAction?tLabels={tracking_number}',
            ],
            'fedex'            => [
                'name' => 'FedEx',
                'url'  => 'https://www.fedex.com/fedextrack/?trknbr={tracking_number}',
            ],
            'ups'              => [
                'name' => 'UPS',
                'url'  => 'https://www.ups.com/track?tracknum={tracking_number}',
            ],
            'dhl'              => [
                'name' => 'DHL',
                'url'  => 'https://www.dhl.com/us-en/home/tracking.html?tracking-id={tracking_number}',
            ],
            'dhl_ecommerce'    => [
                'name' => 'DHL eCommerce',
                'url'  => 'https://webtrack.dhlglobalmail.com/?trackingnumber={tracking_number}',
            ],
            'amazon_logistics' => [
                'name' => 'Amazon Logistics',
                'url'  => 'https://track.amazon.com/tracking/{tracking_number}',
            ],
            'ontrac'           => [
                'name' => 'OnTrac',
                'url'  => 'https://www.ontrac.com/tracking/?number={tracking_number}',
            ],
            'lasership'        => [
                'name' => 'LaserShip',
                'url'  => 'https://www.lasership.com/track/{tracking_number}',
            ],
            'stamps_com'       => [
                'name' => 'Stamps.com',
                'url'  => 'https://tools.usps.com/go/TrackConfirmAction?tLabels={tracking_number}',
            ],
        ];

        /** @var array<string, array{name: string, url: string}> */
        return apply_filters('wcstt_tracking_providers', $providers);
    }

    /**
     * Build a tracking URL for a predefined provider.
     */
    public static function get_tracking_url(string $provider_slug, string $tracking_number): string {
        $providers = self::get_providers();
        if (! isset($providers[ $provider_slug ])) {
            return '';
        }
        return str_replace(
            '{tracking_number}',
            rawurlencode($tracking_number),
            $providers[ $provider_slug ]['url']
        );
    }

    /**
     * Get provider display name by slug.
     */
    public static function get_provider_name(string $provider_slug): string {
        $providers = self::get_providers();
        return $providers[ $provider_slug ]['name'] ?? $provider_slug;
    }

    /**
     * Get default provider slug (filterable).
     */
    public static function get_default_provider(): string {
        /** @var string */
        return apply_filters('wcstt_default_tracking_provider', '');
    }

    /**
     * Format providers for an HTML <select> dropdown: slug => name.
     */
    public static function get_providers_dropdown(): array {
        $dropdown = [];
        foreach (self::get_providers() as $slug => $data) {
            $dropdown[ $slug ] = $data['name'];
        }
        $dropdown['custom'] = __('Custom Provider', 'wc-shipment-tracking-tool');
        return $dropdown;
    }
}
