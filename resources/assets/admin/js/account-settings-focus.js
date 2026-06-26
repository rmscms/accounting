(function () {
    'use strict';

    function splitCsv(input) {
        if (typeof input !== 'string' || input.trim() === '') {
            return [];
        }
        return input
            .split(',')
            .map(function (part) { return part.trim(); })
            .filter(function (part) { return part !== ''; });
    }

    function getStateFromUrl() {
        try {
            var params = new URLSearchParams(window.location.search || '');
            var singleTag = (params.get('account_setting_tag') || '').trim();
            var multiTags = splitCsv(params.get('settings_focus_tags') || '');
            if (singleTag !== '' && multiTags.indexOf(singleTag) === -1) {
                multiTags.unshift(singleTag);
            }
            var tabId = (params.get('settings_tab') || '').trim();
            var hash = (window.location.hash || '').replace(/^#/, '');
            if (hash.indexOf('account-setting:') === 0) {
                multiTags = multiTags.concat(splitCsv(hash.substring('account-setting:'.length)));
            }
            if (hash.indexOf('settings-tab:') === 0 && !tabId) {
                tabId = hash.substring('settings-tab:'.length).trim();
            }
            if (tabId !== '' && tabId.charAt(0) === '#') {
                tabId = tabId.substring(1);
            }
            return {
                tags: multiTags.filter(function (value, index, arr) {
                    return arr.indexOf(value) === index;
                }),
                tabId: tabId
            };
        } catch (e) {
            return { tags: [], tabId: '' };
        }
    }

    function activateTabByPaneId(tabPaneId) {
        if (!tabPaneId) {
            return;
        }
        var tabTrigger = document.querySelector('[data-bs-toggle="tab"][href="#' + tabPaneId + '"]');
        if (!tabTrigger || typeof window.bootstrap === 'undefined' || !window.bootstrap.Tab) {
            return;
        }
        window.bootstrap.Tab.getOrCreateInstance(tabTrigger).show();
    }

    function activateTabForTarget(target, forcedTabId) {
        if (forcedTabId) {
            activateTabByPaneId(forcedTabId);
            return;
        }
        var tabPane = target.closest('.tab-pane');
        if (tabPane && tabPane.id) {
            activateTabByPaneId(tabPane.id);
        }
    }

    function markSetting(tag, forcedTabId, shouldScroll) {
        var target = document.querySelector('[data-account-setting-tag="' + tag + '"]');
        if (!target) {
            return false;
        }

        activateTabForTarget(target, forcedTabId);

        setTimeout(function () {
            var scrollTarget = target.closest('.col-md-6') || target.closest('.col-12') || target.parentElement || target;
            var label = scrollTarget ? scrollTarget.querySelector('label.form-label') : null;
            var select2Container = target.nextElementSibling && target.nextElementSibling.classList && target.nextElementSibling.classList.contains('select2-container')
                ? target.nextElementSibling
                : null;
            var visibleFocusTarget = select2Container || target;

            if (shouldScroll && scrollTarget && typeof scrollTarget.scrollIntoView === 'function') {
                scrollTarget.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            visibleFocusTarget.classList.add('border', 'border-success', 'bg-success-subtle');
            visibleFocusTarget.style.boxShadow = '0 0 0 .2rem rgba(25,135,84,.25)';
            if (label) {
                label.classList.add('text-success', 'fw-bold');
            }

            if (typeof target.focus === 'function') {
                target.focus({ preventScroll: true });
            }
            visibleFocusTarget.setAttribute('data-settings-linked-focus', '1');
        }, 180);

        return true;
    }

    function parseServerValidationErrors() {
        var el = document.getElementById('accounting-settings-errors-json');
        if (!el) {
            return {};
        }
        try {
            var parsed = JSON.parse(el.textContent || '{}');
            return (parsed && typeof parsed === 'object') ? parsed : {};
        } catch (e) {
            return {};
        }
    }

    function findFieldElement(fieldName) {
        if (!fieldName) {
            return null;
        }
        var direct = document.querySelector('[name="' + fieldName + '"]');
        if (direct) {
            return direct;
        }
        if (fieldName.slice(-2) !== '[]') {
            var asArray = document.querySelector('[name="' + fieldName + '[]"]');
            if (asArray) {
                return asArray;
            }
        }
        return null;
    }

    function ensureInvalidFeedback(target, message) {
        if (!target || !message) {
            return;
        }
        var existing = target.parentElement ? target.parentElement.querySelector('.invalid-feedback') : null;
        if (existing) {
            if (!existing.classList.contains('d-block')) {
                existing.classList.add('d-block');
            }
            if ((existing.textContent || '').trim() === '') {
                existing.textContent = message;
            }
            return;
        }

        var generated = document.createElement('div');
        generated.className = 'invalid-feedback d-block';
        generated.textContent = message;
        if (target.parentElement) {
            target.parentElement.appendChild(generated);
        }
    }

    function markInvalidField(target, message) {
        if (!target) {
            return;
        }

        target.classList.add('is-invalid');
        target.setAttribute('aria-invalid', 'true');

        var select2Container = target.nextElementSibling && target.nextElementSibling.classList && target.nextElementSibling.classList.contains('select2-container')
            ? target.nextElementSibling
            : null;
        if (select2Container) {
            select2Container.classList.add('is-invalid');
            select2Container.style.boxShadow = '0 0 0 .2rem rgba(220,53,69,.15)';
            var rendered = select2Container.querySelector('.select2-selection');
            if (rendered) {
                rendered.style.borderColor = '#dc3545';
            }
        }

        ensureInvalidFeedback(target, message);
    }

    function focusFirstValidationError() {
        var errors = parseServerValidationErrors();
        var fieldNames = Object.keys(errors || {});
        if (!fieldNames.length) {
            return;
        }

        var firstTarget = null;
        for (var i = 0; i < fieldNames.length; i += 1) {
            var field = fieldNames[i];
            var messages = Array.isArray(errors[field]) ? errors[field] : [];
            var target = findFieldElement(field);
            if (!target) {
                continue;
            }
            markInvalidField(target, messages[0] || '');
            if (!firstTarget) {
                firstTarget = target;
            }
        }

        if (!firstTarget) {
            return;
        }

        activateTabForTarget(firstTarget, '');
        setTimeout(function () {
            var scrollTarget = firstTarget.closest('.col-md-6') || firstTarget.closest('.col-12') || firstTarget.parentElement || firstTarget;
            if (scrollTarget && typeof scrollTarget.scrollIntoView === 'function') {
                scrollTarget.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            if (typeof firstTarget.focus === 'function') {
                firstTarget.focus({ preventScroll: true });
            }
        }, 180);
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.content) {
            return meta.content;
        }
        var hidden = document.querySelector('input[name="_token"]');
        return hidden ? (hidden.value || '') : '';
    }

    function appendPageAlert(type, message) {
        var cardBody = document.querySelector('.card-body');
        if (!cardBody) {
            return;
        }
        var el = document.createElement('div');
        el.className = 'alert alert-' + type + ' mt-3';
        el.innerHTML = '<i class="ph-info me-1"></i> ' + message;
        cardBody.appendChild(el);
    }

    function setupDefaultCustomerAjaxButton() {
        var btn = document.getElementById('create-general-customer-btn');
        if (!btn) {
            return;
        }

        btn.addEventListener('click', function () {
            var route = btn.getAttribute('data-route') || '';
            if (!route) {
                appendPageAlert('danger', 'مسیر ایجاد مشتری عمومی تعریف نشده است.');
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

                var customer = result.data.customer || {};
                var select = document.getElementById('sales-default-customer-id');
                if (select && customer.id) {
                    var value = String(customer.id);
                    var option = select.querySelector('option[value="' + value + '"]');
                    if (!option) {
                        option = document.createElement('option');
                        option.value = value;
                        option.textContent = String(customer.name || ('#' + value));
                        select.appendChild(option);
                    }
                    select.value = value;
                    select.classList.remove('is-invalid');
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                }

                var warning = document.getElementById('general-customer-warning');
                if (warning) {
                    warning.classList.remove('alert-warning');
                    warning.classList.add('alert-success');
                }
                var warningText = document.getElementById('general-customer-warning-text');
                if (warningText && result.data.message) {
                    warningText.textContent = result.data.message;
                }

                appendPageAlert('success', result.data.message || 'مشتری عمومی ایجاد شد. حالا تنظیمات را ذخیره کنید.');
            }).catch(function (error) {
                appendPageAlert('danger', (error && error.message) ? error.message : 'ایجاد مشتری عمومی ناموفق بود.');
            }).then(function () {
                btn.disabled = false;
                btn.textContent = originalText;
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        setupDefaultCustomerAjaxButton();

        var state = getStateFromUrl();
        if (!state || !Array.isArray(state.tags) || state.tags.length === 0) {
            if (state && state.tabId) {
                activateTabByPaneId(state.tabId);
            }
            focusFirstValidationError();
            return;
        }

        var focusedAny = false;
        for (var i = 0; i < state.tags.length; i += 1) {
            var ok = markSetting(state.tags[i], state.tabId, i === 0);
            focusedAny = focusedAny || ok;
        }

        if (!focusedAny && state.tabId) {
            activateTabByPaneId(state.tabId);
        }

        focusFirstValidationError();
    });
})();
