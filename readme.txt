=== WC Shipment Tracking Tool ===
Contributors: duongtuanvn
Tags: woocommerce, shipment, tracking, shipping, orders, paypal
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Them ma van don (tracking) vao don hang WooCommerce voi dong bo PayPal va tu dong nhan dien tu order notes. Tuong thich dinh dang du lieu cua extension Shipment Tracking chinh thuc.

== Mo ta ==

WC Shipment Tracking Tool cho phep them mot hoac nhieu ma van don vao bat ky don hang WooCommerce nao. Thong tin tracking tu dong hien thi trong email gui khach hang va trang My Account cua khach.

**Tuong thich du lieu** voi extension WooCommerce Shipment Tracking chinh thuc (meta key `_wc_shipment_tracking_items`).

= Tinh nang chinh =

* Them nhieu ma van don cho moi don hang
* 9 hang van chuyen My co san: USPS, FedEx, UPS, DHL, DHL eCommerce, Amazon Logistics, OnTrac, LaserShip, Stamps.com
* Ho tro hang van chuyen tu dinh nghia voi URL tracking tuy chinh
* Tu dong chen tracking vao tat ca email gui khach hang (HTML + plain text)
* Hien thi bang tracking tren trang My Account → Xem don hang
* REST API (v3) de truy cap tu dong (lap trinh)
* Tuong thich HPOS (High Performance Order Storage)
* Chong trung lap ma van don
* Danh sach hang van chuyen mo rong duoc qua filters
* Meta box cap nhat tuc thi khi them tracking tu order notes (khong can reload trang)

= Dong bo Tracking len PayPal =

Tu dong day ma van don len PayPal khi them vao don hang. PayPal Seller Protection yeu cau thong tin tracking — tinh nang nay xu ly tu dong.

* Su dung PayPal REST API v1 (Shipping Trackers Batch)
* Xac thuc OAuth2 voi cache token
* Tu dong map hang van chuyen (USPS, FedEx, UPS, DHL, v.v.)
* Huy tracking tren PayPal khi xoa tracking cuc bo
* Hoat dong voi WooCommerce PayPal Payments (PPCP), PayPal Standard, va cac gateway PayPal khac
* Tu dong bo qua don hang khong thanh toan qua PayPal
* Ghi log trang thai dong bo vao order notes (rieng tu)

= Nhan dien Tracking tu Order Notes =

Tu dong phat hien va import ma van don tu order notes cua WooCommerce — huu ich khi nhan vien hoac plugin khac them tracking qua notes.

* Nhan dien theo nhan: "Tracking: 9400...", "Tracking #: 1Z..."
* Nhan dien theo mau so hang van chuyen: USPS (9400...), UPS (1Z...), FedEx (kem tu khoa)
* Chong trung lap tich hop san
* Chong vong lap: bo qua notes tu he thong va tu chinh plugin
* Meta box Shipment Tracking tu dong cap nhat khi notes duoc them (khong can F5)

= REST API =

Namespace: `/wp-json/wc-shipment-tracking/v3/`

Endpoints:

* `POST   /orders/{id}/shipment-trackings` — Them tracking
* `GET    /orders/{id}/shipment-trackings` — Liet ke tat ca tracking
* `GET    /orders/{id}/shipment-trackings/{number}` — Lay tracking cu the
* `DELETE /orders/{id}/shipment-trackings/{number}` — Xoa tracking

Xac thuc: WooCommerce consumer key / secret (Basic Auth).

= Hooks & Filters =

Actions:
* `wcstt_tracking_added` — Chay sau khi tracking duoc them (params: order_id, item array)
* `wcstt_tracking_removed` — Chay sau khi tracking bi xoa (params: order_id, tracking_number)

Filters:
* `wcstt_tracking_providers` — Them/sua danh sach hang van chuyen
* `wcstt_default_tracking_provider` — Dat hang van chuyen mac dinh

= Them hang van chuyen tu dinh nghia =

Su dung filter `wcstt_tracking_providers`:

`
add_filter('wcstt_tracking_providers', function ($providers) {
    $providers['my_carrier'] = [
        'name' => 'My Carrier',
        'url'  => 'https://mycarrier.com/track/{tracking_number}',
    ];
    return $providers;
});
`

= Su dung tu code (Programmatic) =

Them tracking tu plugin khac:

`
wcstt_add_tracking_number(
    $order_id,
    '9400111899223100012345',
    'usps'
);
`

== Cai dat ==

1. Upload thu muc `wc-shipment-tracking-tool` vao `/wp-content/plugins/`.
2. Kich hoat qua menu Plugins.
3. Vao bat ky trang chinh sua don hang nao → meta box Shipment Tracking de them tracking.
4. (Tuy chon) Vao WooCommerce → Settings → Shipment Tracking de cau hinh dong bo PayPal va nhan dien order notes.

= Cau hinh PayPal =

1. Vao [developer.paypal.com](https://developer.paypal.com/) va tao REST API app.
2. Copy Client ID va Client Secret.
3. Vao WooCommerce → Settings → Shipment Tracking.
4. Bat PayPal Sync, chon moi truong (Sandbox/Live), dan credentials.
5. Save. Tracking se tu dong dong bo len PayPal cho tat ca don hang PayPal.

== Cau hoi thuong gap ==

= Co hoat dong voi WooCommerce PayPal Payments (PPCP) khong? =

Co. Plugin su dung `$order->get_transaction_id()` ma tat ca cac gateway PayPal deu cung cap.

= Don hang khong thanh toan PayPal thi sao? =

Dong bo PayPal tu dong bo qua don hang khong co PayPal transaction ID. Khong co loi.

= Co tuong thich voi extension WC Shipment Tracking chinh thuc khong? =

Co. Plugin su dung cung meta key `_wc_shipment_tracking_items`, nen du lieu co the hoan doi.

= Co the dung dong thoi ca hai plugin khong? =

Nen dung mot trong hai, khong dung dong thoi. Ca hai ghi vao cung meta key va se xung dot tren giao dien admin.

== Changelog ==

= 1.1.0 =
* Moi: Dong bo Tracking len PayPal — tu dong day tracking len PayPal API.
* Moi: Nhan dien Tracking tu Order Notes — tu dong import tracking tu order notes.
* Moi: Trang cai dat tai WooCommerce → Settings → Shipment Tracking.
* Moi: Map hang van chuyen PayPal (USPS, FedEx, UPS, DHL, v.v.).
* Moi: Cache token OAuth2 cho PayPal API.
* Moi: Meta box tu dong refresh khi them tracking tu order notes.

= 1.0.0 =
* Phien ban dau tien.
* Meta box admin voi AJAX them/xoa.
* Hien thi tracking trong tat ca email gui khach hang.
* Bang tracking tren trang My Account.
* REST API v3 (them, liet ke, lay, xoa).
* Tuong thich HPOS.
* 9 hang van chuyen My co san.
