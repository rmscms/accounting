(function () {
    'use strict';

    function toEnglishDigits(value) {
        if (typeof value !== 'string') {
            return '';
        }
        var fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
        var ar = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
        var out = value;
        for (var i = 0; i < 10; i += 1) {
            out = out.split(fa[i]).join(String(i));
            out = out.split(ar[i]).join(String(i));
        }
        return out;
    }

    function sanitize(raw) {
        var value = toEnglishDigits(String(raw || ''));
        value = value.replace(/[٬،,\s]/g, '');
        value = value.replace(/[^0-9.]/g, '');
        var parts = value.split('.');
        if (parts.length > 2) {
            value = parts[0] + '.' + parts.slice(1).join('');
        }
        return value;
    }

    function formatForDisplay(raw) {
        var clean = sanitize(raw);
        if (clean === '') {
            return '';
        }
        var parts = clean.split('.');
        var intPart = parts[0] || '0';
        var decimalPart = parts.length > 1 ? parts[1] : '';
        intPart = intPart.replace(/^0+(?=\d)/, '');
        intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return decimalPart !== '' ? intPart + '.' + decimalPart : intPart;
    }

    function bindAmountInput(input) {
        if (!input) {
            return;
        }

        input.addEventListener('input', function () {
            var start = input.selectionStart || 0;
            var beforeLength = input.value.length;
            input.value = formatForDisplay(input.value);
            var diff = input.value.length - beforeLength;
            var pos = Math.max(0, start + diff);
            input.setSelectionRange(pos, pos);
        });

        input.form && input.form.addEventListener('submit', function () {
            input.value = sanitize(input.value);
        });

        input.value = formatForDisplay(input.value);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var inputs = document.querySelectorAll('.js-accounting-amount-input');
        for (var i = 0; i < inputs.length; i += 1) {
            bindAmountInput(inputs[i]);
        }
    });
})();
