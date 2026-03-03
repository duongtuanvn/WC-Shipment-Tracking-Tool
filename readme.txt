=== WC Shipment Tracking Tool ===
Contributors: duongtuanvn
Tags: woocommerce, shipment, tracking, shipping, orders, paypal
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add shipment tracking numbers to WooCommerce orders with PayPal sync and auto-import from order notes. Compatible with the official Shipment Tracking extension data format.

== Description ==

WC Shipment Tracking Tool lets you add one or more tracking numbers to any WooCommerce order. Tracking information is automatically included in customer emails and displayed on the My Account order page.

**Data compatible** with the official WooCommerce Shipment Tracking extension (`_wc_shipment_tracking_items` meta key).

= Features =

* Add multiple tracking numbers per order
* Predefined US carriers: USPS, FedEx, UPS, DHL, DHL eCommerce, Amazon Logistics, OnTrac, LaserShip, Stamps.com
* Custom provider support with custom tracking URLs
* Tracking info injected into all customer emails (HTML + plain text)
* Tracking table on My Account → View Order page
* REST API (v3) for programmatic access
* HPOS (High Performance Order Storage) compatible
* Anti-duplicate tracking number protection
* Extensible provider list via filters

= PayPal Tracking Sync =

Automatically push tracking numbers to PayPal when added to an order. PayPal Seller Protection requires tracking info — this feature handles it automatically.

* Uses PayPal REST API v1 (Shipping Trackers Batch)
* OAuth2 authentication with token caching
* Auto-maps carriers (USPS, FedEx, UPS, DHL, etc.)
* Cancels tracking on PayPal when removed locally
* Works with WooCommerce PayPal Payments (PPCP), PayPal Standard, and other PayPal gateways
* Skips non-PayPal orders automatically
* Logs sync status to private order notes

= Order Notes Parser =

Automatically detect and import tracking numbers from WooCommerce order notes — useful when staff or other plugins add tracking info via notes.

* Label-based detection: "Tracking: 9400...", "Tracking #: 1Z..."
* Carrier pattern recognition: USPS (9400...), UPS (1Z...), FedEx (with keyword)
* Duplicate protection built-in
* Anti-loop: ignores system and self-generated notes

= REST API =

Namespace: `/wp-json/wc-shipment-tracking/v3/`

Endpoints:

* `POST   /orders/{id}/shipment-trackings` — Add tracking
* `GET    /orders/{id}/shipment-trackings` — List all tracking
* `GET    /orders/{id}/shipment-trackings/{number}` — Get single
* `DELETE /orders/{id}/shipment-trackings/{number}` — Delete tracking

Authentication: WooCommerce consumer key / secret (Basic Auth).

= Hooks & Filters =

Actions:
* `wcstt_tracking_added` — Fires after tracking is added (params: order_id, item array)
* `wcstt_tracking_removed` — Fires after tracking is removed (params: order_id, tracking_number)

Filters:
* `wcstt_tracking_providers` — Add/modify provider list
* `wcstt_default_tracking_provider` — Set default provider

= Adding Custom Providers =

Use the `wcstt_tracking_providers` filter:

`
add_filter('wcstt_tracking_providers', function ($providers) {
    $providers['my_carrier'] = [
        'name' => 'My Carrier',
        'url'  => 'https://mycarrier.com/track/{tracking_number}',
    ];
    return $providers;
});
`

= Programmatic Usage =

Add tracking from another plugin:

`
wcstt_add_tracking_number(
    $order_id,
    '9400111899223100012345',
    'usps'
);
`

== Installation ==

1. Upload the `wc-shipment-tracking-tool` folder to `/wp-content/plugins/`.
2. Activate through the Plugins menu.
3. Go to any order edit screen → Shipment Tracking meta box to add tracking.
4. (Optional) Go to WooCommerce → Settings → Shipment Tracking to configure PayPal sync and order notes parser.

= PayPal Setup =

1. Go to [developer.paypal.com](https://developer.paypal.com/) and create a REST API app.
2. Copy the Client ID and Client Secret.
3. Go to WooCommerce → Settings → Shipment Tracking.
4. Enable PayPal Sync, select environment (Sandbox/Live), paste credentials.
5. Save. Tracking will now auto-sync to PayPal for all PayPal orders.

== Frequently Asked Questions ==

= Does this work with WooCommerce PayPal Payments (PPCP)? =

Yes. It uses `$order->get_transaction_id()` which all PayPal gateways populate.

= What happens with non-PayPal orders? =

PayPal sync silently skips orders without a PayPal transaction ID. No errors.

= Is it compatible with the official WC Shipment Tracking extension? =

Yes. It uses the same `_wc_shipment_tracking_items` meta key, so data is interchangeable.

= Can I use both this plugin and the official extension? =

You should use one or the other, not both. They write to the same meta key and would conflict on the admin UI.

== Changelog ==

= 1.1.0 =
* New: PayPal Tracking Sync — auto-push tracking to PayPal API.
* New: Order Notes Parser — auto-import tracking from order notes.
* New: Settings page under WooCommerce → Settings → Shipment Tracking.
* New: PayPal carrier mapping (USPS, FedEx, UPS, DHL, etc.).
* New: OAuth2 token caching for PayPal API.

= 1.0.0 =
* Initial release.
* Admin meta box with AJAX add/delete.
* Email tracking block for all customer emails.
* My Account order tracking table.
* REST API v3 (create, list, get, delete).
* HPOS compatibility.
* 9 predefined US carriers.
