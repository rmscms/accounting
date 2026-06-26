(function () {
    'use strict';

    const normalizeAmountInput = function (value) {
        const raw = String(value ?? '')
            .replace(/[\u0660-\u0669]/g, function (ch) { return String(ch.charCodeAt(0) - 1632); })
            .replace(/[\u06f0-\u06f9]/g, function (ch) { return String(ch.charCodeAt(0) - 1776); })
            .replace(/[٬،,\s]/g, '')
            .replace(/[^\d.\-]/g, '');
        const negative = raw.trim().startsWith('-');
        const unsigned = raw.replace(/-/g, '');
        const hasDot = unsigned.indexOf('.') >= 0;
        const parts = unsigned.split('.');
        const integerRaw = parts.shift() || '';
        const decimalRaw = parts.join('');
        const integerPart = integerRaw === '' && hasDot ? '0' : integerRaw;
        if (integerPart === '' && decimalRaw === '') {
            return negative ? '-' : '';
        }

        return (negative ? '-' : '') + integerPart + (hasDot ? ('.' + decimalRaw) : '');
    };

    const formatAmountInput = function (value) {
        const normalized = normalizeAmountInput(value);
        if (normalized === '' || normalized === '-' || normalized === '.'
            || normalized === '-.' || Number.isNaN(Number(normalized))) {
            return normalized;
        }

        const negative = normalized.startsWith('-');
        const absolute = negative ? normalized.substring(1) : normalized;
        const pieces = absolute.split('.');
        const integerPart = pieces[0] || '0';
        const decimalPart = pieces.length > 1 ? pieces[1] : '';
        const hadTrailingDot = normalized.endsWith('.') && decimalPart === '';
        const grouped = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

        return (negative ? '-' : '') + grouped + ((decimalPart !== '' || hadTrailingDot) ? '.' + decimalPart : '');
    };

    const positionAfterNthDigit = function (formatted, n, keepSignPosition) {
        if (n <= 0) {
            return keepSignPosition && formatted.startsWith('-') ? 1 : 0;
        }
        let count = 0;
        for (let i = 0; i < formatted.length; i++) {
            if (/\d/.test(formatted.charAt(i))) {
                count++;
                if (count >= n) {
                    return i + 1;
                }
            }
        }

        return formatted.length;
    };

    const resolveCaretPosition = function (formatted, normalizedBeforeCaret) {
        const before = String(normalizedBeforeCaret || '');
        if (before === '') {
            return 0;
        }
        if (before === '-') {
            return formatted.startsWith('-') ? 1 : 0;
        }

        const keepSignPosition = before.startsWith('-');
        const unsignedBefore = keepSignPosition ? before.substring(1) : before;
        const dotIndex = unsignedBefore.indexOf('.');

        if (dotIndex < 0) {
            const digitCount = unsignedBefore.replace(/[^\d]/g, '').length;
            return positionAfterNthDigit(formatted, digitCount, keepSignPosition);
        }

        const integerDigits = unsignedBefore.substring(0, dotIndex).replace(/[^\d]/g, '').length;
        const decimalDigits = unsignedBefore.substring(dotIndex + 1).replace(/[^\d]/g, '').length;
        const formattedDotIndex = formatted.indexOf('.');
        if (formattedDotIndex < 0) {
            return positionAfterNthDigit(formatted, integerDigits + decimalDigits, keepSignPosition);
        }

        return Math.min(formatted.length, formattedDotIndex + 1 + decimalDigits);
    };

    const applyLiveAmountFormat = function (input) {
        const rawValue = String(input.value || '');
        const currentCaret = Number.isInteger(input.selectionStart) ? input.selectionStart : rawValue.length;
        const normalizedBeforeCaret = normalizeAmountInput(rawValue.substring(0, currentCaret));
        const formattedValue = formatAmountInput(rawValue);
        const nextCaret = resolveCaretPosition(formattedValue, normalizedBeforeCaret);

        input.value = formattedValue;
        if (document.activeElement === input && typeof input.setSelectionRange === 'function') {
            input.setSelectionRange(nextCaret, nextCaret);
        }
    };

    const bindAmountInputs = function (sourceForm) {
        const amountInputs = sourceForm.querySelectorAll('.js-accounting-amount-input');
        amountInputs.forEach(function (input) {
            if (input.dataset.amountBound === '1') {
                return;
            }
            input.dataset.amountBound = '1';
            const applyPrettyFormat = function () {
                input.value = formatAmountInput(input.value);
            };

            // Live formatting with caret preservation for natural typing/editing.
            input.addEventListener('input', function () {
                applyLiveAmountFormat(input);
            });
            input.addEventListener('blur', applyPrettyFormat);
            input.value = formatAmountInput(input.value);
        });
    };

    const normalizeAmountOnSubmit = function (form) {
        form.querySelectorAll('.js-accounting-amount-input').forEach(function (input) {
            input.value = normalizeAmountInput(input.value);
        });
    };

    const shouldShowProfileBlock = function (blockKey, requiredFields) {
        const fieldSet = new Set(requiredFields || []);
        if (blockKey === 'common_customer_supplier') {
            return fieldSet.has('customer_id') || fieldSet.has('supplier_id');
        }
        if (blockKey === 'treasury_entities') {
            return fieldSet.has('bank_id')
                || fieldSet.has('cash_box_id')
                || fieldSet.has('wallet_id')
                || fieldSet.has('payment_method_id')
                || fieldSet.has('chequebook_id');
        }
        if (blockKey === 'other_entities') {
            return fieldSet.has('expense_category_id')
                || fieldSet.has('fixed_asset_category_id')
                || fieldSet.has('shareholder_id');
        }
        if (blockKey === 'transfer_details') {
            return fieldSet.has('from_treasury_type')
                || fieldSet.has('from_treasury_id')
                || fieldSet.has('to_treasury_type')
                || fieldSet.has('to_treasury_id')
                || fieldSet.has('value_date')
                || fieldSet.has('transfer_fee');
        }

        return true;
    };

    const setFieldContainerEnabled = function (container, enabled) {
        container.querySelectorAll('input, select, textarea, button').forEach(function (control) {
            if (typeof control.dataset.scenarioDefaultDisabled === 'undefined') {
                control.dataset.scenarioDefaultDisabled = control.disabled ? '1' : '0';
            }
            const defaultDisabled = control.dataset.scenarioDefaultDisabled === '1';
            control.disabled = defaultDisabled || !enabled;
        });
    };

    const applyScenarioFieldVisibility = function (sourceForm, requiredFields) {
        const requiredFieldSet = new Set(Array.isArray(requiredFields) ? requiredFields : []);
        sourceForm.querySelectorAll('[data-scenario-field-key]').forEach(function (fieldContainer) {
            const keyAttr = String(fieldContainer.getAttribute('data-scenario-field-key') || '').trim();
            const keys = keyAttr === '' ? [] : keyAttr.split(/\s+/).filter(Boolean);
            const visible = keys.length === 0 || keys.some(function (key) {
                return requiredFieldSet.has(key);
            });
            fieldContainer.classList.toggle('d-none', !visible);
            setFieldContainerEnabled(fieldContainer, visible);
        });
    };

    const applyScenarioProfileVisibility = function (scenarioSelect, sourceForm, scenarioDefinitions) {
        const selectedScenario = scenarioSelect.value;
        const meta = scenarioDefinitions[selectedScenario] || {};
        const requiredFields = Array.isArray(meta.required_fields) ? meta.required_fields : [];
        sourceForm.querySelectorAll('.scenario-profile-block').forEach(function (block) {
            const blockKey = block.getAttribute('data-profile-target') || '';
            block.classList.toggle('d-none', !shouldShowProfileBlock(blockKey, requiredFields));
        });
        applyScenarioFieldVisibility(sourceForm, requiredFields);
    };

    const syncHiddenFields = function (sourceForm, runForm) {
        const sourceData = new FormData(sourceForm);
        [
            'scenario_key',
            'amount',
            'scenario_date',
            'notes',
            'customer_id',
            'supplier_id',
            'bank_id',
            'cash_box_id',
            'wallet_id',
            'chequebook_id',
            'payment_method_id',
            'expense_category_id',
            'fixed_asset_category_id',
            'shareholder_id',
            'from_treasury_type',
            'from_treasury_id',
            'to_treasury_type',
            'to_treasury_id',
            'value_date',
            'transfer_fee',
            'reference_number',
            'description',
        ].forEach(function (key) {
            const target = runForm.querySelector('input[name="' + key + '"]');
            if (!target) {
                return;
            }
            target.value = String(sourceData.get(key) ?? '');
        });
    };

    const parseJsonNode = function (node) {
        if (!node) {
            return {};
        }
        try {
            return JSON.parse(node.textContent || '{}');
        } catch (e) {
            return {};
        }
    };

    const resolveScenarioRow = function (scenarioRows, scenarioKey) {
        if (!scenarioRows || typeof scenarioRows !== 'object') {
            return {};
        }
        const row = scenarioRows[scenarioKey];
        return row && typeof row === 'object' ? row : {};
    };

    const normalizeSearch = function (value) {
        return String(value || '').trim().toLowerCase();
    };

    const applyScenarioFilters = function (scenarioSelect, searchInput, statusFilter, scenarioRows) {
        if (!scenarioSelect) {
            return;
        }
        const term = normalizeSearch(searchInput ? searchInput.value : '');
        const selectedStatus = String(statusFilter ? statusFilter.value : 'all').trim();
        let hasVisible = false;
        let firstVisibleValue = '';

        Array.from(scenarioSelect.options).forEach(function (option) {
            const optionText = normalizeSearch(option.textContent || option.innerText || '');
            const scenarioKey = String(option.value || '');
            const scenarioRow = resolveScenarioRow(scenarioRows, scenarioKey);
            const scenarioStatus = String(scenarioRow.status || 'not_run');
            const searchMatch = term === '' || optionText.indexOf(term) >= 0;
            const statusMatch = selectedStatus === 'all' || scenarioStatus === selectedStatus;
            const visible = searchMatch && statusMatch;
            option.hidden = !visible;
            if (visible) {
                hasVisible = true;
                if (firstVisibleValue === '') {
                    firstVisibleValue = scenarioKey;
                }
            }
        });

        const currentOption = scenarioSelect.selectedOptions.length > 0 ? scenarioSelect.selectedOptions[0] : null;
        if (!hasVisible) {
            Array.from(scenarioSelect.options).forEach(function (option) {
                option.hidden = false;
            });
            return;
        }
        if (!currentOption || currentOption.hidden) {
            scenarioSelect.value = firstVisibleValue;
            scenarioSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }
    };

    const statusBadgeClass = function (status) {
        if (status === 'success') {
            return 'badge bg-success';
        }
        if (status === 'failed') {
            return 'badge bg-danger';
        }
        if (status === 'mixed') {
            return 'badge bg-warning text-dark';
        }
        return 'badge bg-secondary';
    };

    const statusLabel = function (status, dictionary) {
        const map = dictionary || {};
        if (status === 'success') {
            return String(map.success || 'Success');
        }
        if (status === 'failed') {
            return String(map.failed || 'Failed');
        }
        if (status === 'mixed') {
            return String(map.mixed || 'Mixed');
        }
        return String(map.not_run || 'Not run');
    };

    const updateSelectedScenarioPanel = function (summaryPanel, scenarioRows, scenarioKey) {
        if (!summaryPanel) {
            return;
        }
        const statusDictionary = parseJsonNode(document.getElementById('scenario-status-labels'));
        const scenarioRow = resolveScenarioRow(scenarioRows, scenarioKey);
        const status = String(scenarioRow.status || summaryPanel.dataset.defaultStatus || 'not_run');
        const totalRuns = Number(scenarioRow.total_runs || summaryPanel.dataset.defaultTotalRuns || 0);
        const successRuns = Number(scenarioRow.success_runs || summaryPanel.dataset.defaultSuccessRuns || 0);
        const failedRuns = Number(scenarioRow.failed_runs || summaryPanel.dataset.defaultFailedRuns || 0);
        const lastRunAtRaw = String(scenarioRow.last_run_at || summaryPanel.dataset.defaultLastRunAt || '').trim();
        const lastRunAt = lastRunAtRaw !== '' ? lastRunAtRaw : '—';
        const lastMessage = String(scenarioRow.last_message || summaryPanel.dataset.defaultLastMessage || '').trim();

        const statusNode = summaryPanel.querySelector('[data-role="status-label"]');
        const totalNode = summaryPanel.querySelector('[data-role="runs-count"]');
        const successNode = summaryPanel.querySelector('[data-role="success-count"]');
        const failedNode = summaryPanel.querySelector('[data-role="failed-count"]');
        const lastRunNode = summaryPanel.querySelector('[data-role="last-run-at"]');
        const messageNode = summaryPanel.querySelector('[data-role="last-message"]');

        if (statusNode) {
            statusNode.className = statusBadgeClass(status);
            statusNode.textContent = statusLabel(status, statusDictionary);
        }
        if (totalNode) {
            totalNode.textContent = String(statusDictionary.total_runs_prefix || 'Runs: ') + String(totalRuns);
        }
        if (successNode) {
            successNode.textContent = String(statusDictionary.success_runs_prefix || 'Success: ') + String(successRuns);
        }
        if (failedNode) {
            failedNode.textContent = String(statusDictionary.failed_runs_prefix || 'Failed: ') + String(failedRuns);
        }
        if (lastRunNode) {
            lastRunNode.textContent = String(statusDictionary.last_run_prefix || 'Last run: ') + lastRunAt;
        }
        if (messageNode) {
            if (lastMessage === '') {
                messageNode.classList.add('d-none');
                messageNode.textContent = '';
            } else {
                messageNode.classList.remove('d-none');
                messageNode.textContent = lastMessage;
            }
        }
    };

    const resolveFocusElement = function (focusTarget) {
        const key = String(focusTarget || '').trim().toLowerCase();
        if (key === 'preview') {
            return document.getElementById('scenario-preview-result');
        }
        if (key === 'result') {
            return document.getElementById('scenario-apply-result');
        }
        if (key === 'progress') {
            return document.getElementById('scenario-progress-report');
        }

        return null;
    };

    const scrollToFocusTarget = function (focusTarget) {
        const targetElement = resolveFocusElement(focusTarget);
        if (!targetElement) {
            return;
        }

        window.setTimeout(function () {
            const top = Math.max(0, Math.round(targetElement.getBoundingClientRect().top + window.pageYOffset - 24));
            window.scrollTo({ top: top, behavior: 'smooth' });
        }, 120);
    };

    const scrollToScenarioForm = function (sourceForm) {
        if (!sourceForm) {
            return;
        }
        const focusCard = sourceForm.closest('.card') || sourceForm;
        // Prefer native element scrolling so nested scroll containers (layout wrappers) are handled too.
        if (typeof focusCard.scrollIntoView === 'function') {
            focusCard.scrollIntoView({ behavior: 'smooth', block: 'start', inline: 'nearest' });
        }
        // Fallback for layouts where the window remains the active scroller.
        const top = Math.max(0, Math.round(focusCard.getBoundingClientRect().top + window.pageYOffset - 18));
        window.scrollTo({ top: top, behavior: 'smooth' });

        focusCard.classList.remove('scenario-runner-form-focus');
        // Reflow to reliably replay the focus animation on repeated clicks.
        void focusCard.offsetWidth;
        focusCard.classList.add('scenario-runner-form-focus');
        window.setTimeout(function () {
            focusCard.classList.remove('scenario-runner-form-focus');
        }, 1300);
    };

    const hideScenarioOutputCards = function () {
        ['scenario-preview-result', 'scenario-apply-result'].forEach(function (id) {
            const card = document.getElementById(id);
            if (!card) {
                return;
            }
            card.querySelectorAll('.collapse.show').forEach(function (collapseEl) {
                if (window.bootstrap && window.bootstrap.Collapse) {
                    window.bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false }).hide();
                } else {
                    collapseEl.classList.remove('show');
                }
            });
            card.classList.add('d-none');
        });

        const runButton = document.querySelector('button[form="scenario-runner-run-form"]');
        if (runButton) {
            runButton.setAttribute('disabled', 'disabled');
        }
    };

    const isScenarioOptionAvailable = function (scenarioSelect, scenarioKey) {
        if (!scenarioSelect || scenarioKey === '') {
            return false;
        }
        return Array.from(scenarioSelect.options).some(function (option) {
            return String(option.value || '') === scenarioKey;
        });
    };

    const setActiveScenarioRow = function (progressTable, scenarioKey) {
        if (!progressTable) {
            return;
        }
        const key = String(scenarioKey || '').trim();
        progressTable.querySelectorAll('.js-scenario-select-row').forEach(function (row) {
            const rowKey = String(row.getAttribute('data-scenario-key') || '').trim();
            const active = key !== '' && rowKey === key;
            row.classList.toggle('is-active', active);
            if (active) {
                row.setAttribute('aria-selected', 'true');
            } else {
                row.removeAttribute('aria-selected');
            }
        });
    };

    const isInteractiveRowTarget = function (target) {
        if (!target || !target.closest) {
            return false;
        }
        return Boolean(target.closest('button, a, input, select, textarea, label, .js-scenario-errors-toggle, .js-scenario-errors-row'));
    };

    const bindScenarioRowSelection = function (progressTable, scenarioSelect, sourceForm) {
        if (!progressTable || !scenarioSelect || !sourceForm) {
            return;
        }
        progressTable.querySelectorAll('.js-scenario-select-row').forEach(function (row) {
            if (row.dataset.rowSelectBound === '1') {
                return;
            }
            row.dataset.rowSelectBound = '1';
            row.addEventListener('click', function (event) {
                if (isInteractiveRowTarget(event.target)) {
                    return;
                }
                const scenarioKey = String(row.getAttribute('data-scenario-key') || '').trim();
                if (!isScenarioOptionAvailable(scenarioSelect, scenarioKey)) {
                    return;
                }

                scenarioSelect.value = scenarioKey;
                scenarioSelect.dispatchEvent(new Event('change', { bubbles: true }));
                setActiveScenarioRow(progressTable, scenarioKey);
                scrollToScenarioForm(sourceForm);
            });
        });
    };

    const resolveErrorLogsUrl = function (routeTemplate, scenarioKey) {
        const template = String(routeTemplate || '').trim();
        const key = String(scenarioKey || '').trim();
        if (template === '' || key === '') {
            return '';
        }
        return template.replace('__SCENARIO_KEY__', encodeURIComponent(key));
    };

    const ensureErrorDetailRow = function (baseRow) {
        if (!baseRow || !baseRow.parentNode) {
            return null;
        }
        const next = baseRow.nextElementSibling;
        if (next && next.classList.contains('js-scenario-errors-row')) {
            return next;
        }

        const detailRow = document.createElement('tr');
        detailRow.className = 'js-scenario-errors-row';
        const detailCell = document.createElement('td');
        detailCell.colSpan = Math.max(1, baseRow.children.length);
        detailCell.className = 'scenario-runner-errors-cell';
        detailRow.appendChild(detailCell);
        baseRow.parentNode.insertBefore(detailRow, baseRow.nextSibling);
        return detailRow;
    };

    const setErrorsButtonLabel = function (button, expanded, config) {
        if (!button) {
            return;
        }
        const count = Number(button.getAttribute('data-error-count') || 0);
        const showTemplate = String(config.show_with_count || 'Show errors (:count)');
        const hideLabel = String(config.hide || 'Hide errors');
        if (expanded) {
            button.textContent = hideLabel;
            return;
        }
        button.textContent = showTemplate.replace(':count', String(count));
    };

    const renderErrorLogs = function (container, errors, config) {
        if (!container) {
            return;
        }
        container.innerHTML = '';
        const list = Array.isArray(errors) ? errors : [];
        if (list.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'small text-muted';
            empty.textContent = String(config.empty || 'No errors');
            container.appendChild(empty);
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'scenario-runner-errors-list';
        list.forEach(function (item) {
            const row = document.createElement('div');
            row.className = 'scenario-runner-errors-item border rounded p-2 mb-2';
            const at = String((item && item.at) || '').trim();
            const message = String((item && item.message) || '').trim();

            const meta = document.createElement('div');
            meta.className = 'small text-muted mb-1';
            meta.textContent = String(config.at_prefix || 'At: ') + (at !== '' ? at : '—');

            const body = document.createElement('div');
            body.className = 'small';
            body.textContent = message !== '' ? message : '—';

            row.appendChild(meta);
            row.appendChild(body);
            wrapper.appendChild(row);
        });

        container.appendChild(wrapper);
    };

    const bindScenarioErrorButtons = function (root, config) {
        if (!root || !config || !config.route_template) {
            return;
        }
        root.querySelectorAll('.js-scenario-errors-toggle').forEach(function (button) {
            if (button.dataset.errorBound === '1') {
                return;
            }
            button.dataset.errorBound = '1';
            setErrorsButtonLabel(button, false, config);

            button.addEventListener('click', function () {
                const baseRow = button.closest('tr');
                const scenarioKey = String(button.getAttribute('data-scenario-key') || '').trim();
                const detailRow = ensureErrorDetailRow(baseRow);
                if (!detailRow) {
                    return;
                }

                const cell = detailRow.querySelector('td');
                if (!cell) {
                    return;
                }

                if (detailRow.dataset.loaded === '1') {
                    const shouldHide = !detailRow.classList.contains('d-none');
                    detailRow.classList.toggle('d-none', shouldHide);
                    setErrorsButtonLabel(button, !shouldHide, config);
                    return;
                }

                detailRow.classList.remove('d-none');
                setErrorsButtonLabel(button, true, config);
                cell.innerHTML = '';
                const loading = document.createElement('div');
                loading.className = 'small text-muted';
                loading.textContent = String(config.loading || 'Loading...');
                cell.appendChild(loading);

                const url = resolveErrorLogsUrl(config.route_template, scenarioKey);
                if (url === '') {
                    cell.textContent = String(config.failed || 'Could not load errors');
                    return;
                }

                fetch(url, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                }).then(function (response) {
                    if (!response.ok) {
                        throw new Error('http_error_' + String(response.status || 0));
                    }
                    return response.json();
                }).then(function (payload) {
                    const errors = payload && Array.isArray(payload.errors) ? payload.errors : [];
                    renderErrorLogs(cell, errors, config);
                    detailRow.dataset.loaded = '1';
                }).catch(function () {
                    cell.innerHTML = '';
                    const failed = document.createElement('div');
                    failed.className = 'small text-danger';
                    failed.textContent = String(config.failed || 'Could not load errors');
                    cell.appendChild(failed);
                });
            });
        });
    };

    const firstRow = function (rows) {
        return Array.isArray(rows) && rows.length > 0 ? rows[0] : null;
    };

    const rowId = function (row) {
        if (!row || typeof row !== 'object') {
            return '';
        }
        return String(row.id ?? '').trim();
    };

    const rowLabel = function (row) {
        if (!row || typeof row !== 'object') {
            return '';
        }
        const direct = String(row.name ?? row.title ?? row.label ?? '').trim();
        if (direct !== '') {
            return direct;
        }
        const id = rowId(row);
        return id !== '' ? ('#' + id) : '';
    };

    const hasRequiredField = function (requiredFields, field) {
        return Array.isArray(requiredFields) && requiredFields.indexOf(field) >= 0;
    };

    const normalizePositiveId = function (value) {
        const num = Number(value);
        return Number.isFinite(num) && num > 0 ? Math.trunc(num) : 0;
    };

    const readNamedFieldValue = function (sourceForm, fieldName) {
        const field = sourceForm.querySelector('[name="' + fieldName + '"]');
        return field ? String(field.value || '').trim() : '';
    };

    const writeNamedFieldValueIfChanged = function (sourceForm, fieldName, value) {
        const field = sourceForm.querySelector('[name="' + fieldName + '"]');
        if (!field) {
            return;
        }
        const nextValue = value === null || value === undefined ? '' : String(value);
        if (String(field.value || '') === nextValue) {
            return;
        }
        field.value = nextValue;
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const resolveTransferEndpointSelection = function (sourceForm, prefix) {
        const bankId = normalizePositiveId(readNamedFieldValue(sourceForm, prefix + 'bank_id'));
        if (bankId > 0) {
            return { type: 'bank', id: bankId };
        }
        const cashBoxId = normalizePositiveId(readNamedFieldValue(sourceForm, prefix + 'cash_box_id'));
        if (cashBoxId > 0) {
            return { type: 'cashbox', id: cashBoxId };
        }
        const walletId = normalizePositiveId(readNamedFieldValue(sourceForm, prefix + 'wallet_id'));
        if (walletId > 0) {
            return { type: 'wallet', id: walletId };
        }

        return { type: '', id: 0 };
    };

    const resolveTransferPickerWrapper = function (sourceForm, endpoint) {
        return sourceForm.querySelector('[data-transfer-endpoint-picker="' + endpoint + '"]');
    };

    const isTransferPickerTouched = function (sourceForm, endpoint) {
        const wrapper = resolveTransferPickerWrapper(sourceForm, endpoint);
        return Boolean(wrapper && wrapper.dataset.transferPickerTouched === '1');
    };

    const syncTransferEndpointFields = function (sourceForm, endpoint, prefix) {
        const selection = resolveTransferEndpointSelection(sourceForm, prefix);
        if (selection.type !== '' && selection.id > 0) {
            writeNamedFieldValueIfChanged(sourceForm, endpoint + '_treasury_type', selection.type);
            writeNamedFieldValueIfChanged(sourceForm, endpoint + '_treasury_id', String(selection.id));
            return;
        }

        const currentType = readNamedFieldValue(sourceForm, endpoint + '_treasury_type');
        const currentId = normalizePositiveId(readNamedFieldValue(sourceForm, endpoint + '_treasury_id'));
        const pickerTouched = isTransferPickerTouched(sourceForm, endpoint);

        // After preview page reloads, keep server-validated transfer endpoints
        // until picker UI finishes restoring or user changes the selection.
        if (!pickerTouched && currentType !== '' && currentId > 0) {
            return;
        }

        writeNamedFieldValueIfChanged(sourceForm, endpoint + '_treasury_type', '');
        writeNamedFieldValueIfChanged(sourceForm, endpoint + '_treasury_id', '');
    };

    const syncTransferDetailsFromPickers = function (sourceForm) {
        syncTransferEndpointFields(sourceForm, 'from', 'transfer_from_');
        syncTransferEndpointFields(sourceForm, 'to', 'transfer_to_');
    };

    const transferTypeToChannelId = function (treasuryType) {
        if (treasuryType === 'bank') {
            return 'bank';
        }
        if (treasuryType === 'cashbox') {
            return 'cash_box';
        }
        if (treasuryType === 'wallet') {
            return 'wallet';
        }
        return '';
    };

    const setTransferEndpointPickerSelection = function (sourceForm, endpoint, treasuryType, treasuryId) {
        const root = sourceForm.querySelector('[data-transfer-endpoint-picker="' + endpoint + '"] [data-payment-picker]');
        const channelId = transferTypeToChannelId(String(treasuryType || '').trim().toLowerCase());
        const destinationId = normalizePositiveId(treasuryId);
        if (!root) {
            return;
        }
        if (window.jQuery) {
            window.jQuery(root).trigger('accounting:payment-picker:set', [{
                channelId: channelId,
                destinationId: destinationId > 0 ? destinationId : '',
                paymentMethodId: '',
            }]);
            return;
        }

        if (channelId === 'bank') {
            writeNamedFieldValueIfChanged(sourceForm, 'transfer_' + endpoint + '_bank_id', destinationId > 0 ? String(destinationId) : '');
            writeNamedFieldValueIfChanged(sourceForm, 'transfer_' + endpoint + '_cash_box_id', '');
            writeNamedFieldValueIfChanged(sourceForm, 'transfer_' + endpoint + '_wallet_id', '');
        } else if (channelId === 'cash_box') {
            writeNamedFieldValueIfChanged(sourceForm, 'transfer_' + endpoint + '_bank_id', '');
            writeNamedFieldValueIfChanged(sourceForm, 'transfer_' + endpoint + '_cash_box_id', destinationId > 0 ? String(destinationId) : '');
            writeNamedFieldValueIfChanged(sourceForm, 'transfer_' + endpoint + '_wallet_id', '');
        } else if (channelId === 'wallet') {
            writeNamedFieldValueIfChanged(sourceForm, 'transfer_' + endpoint + '_bank_id', '');
            writeNamedFieldValueIfChanged(sourceForm, 'transfer_' + endpoint + '_cash_box_id', '');
            writeNamedFieldValueIfChanged(sourceForm, 'transfer_' + endpoint + '_wallet_id', destinationId > 0 ? String(destinationId) : '');
        } else {
            writeNamedFieldValueIfChanged(sourceForm, 'transfer_' + endpoint + '_bank_id', '');
            writeNamedFieldValueIfChanged(sourceForm, 'transfer_' + endpoint + '_cash_box_id', '');
            writeNamedFieldValueIfChanged(sourceForm, 'transfer_' + endpoint + '_wallet_id', '');
        }
    };

    const bindTransferEndpointPickers = function (sourceForm, runForm) {
        ['from', 'to'].forEach(function (endpoint) {
            const wrapper = resolveTransferPickerWrapper(sourceForm, endpoint);
            if (!wrapper || wrapper.dataset.transferPickerBound === '1') {
                return;
            }
            wrapper.dataset.transferPickerBound = '1';
            const syncSelection = function () {
                wrapper.dataset.transferPickerTouched = '1';
                syncTransferDetailsFromPickers(sourceForm);
                syncHiddenFields(sourceForm, runForm);
            };
            wrapper.addEventListener('click', function () {
                window.setTimeout(syncSelection, 0);
            });
            wrapper.addEventListener('change', function () {
                window.setTimeout(syncSelection, 0);
            });
        });
    };

    const parseGregorianDateToUnix = function (value) {
        const raw = String(value || '').trim().replace(/\//g, '-');
        const parts = raw.split('-');
        if (parts.length !== 3) {
            return null;
        }
        const year = Number(parts[0]);
        const month = Number(parts[1]);
        const day = Number(parts[2]);
        if (!Number.isInteger(year) || !Number.isInteger(month) || !Number.isInteger(day)) {
            return null;
        }
        // Sample values are generated as Gregorian YYYY-MM-DD; ignore probable Jalali years here.
        if (year < 1900 || year > 2200 || month < 1 || month > 12 || day < 1 || day > 31) {
            return null;
        }
        const unix = new Date(year, month - 1, day).getTime();
        return Number.isFinite(unix) ? unix : null;
    };

    const syncPersianDatepickerValue = function (field, rawValue) {
        if (!field || typeof window.jQuery === 'undefined') {
            return;
        }

        if (typeof window.initAccountingDateFields === 'function') {
            window.initAccountingDateFields(field.closest('form') || document);
        }

        const $field = window.jQuery(field);
        if (window.RMS2PersianDatePicker && typeof window.RMS2PersianDatePicker.initElement === 'function') {
            window.RMS2PersianDatePicker.initElement($field);
        }

        const unix = parseGregorianDateToUnix(rawValue);
        if (unix === null) {
            return;
        }

        const datepicker = $field.data('datepicker');
        if (datepicker && typeof datepicker.setDate === 'function') {
            datepicker.setDate(unix);
        }
    };

    const setNamedFieldValue = function (sourceForm, fieldName, value) {
        const field = sourceForm.querySelector('[name="' + fieldName + '"]');
        if (!field) {
            return;
        }
        const finalValue = value === null || value === undefined ? '' : String(value);
        field.value = finalValue;
        if (field.classList.contains('accounting-date-field') && String(field.getAttribute('data-calendar') || '').toLowerCase() === 'jalali') {
            syncPersianDatepickerValue(field, finalValue);
        }
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const applyCustomerPicker = function (sourceForm, customerId, customerText) {
        const pickerRoot = sourceForm.querySelector('[data-sales-customer-picker]');
        if (pickerRoot && window.jQuery) {
            window.jQuery(pickerRoot).trigger('accounting:customer-picker:set', [{
                id: customerId,
                text: customerText,
                customerId: customerId,
            }]);
        } else {
            setNamedFieldValue(sourceForm, 'customer_id', customerId);
        }
    };

    const applySupplierPicker = function (sourceForm, supplierId, supplierText) {
        const pickerRoot = sourceForm.querySelector('.js-accounting-card-picker');
        if (pickerRoot && window.jQuery) {
            window.jQuery(pickerRoot).trigger('accounting:card-picker:set', [{
                id: supplierId,
                text: supplierText,
            }]);
        } else {
            setNamedFieldValue(sourceForm, 'supplier_id', supplierId);
        }
    };

    const applyPaymentDestinationPicker = function (sourceForm, payload) {
        const pickerRoot = sourceForm.querySelector('[data-payment-picker]');
        if (pickerRoot && window.jQuery) {
            window.jQuery(pickerRoot).trigger('accounting:payment-picker:set', [payload]);
            return;
        }
        setNamedFieldValue(sourceForm, 'payment_method_id', payload.paymentMethodId || '');
        setNamedFieldValue(sourceForm, 'bank_id', payload.channelId === 'bank' ? payload.destinationId : '');
        setNamedFieldValue(sourceForm, 'cash_box_id', payload.channelId === 'cash_box' ? payload.destinationId : '');
        setNamedFieldValue(sourceForm, 'wallet_id', payload.channelId === 'wallet' ? payload.destinationId : '');
    };

    const buildScenarioSample = function (scenarioKey, scenarioMeta, entityOptions) {
        const requiredFields = Array.isArray(scenarioMeta.required_fields) ? scenarioMeta.required_fields : [];
        const today = new Date();
        const year = String(today.getFullYear());
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const day = String(today.getDate()).padStart(2, '0');
        const dateValue = year + '-' + month + '-' + day;
        const customers = Array.isArray(entityOptions.customers) ? entityOptions.customers : [];
        const suppliers = Array.isArray(entityOptions.suppliers) ? entityOptions.suppliers : [];
        const banks = Array.isArray(entityOptions.banks) ? entityOptions.banks : [];
        const cashBoxes = Array.isArray(entityOptions.cash_boxes) ? entityOptions.cash_boxes : [];
        const wallets = Array.isArray(entityOptions.wallets) ? entityOptions.wallets : [];
        const chequebooks = Array.isArray(entityOptions.chequebooks) ? entityOptions.chequebooks : [];
        const expenseCategories = Array.isArray(entityOptions.expense_categories) ? entityOptions.expense_categories : [];
        const fixedAssetCategories = Array.isArray(entityOptions.fixed_asset_categories) ? entityOptions.fixed_asset_categories : [];
        const shareholders = Array.isArray(entityOptions.shareholders) ? entityOptions.shareholders : [];
        const cashMethods = Array.isArray(entityOptions.cash_payment_methods) ? entityOptions.cash_payment_methods : [];
        const chequeMethods = Array.isArray(entityOptions.cheque_payment_methods) ? entityOptions.cheque_payment_methods : [];
        const customer = firstRow(customers);
        const supplier = firstRow(suppliers);
        const bank = firstRow(banks);
        const cashBox = firstRow(cashBoxes);
        const wallet = firstRow(wallets);
        const chequebook = firstRow(chequebooks);
        const expenseCategory = firstRow(expenseCategories);
        const fixedAssetCategory = firstRow(fixedAssetCategories);
        const shareholder = firstRow(shareholders);
        const scenarioTitle = String(scenarioMeta.title || scenarioKey);
        const sample = {
            amount: '1000000',
            scenario_date: dateValue,
            value_date: dateValue,
            transfer_fee: '0',
            notes: 'نمونه | ' + scenarioTitle,
            reference_number: 'SCN-SAMPLE-' + String(today.getTime()),
            description: 'اجرای نمونه برای ' + scenarioTitle,
            customer_id: rowId(customer),
            customer_text: rowLabel(customer),
            supplier_id: rowId(supplier),
            supplier_text: rowLabel(supplier),
            bank_id: rowId(bank),
            cash_box_id: rowId(cashBox),
            wallet_id: rowId(wallet),
            chequebook_id: rowId(chequebook),
            expense_category_id: rowId(expenseCategory),
            fixed_asset_category_id: rowId(fixedAssetCategory),
            shareholder_id: rowId(shareholder),
            payment_method_id: '',
            payment_channel_id: '',
            payment_destination_id: '',
            from_treasury_type: '',
            from_treasury_id: '',
            to_treasury_type: '',
            to_treasury_id: '',
        };

        if (scenarioKey === 'bank_transfer_treasury') {
            sample.from_treasury_type = 'wallet';
            sample.from_treasury_id = sample.wallet_id;
            sample.to_treasury_type = 'bank';
            sample.to_treasury_id = sample.bank_id;
        } else if (scenarioKey === 'bank_transfer_cashbox_to_bank') {
            sample.from_treasury_type = 'cashbox';
            sample.from_treasury_id = sample.cash_box_id;
            sample.to_treasury_type = 'bank';
            sample.to_treasury_id = sample.bank_id;
        } else if (scenarioKey === 'customer_advance_apply') {
            if (sample.bank_id) {
                sample.payment_channel_id = 'bank';
                sample.payment_destination_id = sample.bank_id;
            } else if (sample.cash_box_id) {
                sample.payment_channel_id = 'cash_box';
                sample.payment_destination_id = sample.cash_box_id;
            }
        }

        if (hasRequiredField(requiredFields, 'payment_method_id')) {
            const prefersCheque = scenarioKey.indexOf('cheque') >= 0 || hasRequiredField(requiredFields, 'chequebook_id');
            const method = firstRow(prefersCheque ? chequeMethods : cashMethods) || firstRow(cashMethods) || firstRow(chequeMethods);
            sample.payment_method_id = rowId(method);
        }

        return { requiredFields: requiredFields, sample: sample };
    };

    const applyScenarioSample = function (sourceForm, runForm, scenarioSelect, scenarioDefinitions, entityOptions) {
        const selectedScenario = String(scenarioSelect.value || '').trim();
        const scenarioMeta = scenarioDefinitions[selectedScenario] || {};
        const sampleData = buildScenarioSample(selectedScenario, scenarioMeta, entityOptions);
        const requiredFields = sampleData.requiredFields;
        const sample = sampleData.sample;

        if (hasRequiredField(requiredFields, 'amount')) {
            setNamedFieldValue(sourceForm, 'amount', sample.amount);
        }
        if (hasRequiredField(requiredFields, 'scenario_date')) {
            setNamedFieldValue(sourceForm, 'scenario_date', sample.scenario_date);
        }
        if (hasRequiredField(requiredFields, 'value_date')) {
            setNamedFieldValue(sourceForm, 'value_date', sample.value_date);
        }
        setNamedFieldValue(sourceForm, 'notes', sample.notes);
        setNamedFieldValue(sourceForm, 'transfer_fee', sample.transfer_fee);
        setNamedFieldValue(sourceForm, 'reference_number', sample.reference_number);
        setNamedFieldValue(sourceForm, 'description', sample.description);

        if (hasRequiredField(requiredFields, 'customer_id')) {
            applyCustomerPicker(sourceForm, sample.customer_id, sample.customer_text);
        }
        if (hasRequiredField(requiredFields, 'supplier_id')) {
            applySupplierPicker(sourceForm, sample.supplier_id, sample.supplier_text);
        }

        if (hasRequiredField(requiredFields, 'expense_category_id')) {
            setNamedFieldValue(sourceForm, 'expense_category_id', sample.expense_category_id);
        }
        if (hasRequiredField(requiredFields, 'fixed_asset_category_id')) {
            setNamedFieldValue(sourceForm, 'fixed_asset_category_id', sample.fixed_asset_category_id);
        }
        if (hasRequiredField(requiredFields, 'shareholder_id')) {
            setNamedFieldValue(sourceForm, 'shareholder_id', sample.shareholder_id);
        }
        if (hasRequiredField(requiredFields, 'chequebook_id')) {
            setNamedFieldValue(sourceForm, 'chequebook_id', sample.chequebook_id);
        }

        if (hasRequiredField(requiredFields, 'bank_id') || hasRequiredField(requiredFields, 'cash_box_id') || hasRequiredField(requiredFields, 'wallet_id') || hasRequiredField(requiredFields, 'payment_method_id')) {
            let channelId = sample.payment_channel_id || '';
            let destinationId = sample.payment_destination_id || '';
            if (!channelId || !destinationId) {
                if (hasRequiredField(requiredFields, 'cash_box_id')) {
                    channelId = 'cash_box';
                    destinationId = sample.cash_box_id;
                } else if (hasRequiredField(requiredFields, 'wallet_id')) {
                    channelId = 'wallet';
                    destinationId = sample.wallet_id;
                } else if (hasRequiredField(requiredFields, 'bank_id')) {
                    channelId = 'bank';
                    destinationId = sample.bank_id;
                }
            }
            applyPaymentDestinationPicker(sourceForm, {
                channelId: channelId,
                destinationId: destinationId,
                paymentMethodId: sample.payment_method_id,
            });
        }

        if (hasRequiredField(requiredFields, 'from_treasury_type')) {
            setNamedFieldValue(sourceForm, 'from_treasury_type', sample.from_treasury_type);
        }
        if (hasRequiredField(requiredFields, 'from_treasury_id')) {
            setNamedFieldValue(sourceForm, 'from_treasury_id', sample.from_treasury_id);
            setTransferEndpointPickerSelection(sourceForm, 'from', sample.from_treasury_type, sample.from_treasury_id);
        }
        if (hasRequiredField(requiredFields, 'to_treasury_type')) {
            setNamedFieldValue(sourceForm, 'to_treasury_type', sample.to_treasury_type);
        }
        if (hasRequiredField(requiredFields, 'to_treasury_id')) {
            setNamedFieldValue(sourceForm, 'to_treasury_id', sample.to_treasury_id);
            setTransferEndpointPickerSelection(sourceForm, 'to', sample.to_treasury_type, sample.to_treasury_id);
        }

        bindAmountInputs(sourceForm);
        syncTransferDetailsFromPickers(sourceForm);
        syncHiddenFields(sourceForm, runForm);
    };

    const boot = function () {
        const scenarioSelect = document.getElementById('scenario-key-select');
        const runForm = document.getElementById('scenario-runner-run-form');
        const sourceForm = document.getElementById('scenario-runner-form');
        const definitionsNode = document.getElementById('scenario-definitions-data');
        const entityOptionsNode = document.getElementById('scenario-entity-options-data');
        const scenarioStatusNode = document.getElementById('scenario-status-data');
        const scenarioErrorLogConfigNode = document.getElementById('scenario-error-log-config');
        const scenarioFilterInput = document.getElementById('scenario-filter-input');
        const scenarioStatusFilter = document.getElementById('scenario-status-filter');
        const selectedScenarioPanel = document.querySelector('[data-scenario-selection-summary]');
        const sampleFillButton = document.getElementById('scenario-sample-fill-btn');
        if (!scenarioSelect || !runForm || !sourceForm || !definitionsNode || !entityOptionsNode || !sampleFillButton) {
            return;
        }
        if (sourceForm.dataset.runnerBooted === '1') {
            return;
        }
        sourceForm.dataset.runnerBooted = '1';

        const scenarioDefinitions = parseJsonNode(definitionsNode);
        const entityOptions = parseJsonNode(entityOptionsNode);
        const scenarioRows = parseJsonNode(scenarioStatusNode);
        const scenarioErrorLogConfig = parseJsonNode(scenarioErrorLogConfigNode);
        const progressTable = document.querySelector('#scenario-progress-report .scenario-runner-table');

        if (typeof window.initAccountingDateFields === 'function') {
            window.initAccountingDateFields(sourceForm);
        }

        bindAmountInputs(sourceForm);
        applyScenarioProfileVisibility(scenarioSelect, sourceForm, scenarioDefinitions);
        bindTransferEndpointPickers(sourceForm, runForm);
        let lastSelectedScenarioKey = String(scenarioSelect.value || '').trim();
        scenarioSelect.addEventListener('change', function () {
            const currentScenarioKey = String(scenarioSelect.value || '').trim();
            const scenarioChanged = currentScenarioKey !== lastSelectedScenarioKey;
            applyScenarioProfileVisibility(scenarioSelect, sourceForm, scenarioDefinitions);
            syncTransferDetailsFromPickers(sourceForm);
            syncHiddenFields(sourceForm, runForm);
            updateSelectedScenarioPanel(selectedScenarioPanel, scenarioRows, scenarioSelect.value);
            setActiveScenarioRow(progressTable, scenarioSelect.value);
            if (scenarioChanged) {
                hideScenarioOutputCards();
            }
            lastSelectedScenarioKey = currentScenarioKey;
        });
        sampleFillButton.addEventListener('click', function () {
            applyScenarioSample(sourceForm, runForm, scenarioSelect, scenarioDefinitions, entityOptions);
        });
        if (scenarioFilterInput) {
            scenarioFilterInput.addEventListener('input', function () {
                applyScenarioFilters(scenarioSelect, scenarioFilterInput, scenarioStatusFilter, scenarioRows);
            });
        }
        if (scenarioStatusFilter) {
            scenarioStatusFilter.addEventListener('change', function () {
                applyScenarioFilters(scenarioSelect, scenarioFilterInput, scenarioStatusFilter, scenarioRows);
            });
        }
        sourceForm.addEventListener('input', function () {
            syncTransferDetailsFromPickers(sourceForm);
            syncHiddenFields(sourceForm, runForm);
        });
        sourceForm.addEventListener('change', function () {
            syncTransferDetailsFromPickers(sourceForm);
            syncHiddenFields(sourceForm, runForm);
        });
        sourceForm.addEventListener('submit', function () {
            normalizeAmountOnSubmit(sourceForm);
        });
        runForm.addEventListener('submit', function () {
            normalizeAmountOnSubmit(sourceForm);
            syncHiddenFields(sourceForm, runForm);
        });
        const resetForm = document.getElementById('scenario-runner-reset-form');
        if (resetForm) {
            resetForm.addEventListener('submit', function (event) {
                const resetButton = document.querySelector('[form="scenario-runner-reset-form"][data-reset-confirm]');
                const confirmMessage = resetButton ? String(resetButton.getAttribute('data-reset-confirm') || '').trim() : '';
                if (confirmMessage !== '' && !window.confirm(confirmMessage)) {
                    event.preventDefault();
                }
            });
        }
        applyScenarioFilters(scenarioSelect, scenarioFilterInput, scenarioStatusFilter, scenarioRows);
        updateSelectedScenarioPanel(selectedScenarioPanel, scenarioRows, scenarioSelect.value);
        bindScenarioRowSelection(progressTable, scenarioSelect, sourceForm);
        setActiveScenarioRow(progressTable, scenarioSelect.value);
        bindScenarioErrorButtons(progressTable, scenarioErrorLogConfig);
        syncTransferDetailsFromPickers(sourceForm);
        syncHiddenFields(sourceForm, runForm);
        window.setTimeout(function () {
            syncTransferDetailsFromPickers(sourceForm);
            syncHiddenFields(sourceForm, runForm);
        }, 200);
        scrollToFocusTarget(sourceForm.dataset.focusTarget || '');
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();

