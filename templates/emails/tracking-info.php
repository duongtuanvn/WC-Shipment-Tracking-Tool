<?php
/**
 * Email template — Tracking Information block.
 *
 * Injected before the order table in customer emails.
 *
 * @var WC_Order $order
 * @var array    $tracking_items
 *
 * @package WCSTT
 */

defined('ABSPATH') || exit;
?>

<div style="margin-bottom:20px;border:1px solid #e0e0e0;border-radius:4px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;">
    <div style="background:#f7f7f7;padding:12px 16px;border-bottom:1px solid #e0e0e0;">
        <h3 style="margin:0;font-size:16px;color:#333;">
            <?php esc_html_e('Tracking Information', 'wc-shipment-tracking-tool'); ?>
        </h3>
    </div>

    <table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="background:#fafafa;">
                <th style="padding:10px 16px;text-align:left;font-size:13px;color:#666;border-bottom:1px solid #eee;">
                    <?php esc_html_e('Provider', 'wc-shipment-tracking-tool'); ?>
                </th>
                <th style="padding:10px 16px;text-align:left;font-size:13px;color:#666;border-bottom:1px solid #eee;">
                    <?php esc_html_e('Tracking Number', 'wc-shipment-tracking-tool'); ?>
                </th>
                <th style="padding:10px 16px;text-align:left;font-size:13px;color:#666;border-bottom:1px solid #eee;">
                    <?php esc_html_e('Date Shipped', 'wc-shipment-tracking-tool'); ?>
                </th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tracking_items as $item) : ?>
                <?php
                $provider = esc_html($item['custom_tracking_provider'] ?: $item['tracking_provider']);
                $number   = esc_html($item['tracking_number']);
                $link     = ! empty($item['custom_tracking_link']) ? esc_url($item['custom_tracking_link']) : '';
                $date     = ! empty($item['date_shipped'])
                    ? esc_html(date_i18n('M j, Y', (int) $item['date_shipped']))
                    : '—';
                ?>
                <tr>
                    <td style="padding:10px 16px;font-size:14px;color:#333;border-bottom:1px solid #f0f0f0;">
                        <?php echo $provider; ?>
                    </td>
                    <td style="padding:10px 16px;font-size:14px;border-bottom:1px solid #f0f0f0;">
                        <?php if ($link) : ?>
                            <a href="<?php echo $link; ?>" target="_blank" rel="noopener"
                               style="color:#0073aa;text-decoration:underline;">
                                <?php echo $number; ?>
                            </a>
                        <?php else : ?>
                            <?php echo $number; ?>
                        <?php endif; ?>
                    </td>
                    <td style="padding:10px 16px;font-size:14px;color:#666;border-bottom:1px solid #f0f0f0;">
                        <?php echo $date; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
