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

    function syncSourceVisibility() {
        var sel = document.getElementById('source_type');
        var wrapBank = document.getElementById('wrap-bank');
        var wrapCash = document.getElementById('wrap-cash');
        if (!sel || !wrapBank || !wrapCash) {
            return;
        }
        if (sel.value === 'cash') {
            wrapBank.style.display = 'none';
            wrapCash.style.display = '';
            return;
        }
        wrapBank.style.display = '';
        wrapCash.style.display = 'none';
    }

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('withdrawal-form');
        if (!form) {
            return;
        }

        var amountInput = form.querySelector('.js-accounting-amount-input');
        bindAmountInput(amountInput);

        var source = document.getElementById('source_type');
        if (source) {
            source.addEventListener('change', syncSourceVisibility);
        }
        syncSourceVisibility();

        form.addEventListener('submit', function () {
            if (amountInput) {
                amountInput.value = sanitizeAmount(amountInput.value);
            }
        });
    });
})();
