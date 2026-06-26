(function () {
    'use strict';
    var DECIMAL_PLACES = 0;

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

    function formatNumber(value) {
        if (!Number.isFinite(value)) {
            return '';
        }
        var normalized = Math.max(0, value);
        if (DECIMAL_PLACES <= 0) {
            return formatAmount(String(Math.round(normalized)));
        }
        return formatAmount(normalized.toFixed(DECIMAL_PLACES));
    }

    function parseFormatConfig() {
        var el = document.getElementById('pr-format-config');
        if (!el) {
            return;
        }
        try {
            var parsed = JSON.parse(el.textContent || '{}');
            var decimals = parseInt(String(parsed.decimal_places || '0'), 10);
            if (Number.isFinite(decimals)) {
                DECIMAL_PLACES = Math.max(0, Math.min(4, decimals));
            }
        } catch (e) {
            DECIMAL_PLACES = 0;
        }
    }

    function parseLoanPreviewMap() {
        var el = document.getElementById('pr-loan-preview-data');
        if (!el) {
            return {};
        }
        try {
            var parsed = JSON.parse(el.textContent || '{}');
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (e) {
            return {};
        }
    }

    function parseSalaryDefaultsMap() {
        var el = document.getElementById('pr-employee-salary-defaults');
        if (!el) {
            return {};
        }
        try {
            var parsed = JSON.parse(el.textContent || '{}');
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (e) {
            return {};
        }
    }

    function parseWageGuardConfig() {
        var el = document.getElementById('pr-wage-guard-config');
        if (!el) {
            return {
                minimum_wage: 0,
                warning_title: '',
                warning_message: '',
                warning_confirm: ''
            };
        }
        try {
            var parsed = JSON.parse(el.textContent || '{}');
            return {
                minimum_wage: parseAmount(parsed.minimum_wage || 0),
                warning_title: String(parsed.warning_title || ''),
                warning_message: String(parsed.warning_message || ''),
                warning_confirm: String(parsed.warning_confirm || '')
            };
        } catch (e) {
            return {
                minimum_wage: 0,
                warning_title: '',
                warning_message: '',
                warning_confirm: ''
            };
        }
    }

    function parseNetGuardConfig() {
        var el = document.getElementById('pr-net-guard-config');
        if (!el) {
            return {
                zero_warning_title: '',
                zero_warning_message: '',
                zero_warning_confirm: '',
                negative_block_title: '',
                negative_block_message: ''
            };
        }
        try {
            var parsed = JSON.parse(el.textContent || '{}');
            return {
                zero_warning_title: String(parsed.zero_warning_title || ''),
                zero_warning_message: String(parsed.zero_warning_message || ''),
                zero_warning_confirm: String(parsed.zero_warning_confirm || ''),
                negative_block_title: String(parsed.negative_block_title || ''),
                negative_block_message: String(parsed.negative_block_message || '')
            };
        } catch (e) {
            return {
                zero_warning_title: '',
                zero_warning_message: '',
                zero_warning_confirm: '',
                negative_block_title: '',
                negative_block_message: ''
            };
        }
    }

    function confirmWithPlugin(title, message, confirmButtonText) {
        if (window.RMSConfirmModal && typeof window.RMSConfirmModal === 'function') {
            var modal = new window.RMSConfirmModal({
                title: title || '',
                message: message || '',
                icon: 'ph-warning',
                confirmText: confirmButtonText || 'Confirm',
                confirmClass: 'btn-warning',
                confirmIcon: 'ph-warning-circle',
                cancelText: 'Cancel',
                cancelClass: 'btn-outline-secondary',
                cancelIcon: 'ph-x',
                closeOnBackdrop: true,
                closeOnEscape: true,
                focusConfirm: false
            });
            return modal.show();
        }

        return Promise.resolve(window.confirm(String((message || title) || '')));
    }

    function collectBelowMinimumWageLines(lineWrapper, minimumWage) {
        if (!lineWrapper || minimumWage <= 0) {
            return [];
        }
        var rows = [];
        lineWrapper.querySelectorAll('.payroll-line-row').forEach(function (lineRow, rowIndex) {
            var baseField = lineRow.querySelector('.js-pr-base');
            if (!baseField) {
                return;
            }
            var baseSalary = parseAmount(baseField.value);
            if (baseSalary <= 0 || baseSalary >= minimumWage) {
                return;
            }
            rows.push(rowIndex + 1);
        });

        return rows;
    }

    function sanitizeAllAmountInputs(form) {
        if (!form) {
            return;
        }
        form.querySelectorAll('.js-accounting-amount-input').forEach(function (input) {
            input.value = sanitizeAmount(input.value);
        });
    }

    function collectNonPositiveNetLines(lineWrapper) {
        var result = {
            zeroRows: [],
            negativeRows: []
        };
        if (!lineWrapper) {
            return result;
        }
        lineWrapper.querySelectorAll('.payroll-line-row').forEach(function (lineRow, rowIndex) {
            var netPreview = lineRow.querySelector('.js-pr-net-preview');
            if (!netPreview) {
                return;
            }
            var netValue = parseAmount(netPreview.value);
            if (netValue < 0) {
                result.negativeRows.push(rowIndex + 1);
            } else if (Math.abs(netValue) < 0.0000001) {
                result.zeroRows.push(rowIndex + 1);
            }
        });
        return result;
    }

    function formatLoanPreviewHtml(list, emptyText, dueLabel, remainingLabel, nextDueLabel) {
        if (!Array.isArray(list) || list.length === 0) {
            return '<span class="text-muted">' + emptyText + '</span>';
        }
        var totalDue = 0;
        for (var t = 0; t < list.length; t += 1) {
            totalDue += parseFloat(String((list[t] || {}).due_total || '0')) || 0;
        }
        var html = '<div class="d-flex flex-column gap-2">';
        html += '<div class="d-flex align-items-center justify-content-between small border border-warning rounded bg-warning bg-opacity-10 text-warning-emphasis px-2 py-1">';
        html += '<strong>' + dueLabel + ':</strong><strong>' + formatNumber(totalDue) + '</strong>';
        html += '</div>';
        for (var i = 0; i < list.length; i += 1) {
            var item = list[i] || {};
            var dueTotal = parseFloat(String(item.due_total || '0'));
            var remaining = parseFloat(String(item.remaining_total || '0'));
            var loanNumber = String(item.loan_number || '#');
            var nextDue = String(item.next_due_date_display || item.next_due_date || '-');
            html += '<div class="d-flex flex-wrap align-items-center gap-2 small border rounded bg-white px-2 py-1">';
            html += '<span class="badge bg-primary bg-opacity-20 text-primary border border-primary border-opacity-25">' + loanNumber + '</span>';
            html += '<span class="text-body"><strong>' + dueLabel + ':</strong> ' + formatNumber(dueTotal) + '</span>';
            html += '<span class="text-body"><strong>' + remainingLabel + ':</strong> ' + formatNumber(remaining) + '</span>';
            html += '<span class="text-body"><strong>' + nextDueLabel + ':</strong> ' + nextDue + '</span>';
            html += '</div>';
        }
        html += '</div>';
        return html;
    }

    function getLoanDueTotalForLine(lineRow, loanPreviewMap) {
        if (!lineRow) {
            return 0;
        }
        var employeeSelect = lineRow.querySelector('.js-pr-employee');
        var skipToggle = lineRow.querySelector('.js-pr-skip-loan');
        if (!employeeSelect || !employeeSelect.value || (skipToggle && skipToggle.checked)) {
            return 0;
        }
        var employeeId = String(employeeSelect.value || '').trim();
        var rows = loanPreviewMap[employeeId] || loanPreviewMap[parseInt(employeeId, 10)] || [];
        if (!Array.isArray(rows) || rows.length === 0) {
            return 0;
        }
        var total = 0;
        for (var i = 0; i < rows.length; i += 1) {
            total += parseFloat(String((rows[i] || {}).due_total || '0')) || 0;
        }
        return total;
    }

    function updateLoanPreviewForLine(lineRow, loanPreviewMap) {
        if (!lineRow) {
            return;
        }
        var employeeSelect = lineRow.querySelector('.js-pr-employee');
        var preview = lineRow.querySelector('.js-pr-loan-preview');
        var skipToggle = lineRow.querySelector('.js-pr-skip-loan');
        if (!employeeSelect || !preview) {
            return;
        }
        var emptyText = preview.getAttribute('data-empty-text') || '';
        var dueLabel = preview.getAttribute('data-label-due') || 'Due';
        var remainingLabel = preview.getAttribute('data-label-remaining') || 'Remaining';
        var nextDueLabel = preview.getAttribute('data-label-next-due') || 'Next due';
        var employeeId = String(employeeSelect.value || '').trim();
        var rows = loanPreviewMap[employeeId] || loanPreviewMap[parseInt(employeeId, 10)] || [];
        preview.innerHTML = formatLoanPreviewHtml(rows, emptyText, dueLabel, remainingLabel, nextDueLabel);
        if (skipToggle && skipToggle.checked) {
            preview.classList.add('opacity-75');
        } else {
            preview.classList.remove('opacity-75');
        }
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
            input.dispatchEvent(new CustomEvent('pr-amount-change', { bubbles: true }));
        });
    }

    function getPolicyRate(form, key, fallback) {
        var field = form.querySelector('[name="policy[' + key + ']"]');
        if (!field) {
            return fallback;
        }
        var value = parseFloat(String(field.value || '').replace(',', '.'));
        if (!Number.isFinite(value) || value < 0) {
            return fallback;
        }
        return value > 1 ? value / 100 : value;
    }

    function updateLineComputed(form, lineRow, loanPreviewMap) {
        if (!lineRow) {
            return;
        }
        var baseField = lineRow.querySelector('.js-pr-base');
        var benefitsField = lineRow.querySelector('.js-pr-benefits');
        var seniorityField = lineRow.querySelector('.js-pr-seniority');
        var employeeField = lineRow.querySelector('.js-pr-employee-insurance');
        var employerField = lineRow.querySelector('.js-pr-employer-insurance');
        var taxField = lineRow.querySelector('.js-pr-tax');
        var otherField = lineRow.querySelector('.js-pr-other-deductions');
        var employeeManual = lineRow.querySelector('[data-target="employee_insurance"]');
        var employerManual = lineRow.querySelector('[data-target="employer_insurance"]');
        var taxManual = lineRow.querySelector('[data-target="tax"]');

        var base = parseAmount(baseField ? baseField.value : '');
        var benefits = parseAmount(benefitsField ? benefitsField.value : '');
        var seniority = parseAmount(seniorityField ? seniorityField.value : '');
        var insurableTaxableBase = base + benefits;
        var gross = insurableTaxableBase;

        var employeeRate = getPolicyRate(form, 'employee_insurance_rate', 0.07);
        var employerRate = getPolicyRate(form, 'employer_insurance_rate', 0.23);
        var taxRate = getPolicyRate(form, 'tax_rate', 0);

        if (employeeField && (!employeeManual || !employeeManual.checked)) {
            employeeField.value = formatNumber(insurableTaxableBase * employeeRate);
        }
        if (employerField && (!employerManual || !employerManual.checked)) {
            employerField.value = formatNumber(insurableTaxableBase * employerRate);
        }
        if (taxField && (!taxManual || !taxManual.checked)) {
            taxField.value = formatNumber(insurableTaxableBase * taxRate);
        }

        var employee = parseAmount(employeeField ? employeeField.value : '');
        var tax = parseAmount(taxField ? taxField.value : '');
        var other = parseAmount(otherField ? otherField.value : '');
        var loanDue = getLoanDueTotalForLine(lineRow, loanPreviewMap || {});
        var net = gross - employee - tax - other - loanDue;

        var grossPreview = lineRow.querySelector('.js-pr-gross-preview');
        var netPreview = lineRow.querySelector('.js-pr-net-preview');
        if (grossPreview) {
            grossPreview.value = formatNumber(gross);
        }
        if (netPreview) {
            netPreview.value = formatNumber(net);
        }
    }

    function renumberAll(lineWrapper) {
        var lines = lineWrapper.querySelectorAll('.payroll-line-row');
        lines.forEach(function (lineRow, lineIndex) {
            lineRow.querySelectorAll('[name]').forEach(function (field) {
                field.name = field.name.replace(/lines\[\d+\]/, 'lines[' + lineIndex + ']');
            });
            lineRow.querySelectorAll('[data-name]').forEach(function (field) {
                field.name = 'lines[' + lineIndex + '][' + field.getAttribute('data-name') + ']';
                field.removeAttribute('data-name');
            });

            var itemRows = lineRow.querySelectorAll('.js-pr-item-row');
            itemRows.forEach(function (itemRow, itemIndex) {
                itemRow.querySelectorAll('[name]').forEach(function (field) {
                    field.name = field.name.replace(/lines\[\d+\]\[items]\[\d+\]/, 'lines[' + lineIndex + '][items][' + itemIndex + ']');
                });
                itemRow.querySelectorAll('[data-item-name]').forEach(function (field) {
                    field.name = 'lines[' + lineIndex + '][items][' + itemIndex + '][' + field.getAttribute('data-item-name') + ']';
                    field.removeAttribute('data-item-name');
                });
            });
        });
    }

    function addItemRow(lineRow) {
        var itemTemplate = document.getElementById('payroll-item-template');
        var tableBody = lineRow.querySelector('.js-pr-items-table tbody');
        if (!itemTemplate || !tableBody) {
            return;
        }
        var node = itemTemplate.content.cloneNode(true);
        tableBody.appendChild(node);
        tableBody.querySelectorAll('.js-accounting-amount-input').forEach(bindAmountInput);
    }

    function bindLineEvents(form, lineWrapper, lineRow, loanPreviewMap) {
        if (!lineRow || lineRow.dataset.bound === '1') {
            return;
        }
        lineRow.dataset.bound = '1';

        lineRow.querySelectorAll('.js-accounting-amount-input').forEach(bindAmountInput);

        lineRow.addEventListener('pr-amount-change', function () {
            updateLineComputed(form, lineRow, loanPreviewMap);
        });

        lineRow.querySelectorAll('.js-pr-manual-toggle').forEach(function (toggle) {
            toggle.addEventListener('change', function () {
                updateLineComputed(form, lineRow, loanPreviewMap);
            });
        });

        var employeeSelect = lineRow.querySelector('.js-pr-employee');
        if (employeeSelect) {
            employeeSelect.addEventListener('change', function () {
                applySalaryDefaultForLine(lineRow, employeeSelect.value, form._prSalaryDefaults || {});
                applyPolicyDefaultsForEmployee(form, employeeSelect.value, form._prSalaryDefaults || {});
                updateLoanPreviewForLine(lineRow, loanPreviewMap);
                updateLineComputed(form, lineRow, loanPreviewMap);
            });
        }

        var skipLoanToggle = lineRow.querySelector('.js-pr-skip-loan');
        if (skipLoanToggle) {
            skipLoanToggle.addEventListener('change', function () {
                updateLoanPreviewForLine(lineRow, loanPreviewMap);
                updateLineComputed(form, lineRow, loanPreviewMap);
            });
        }

        var removeBtn = lineRow.querySelector('.remove-line-btn');
        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                var allRows = lineWrapper.querySelectorAll('.payroll-line-row');
                if (allRows.length <= 1) {
                    return;
                }
                lineRow.remove();
                renumberAll(lineWrapper);
            });
        }

        var addItemBtn = lineRow.querySelector('.js-pr-add-item');
        if (addItemBtn) {
            addItemBtn.addEventListener('click', function () {
                addItemRow(lineRow);
                renumberAll(lineWrapper);
            });
        }

        lineRow.addEventListener('click', function (event) {
            var target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }
            if (target.classList.contains('js-pr-remove-item')) {
                var row = target.closest('.js-pr-item-row');
                if (row) {
                    row.remove();
                    renumberAll(lineWrapper);
                    updateLineComputed(form, lineRow, loanPreviewMap);
                }
            }
        });

        updateLineComputed(form, lineRow, loanPreviewMap);
        updateLoanPreviewForLine(lineRow, loanPreviewMap);
    }

    function applySalaryDefaultForLine(lineRow, employeeId, defaultsMap) {
        var baseField = lineRow.querySelector('.js-pr-base');
        var seniorityField = lineRow.querySelector('.js-pr-seniority');
        if (!baseField && !seniorityField) {
            return;
        }
        var normalizedId = String(employeeId || '').trim();
        if (normalizedId === '') {
            return;
        }
        var contractDefault = defaultsMap[normalizedId] || defaultsMap[parseInt(normalizedId, 10)] || null;
        var defaultAmount = contractDefault && typeof contractDefault === 'object'
            ? contractDefault.base_salary
            : contractDefault;

        if (baseField && !(defaultAmount === null || defaultAmount === undefined || defaultAmount === '')) {
            var currentAmount = parseAmount(baseField.value);
            var previousEmployee = String(baseField.dataset.defaultEmployeeId || '');
            if (!(currentAmount > 0 && (previousEmployee === normalizedId || previousEmployee === 'manual'))) {
                baseField.value = formatNumber(parseFloat(String(defaultAmount)));
                baseField.dataset.defaultEmployeeId = normalizedId;
                baseField.dispatchEvent(new CustomEvent('pr-amount-change', { bubbles: true }));
            }
        }

        if (seniorityField) {
            var seniorityDefault = contractDefault && typeof contractDefault === 'object'
                ? contractDefault.seniority_monthly_default
                : null;
            if (seniorityDefault !== null && seniorityDefault !== undefined && seniorityDefault !== '') {
                var currentSeniority = parseAmount(seniorityField.value);
                var previousSeniorityEmployee = String(seniorityField.dataset.defaultEmployeeId || '');
                if (!(currentSeniority > 0 && (previousSeniorityEmployee === normalizedId || previousSeniorityEmployee === 'manual'))) {
                    seniorityField.value = formatNumber(parseFloat(String(seniorityDefault)));
                    seniorityField.dataset.defaultEmployeeId = normalizedId;
                    seniorityField.dispatchEvent(new CustomEvent('pr-amount-change', { bubbles: true }));
                }
            }
        }
    }

    function applyPolicyDefaultsForEmployee(form, employeeId, defaultsMap) {
        if (!form) {
            return;
        }
        var normalizedId = String(employeeId || '').trim();
        if (normalizedId === '') {
            return;
        }
        var contractDefault = defaultsMap[normalizedId] || defaultsMap[parseInt(normalizedId, 10)] || null;
        if (!contractDefault || typeof contractDefault !== 'object') {
            return;
        }

        var mappings = [
            ['employee_insurance_rate', 'employee_insurance_rate'],
            ['employer_insurance_rate', 'employer_insurance_rate'],
            ['tax_rate', 'tax_rate']
        ];

        mappings.forEach(function (mapping) {
            var contractKey = mapping[0];
            var policyKey = mapping[1];
            var value = contractDefault[contractKey];
            if (value === null || value === undefined || value === '') {
                return;
            }
            var field = form.querySelector('[name="policy[' + policyKey + ']"]');
            if (!field) {
                return;
            }
            field.value = String(value);
            field.dispatchEvent(new Event('input', { bubbles: true }));
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        parseFormatConfig();
        var lineWrapper = document.getElementById('payroll-lines-wrapper');
        var addLineBtn = document.getElementById('add-line-btn');
        var lineTemplate = document.getElementById('payroll-line-template');
        if (!lineWrapper || !addLineBtn || !lineTemplate) {
            return;
        }

        var form = lineWrapper.closest('form');
        if (!form) {
            return;
        }
        var loanPreviewMap = parseLoanPreviewMap();
        var salaryDefaultsMap = parseSalaryDefaultsMap();
        var wageGuardConfig = parseWageGuardConfig();
        var netGuardConfig = parseNetGuardConfig();
        form._prSalaryDefaults = salaryDefaultsMap;

        lineWrapper.querySelectorAll('.payroll-line-row').forEach(function (lineRow) {
            bindLineEvents(form, lineWrapper, lineRow, loanPreviewMap);
            var select = lineRow.querySelector('.js-pr-employee');
            if (select) {
                applySalaryDefaultForLine(lineRow, select.value, salaryDefaultsMap);
            }
            var baseField = lineRow.querySelector('.js-pr-base');
            if (baseField) {
                baseField.addEventListener('input', function () {
                    baseField.dataset.defaultEmployeeId = 'manual';
                });
            }
            var seniorityField = lineRow.querySelector('.js-pr-seniority');
            if (seniorityField) {
                seniorityField.addEventListener('input', function () {
                    seniorityField.dataset.defaultEmployeeId = 'manual';
                });
            }
        });

        addLineBtn.addEventListener('click', function () {
            var node = lineTemplate.content.cloneNode(true);
            lineWrapper.appendChild(node);
            renumberAll(lineWrapper);
            var rows = lineWrapper.querySelectorAll('.payroll-line-row');
            var newRow = rows[rows.length - 1];
            bindLineEvents(form, lineWrapper, newRow, loanPreviewMap);
            var newSelect = newRow.querySelector('.js-pr-employee');
            if (newSelect) {
                applySalaryDefaultForLine(newRow, newSelect.value, salaryDefaultsMap);
            }
            var newBaseField = newRow.querySelector('.js-pr-base');
            if (newBaseField) {
                newBaseField.addEventListener('input', function () {
                    newBaseField.dataset.defaultEmployeeId = 'manual';
                });
            }
            var newSeniorityField = newRow.querySelector('.js-pr-seniority');
            if (newSeniorityField) {
                newSeniorityField.addEventListener('input', function () {
                    newSeniorityField.dataset.defaultEmployeeId = 'manual';
                });
            }
        });

        form.querySelectorAll('[name^="policy["]').forEach(function (field) {
            field.addEventListener('input', function () {
                lineWrapper.querySelectorAll('.payroll-line-row').forEach(function (lineRow) {
                    updateLineComputed(form, lineRow, loanPreviewMap);
                });
            });
        });

        form.querySelectorAll('.js-accounting-amount-input').forEach(bindAmountInput);
        form.addEventListener('submit', function (event) {
            var overrideInput = form.querySelector('[data-pr-min-wage-override]');
            var zeroNetOverrideInput = form.querySelector('[data-pr-zero-net-override]');
            var hasForcedOverride = overrideInput && String(overrideInput.value || '') === '1';
            var hasZeroNetOverride = zeroNetOverrideInput && String(zeroNetOverrideInput.value || '') === '1';

            lineWrapper.querySelectorAll('.payroll-line-row').forEach(function (lineRow) {
                updateLineComputed(form, lineRow, loanPreviewMap);
            });

            var nonPositiveNets = collectNonPositiveNetLines(lineWrapper);
            if (nonPositiveNets.negativeRows.length > 0) {
                event.preventDefault();
                confirmWithPlugin(
                    netGuardConfig.negative_block_title,
                    netGuardConfig.negative_block_message,
                    ''
                );
                return;
            }
            if (nonPositiveNets.zeroRows.length > 0 && !hasZeroNetOverride) {
                event.preventDefault();
                confirmWithPlugin(
                    netGuardConfig.zero_warning_title,
                    netGuardConfig.zero_warning_message,
                    netGuardConfig.zero_warning_confirm
                ).then(function (ok) {
                    if (!ok) {
                        return;
                    }
                    if (zeroNetOverrideInput) {
                        zeroNetOverrideInput.value = '1';
                    }
                    sanitizeAllAmountInputs(form);
                    form.submit();
                });
                return;
            }

            if (!hasForcedOverride) {
                var belowMinRows = collectBelowMinimumWageLines(lineWrapper, wageGuardConfig.minimum_wage);
                if (belowMinRows.length > 0) {
                    event.preventDefault();
                    confirmWithPlugin(
                        wageGuardConfig.warning_title,
                        wageGuardConfig.warning_message,
                        wageGuardConfig.warning_confirm
                    ).then(function (ok) {
                        if (!ok) {
                            return;
                        }
                        if (overrideInput) {
                            overrideInput.value = '1';
                        }
                        sanitizeAllAmountInputs(form);
                        form.submit();
                    });
                    return;
                }
            }
            sanitizeAllAmountInputs(form);
        });
    });
})();
