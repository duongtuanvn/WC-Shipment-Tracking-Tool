<?php
/**
 * Order Notes Parser — auto-detect tracking numbers from WooCommerce order notes.
 *
 * Listens for new order notes and parses tracking info using
 * label-first regex, then carrier-specific pattern fallback.
 *
 * @package WCSTT
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

class WCSTT_Order_Notes_Parser {

    /** Carrier-specific tracking number patterns (high confidence only). */
    private const CARRIER_PATTERNS = [
        'usps' => '/\b(9[234]\d{20,22})\b/',       // 22-24 digit starting with 92/93/94
        'ups'  => '/\b(1Z[0-9A-Z]{16})\b/i',       // 1Z + 16 alphanumeric
    ];

    /** Keywords to detect carrier from note text (case-insensitive). */
    private const CARRIER_KEYWORDS = [
        'usps'             => ['usps', 'united states postal'],
        'ups'              => ['ups', 'united parcel'],
        'fedex'            => ['fedex', 'fed ex'],
        'dhl'              => ['dhl'],
        'dhl_ecommerce'    => ['dhl ecommerce', 'dhl global'],
        'amazon_logistics' => ['amazon logistics', 'amzn'],
    ];

    /**
     * FedEx patterns — only matched when "fedex" keyword is present in note
     * to avoid false positives with random 12-digit numbers.
     */
    private const FEDEX_PATTERNS = '/\b(\d{12}|\d{15}|\d{20})\b/';

    public function __construct() {
        add_action('woocommerce_order_note_added', [$this, 'on_note_added'], 10, 2);
    }

    /**
     * Parse newly added order note for tracking numbers.
     *
     * @param int      $note_id Note ID.
     * @param WC_Order $order   Order object.
     */
    public function on_note_added(int $note_id, \WC_Order $order): void {
        $note = wc_get_order_note($note_id);
        if (! $note || empty($note->content)) {
            return;
        }

        // Prevent infinite loop: skip notes added by this plugin or WooCommerce system.
        if ($this->is_system_note($note)) {
            return;
        }

        $content  = $note->content;
        $order_id = $order->get_id();
        $found    = [];

        // Step 1: Label-based extraction (highest confidence).
        // Matches: "Tracking: ABC123", "Tracking #: 9400...", "Tracking Number: 1Z..."
        if (preg_match_all('/(?:tracking\s*(?:number|#|no\.?)?)\s*[:=]\s*([A-Z0-9]{8,35})/i', $content, $matches)) {
            foreach ($matches[1] as $number) {
                $carrier = $this->detect_carrier($content);
                $found[$number] = $carrier;
            }
        }

        // Step 2: Carrier-specific pattern fallback (no label needed).
        foreach (self::CARRIER_PATTERNS as $carrier_slug => $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[1] as $number) {
                    if (! isset($found[$number])) {
                        $found[$number] = $carrier_slug;
                    }
                }
            }
        }

        // Step 3: FedEx patterns — only if "fedex" keyword present.
        if ($this->text_contains_keyword($content, 'fedex')) {
            if (preg_match_all(self::FEDEX_PATTERNS, $content, $matches)) {
                foreach ($matches[1] as $number) {
                    if (! isset($found[$number])) {
                        $found[$number] = 'fedex';
                    }
                }
            }
        }

        // Import each found tracking number.
        foreach ($found as $tracking_number => $provider) {
            $result = WCSTT_Tracking_Repository::add_tracking_item(
                $order_id,
                (string) $tracking_number,
                $provider
            );

            // Silently skip duplicates (WP_Error with code wcstt_duplicate_tracking).
            if (is_wp_error($result) && $result->get_error_code() !== 'wcstt_duplicate_tracking') {
                $order->add_order_note(
                    sprintf('WCSTT auto-import failed for %s: %s', $tracking_number, $result->get_error_message()),
                    false
                );
            }
        }
    }

    /* ------------------------------------------------------------------
     * Helpers
     * ----------------------------------------------------------------*/

    /**
     * Check if note was added by this plugin or WooCommerce system to prevent loops.
     */
    private function is_system_note(object $note): bool {
        $author = $note->added_by ?? '';

        // Skip WooCommerce system notes and our own notes.
        if ($author === 'WooCommerce' || $author === 'wcstt' || $author === 'system') {
            return true;
        }

        // Skip notes that look like our own output.
        $content = $note->content ?? '';
        if (str_starts_with($content, 'PayPal sync') ||
            str_starts_with($content, 'PayPal tracking') ||
            str_starts_with($content, 'PayPal cancel') ||
            str_starts_with($content, 'WCSTT auto-import')) {
            return true;
        }

        return false;
    }

    /**
     * Detect carrier from note text by keyword matching.
     *
     * @return string WCSTT provider slug, or empty string if unknown.
     */
    private function detect_carrier(string $text): string {
        $lower = strtolower($text);

        foreach (self::CARRIER_KEYWORDS as $slug => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($lower, $keyword)) {
                    return $slug;
                }
            }
        }

        return '';
    }

    /**
     * Check if text contains a specific keyword (case-insensitive).
     */
    private function text_contains_keyword(string $text, string $keyword): bool {
        return str_contains(strtolower($text), strtolower($keyword));
    }
}
