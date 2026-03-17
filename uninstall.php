<?php
/**
 * Uninstall handler — runs when the plugin is deleted via WP admin.
 *
 * NOTE: We do NOT delete _wc_shipment_tracking_items order meta because
 * that data belongs to orders and may be used by other integrations.
 * Only plugin-specific options (if any) are cleaned up here.
 *
 * @package WCSTT
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

// Currently no plugin-specific options to remove.
// Future: delete_option('wcstt_settings');
