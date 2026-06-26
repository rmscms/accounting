/**
 * Credit note structured form: invoice reference preview loader.
 */
(function ($) {
    'use strict';

    function customerIdFrom($root) {
        var $customer = $root.find('#fld-customer_id');
        var val = $customer.val();
        return val && String(val) !== '0' ? String(val) : '';
    }

    function invoiceIdFrom($root) {
        var $invoice = $root.find('#fld-customer_invoice_id');
        var val = $invoice.val();
        return val && String(val) !== '0' ? String(val) : '';
    }

    function initReferencePreview($root) {
        var $mount = $root.find('#credit-note-reference-invoice-mount');
        var $card = $root.find('#credit-note-reference-invoice-card');
        if (!$mount.length || !$card.length) {
            return;
        }

        var template = String($mount.attr('data-preview-template') || '');
        var msgLoading = String($mount.attr('data-msg-loading') || '...');
        var msgFailed = String($mount.attr('data-msg-failed') || '');
        var msgPlaceholder = String($mount.attr('data-msg-placeholder') || '');

        function showPlaceholder() {
            $card.addClass('d-none');
            $mount.empty().append($('<p class="text-muted small mb-0"></p>').text(msgPlaceholder));
        }

        function loadPreview() {
            var customerId = customerIdFrom($root);
            var invoiceId = invoiceIdFrom($root);
            if (!template || !customerId || !invoiceId) {
                showPlaceholder();
                return;
            }

            var url = template.replace('__INVOICE_ID__', invoiceId);
            var separator = url.indexOf('?') === -1 ? '?' : '&';
            url = url + separator + 'customer_id=' + encodeURIComponent(customerId);

            $card.removeClass('d-none');
            $mount.empty().append($('<p class="text-muted small mb-0"></p>').text(msgLoading));

            $.ajax({
                url: url,
                method: 'GET',
                dataType: 'html'
            })
                .done(function (html) {
                    $mount.html(html);
                })
                .fail(function () {
                    $mount.empty().append($('<div class="alert alert-danger small mb-0"></div>').text(msgFailed));
                });
        }

        $root.find('#fld-customer_invoice_id').on('change.creditNoteRefPreview', function () {
            window.setTimeout(loadPreview, 60);
        });
        $root.find('#fld-customer_id').on(
            'change.creditNoteRefPreview select2:select.creditNoteRefPreview select2:clear.creditNoteRefPreview',
            function () {
                window.setTimeout(loadPreview, 60);
            }
        );

        window.setTimeout(loadPreview, 280);
    }

    function boot() {
        $('.accounting-structured-form[data-form-slug="credit_notes"]').each(function () {
            initReferencePreview($(this));
        });
    }

    $(boot);
}(window.jQuery));
