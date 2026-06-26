(function () {
    'use strict';

    function toEnglishDigits(value) {
        if (typeof value !== 'string') {
            return '';
        }
        var fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        var ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        var out = value;
        for (var i = 0; i < 10; i += 1) {
            out = out.split(fa[i]).join(String(i));
            out = out.split(ar[i]).join(String(i));
        }
        return out;
    }

    function sanitizeAmount(raw) {
        var value = toEnglishDigits(String(raw || ''));
        value = value.replace(/[٬،,\s]/g, '');
        value = value.replace(/[^0-9.]/g, '');
        var parts = value.split('.');
        if (parts.length > 2) {
            value = parts[0] + '.' + parts.slice(1).join('');
        }
        return value;
    }

    function formatAmount(raw) {
        var clean = sanitizeAmount(raw);
        if (clean === '') {
            return '';
        }
        var parts = clean.split('.');
        var intPart = (parts[0] || '0').replace(/^0+(?=\d)/, '');
        var decimalPart = parts.length > 1 ? parts[1] : '';
        intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return decimalPart !== '' ? intPart + '.' + decimalPart : intPart;
    }

    function bindAmountInput(input) {
        if (!input || input.dataset.amountBound === '1') {
            return;
        }
        input.dataset.amountBound = '1';
        input.value = formatAmount(input.value);
        input.addEventListener('input', function () {
            var start = input.selectionStart || 0;
            var before = input.value.length;
            input.value = formatAmount(input.value);
            var diff = input.value.length - before;
            var pos = Math.max(0, start + diff);
            input.setSelectionRange(pos, pos);
        });
    }

    function init() {
        var form = document.querySelector('form');
        if (!form) {
            return;
        }
        if (form.dataset.employeeContractsAmountInit === '1') {
            return;
        }
        form.dataset.employeeContractsAmountInit = '1';

        form.querySelectorAll('.js-accounting-amount-input, .amount-decimal').forEach(bindAmountInput);
        form.addEventListener('submit', function () {
            form.querySelectorAll('.js-accounting-amount-input, .amount-decimal').forEach(function (input) {
                input.value = sanitizeAmount(input.value);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
