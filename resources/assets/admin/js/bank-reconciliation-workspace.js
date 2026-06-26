(function () {
    'use strict';
    var DECIMAL_PLACES = 0;

    function toEnglishDigits(value) {
        if (typeof value !== 'string') return '';
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
        if (parts.length > 2) value = parts[0] + '.' + parts.slice(1).join('');
        return value;
    }

    function formatAmount(raw) {
        var clean = sanitizeAmount(raw);
        if (clean === '') return '';
        var parts = clean.split('.');
        var intPart = (parts[0] || '0').replace(/^0+(?=\d)/, '');
        var decimalPart = parts.length > 1 ? parts[1] : '';
        intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return decimalPart !== '' ? intPart + '.' + decimalPart : intPart;
    }

    function fmtNumber(value) {
        var n = Number(value || 0);
        if (!isFinite(n)) n = 0;
        return n.toLocaleString('en-US', {
            minimumFractionDigits: DECIMAL_PLACES,
            maximumFractionDigits: DECIMAL_PLACES
        });
    }

    function parseFormatConfig() {
        var el = document.getElementById('br-format-config');
        if (!el) return;
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

    function bindAmountInput(input) {
        if (!input || input.dataset.amountBound === '1') return;
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

    function csrfToken() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    function request(url, method, payload, isFormData) {
        var headers = { 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' };
        if (!isFormData) headers['Content-Type'] = 'application/json';
        return fetch(url, {
            method: method,
            headers: headers,
            body: payload ? (isFormData ? payload : JSON.stringify(payload)) : undefined
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (json) {
                if (!res.ok) {
                    var msg = (json && (json.message || json.error)) ? (json.message || json.error) : 'Request failed';
                    var error = new Error(msg);
                    error.payload = json || {};
                    throw error;
                }
                return json;
            });
        });
    }

    function byId(id) {
        return document.getElementById(id);
    }

    function handleConfirmResult(result, onConfirm) {
        // confirmAction may return boolean (sync) or Promise<boolean> (async)
        if (result && typeof result.then === 'function') {
            result.then(function (ok) {
                if (ok) onConfirm();
            });
            return;
        }
        if (result) {
            onConfirm();
        }
    }

    function requireConfirmAction() {
        if (typeof window.RMSConfirmModal === 'function') {
            return function (title, message, options) {
                var modal = new window.RMSConfirmModal(Object.assign({
                    title: title,
                    message: message
                }, options || {}));
                return modal.show();
            };
        }
        throw new Error('confirm-modal plugin is required but RMSConfirmModal is not available.');
    }

    function refreshAccountingDateInput(input, dispatchEvents) {
        if (!input) return;
        var shouldDispatch = dispatchEvents !== false;
        if (input.dataset.reinitializingDatepicker === '1') return;
        input.dataset.reinitializingDatepicker = '1';
        if (typeof window.initAccountingDateFields === 'function') {
            window.initAccountingDateFields(document);
        }
        if (shouldDispatch) {
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
        input.dataset.reinitializingDatepicker = '0';
    }

    function showInlineMessage(message, level) {
        var hint = byId('validation-hint');
        if (!hint) return;
        var map = {
            success: 'alert alert-success mt-3 mb-0 small',
            info: 'alert alert-info mt-3 mb-0 small',
            warning: 'alert alert-warning mt-3 mb-0 small',
            danger: 'alert alert-danger mt-3 mb-0 small'
        };
        hint.className = map[level] || map.info;
        hint.textContent = String(message || '');
    }

    function replaceTemplate(urlTemplate, values) {
        var out = urlTemplate;
        Object.keys(values).forEach(function (k) {
            out = out.replace(k, String(values[k]));
        });
        return out;
    }

    function typeLabel(type) {
        var map = {
            outstanding_cheque: 'Outstanding Cheque',
            deposit_in_transit: 'Deposit in Transit',
            bank_charge: 'Bank Charge',
            interest_income: 'Interest Income',
            manual_adjustment: 'Manual Adjustment'
        };
        return map[type] || type;
    }

    function effectLabel(side, sign) {
        var symbol = Number(sign) >= 0 ? '+' : '-';
        return side + ' (' + symbol + ')';
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = byId('bank-recon-workspace');
        if (!root) return;
        parseFormatConfig();
        document.querySelectorAll('.js-accounting-amount-input').forEach(bindAmountInput);

        var state = {
            session: null,
            candidateMode: null,
            candidates: [],
            headerBaseline: null,
            itemsVisibleCount: 100,
            sessionsVisibleCount: 20
        };

        var modalBankCharge = new bootstrap.Modal(byId('modal-bank-charge'));
        var modalInterest = new bootstrap.Modal(byId('modal-interest-income'));
        var modalCandidates = new bootstrap.Modal(byId('modal-candidates'));

        function setStatusBadge(session) {
            var badge = byId('bank-recon-status-badge');
            if (!badge) return;
            if (!session) {
                badge.className = 'badge bg-light text-dark px-3 py-2';
                badge.textContent = 'Idle';
                return;
            }
            if (session.status === 'finalized') {
                badge.className = 'badge bg-success px-3 py-2';
                badge.textContent = 'Finalized';
                return;
            }
            if (session.is_balanced) {
                badge.className = 'badge bg-info text-dark px-3 py-2';
                badge.textContent = 'Balanced';
                return;
            }
            badge.className = 'badge bg-warning text-dark px-3 py-2';
            badge.textContent = 'Need Review';
        }

        function renderAttachments(session) {
            var wrap = byId('attachment-list');
            if (!wrap) return;
            if (!session || !Array.isArray(session.attachments) || session.attachments.length === 0) {
                wrap.innerHTML = '<small class="text-muted">No attachment uploaded.</small>';
                return;
            }
            wrap.innerHTML = session.attachments.map(function (a) {
                return '<a class="btn btn-sm btn-outline-primary me-1 mb-1" target="_blank" rel="noopener" href="' + a.download_url + '">' + a.name + '</a>';
            }).join('');
        }

        function renderKpis(session) {
            byId('kpi-book-balance').textContent = fmtNumber(session ? session.book_balance : 0);
            byId('kpi-bank-balance').textContent = fmtNumber(session ? session.bank_statement_balance : 0);
            byId('kpi-adjusted-book').textContent = fmtNumber(session ? session.adjusted_book_balance : 0);
            byId('kpi-difference').textContent = fmtNumber(session ? session.difference_amount : 0);
        }

        function setMutationUiState(session) {
            var isFinalized = !!(session && session.status === 'finalized');
            var quickAddCard = byId('br-quick-add-card');
            var finalizeCard = byId('br-finalize-card');
            if (quickAddCard) {
                quickAddCard.classList.toggle('d-none', isFinalized);
            }
            if (finalizeCard) {
                finalizeCard.classList.toggle('d-none', isFinalized);
            }
        }

        function renderItems(session) {
            var body = byId('recon-items-body');
            if (!body) return;
            if (!session || !Array.isArray(session.items) || session.items.length === 0) {
                body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No items yet.</td></tr>';
                var emptyWrap = byId('items-load-more-wrap');
                if (emptyWrap) emptyWrap.classList.add('d-none');
                return;
            }
            body.innerHTML = session.items.map(function (item, index) {
                var ref = item.reference_number || '-';
                var desc = item.description || '-';
                var canDelete = session.status !== 'finalized';
                var hiddenClass = index >= state.itemsVisibleCount ? ' d-none recon-item-extra' : '';
                var delBtn = canDelete
                    ? '<button type="button" class="btn btn-sm btn-outline-danger btn-delete-item" data-item-id="' + item.id + '">Delete</button>'
                    : '<span class="text-muted small">Locked</span>';
                var journalLinkLabel = root.dataset.textJournalLink || 'Journal';
                var journalDraftLabel = root.dataset.textJournalDraft || 'Journal draft prepared (will post on finalize)';
                var journalHtml = (item.journal && item.journal.url)
                    ? ('<div class="small mt-1"><a target="_blank" rel="noopener" href="' + item.journal.url + '">' + journalLinkLabel + ': ' + (item.journal.number || ('#' + item.journal.id)) + '</a></div>')
                    : (item.has_journal_draft
                        ? '<div class="small mt-1 text-muted">' + journalDraftLabel + '</div>'
                        : '');
                return '<tr class="' + hiddenClass.trim() + '">' +
                    '<td>' + typeLabel(item.item_type) + '</td>' +
                    '<td>' + ref + '</td>' +
                    '<td class="text-end">' + fmtNumber(item.amount) + '</td>' +
                    '<td>' + effectLabel(item.effect_side, item.effect_sign) + '</td>' +
                    '<td>' + desc + journalHtml + '</td>' +
                    '<td>' + delBtn + '</td>' +
                    '</tr>';
            }).join('');

            var hiddenCount = Math.max(0, session.items.length - state.itemsVisibleCount);
            var loadWrap = byId('items-load-more-wrap');
            var loadBtn = byId('br-items-load-more');
            if (loadWrap && loadBtn) {
                if (hiddenCount > 0) {
                    loadWrap.classList.remove('d-none');
                    var loadItemsText = root.dataset.textLoadMoreItems || 'Show more items';
                    loadBtn.textContent = loadItemsText + ' (' + hiddenCount + ')';
                } else {
                    loadWrap.classList.add('d-none');
                }
            }
        }

        function renderSession(session) {
            state.session = session;
            state.itemsVisibleCount = 100;
            setStatusBadge(session);
            renderKpis(session);
            renderItems(session);
            renderAttachments(session);
            setMutationUiState(session);
            state.headerBaseline = currentHeaderSnapshot();
            var syncBtn = byId('br-sync-session');
            if (syncBtn) {
                syncBtn.disabled = !session || session.status === 'finalized';
                syncBtn.classList.toggle('d-none', !!(session && session.status === 'finalized'));
            }
            updateHeaderDirtyState();
        }

        function guardSession() {
            if (!state.session) throw new Error('ابتدا جلسه تطبیق را باز کنید.');
        }

        function openSession() {
            var bankId = byId('br-bank-id').value;
            var statementDate = document.querySelector('input[name="statement_date"]').value;
            var bankBalance = byId('br-bank-balance').value;
            return request(root.dataset.urlOpenSession, 'POST', {
                bank_id: bankId,
                statement_date: statementDate,
                bank_statement_balance: sanitizeAmount(bankBalance || '0')
            }).then(function (res) {
                renderSession(res.session);
                byId('br-bank-balance').value = fmtNumber(res.session.bank_statement_balance || 0);
            });
        }

        function ensureSessionHeaderSynced() {
            guardSession();
            return openSession();
        }

        function syncHeaderForAction(actionMessage) {
            var textSyncing = root.dataset.textLiveSyncing || 'Auto-syncing session header...';
            var textSynced = root.dataset.textLiveSynced || 'Session header synced, applying action...';
            showInlineMessage(textSyncing, 'info');
            return ensureSessionHeaderSynced().then(function () {
                showInlineMessage(actionMessage || textSynced, 'info');
            });
        }

        function normalizeDateSnapshot(value) {
            return toEnglishDigits(String(value || '')).replace(/\s+/g, '').trim();
        }

        function currentHeaderSnapshot() {
            var dateInput = document.querySelector('input[name="statement_date"]');
            return {
                bankId: String((byId('br-bank-id') && byId('br-bank-id').value) || '').trim(),
                statementDate: normalizeDateSnapshot(dateInput ? dateInput.value : ''),
                bankBalance: sanitizeAmount((byId('br-bank-balance') && byId('br-bank-balance').value) || '')
            };
        }

        function setSyncButtonState(isDirty) {
            var syncBtn = byId('br-sync-session');
            if (!syncBtn) return;
            var cleanText = root.dataset.textSyncSessionBtn || 'Update session header';
            var dirtyText = root.dataset.textSyncSessionDirtyBtn || cleanText;

            syncBtn.classList.remove('btn-outline-primary', 'btn-warning', 'text-dark');
            if (isDirty) {
                syncBtn.classList.add('btn-warning', 'text-dark');
                syncBtn.textContent = dirtyText;
                return;
            }
            syncBtn.classList.add('btn-outline-primary');
            syncBtn.textContent = cleanText;
        }

        function updateHeaderDirtyState() {
            if (!state.session || state.session.status === 'finalized' || !state.headerBaseline) {
                setSyncButtonState(false);
                return;
            }
            var current = currentHeaderSnapshot();
            var dirty = current.bankId !== state.headerBaseline.bankId
                || current.statementDate !== state.headerBaseline.statementDate
                || current.bankBalance !== state.headerBaseline.bankBalance;
            setSyncButtonState(dirty);
        }

        function clearWorkspaceView() {
            state.session = null;
            state.itemsVisibleCount = 100;
            setStatusBadge(null);
            renderKpis(null);
            renderItems(null);
            renderAttachments(null);
            setMutationUiState(null);
            state.headerBaseline = null;
            updateHeaderDirtyState();
        }

        function canMutateSession() {
            var textFinalizedLocked = root.dataset.textSessionFinalizedLocked || 'Finalized session cannot be updated.';
            if (!state.session) {
                showInlineMessage(root.dataset.textOpenSessionFirst || 'Open a session first.', 'warning');
                return false;
            }
            if (state.session.status === 'finalized') {
                showInlineMessage(textFinalizedLocked, 'warning');
                return false;
            }
            return true;
        }

        function addItem(payload, options) {
            options = options || {};
            var syncPromise = options.skipSync
                ? Promise.resolve()
                : syncHeaderForAction(root.dataset.textLiveSynced || 'Session header synced, applying action...');
            return syncPromise.then(function () {
                var url = replaceTemplate(root.dataset.urlAddItemTemplate, { '__SESSION__': state.session.id });
                return request(url, 'POST', payload).then(function (res) {
                    renderSession(res.session);
                    var hint = byId('validation-hint');
                    if (hint) {
                        hint.className = 'd-none';
                        hint.innerHTML = '';
                    }
                });
            });
        }

        function showError(err) {
            var payload = err && err.payload ? err.payload : null;
            if (payload && payload.settings_url) {
                var hint = byId('validation-hint');
                if (hint) {
                    hint.className = 'alert alert-warning mt-3 mb-0 small';
                    hint.innerHTML =
                        '<div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">' +
                        '<span>' + (payload.message || 'Required system accounts are not configured.') + '</span>' +
                        '<a class="btn btn-sm btn-success" href="' + payload.settings_url + '">' +
                        'Open settings' +
                        '</a>' +
                        '</div>';
                }
                return;
            }
            showInlineMessage(err && err.message ? err.message : 'خطا در انجام عملیات', 'danger');
        }

        byId('br-open-session').addEventListener('click', function () {
            openSession().catch(showError);
        });

        byId('br-sync-session').addEventListener('click', function () {
            var textOpenSessionFirst = root.dataset.textOpenSessionFirst || 'Open a session first.';
            var textFinalizedLocked = root.dataset.textSessionFinalizedLocked || 'Finalized session cannot be updated.';
            var textSyncSuccess = root.dataset.textSyncSuccess || 'Session header updated successfully.';
            if (!state.session) {
                showInlineMessage(textOpenSessionFirst, 'warning');
                return;
            }
            if (state.session.status === 'finalized') {
                showInlineMessage(textFinalizedLocked, 'warning');
                return;
            }
            syncHeaderForAction(textSyncSuccess).then(function () {
                showInlineMessage(textSyncSuccess, 'info');
            }).catch(showError);
        });

        document.querySelectorAll('.btn-load-session').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var sessionId = btn.getAttribute('data-session-id') || '';
                var bankId = btn.getAttribute('data-bank-id') || '';
                var statementDate = btn.getAttribute('data-statement-date-display') || btn.getAttribute('data-statement-date') || '';
                var bankBalance = btn.getAttribute('data-bank-balance') || '';
                var bankSelect = byId('br-bank-id');
                var dateInput = document.querySelector('input[name="statement_date"]');
                var balanceInput = byId('br-bank-balance');
                if (bankSelect) bankSelect.value = bankId;
                if (dateInput) dateInput.value = statementDate;
                if (balanceInput) {
                    balanceInput.value = fmtNumber(Number(sanitizeAmount(bankBalance) || 0));
                }
                refreshAccountingDateInput(dateInput, true);
                updateHeaderDirtyState();
                if (!sessionId) {
                    openSession().catch(showError);
                    return;
                }
                var url = replaceTemplate(root.dataset.urlLoadSessionTemplate, { '__SESSION__': sessionId });
                request(url, 'GET').then(function (res) {
                    renderSession(res.session);
                }).catch(showError);
            });
        });

        var bankInput = byId('br-bank-id');
        if (bankInput) {
            bankInput.addEventListener('change', updateHeaderDirtyState);
        }
        var dateInput = document.querySelector('input[name="statement_date"]');
        if (dateInput) {
            dateInput.addEventListener('input', updateHeaderDirtyState);
            dateInput.addEventListener('change', updateHeaderDirtyState);
            dateInput.addEventListener('change', function () {
                refreshAccountingDateInput(dateInput, false);
            });
        }
        var balanceInput = byId('br-bank-balance');
        if (balanceInput) {
            balanceInput.addEventListener('input', updateHeaderDirtyState);
            balanceInput.addEventListener('change', updateHeaderDirtyState);
        }

        var itemsLoadMoreBtn = byId('br-items-load-more');
        if (itemsLoadMoreBtn) {
            itemsLoadMoreBtn.addEventListener('click', function () {
                state.itemsVisibleCount += 100;
                renderItems(state.session);
            });
        }

        function applySessionsVisibility() {
            var tbody = byId('recent-sessions-body');
            var wrap = byId('sessions-load-more-wrap');
            var btn = byId('br-sessions-load-more');
            if (!tbody || !wrap || !btn) return;
            var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
            var dataRows = rows.filter(function (row) { return row.children && row.children.length > 1; });
            dataRows.forEach(function (row, index) {
                row.classList.toggle('d-none', index >= state.sessionsVisibleCount);
            });
            var hiddenCount = Math.max(0, dataRows.length - state.sessionsVisibleCount);
            if (hiddenCount > 0) {
                wrap.classList.remove('d-none');
                btn.textContent = (root.dataset.textLoadMoreSessions || 'Show more sessions') + ' (' + hiddenCount + ')';
            } else {
                wrap.classList.add('d-none');
            }
        }

        var sessionsLoadMoreBtn = byId('br-sessions-load-more');
        if (sessionsLoadMoreBtn) {
            sessionsLoadMoreBtn.addEventListener('click', function () {
                state.sessionsVisibleCount += 20;
                applySessionsVisibility();
            });
        }
        applySessionsVisibility();

        byId('btn-add-bank-charge').addEventListener('click', function () {
            if (!canMutateSession()) {
                return;
            }
            byId('bank-charge-amount').value = '';
            byId('bank-charge-desc').value = '';
            modalBankCharge.show();
        });

        byId('btn-add-interest').addEventListener('click', function () {
            if (!canMutateSession()) {
                return;
            }
            byId('interest-amount').value = '';
            byId('interest-desc').value = '';
            modalInterest.show();
        });

        byId('save-bank-charge').addEventListener('click', function () {
            addItem({
                item_type: 'bank_charge',
                amount: sanitizeAmount(byId('bank-charge-amount').value || '0'),
                description: byId('bank-charge-desc').value || ''
            }).then(function () {
                modalBankCharge.hide();
                showInlineMessage(root.dataset.textLiveActionAdd || 'Item added and session header is up to date.', 'success');
            }).catch(showError);
        });

        byId('save-interest').addEventListener('click', function () {
            addItem({
                item_type: 'interest_income',
                amount: sanitizeAmount(byId('interest-amount').value || '0'),
                description: byId('interest-desc').value || ''
            }).then(function () {
                modalInterest.hide();
                showInlineMessage(root.dataset.textLiveActionAdd || 'Item added and session header is up to date.', 'success');
            }).catch(showError);
        });

        function openCandidates(kind) {
            syncHeaderForAction(root.dataset.textLiveSynced || 'Session header synced, applying action...').then(function () {
                state.candidateMode = kind;
                var title = byId('candidate-modal-title');
                var rowsWrap = byId('candidate-rows');
                var urlTpl = kind === 'outstanding'
                    ? root.dataset.urlOutstandingTemplate
                    : root.dataset.urlDitTemplate;
                var url = replaceTemplate(urlTpl, { '__SESSION__': state.session.id });
                var titleOutstanding = root.dataset.textCandidatesOutstandingTitle || 'Outstanding Cheques';
                var titleDit = root.dataset.textCandidatesDitTitle || 'Deposits In Transit';
                var textLoading = root.dataset.textCandidatesLoading || 'Loading...';
                var textEmpty = root.dataset.textCandidatesEmpty || 'No rows found.';
                title.textContent = kind === 'outstanding' ? titleOutstanding : titleDit;
                rowsWrap.innerHTML = '<tr><td colspan="4" class="text-center text-muted">' + textLoading + '</td></tr>';
                modalCandidates.show();
                return request(url, 'GET').then(function (res) {
                    state.candidates = Array.isArray(res.rows) ? res.rows : [];
                    if (state.candidates.length === 0) {
                        rowsWrap.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">' + textEmpty + '</td></tr>';
                        return;
                    }
                    rowsWrap.innerHTML = state.candidates.map(function (r) {
                        var number = r.number || '-';
                        var date = r.issue_date || r.payment_date || '-';
                        return '<tr>' +
                            '<td><input type="checkbox" class="candidate-check" value="' + r.id + '"></td>' +
                            '<td>' + number + '</td>' +
                            '<td>' + date + '</td>' +
                            '<td class="text-end">' + fmtNumber(r.amount) + '</td>' +
                            '</tr>';
                    }).join('');
                });
            }).catch(showError);
        }

        byId('btn-pick-outstanding').addEventListener('click', function () {
            if (!canMutateSession()) {
                return;
            }
            openCandidates('outstanding');
        });
        byId('btn-pick-dit').addEventListener('click', function () {
            if (!canMutateSession()) {
                return;
            }
            openCandidates('dit');
        });

        byId('apply-candidates').addEventListener('click', function () {
            if (!canMutateSession()) return;
            var checks = Array.prototype.slice.call(document.querySelectorAll('.candidate-check:checked'));
            if (checks.length === 0) return;
            syncHeaderForAction(root.dataset.textLiveSynced || 'Session header synced, applying action...').then(function () {
                var lookup = {};
                state.candidates.forEach(function (r) { lookup[String(r.id)] = r; });
                var queue = Promise.resolve();
                checks.forEach(function (ch) {
                    var row = lookup[String(ch.value)];
                    if (!row) return;
                    queue = queue.then(function () {
                        if (state.candidateMode === 'outstanding') {
                            return addItem({
                                item_type: 'outstanding_cheque',
                                amount: sanitizeAmount(String(row.amount || 0)),
                                reference_type: 'RMS\\Accounting\\Models\\Cheque',
                                reference_id: row.id,
                                reference_number: row.number || '',
                                reference_date: row.issue_date || '',
                                description: row.payee_name || ''
                            }, { skipSync: true });
                        }
                        return addItem({
                            item_type: 'deposit_in_transit',
                            amount: sanitizeAmount(String(row.amount || 0)),
                            reference_type: 'RMS\\Accounting\\Models\\CustomerPayment',
                            reference_id: row.id,
                            reference_number: row.number || '',
                            reference_date: row.payment_date || '',
                            description: row.notes || ''
                        }, { skipSync: true });
                    });
                });
                queue.then(function () {
                    modalCandidates.hide();
                    showInlineMessage(root.dataset.textLiveActionCandidates || 'Selected candidates applied and session header is up to date.', 'success');
                }).catch(showError);
            }).catch(showError);
        });

        byId('recon-items-body').addEventListener('click', function (e) {
            var btn = e.target.closest('.btn-delete-item');
            if (!btn || !state.session) return;
            if (!canMutateSession()) return;
            var itemId = btn.getAttribute('data-item-id');
            if (!itemId) return;
            var deleteTitle = root.dataset.textDeleteTitle || 'Delete item';
            var deleteMessage = root.dataset.textDeleteMessage || 'Are you sure?';
            var deleteButton = root.dataset.textDeleteButton || 'Delete';
            var doDelete = function () {
                syncHeaderForAction(root.dataset.textLiveSynced || 'Session header synced, applying action...').then(function () {
                    var url = replaceTemplate(root.dataset.urlDeleteItemTemplate, {
                        '__SESSION__': state.session.id,
                        '__ITEM__': itemId
                    });
                    return request(url, 'DELETE').then(function (res) {
                        renderSession(res.session);
                        showInlineMessage(root.dataset.textLiveActionDelete || 'Item removed and session header is up to date.', 'success');
                    });
                }).catch(showError);
            };
            try {
                var deleteConfirmAction = requireConfirmAction();
                handleConfirmResult(deleteConfirmAction(deleteTitle, deleteMessage, {
                    icon: 'ph-trash',
                    confirmText: deleteButton,
                    confirmClass: 'btn-danger'
                }), doDelete);
            } catch (e) {
                showError(e);
            }
        });

        byId('btn-validate').addEventListener('click', function () {
            if (!canMutateSession()) return;
            syncHeaderForAction(root.dataset.textLiveSynced || 'Session header synced, applying action...').then(function () {
                var url = replaceTemplate(root.dataset.urlValidateTemplate, { '__SESSION__': state.session.id });
                return request(url, 'POST').then(function (res) {
                    renderSession(res.session);
                    var hint = byId('validation-hint');
                    if (!hint) return;
                    hint.className = res.metrics.is_balanced
                        ? 'alert alert-success mt-3 mb-0 small'
                        : 'alert alert-warning mt-3 mb-0 small';
                    hint.textContent = res.metrics.is_balanced
                        ? 'Balanced. Session is ready to finalize.'
                        : 'Not balanced yet. Difference: ' + fmtNumber(res.metrics.difference_amount);
                });
            }).catch(showError);
        });

        byId('btn-finalize').addEventListener('click', function () {
            if (!canMutateSession()) return;
            var finalize = function () {
                syncHeaderForAction(root.dataset.textLiveSynced || 'Session header synced, applying action...').then(function () {
                    var url = replaceTemplate(root.dataset.urlFinalizeTemplate, { '__SESSION__': state.session.id });
                    return request(url, 'POST').then(function (res) {
                        renderSession(res.session);
                        showInlineMessage(res.message || 'Finalized', 'success');
                    });
                }).catch(showError);
            };
            try {
                var finalizeConfirmAction = requireConfirmAction();
                var finalizeConfirmResult = finalizeConfirmAction('Finalize reconciliation', 'Draft journals will be posted and session locked.', {
                    icon: 'ph-lock',
                    confirmText: 'Finalize',
                    confirmClass: 'btn-success'
                });
                handleConfirmResult(finalizeConfirmResult, finalize);
            } catch (e) {
                showError(e);
            }
        });

        byId('btn-upload-attachment').addEventListener('click', function () {
            if (!canMutateSession()) return;
            var fileInput = byId('br-attachment-file');
            var file = fileInput && fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
            if (!file) {
                showInlineMessage('File not selected.', 'warning');
                return;
            }
            syncHeaderForAction(root.dataset.textLiveSynced || 'Session header synced, applying action...').then(function () {
                var formData = new FormData();
                formData.append('file', file);
                var url = replaceTemplate(root.dataset.urlAttachTemplate, { '__SESSION__': state.session.id });
                return request(url, 'POST', formData, true).then(function (res) {
                    renderSession(res.session);
                    if (fileInput) fileInput.value = '';
                    showInlineMessage(root.dataset.textLiveActionUpload || 'Attachment uploaded and session header is up to date.', 'success');
                });
            }).catch(showError);
        });

        var recentSessionsBody = byId('recent-sessions-body');
        if (recentSessionsBody) {
            recentSessionsBody.addEventListener('click', function (e) {
                var btn = e.target.closest('.btn-delete-session');
                if (!btn) return;
                var sessionId = btn.getAttribute('data-session-id');
                if (!sessionId) return;
                var title = root.dataset.textDeleteSessionTitle || 'Delete draft session';
                var message = root.dataset.textDeleteSessionMessage || 'Are you sure you want to delete this draft session?';
                var confirmText = root.dataset.textDeleteSessionButton || 'Delete draft';
                var doDelete = function () {
                    var url = replaceTemplate(root.dataset.urlDeleteSessionTemplate, { '__SESSION__': sessionId });
                    request(url, 'DELETE').then(function (res) {
                        var row = btn.closest('tr');
                        if (row) row.remove();
                        if (state.session && String(state.session.id) === String(sessionId)) {
                            clearWorkspaceView();
                        }
                        applySessionsVisibility();
                        showInlineMessage((res && res.message) ? res.message : 'Draft session deleted.', 'success');
                    }).catch(showError);
                };
                try {
                    var confirmAction = requireConfirmAction();
                    handleConfirmResult(confirmAction(title, message, {
                        icon: 'ph-trash',
                        confirmText: confirmText,
                        confirmClass: 'btn-danger'
                    }), doDelete);
                } catch (err) {
                    showError(err);
                }
            });
        }
    });
})();

