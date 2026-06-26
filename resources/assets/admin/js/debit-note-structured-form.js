/**
 * فرم ساختاریافته یادداشت بدهکار: Select2 تأمین‌کننده + فاکتور مرجع + پیش‌نمایج AJAX فاکتور.
 */
(function ($) {
    'use strict';

    function supplierIdFrom($root) {
        var $s = $root.find('#fld-supplier_id');
        var v = $s.val();
        return v && String(v) !== '0' ? String(v) : '';
    }

    function invoiceIdFrom($root) {
        var $i = $root.find('#fld-supplier_invoice_id');
        var v = $i.val();
        return v && String(v) !== '0' ? String(v) : '';
    }

    function initDebitNoteReferenceInvoicePreview($root) {
        var $mount = $root.find('#debit-note-reference-invoice-mount');
        var $card = $root.find('#debit-note-reference-invoice-card');
        if (!$mount.length || !$card.length) {
            return;
        }

        var template = String($mount.attr('data-preview-template') || '');
        var msgLoading = String($mount.attr('data-msg-loading') || '…');
        var msgFailed = String($mount.attr('data-msg-failed') || '');
        var msgPlaceholder = String($mount.attr('data-msg-placeholder') || '');

        function showPlaceholder() {
            $card.addClass('d-none');
            $mount.empty().append($('<p class="text-muted small mb-0"></p>').text(msgPlaceholder));
        }

        function loadPreview() {
            var sid = supplierIdFrom($root);
            var iid = invoiceIdFrom($root);
            if (!template || !sid || !iid) {
                showPlaceholder();
                return;
            }
            var url = template.replace('__INVOICE_ID__', iid);
            var sep = url.indexOf('?') === -1 ? '?' : '&';
            url = url + sep + 'supplier_id=' + encodeURIComponent(sid);

            $card.removeClass('d-none');
            $mount.empty().append($('<p class="text-muted small mb-0"></p>').text(msgLoading));

            $.ajax({
                url: url,
                method: 'GET',
                dataType: 'html',
            })
                .done(function (html) {
                    $mount.html(html);
                })
                .fail(function () {
                    $mount.empty().append($('<div class="alert alert-danger small mb-0"></div>').text(msgFailed));
                });
        }

        $root.find('#fld-supplier_invoice_id').on(
            'change.debitNoteRefPreview select2:select.debitNoteRefPreview select2:clear.debitNoteRefPreview',
            function () {
                window.setTimeout(loadPreview, 80);
            }
        );
        $root.find('#fld-supplier_id').on('change.debitNoteRefPreview select2:select.debitNoteRefPreview select2:clear.debitNoteRefPreview', function () {
            window.setTimeout(loadPreview, 80);
        });

        window.setTimeout(loadPreview, 350);
    }

    function boot() {
        $('.accounting-structured-form[data-form-slug="debit_notes"]').each(function () {
            var $root = $(this);
            if (!window.AccountingAjaxSupplierWidgets) {
                return;
            }
            window.AccountingAjaxSupplierWidgets.initSupplierSelect2($root);
            window.AccountingAjaxSupplierWidgets.initSupplierInvoiceSelect2($root);
            initDebitNoteReferenceInvoicePreview($root);
        });
    }

    $(boot);
}(window.jQuery));
