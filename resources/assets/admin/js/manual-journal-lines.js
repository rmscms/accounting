/**
 * CRUD سطرهای سند دستی (Ajax) — تنظیمات از #manual-journal-lines-config (JSON).
 */
(function () {
    'use strict';

    /**
     * اسکریپت از head لود می‌شود؛ المان‌های body (مثل #manual-journal-lines-config) بعداً در DOM هستند.
     */
    function initManualJournalLines() {
    var cfgEl = document.getElementById('manual-journal-lines-config');
    if (!cfgEl || !cfgEl.textContent) {
        return;
    }

    var cfg;
    try {
        cfg = JSON.parse(cfgEl.textContent.trim());
    } catch (e) {
        return;
    }

    if (!cfg || !cfg.urls) {
        return;
    }

    var decimals = cfg.decimals;
    var csrf = cfg.csrf;
    var i18n = cfg.i18n || {};

    function formatAmount(num) {
        var n = Number(num);
        if (isNaN(n)) {
            return '';
        }
        return n.toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
    }

    function showAlert(type, message) {
        var el = document.getElementById('mj-alert');
        if (!el) {
            return;
        }
        el.className = 'alert mb-3 alert-' + (type === 'danger' ? 'danger' : (type === 'success' ? 'success' : 'info'));
        el.textContent = message;
        el.classList.remove('d-none');
        clearTimeout(el._mjT);
        el._mjT = setTimeout(function () {
            el.classList.add('d-none');
        }, 6000);
    }

    function readLineFromRow(tr) {
        return {
            id: parseInt(tr.getAttribute('data-line-id'), 10),
            line_number: parseInt(tr.getAttribute('data-line-number'), 10),
            account_id: parseInt(tr.getAttribute('data-account-id'), 10),
            account_code: tr.getAttribute('data-account-code') || '',
            account_name: tr.getAttribute('data-account-name') || '',
            debit_amount: tr.getAttribute('data-debit-amount') || '0',
            credit_amount: tr.getAttribute('data-credit-amount') || '0',
            description: tr.getAttribute('data-description') || ''
        };
    }

    function applyLineDataset(tr, line) {
        tr.setAttribute('data-line-id', String(line.id));
        tr.setAttribute('data-line-number', String(line.line_number));
        tr.setAttribute('data-account-id', String(line.account_id));
        tr.setAttribute('data-account-code', line.account_code || '');
        tr.setAttribute('data-account-name', line.account_name || '');
        tr.setAttribute('data-debit-amount', String(line.debit_amount));
        tr.setAttribute('data-credit-amount', String(line.credit_amount));
        tr.setAttribute('data-description', line.description || '');
    }

    function buildAccountSelect(selectedId) {
        var ref = document.getElementById('mj-add-account');
        var sel = document.createElement('select');
        sel.className = 'form-select form-select-sm enhanced-select';
        sel.required = true;
        if (!ref) {
            return sel;
        }
        for (var i = 0; i < ref.options.length; i++) {
            var opt = ref.options[i];
            var o = document.createElement('option');
            o.value = opt.value;
            o.textContent = opt.textContent;
            if (String(opt.value) === String(selectedId)) {
                o.selected = true;
            }
            sel.appendChild(o);
        }
        return sel;
    }

    function destroyRowSelect2(tr) {
        var sel = tr.querySelector('select');
        if (sel && window.jQuery && window.jQuery(sel).data('select2')) {
            window.jQuery(sel).select2('destroy');
        }
    }

    function escapeHtml(s) {
        if (!s) {
            return '';
        }
        return String(s).replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

    function renderViewCells(tr, line) {
        destroyRowSelect2(tr);
        var accLabel = line.account_code
            ? '<span class="text-body">' + escapeHtml(line.account_code) + '</span> <span class="text-muted">— ' + escapeHtml(line.account_name) + '</span>'
            : '<span class="text-muted">#' + escapeHtml(String(line.account_id)) + '</span>';
        tr.innerHTML =
            '<td class="text-center mj-col-num mj-td-num">' + line.line_number + '</td>' +
            '<td class="mj-col-account">' + accLabel + '</td>' +
            '<td class="text-end font-monospace mj-col-debit mj-td-amount">' + formatAmount(line.debit_amount) + '</td>' +
            '<td class="text-end font-monospace mj-col-credit mj-td-amount">' + formatAmount(line.credit_amount) + '</td>' +
            '<td class="small text-muted mj-col-desc">' + escapeHtml(line.description || '') + '</td>' +
            '<td class="text-center p-1 mj-col-actions mj-td-actions">' +
            '<button type="button" class="btn btn-outline-secondary btn-sm py-0 px-1 mj-btn-edit" title="' + escapeHtml(i18n.btnEdit || '') + '"><i class="ph-pencil-simple"></i></button> ' +
            '<button type="button" class="btn btn-outline-danger btn-sm py-0 px-1 mj-btn-delete" title="' + escapeHtml(i18n.btnDelete || '') + '"><i class="ph-trash"></i></button>' +
            '</td>';
        applyLineDataset(tr, line);
    }

    function enterEditMode(tr) {
        destroyRowSelect2(tr);
        var line = readLineFromRow(tr);
        tr._mjBackup = line;
        var sel = buildAccountSelect(line.account_id);
        var debitVal = Number(line.debit_amount) > 0 ? formatAmount(line.debit_amount) : '';
        var creditVal = Number(line.credit_amount) > 0 ? formatAmount(line.credit_amount) : '';
        tr.innerHTML =
            '<td class="text-center align-middle">' + line.line_number + '</td>' +
            '<td class="mj-edit-account"></td>' +
            '<td><input type="text" class="form-control form-control-sm amount-decimal mj-edit-debit" inputmode="decimal" data-type="amount-decimal" data-decimals="' + decimals + '" value="' + escapeHtml(debitVal) + '" placeholder="0" autocomplete="off"></td>' +
            '<td><input type="text" class="form-control form-control-sm amount-decimal mj-edit-credit" inputmode="decimal" data-type="amount-decimal" data-decimals="' + decimals + '" value="' + escapeHtml(creditVal) + '" placeholder="0" autocomplete="off"></td>' +
            '<td><input type="text" class="form-control form-control-sm mj-edit-desc" maxlength="500" value="' + escapeHtml(line.description || '') + '" autocomplete="off"></td>' +
            '<td class="text-center p-1">' +
            '<button type="button" class="btn btn-primary btn-sm py-0 px-1 mj-btn-save">' + escapeHtml(i18n.btnSave || '') + '</button> ' +
            '<button type="button" class="btn btn-light btn-sm py-0 px-1 mj-btn-cancel">' + escapeHtml(i18n.btnCancel || '') + '</button>' +
            '</td>';
        tr.querySelector('.mj-edit-account').appendChild(sel);
        if (window.jQuery && typeof window.jQuery(sel).select2 === 'function') {
            window.jQuery(sel).select2({ width: '100%' });
        }
    }

    function exitEditMode(tr, line) {
        renderViewCells(tr, line);
    }

    function updateTotals(j) {
        var fd = document.getElementById('mj-total-debit');
        var fc = document.getElementById('mj-total-credit');
        var tf = document.getElementById('mj-lines-tfoot');
        if (fd) {
            fd.textContent = formatAmount(j.total_debit);
        }
        if (fc) {
            fc.textContent = formatAmount(j.total_credit);
        }
        if (tf) {
            var tbody = document.getElementById('mj-lines-tbody');
            var hasRows = tbody && tbody.querySelectorAll('tr.mj-line-row').length > 0;
            tf.classList.toggle('d-none', !hasRows);
        }
    }

    function removeEmptyRow() {
        var er = document.getElementById('mj-empty-row');
        if (er) {
            er.remove();
        }
    }

    function ensureEmptyRow() {
        var tbody = document.getElementById('mj-lines-tbody');
        if (!tbody || tbody.querySelector('tr.mj-line-row')) {
            return;
        }
        if (document.getElementById('mj-empty-row')) {
            return;
        }
        var tr = document.createElement('tr');
        tr.id = 'mj-empty-row';
        var emptyText = i18n.emptyLinesRow || '';
        tr.innerHTML = '<td colspan="6" class="text-center text-muted py-3">' + escapeHtml(emptyText) + '</td>';
        tbody.appendChild(tr);
    }

    function appendLineRow(line) {
        removeEmptyRow();
        var tbody = document.getElementById('mj-lines-tbody');
        var tr = document.createElement('tr');
        tr.className = 'mj-line-row';
        renderViewCells(tr, line);
        tbody.appendChild(tr);
    }

    function fetchJson(url, options) {
        var headers = Object.assign({
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest'
        }, options.headers || {});
        return fetch(url, Object.assign({}, options, { headers: headers })).then(function (res) {
            return res.json().then(function (body) {
                return { ok: res.ok, status: res.status, body: body };
            }).catch(function () {
                return { ok: false, status: res.status, body: { message: i18n.genericError } };
            });
        });
    }

    /**
     * پلاگین ادمین confirm-modal (RMSConfirmModal)؛ در صورت نبود به confirm مرورگر برمی‌گردد.
     */
    function confirmWithPlugin(title, message, modalOverrides) {
        if (window.RMSConfirmModal && typeof window.RMSConfirmModal === 'function') {
            var opts = Object.assign({
                title: title || '',
                message: message || '',
                description: null,
                icon: 'ph-warning',
                confirmText: 'تأیید',
                confirmClass: 'btn-primary',
                confirmIcon: 'ph-check',
                cancelText: i18n.btnCancel || 'انصراف',
                cancelClass: 'btn-outline-secondary',
                cancelIcon: 'ph-x',
                closeOnBackdrop: true,
                closeOnEscape: true,
                focusConfirm: false,
            }, modalOverrides || {});
            return new window.RMSConfirmModal(opts).show();
        }
        return Promise.resolve(window.confirm(String((message || title) || '')));
    }

    var tbody = document.getElementById('mj-lines-tbody');
    if (tbody) {
        tbody.addEventListener('click', function (e) {
            var tr = e.target.closest('tr.mj-line-row');
            if (!tr) {
                return;
            }
            if (e.target.closest('.mj-btn-edit')) {
                enterEditMode(tr);
                return;
            }
            if (e.target.closest('.mj-btn-delete')) {
                void (async function () {
                    var ok = await confirmWithPlugin(
                        i18n.confirmDeleteTitle || '',
                        i18n.confirmDelete || '',
                        {
                            icon: 'ph-trash',
                            confirmText: i18n.confirmDeleteBtn || '',
                            confirmClass: 'btn-danger',
                            confirmIcon: 'ph-trash',
                        }
                    );
                    if (!ok) {
                        return;
                    }
                    var lineId = tr.getAttribute('data-line-id');
                    var url = cfg.urls.destroyLineTpl.replace('__MJ_LINE__', lineId);
                    fetchJson(url, { method: 'DELETE' }).then(function (r) {
                        if (r.ok && r.body.ok) {
                            tr.remove();
                            updateTotals(r.body.journal);
                            ensureEmptyRow();
                            showAlert('success', r.body.message);
                        } else {
                            showAlert('danger', (r.body && r.body.message) ? r.body.message : i18n.genericError);
                        }
                    });
                })();
                return;
            }
            if (e.target.closest('.mj-btn-cancel')) {
                if (tr._mjBackup) {
                    exitEditMode(tr, tr._mjBackup);
                }
                return;
            }
            if (e.target.closest('.mj-btn-save')) {
                var sel = tr.querySelector('.mj-edit-account select');
                var accountId = sel ? sel.value : '';
                var fd = new FormData();
                fd.append('_token', csrf);
                fd.append('_method', 'PUT');
                fd.append('account_id', accountId);
                fd.append('debit_amount', tr.querySelector('.mj-edit-debit').value);
                fd.append('credit_amount', tr.querySelector('.mj-edit-credit').value);
                fd.append('description', tr.querySelector('.mj-edit-desc').value);
                var lineId = tr._mjBackup ? tr._mjBackup.id : tr.getAttribute('data-line-id');
                var updateUrl = cfg.urls.updateLineTpl.replace('__MJ_LINE__', String(lineId));
                fetchJson(updateUrl, { method: 'POST', body: fd }).then(function (r) {
                    if (r.ok && r.body.ok) {
                        exitEditMode(tr, r.body.line);
                        updateTotals(r.body.journal);
                        showAlert('success', r.body.message);
                    } else {
                        var msg = (r.body && r.body.message) ? r.body.message : i18n.genericError;
                        if (r.body && r.body.errors) {
                            var first = Object.keys(r.body.errors)[0];
                            if (first && r.body.errors[first][0]) {
                                msg = r.body.errors[first][0];
                            }
                        }
                        showAlert('danger', msg);
                    }
                });
            }
        });
    }

    var addForm = document.getElementById('mj-add-line-form');
    if (addForm) {
        addForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var form = e.target;
            var fd = new FormData(form);
            var btn = document.getElementById('mj-add-submit');
            if (btn) {
                btn.disabled = true;
            }
            fetchJson(cfg.urls.storeLine, { method: 'POST', body: fd }).then(function (r) {
                if (btn) {
                    btn.disabled = false;
                }
                if (r.ok && r.body.ok) {
                    appendLineRow(r.body.line);
                    updateTotals(r.body.journal);
                    form.reset();
                    var acc = document.getElementById('mj-add-account');
                    if (acc && window.jQuery && typeof window.jQuery(acc).select2 === 'function') {
                        window.jQuery(acc).val(null).trigger('change');
                    }
                    showAlert('success', r.body.message);
                } else {
                    var msg = (r.body && r.body.message) ? r.body.message : i18n.genericError;
                    if (r.body && r.body.errors) {
                        var fk = Object.keys(r.body.errors)[0];
                        if (fk && r.body.errors[fk][0]) {
                            msg = r.body.errors[fk][0];
                        }
                    }
                    showAlert('danger', msg);
                }
            });
        });
    }

    var postForm = document.getElementById('mj-post-form');
    if (postForm) {
        postForm.addEventListener('submit', function (e) {
            e.preventDefault();
            void (async function () {
                var ok = await confirmWithPlugin(
                    i18n.confirmPostTitle || '',
                    i18n.confirmPost || '',
                    {
                        icon: 'ph-check-circle',
                        confirmText: i18n.confirmPostBtn || '',
                        confirmClass: 'btn-success',
                        confirmIcon: 'ph-check-circle',
                    }
                );
                if (!ok) {
                    return;
                }
                var fd = new FormData();
                fd.append('_token', csrf);
                var btn = document.getElementById('mj-post-submit');
                if (btn) {
                    btn.disabled = true;
                }
                fetchJson(cfg.urls.post, { method: 'POST', body: fd }).then(function (r) {
                    if (btn) {
                        btn.disabled = false;
                    }
                    if (r.ok && r.body.ok && r.body.redirect) {
                        window.location.href = r.body.redirect;
                    } else {
                        showAlert('danger', (r.body && r.body.message) ? r.body.message : i18n.genericError);
                    }
                });
            })();
        });
    }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initManualJournalLines);
    } else {
        initManualJournalLines();
    }
})();
