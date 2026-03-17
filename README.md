# WC Shipment Tracking Tool

WooCommerce shipment tracking plugin with automatic PayPal sync and order notes parsing. Data-compatible with the official [WooCommerce Shipment Tracking](https://woocommerce.com/products/shipment-tracking/) extension.

## Features

- Multiple tracking numbers per order
- 9 built-in US carriers: USPS, FedEx, UPS, DHL, DHL eCommerce, Amazon Logistics, OnTrac, LaserShip, Stamps.com
- Custom carriers with custom tracking URLs
- Tracking info injected into all customer emails (HTML + plain text)
- Tracking table on My Account order view
- REST API (v3) compatible with the official extension
- HPOS compatible
- Duplicate tracking prevention

## PayPal Sync

Automatically pushes tracking numbers to PayPal when added to an order — required for PayPal Seller Protection.

- Uses PayPal REST API v1 (Shipping Trackers Batch)
- OAuth2 authentication with token caching
- Auto-maps carriers (USPS, FedEx, UPS, DHL, etc.)
- Removes tracking from PayPal when deleted locally
- Works with WooCommerce PayPal Payments (PPCP), PayPal Standard, and other PayPal gateways
- Non-PayPal orders are silently skipped

## Order Notes Parser

Automatically detects and imports tracking numbers from order notes — useful when staff or other plugins add tracking via notes.

- Recognizes labeled formats: `Tracking: 9400...`, `Tracking #: 1Z...`
- Pattern-based detection: USPS (`9400...`), UPS (`1Z...`), FedEx (with keyword)
- Built-in duplicate and loop prevention

## Requirements

- WordPress 6.4+
- WooCommerce 8.0+
- PHP 8.0+

## Installation

1. Upload the plugin folder to `wp-content/plugins/`
2. Activate via **Plugins** menu
3. Open any order edit page to see the **Shipment Tracking** meta box
4. (Optional) Go to **WooCommerce > Settings > Shipment Tracking** for PayPal sync and order notes settings

### PayPal Setup

1. Create a REST API app at [developer.paypal.com](https://developer.paypal.com/)
2. Go to **WooCommerce > Settings > Shipment Tracking**
3. Enable PayPal Sync, select environment (Sandbox/Live), paste Client ID and Secret
4. Save — tracking will auto-sync to PayPal for all PayPal orders

## REST API

**Namespace:** `/wp-json/wc-shipment-tracking/v3/`

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/orders/{id}/shipment-trackings` | Add tracking |
| `GET` | `/orders/{id}/shipment-trackings` | List all tracking |
| `GET` | `/orders/{id}/shipment-trackings/{number}` | Get specific tracking |
| `DELETE` | `/orders/{id}/shipment-trackings/{number}` | Delete tracking |

**Auth:** WooCommerce consumer key/secret (Basic Auth).

## Usage from Code

```php
// Add tracking to an order
wcstt_add_tracking_number($order_id, '9400111899223100012345', 'usps');

// Add a custom carrier
add_filter('wcstt_tracking_providers', function ($providers) {
    $providers['my_carrier'] = [
        'name' => 'My Carrier',
        'url'  => 'https://mycarrier.com/track/{tracking_number}',
    ];
    return $providers;
});
```

## Hooks

### Actions

| Hook | Params |
|---|---|
| `wcstt_tracking_added` | `int $order_id`, `array $item` |
| `wcstt_tracking_removed` | `int $order_id`, `string $tracking_number` |

### Filters

| Filter | Description |
|---|---|
| `wcstt_tracking_providers` | Add/modify carrier list |
| `wcstt_default_tracking_provider` | Set default carrier |

## Project Structure

```
wc-shipment-tracking-tool/
├── wc-shipment-tracking-tool.php           # Main plugin file, global helper
├── uninstall.php                           # Cleanup on uninstall
├── includes/
│   ├── class-wcstt-loader.php              # Singleton loader
│   ├── class-wcstt-tracking-repository.php # CRUD tracking data (HPOS-safe)
│   ├── class-wcstt-providers.php           # 9 carriers + custom
│   ├── class-wcstt-admin-meta-box.php      # Admin meta box (AJAX add/delete)
│   ├── class-wcstt-email-tracking.php      # Inject tracking into emails
│   ├── class-wcstt-frontend-tracking.php   # My Account tracking table
│   ├── class-wcstt-rest-controller.php     # REST API v3
│   ├── class-wcstt-settings.php            # WooCommerce settings tab
│   ├── class-wcstt-paypal-sync.php         # PayPal sync
│   └── class-wcstt-order-notes-parser.php  # Tracking detection from notes
├── assets/
│   ├── css/wcstt-admin.css
│   └── js/wcstt-admin.js
└── templates/
    └── emails/tracking-info.php            # Email tracking template
```

## Compatibility Note

This plugin uses the same meta key (`_wc_shipment_tracking_items`) as the official WooCommerce Shipment Tracking extension. Data is interchangeable, but **do not run both plugins simultaneously** — they will conflict on the admin UI.

## License

GPL-2.0-or-later

## Author

**Tuan Duong** — [tuan.digital](https://tuan.digital) | [@duongtuanvn](https://github.com/duongtuanvn)
