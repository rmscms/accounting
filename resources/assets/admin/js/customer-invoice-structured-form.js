/**
 * فاکتور فروش (فرم ساختاریافته): Select2 AJAX برای customer_id، یکتایی شماره فاکتور با blur.
 */
(function ($) {
    'use strict';

    function initCustomerSelect2($root) {
        if (!$ || typeof $.fn.select2 !== 'function') {
            return;
        }
        $root.find('select.accounting-customer-invoice-select2').each(function () {
            var $el = $(this);
            if ($el.data('select2')) {
                return;
            }
            var url = $el.data('search-url');
            if (!url) {
                return;
            }
            var placeholder = $el.data('placeholder') || '';
            $el.select2({
                width: '100%',
                placeholder: placeholder,
                allowClear: !($el.prop('required')),
                minimumInputLength: 2,
                language: {
                    inputTooShort: function () {
                        return placeholder;
                    }
                },
                ajax: {
                    url: url,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return { q: params.term || '', limit: 30 };
                    },
                    processResults: function (data) {
                        var rows = (data && data.results) ? data.results : [];
                        return { results: rows };
                    }
                }
            });
        });
    }

    function wireInvoiceNumberUniqueness($root) {
        var $inp = $root.find('#fld-invoice_number');
        if (!$inp.length) {
            return;
        }
        var checkUrl = $inp.data('invoice-unique-url');
        if (!checkUrl) {
            return;
        }
        var $fb = $root.find('#fld-invoice_number-unique-feedback');
        var takenMsg = ($root.data('invoice-msg-taken') || '').toString();

        function setFeedback(ok, msg) {
            if (!$fb.length) {
                return;
            }
            if (ok) {
                $fb.addClass('d-none').text('');
                $inp.removeClass('is-invalid');
                return;
            }
            $fb.removeClass('d-none').text(msg || takenMsg);
            $inp.addClass('is-invalid');
        }

        function runCheck() {
            var num = ($inp.val() || '').toString().trim();
            if (num === '') {
                setFeedback(true);
                return;
            }
            var exclude = $inp.data('invoice-exclude-id');
            $.ajax({
                url: checkUrl,
                method: 'GET',
                dataType: 'json',
                data: { number: num, exclude_id: exclude || 0 }
            }).done(function (res) {
                if (res && res.available) {
                    setFeedback(true);
                } else {
                    setFeedback(false, (res && res.message) ? String(res.message) : '');
                }
            }).fail(function () {
                setFeedback(true);
            });
        }

        $inp.on('blur', function () {
            runCheck();
        });
    }

    function loadItemsFragment($root) {
        var $mount = $root.find('#customer-invoice-items-mount');
        if (!$mount.length) {
            return;
        }
        var itemsUrl = $mount.data('items-url');
        if (!itemsUrl) {
            return;
        }
        $.ajax({
            url: itemsUrl,
            method: 'GET',
            dataType: 'html'
        }).done(function (html) {
            $mount.html(html);
            bindItemsEditorWhenReady($mount, 15);
        }).fail(function () {
            var msg = $mount.attr('data-load-error') || 'Load failed.';
            $mount.html('<div class="alert alert-warning mb-0"></div>').find('.alert').text(msg);
        });
    }

    function bindItemsEditorWhenReady($mount, retriesLeft) {
        if (window.AccountingLineItemsEditor && typeof window.AccountingLineItemsEditor.bind === 'function') {
            window.AccountingLineItemsEditor.bind($mount);
            return;
        }
        if (retriesLeft <= 0) {
            return;
        }
        window.setTimeout(function () {
            bindItemsEditorWhenReady($mount, retriesLeft - 1);
        }, 80);
    }

    function syncSettlementDestinationVisibility($root) {
        var $mode = $root.find('select[name="settlement_mode"]');
        var $paidAtSourcePanels = $root.find('.js-settlement-paid-at-source-only');
        var $mixedOnlyPanels = $root.find('.js-settlement-mixed-only');
        if (
            !$mode.length ||
            (!$paidAtSourcePanels.length && !$mixedOnlyPanels.length)
        ) {
            return;
        }
        function apply() {
            var v = ($mode.val() || '').toString();
            if ($paidAtSourcePanels.length) {
                if (v === 'cash' || v === 'mixed') {
                    $paidAtSourcePanels.removeClass('d-none');
                } else {
                    $paidAtSourcePanels.addClass('d-none');
                }
            }
            if ($mixedOnlyPanels.length) {
                if (v === 'mixed') {
                    $mixedOnlyPanels.removeClass('d-none');
                } else {
                    $mixedOnlyPanels.addClass('d-none');
                }
            }
        }
        $mode.on('change', apply);
        apply();
    }

    function boot() {
        var $root = $('.accounting-structured-form[data-form-slug="customer_invoices"]');
        if (!$root.length) {
            return;
        }
        if (window.AccountingLineItemsEditor && typeof window.AccountingLineItemsEditor.initSummaryFromAttribute === 'function') {
            window.AccountingLineItemsEditor.initSummaryFromAttribute($root);
        }
        initCustomerSelect2($root);
        wireInvoiceNumberUniqueness($root);
        loadItemsFragment($root);
        syncSettlementDestinationVisibility($root);
    }

    $(boot);
}(window.jQuery));
