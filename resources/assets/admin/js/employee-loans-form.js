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

    function parseAmount(raw) {
        var clean = sanitizeAmount(raw);
        if (clean === '') {
            return 0;
        }
        var parsed = parseFloat(clean);
        return Number.isFinite(parsed) ? parsed : 0;
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

    function formatNumber(value, decimalPlaces) {
        if (!Number.isFinite(value)) {
            return '';
        }
        var normalized = Math.max(0, value);
        var places = Number.isFinite(decimalPlaces) ? Math.max(0, decimalPlaces) : 0;
        var rounded = normalized.toFixed(places);
        return formatAmount(rounded);
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
            input.dispatchEvent(new CustomEvent('loan-amount-change', { bubbles: true }));
        });
    }

    function annuityMonthlyPayment(principal, annualRatePercent, months) {
        if (months <= 0) {
            return 0;
        }
        var monthlyRate = Math.max(0, annualRatePercent) / 100 / 12;
        if (monthlyRate <= 0) {
            return principal / months;
        }
        var factor = Math.pow(1 + monthlyRate, months);
        return principal * (monthlyRate * factor) / (factor - 1);
    }

    function buildInstallmentPreview(principal, annualRatePercent, months, maxRows) {
        var count = Math.max(0, months);
        if (count <= 0 || principal <= 0) {
            return [];
        }
        var monthlyRate = Math.max(0, annualRatePercent) / 100 / 12;
        var installmentAmount = annuityMonthlyPayment(principal, annualRatePercent, count);
        var remaining = principal;
        var rows = [];

        for (var i = 0; i < count; i += 1) {
            if (remaining <= 0) {
                break;
            }
            var opening = remaining;
            var interestPart = opening * monthlyRate;
            var principalPart = installmentAmount - interestPart;
            var monthlyAmount = installmentAmount;
            if (i === count - 1 || principalPart > remaining) {
                principalPart = remaining;
                monthlyAmount = principalPart + interestPart;
            }
            remaining = Math.max(0, remaining - principalPart);

            if (i < maxRows) {
                rows.push({
                    index: i + 1,
                    monthlyAmount: monthlyAmount,
                    principalPart: principalPart,
                    interestPart: interestPart,
                    remaining: remaining
                });
            }
        }

        return rows;
    }

    function renderInstallmentPreview(form, principal, annualRate, months, decimalPlaces) {
        var previewContainer = form.querySelector('[data-loan-preview]');
        if (!previewContainer) {
            return;
        }
        var emptyEl = previewContainer.querySelector('.js-loan-preview-empty');
        var tableWrap = previewContainer.querySelector('.js-loan-preview-table-wrap');
        var tbody = previewContainer.querySelector('.js-loan-preview-body');
        if (!emptyEl || !tableWrap || !tbody) {
            return;
        }

        if (principal <= 0 || months <= 0) {
            tbody.innerHTML = '';
            emptyEl.classList.remove('d-none');
            tableWrap.classList.add('d-none');
            return;
        }

        var rows = buildInstallmentPreview(principal, annualRate, months, 12);
        if (!rows.length) {
            tbody.innerHTML = '';
            emptyEl.classList.remove('d-none');
            tableWrap.classList.add('d-none');
            return;
        }

        var html = '';
        for (var i = 0; i < rows.length; i += 1) {
            var row = rows[i];
            html += '<tr>';
            html += '<td>' + row.index + '</td>';
            html += '<td>' + formatNumber(row.monthlyAmount, decimalPlaces) + '</td>';
            html += '<td>' + formatNumber(row.principalPart, decimalPlaces) + '</td>';
            html += '<td>' + formatNumber(row.interestPart, decimalPlaces) + '</td>';
            html += '<td>' + formatNumber(row.remaining, decimalPlaces) + '</td>';
            html += '</tr>';
        }
        tbody.innerHTML = html;
        emptyEl.classList.add('d-none');
        tableWrap.classList.remove('d-none');
    }

    function updateLoanCards(form) {
        if (!form) {
            return;
        }
        var principalField = form.querySelector('.js-loan-principal');
        var rateField = form.querySelector('.js-loan-rate');
        var monthsField = form.querySelector('.js-loan-months');
        var monthlyCard = form.querySelector('.js-loan-monthly-card');
        var interestCard = form.querySelector('.js-loan-interest-card');
        var totalCard = form.querySelector('.js-loan-total-card');
        if (!principalField || !rateField || !monthsField || !monthlyCard || !interestCard || !totalCard) {
            return;
        }

        var principal = parseAmount(principalField.value);
        var annualRate = parseFloat(String(rateField.value || '0').replace(',', '.'));
        if (!Number.isFinite(annualRate) || annualRate < 0) {
            annualRate = 0;
        }
        var months = parseInt(String(monthsField.value || '0'), 10);
        if (!Number.isFinite(months) || months < 1) {
            months = 0;
        }

        var monthly = annuityMonthlyPayment(principal, annualRate, months);
        var total = monthly * months;
        var interest = Math.max(0, total - principal);
        var decimalPlaces = parseInt(String(form.getAttribute('data-decimal-places') || '0'), 10);
        if (!Number.isFinite(decimalPlaces) || decimalPlaces < 0) {
            decimalPlaces = 0;
        }

        monthlyCard.textContent = formatNumber(monthly, decimalPlaces);
        interestCard.textContent = formatNumber(interest, decimalPlaces);
        totalCard.textContent = formatNumber(total, decimalPlaces);
        renderInstallmentPreview(form, principal, annualRate, months, decimalPlaces);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.querySelector('form[action*="employee-loans"]');
        if (!form) {
            return;
        }

        form.querySelectorAll('.js-accounting-amount-input').forEach(bindAmountInput);
        updateLoanCards(form);

        var principalField = form.querySelector('.js-loan-principal');
        var rateField = form.querySelector('.js-loan-rate');
        var monthsField = form.querySelector('.js-loan-months');

        if (principalField) {
            principalField.addEventListener('loan-amount-change', function () {
                updateLoanCards(form);
            });
        }
        if (rateField) {
            rateField.addEventListener('input', function () {
                updateLoanCards(form);
            });
        }
        if (monthsField) {
            monthsField.addEventListener('input', function () {
                updateLoanCards(form);
            });
        }

        form.addEventListener('submit', function () {
            form.querySelectorAll('.js-accounting-amount-input').forEach(function (input) {
                input.value = sanitizeAmount(input.value);
            });
        });
    });
})();
