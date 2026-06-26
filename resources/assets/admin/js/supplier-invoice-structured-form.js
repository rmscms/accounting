/**
 * فاکتور خرید (فرم ساختاریافته): Select2 AJAX برای supplier_id، بارگذاری جدول اقلام، یکتایی شماره فاکتور با blur.
 */
(function ($) {
    'use strict';

    function parseNumber(input) {
        if (input === null || input === undefined) {
            return 0;
        }
        var value = String(input).trim();
        if (value === '') {
            return 0;
        }
        value = value
            .replace(/[٠-٩]/g, function (d) { return String(d.charCodeAt(0) - 1632); })
            .replace(/[۰-۹]/g, function (d) { return String(d.charCodeAt(0) - 1776); })
            .replace(/,/g, '');
        var n = Number(value);
        return Number.isFinite(n) ? n : 0;
    }

    function formatNumber(value, decimals) {
        var n = Number(value);
        if (!Number.isFinite(n)) {
            return '';
        }
        return n.toLocaleString('en-US', {
            minimumFractionDigits: 0,
            maximumFractionDigits: decimals
        });
    }

    function parseCurrencyMeta(raw) {
        if (!raw) {
            return {};
        }
        if (typeof raw === 'object') {
            return raw;
        }
        try {
            var parsed = JSON.parse(String(raw));
            return (parsed && typeof parsed === 'object') ? parsed : {};
        } catch (e) {
            return {};
        }
    }

    function resolveCurrencyDecimals(currencyMeta, code, fallback) {
        var upperCode = String(code || '').toUpperCase();
        var fallbackInt = Math.max(0, Math.min(6, Number(fallback) || 0));
        if (!upperCode || !currencyMeta || typeof currencyMeta !== 'object') {
            return fallbackInt;
        }
        var row = currencyMeta[upperCode];
        if (!row || typeof row !== 'object') {
            return fallbackInt;
        }
        var d = Number(row.decimals);
        if (!Number.isFinite(d)) {
            return fallbackInt;
        }
        return Math.max(0, Math.min(6, Math.round(d)));
    }

    function initFxCard($root) {
        var $card = $root.find('.js-fx-card');
        if (!$card.length) {
            return;
        }
        var totalField = String($card.data('total-field') || 'total_amount');
        var currencyField = String($card.data('currency-field') || 'currency_code');
        var rateField = String($card.data('rate-field') || 'fx_rate_at_invoice');
        var baseAmountField = String($card.data('base-amount-field') || 'amount_base_at_invoice');
        var baseCurrency = String($card.data('base-currency') || '').toUpperCase();
        var currencyMeta = parseCurrencyMeta($card.attr('data-currency-meta') || $card.data('currency-meta'));

        var $total = $root.find('#fld-' + totalField);
        var $currency = $root.find('#fld-' + currencyField);
        var $rate = $root.find('#fld-' + rateField);
        var $baseAmount = $root.find('#fld-' + baseAmountField);
        var $totalHint = $root.find('.js-amount-currency-hint').first();
        var hintTemplate = $totalHint.length ? String($totalHint.data('hint-template') || '') : '';
        if (!$total.length || !$currency.length || !$rate.length || !$baseAmount.length) {
            return;
        }

        function updateTotalHint(currencyCode) {
            if (!$totalHint.length || !hintTemplate) {
                return;
            }
            $totalHint.text(hintTemplate.replace('__CURRENCY__', String(currencyCode || '').toUpperCase()));
        }

        function recalculate() {
            var selectedCurrency = String($currency.val() || '').toUpperCase();
            var selectedDecimals = resolveCurrencyDecimals(currencyMeta, selectedCurrency, Number($total.attr('data-decimals') || 0));
            var baseDecimals = resolveCurrencyDecimals(currencyMeta, baseCurrency, Number($baseAmount.attr('data-decimals') || 0));

            $total.attr('data-decimals', String(selectedDecimals));
            $baseAmount.attr('data-decimals', String(baseDecimals));
            updateTotalHint(selectedCurrency);

            if (selectedCurrency === baseCurrency) {
                $rate.val('1');
                $rate.prop('readonly', true);
            } else {
                $rate.prop('readonly', false);
            }
            var total = parseNumber($total.val());
            var fxRate = parseNumber($rate.val());
            var baseAmount = total * fxRate;
            $baseAmount.val(formatNumber(baseAmount, baseDecimals));
        }

        $total.on('input change', recalculate);
        $currency.on('change', recalculate);
        $rate.on('input change', recalculate);
        recalculate();
    }

    function loadItemsFragment($root) {
        var $mount = $root.find('#supplier-invoice-items-mount');
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
        var $panels = $root.find('.js-settlement-paid-at-source-only');
        if (!$mode.length || !$panels.length) {
            return;
        }
        function apply() {
            var v = ($mode.val() || '').toString();
            if (v === 'paid_at_source') {
                $panels.removeClass('d-none');
            } else {
                $panels.addClass('d-none');
            }
        }
        $mode.on('change', apply);
        apply();
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

    function boot() {
        var $root = $('.accounting-structured-form[data-form-slug="supplier_invoices"]');
        if (!$root.length) {
            return;
        }
        if (window.AccountingLineItemsEditor && typeof window.AccountingLineItemsEditor.initSummaryFromAttribute === 'function') {
            window.AccountingLineItemsEditor.initSummaryFromAttribute($root);
        }
        if (window.AccountingAjaxSupplierWidgets && typeof window.AccountingAjaxSupplierWidgets.initSupplierSelect2 === 'function') {
            window.AccountingAjaxSupplierWidgets.initSupplierSelect2($root);
        }
        loadItemsFragment($root);
        wireInvoiceNumberUniqueness($root);
        syncSettlementDestinationVisibility($root);
        initFxCard($root);
    }

    $(boot);
})(window.jQuery);
