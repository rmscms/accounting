(function () {
    'use strict';

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.content) {
            return meta.content;
        }
        var hidden = document.querySelector('input[name="_token"]');
        return hidden ? (hidden.value || '') : '';
    }

    function appendAlert(type, message) {
        var cardBody = document.querySelector('.card-body');
        if (!cardBody) {
            return;
        }
        var el = document.createElement('div');
        el.className = 'alert alert-' + type + ' mt-3';
        el.innerHTML = '<i class="ph-info me-1"></i> ' + message;
        cardBody.appendChild(el);
    }

    function upsertCustomerOption(select, customer) {
        if (!select || !customer || !customer.id) {
            return;
        }

        var value = String(customer.id);
        var label = String(customer.name || ('#' + value));
        var existing = select.querySelector('option[value="' + value + '"]');
        if (!existing) {
            existing = document.createElement('option');
            existing.value = value;
            existing.textContent = label;
            select.appendChild(existing);
        } else if (!existing.textContent || existing.textContent.trim() === '') {
            existing.textContent = label;
        }

        select.value = value;
        select.classList.remove('is-invalid');
    }

    function updateWarningState(message) {
        var warning = document.getElementById('general-customer-warning');
        if (!warning) {
            return;
        }
        warning.classList.remove('alert-warning');
        warning.classList.add('alert-success');

        var text = document.getElementById('general-customer-warning-text');
        if (text && message) {
            text.textContent = message;
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('create-general-customer-btn');
        if (!btn) {
            return;
        }

        btn.addEventListener('click', function () {
            var route = btn.getAttribute('data-route') || '';
            if (!route) {
                appendAlert('danger', 'مسیر ایجاد مشتری عمومی تعریف نشده است.');
                return;
            }

            btn.disabled = true;
            var originalText = btn.textContent;
            btn.textContent = 'در حال ایجاد...';

            fetch(route, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({})
            }).then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            }).then(function (result) {
                if (!result.ok || !result.data || result.data.ok !== true) {
                    var err = (result.data && result.data.message) ? result.data.message : 'خطا در ایجاد مشتری عمومی.';
                    throw new Error(err);
                }

                var select = document.getElementById('sales-default-customer-id');
                upsertCustomerOption(select, result.data.customer || null);
                updateWarningState(result.data.message || '');
                appendAlert('success', result.data.message || 'مشتری عمومی ایجاد شد. حالا تنظیمات را ذخیره کنید.');
            }).catch(function (error) {
                appendAlert('danger', error && error.message ? error.message : 'ایجاد مشتری عمومی ناموفق بود.');
            }).finally(function () {
                btn.disabled = false;
                btn.textContent = originalText;
            });
        });
    });
})();
