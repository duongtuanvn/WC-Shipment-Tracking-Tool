# WC Shipment Tracking Tool

Plugin WooCommerce quản lý mã vận đơn (tracking) với đồng bộ PayPal tự động và nhận diện tracking từ order notes.

**Tương thích dữ liệu** với extension [WooCommerce Shipment Tracking](https://woocommerce.com/products/shipment-tracking/) chính thức (meta key `_wc_shipment_tracking_items`).

---

## Tính năng chính

- **Nhiều mã vận đơn** cho mỗi đơn hàng
- **9 hãng vận chuyển Mỹ** có sẵn: USPS, FedEx, UPS, DHL, DHL eCommerce, Amazon Logistics, OnTrac, LaserShip, Stamps.com
- **Hãng vận chuyển tùy chỉnh** với URL tracking riêng
- **Tự động chèn tracking** vào tất cả email gửi khách hàng (HTML + plain text)
- **Bảng tracking** trên trang My Account → Xem đơn hàng
- **REST API (v3)** tương thích extension chính thức
- **Tương thích HPOS** (High Performance Order Storage)
- **Chống trùng lặp** mã vận đơn
- **Mở rộng được** qua WordPress filters

---

## Đồng bộ Tracking lên PayPal

Tự động đẩy mã vận đơn lên PayPal khi thêm vào đơn hàng. PayPal Seller Protection yêu cầu thông tin tracking — tính năng này xử lý tự động.

| Tính năng | Chi tiết |
|---|---|
| API | PayPal REST API v1 (Shipping Trackers Batch) |
| Xác thực | OAuth2 với cache token |
| Hãng vận chuyển | Tự động map (USPS, FedEx, UPS, DHL, v.v.) |
| Hủy tracking | Tự động hủy trên PayPal khi xóa cục bộ |
| Gateway hỗ trợ | WooCommerce PayPal Payments (PPCP), PayPal Standard, và các gateway khác |
| Đơn không PayPal | Tự động bỏ qua, không lỗi |
| Log | Ghi trạng thái đồng bộ vào order notes (riêng tư) |

---

## Nhận diện Tracking từ Order Notes

Tự động phát hiện và import mã vận đơn từ order notes — hữu ích khi nhân viên hoặc plugin khác thêm tracking qua notes.

- **Nhận diện theo nhãn:** `Tracking: 9400...`, `Tracking #: 1Z...`
- **Nhận diện theo mẫu số:** USPS (`9400...`), UPS (`1Z...`), FedEx (kèm từ khóa)
- **Chống trùng lặp** tích hợp sẵn
- **Chống vòng lặp:** bỏ qua notes từ hệ thống và từ chính plugin
- **Cập nhật tức thì:** meta box Shipment Tracking tự động refresh khi notes được thêm

---

## Cài đặt

1. Upload thư mục `wc-shipment-tracking-tool` vào `/wp-content/plugins/`
2. Kích hoạt qua menu **Plugins**
3. Vào bất kỳ trang chỉnh sửa đơn hàng nào → meta box **Shipment Tracking** để thêm tracking
4. *(Tùy chọn)* Vào **WooCommerce → Settings → Shipment Tracking** để cấu hình đồng bộ PayPal và nhận diện order notes

### Cấu hình PayPal

1. Vào [developer.paypal.com](https://developer.paypal.com/) và tạo REST API app
2. Copy **Client ID** và **Client Secret**
3. Vào **WooCommerce → Settings → Shipment Tracking**
4. Bật **PayPal Sync**, chọn môi trường (Sandbox/Live), dán credentials
5. **Save**. Tracking sẽ tự động đồng bộ lên PayPal cho tất cả đơn hàng PayPal

---

## REST API

**Namespace:** `/wp-json/wc-shipment-tracking/v3/`

| Method | Endpoint | Mô tả |
|---|---|---|
| `POST` | `/orders/{id}/shipment-trackings` | Thêm tracking |
| `GET` | `/orders/{id}/shipment-trackings` | Liệt kê tất cả tracking |
| `GET` | `/orders/{id}/shipment-trackings/{number}` | Lấy tracking cụ thể |
| `DELETE` | `/orders/{id}/shipment-trackings/{number}` | Xóa tracking |

**Xác thực:** WooCommerce consumer key / secret (Basic Auth).

---

## Hooks & Filters

### Actions

| Hook | Mô tả | Params |
|---|---|---|
| `wcstt_tracking_added` | Sau khi tracking được thêm | `int $order_id`, `array $item` |
| `wcstt_tracking_removed` | Sau khi tracking bị xóa | `int $order_id`, `string $tracking_number` |

### Filters

| Filter | Mô tả |
|---|---|
| `wcstt_tracking_providers` | Thêm/sửa danh sách hãng vận chuyển |
| `wcstt_default_tracking_provider` | Đặt hãng vận chuyển mặc định |

---

## Sử dụng từ code

### Thêm hãng vận chuyển tùy chỉnh

```php
add_filter('wcstt_tracking_providers', function ($providers) {
    $providers['my_carrier'] = [
        'name' => 'My Carrier',
        'url'  => 'https://mycarrier.com/track/{tracking_number}',
    ];
    return $providers;
});
```

### Thêm tracking từ plugin khác

```php
wcstt_add_tracking_number(
    $order_id,
    '9400111899223100012345',
    'usps'
);
```

---

## Câu hỏi thường gặp

**Có hoạt động với WooCommerce PayPal Payments (PPCP) không?**
> Có. Plugin sử dụng `$order->get_transaction_id()` mà tất cả các gateway PayPal đều cung cấp.

**Đơn hàng không thanh toán PayPal thì sao?**
> Đồng bộ PayPal tự động bỏ qua. Không có lỗi.

**Có tương thích với extension WC Shipment Tracking chính thức không?**
> Có. Cùng meta key `_wc_shipment_tracking_items`, dữ liệu hoán đổi được.

**Có thể dùng đồng thời cả hai plugin không?**
> Nên dùng một trong hai. Cả hai ghi vào cùng meta key và sẽ xung đột trên giao diện admin.

---

## Cấu trúc files

```
wc-shipment-tracking-tool/
├── wc-shipment-tracking-tool.php        # Plugin chính, global helper
├── uninstall.php                        # Cleanup khi gỡ plugin
├── readme.txt                           # WordPress plugin readme
├── includes/
│   ├── class-wcstt-loader.php           # Singleton loader
│   ├── class-wcstt-tracking-repository.php  # CRUD tracking data (HPOS-safe)
│   ├── class-wcstt-providers.php        # Registry 9 hãng vận chuyển + custom
│   ├── class-wcstt-admin-meta-box.php   # Admin meta box (AJAX add/delete)
│   ├── class-wcstt-email-tracking.php   # Chèn tracking vào email
│   ├── class-wcstt-frontend-tracking.php # Hiển thị tracking trên My Account
│   ├── class-wcstt-rest-controller.php  # REST API v3
│   ├── class-wcstt-settings.php         # Trang cài đặt WooCommerce
│   ├── class-wcstt-paypal-sync.php      # Đồng bộ PayPal
│   └── class-wcstt-order-notes-parser.php # Nhận diện tracking từ notes
├── assets/
│   ├── css/wcstt-admin.css
│   └── js/wcstt-admin.js
└── templates/
    └── emails/tracking-info.php         # Template email tracking
```

---

## Changelog

### 1.1.0
- **Mới:** Đồng bộ Tracking lên PayPal (PayPal REST API v1)
- **Mới:** Nhận diện Tracking từ Order Notes
- **Mới:** Trang cài đặt tại WooCommerce → Settings → Shipment Tracking
- **Mới:** Map hãng vận chuyển PayPal (USPS, FedEx, UPS, DHL, v.v.)
- **Mới:** Cache token OAuth2 cho PayPal API
- **Mới:** Meta box tự động refresh khi thêm tracking từ order notes

### 1.0.0
- Phiên bản đầu tiên
- Meta box admin với AJAX thêm/xóa
- Hiển thị tracking trong tất cả email gửi khách hàng
- Bảng tracking trên trang My Account
- REST API v3 (thêm, liệt kê, lấy, xóa)
- Tương thích HPOS
- 9 hãng vận chuyển Mỹ có sẵn

---

## Yêu cầu

- WordPress 6.4+
- WooCommerce 8.0+
- PHP 8.0+

## License

GPL-2.0-or-later
