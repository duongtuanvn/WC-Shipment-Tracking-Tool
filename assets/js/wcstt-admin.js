/**
 * Admin meta-box JavaScript for WC Shipment Tracking Tool.
 *
 * Handles provider dropdown toggle, AJAX add / delete of tracking items.
 */
(function ($) {
    'use strict';

    var $items    = $('#wcstt-items');
    var $form     = $('#wcstt-add-form');
    var $provider = $('#wcstt-provider');
    var $addBtn   = $('#wcstt-add-btn');

    /* ---- Provider dropdown: show/hide custom fields ---- */

    $provider.on('change', function () {
        var isCustom = $(this).val() === 'custom';
        $form.find('.wcstt-custom-field').toggle(isCustom);
    });

    /* ---- Add tracking (AJAX) ---- */

    $addBtn.on('click', function (e) {
        e.preventDefault();

        var number = $('#wcstt-number').val().trim();
        if (!number) {
            $('#wcstt-number').focus();
            return;
        }

        $addBtn.prop('disabled', true).text('…');

        $.post(wcsttAdmin.ajaxUrl, {
            action:           'wcstt_add_tracking',
            nonce:            wcsttAdmin.nonce,
            order_id:         wcsttAdmin.orderId,
            tracking_number:  number,
            provider:         $provider.val(),
            custom_provider:  $('#wcstt-custom-provider').val(),
            custom_link:      $('#wcstt-custom-link').val(),
            date_shipped:     $('#wcstt-date').val()
        })
        .done(function (res) {
            if (res.success) {
                // Remove "no tracking" message if present.
                $items.find('.wcstt-empty').remove();
                // Append new row.
                $items.append(res.data.html);
                // Reset form fields.
                $('#wcstt-number').val('');
                $provider.val('').trigger('change');
                $('#wcstt-custom-provider').val('');
                $('#wcstt-custom-link').val('');
            } else {
                alert(res.data && res.data.message ? res.data.message : wcsttAdmin.i18n.error);
            }
        })
        .fail(function () {
            alert(wcsttAdmin.i18n.error);
        })
        .always(function () {
            $addBtn.prop('disabled', false).text(
                // Restore button label.
                $addBtn.data('label') || 'Add Tracking Number'
            );
        });
    });

    // Store original label.
    $addBtn.data('label', $addBtn.text());

    /* ---- Delete tracking (AJAX, delegated) ---- */

    $items.on('click', '.wcstt-delete', function (e) {
        e.preventDefault();

        if (!confirm(wcsttAdmin.i18n.confirmDelete)) {
            return;
        }

        var $row   = $(this).closest('.wcstt-item');
        var number = $(this).data('number');

        $.post(wcsttAdmin.ajaxUrl, {
            action:          'wcstt_delete_tracking',
            nonce:           wcsttAdmin.nonce,
            order_id:        wcsttAdmin.orderId,
            tracking_number: number
        })
        .done(function (res) {
            if (res.success) {
                $row.fadeOut(200, function () {
                    $row.remove();
                    // Show empty message if no items left.
                    if (!$items.find('.wcstt-item').length) {
                        $items.html('<p class="wcstt-empty">No tracking numbers yet.</p>');
                    }
                });
            } else {
                alert(res.data && res.data.message ? res.data.message : wcsttAdmin.i18n.error);
            }
        })
        .fail(function () {
            alert(wcsttAdmin.i18n.error);
        });
    });

})(jQuery);
