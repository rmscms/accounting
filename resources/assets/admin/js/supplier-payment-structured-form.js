/**
 * پرداخت به تأمین‌کننده: Select2 برای supplier_id، یکتایی شماره پرداخت با blur.
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
        var totalField = String($card.data('total-field') || 'amount');
        var currencyField = String($card.data('currency-field') || 'currency_code');
        var rateField = String($card.data('rate-field') || 'fx_rate_at_payment');
        var baseAmountField = String($card.data('base-amount-field') || 'amount_base_at_payment');
        var baseCurrency = String($card.data('base-currency') || '').toUpperCase();
        var currencyMeta = parseCurrencyMeta($card.attr('data-currency-meta') || $card.data('currency-meta'));

        var $total = $root.find('#fld-' + totalField);
        if (!$total.length) {
            $total = $root.find('[name="' + totalField + '"]').first();
        }
        if (!$total.length) {
            $total = $root.find('[data-fx-total-amount="1"]').first();
        }
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

    function wirePaymentNumberUniqueness($root) {
        var $inp = $root.find('#fld-payment_number');
        if (!$inp.length) {
            return;
        }
        var checkUrl = $inp.data('payment-number-unique-url');
        if (!checkUrl) {
            return;
        }
        var $fbBad = $root.find('#fld-payment_number-unique-feedback');
        var $fbOk = $root.find('#fld-payment_number-unique-success');
        var takenMsg = ($root.data('payment-number-msg-taken') || '').toString();
        var availMsg = ($root.data('payment-number-msg-available') || '').toString();

        function clearFeedback() {
            $inp.removeClass('is-invalid is-valid');
            if ($fbBad.length) {
                $fbBad.addClass('d-none').text('');
            }
            if ($fbOk.length) {
                $fbOk.addClass('d-none').empty();
            }
        }

        function setTaken(msg) {
            $inp.removeClass('is-valid').addClass('is-invalid');
            if ($fbOk.length) {
                $fbOk.addClass('d-none').empty();
            }
            if ($fbBad.length) {
                $fbBad.removeClass('d-none').text(msg || takenMsg);
            }
        }

        function setAvailable() {
            $inp.removeClass('is-invalid').addClass('is-valid');
            if ($fbBad.length) {
                $fbBad.addClass('d-none').text('');
            }
            if ($fbOk.length) {
                $fbOk.removeClass('d-none').empty()
                    .append($('<i class="ph-check-circle me-1" aria-hidden="true"></i>'))
                    .append($('<span></span>').text(availMsg));
            }
        }

        function runCheck() {
            var num = ($inp.val() || '').toString().trim();
            if (num === '') {
                clearFeedback();
                return;
            }
            var exclude = $inp.data('payment-number-exclude-id');
            $.ajax({
                url: checkUrl,
                method: 'GET',
                dataType: 'json',
                data: { number: num, exclude_id: exclude || 0 }
            }).done(function (res) {
                if (res && res.available) {
                    setAvailable();
                } else {
                    setTaken((res && res.message) ? String(res.message) : '');
                }
            }).fail(function () {
                clearFeedback();
            });
        }

        $inp.on('blur', function () {
            runCheck();
        });
    }

    function boot() {
        $('.accounting-structured-form[data-form-slug="supplier_payments"], .accounting-structured-form[data-form-slug="supplier_advances"]').each(function () {
            var $root = $(this);
            if (window.AccountingAjaxSupplierWidgets && typeof window.AccountingAjaxSupplierWidgets.initSupplierSelect2 === 'function') {
                window.AccountingAjaxSupplierWidgets.initSupplierSelect2($root);
            }
            if (($root.data('form-slug') || '').toString() === 'supplier_payments') {
                wirePaymentNumberUniqueness($root);
                initFxCard($root);
            }
        });
    }

    $(boot);
}(window.jQuery));
