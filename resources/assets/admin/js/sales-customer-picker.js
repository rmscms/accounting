/**
 * Sales customer picker: select2 + quick create modal (AJAX).
 */
(function (window, $) {
    'use strict';

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function badgeClassForType(type) {
        var normalized = String(type || '').toLowerCase();
        if (normalized === 'supplier') {
            return 'bg-warning text-dark';
        }
        if (normalized === 'customer') {
            return 'bg-info text-dark';
        }
        return 'bg-secondary';
    }

    function renderTypeBadges(row) {
        var labels = [];
        var types = [];

        if (Array.isArray(row && row.entity_types) && row.entity_types.length > 0) {
            types = row.entity_types.map(function (item) { return String(item || '').toLowerCase(); });
        } else if (row && row.entity_type) {
            types = [String(row.entity_type || '').toLowerCase()];
        }

        if (row && row.entity_type_label) {
            labels = String(row.entity_type_label).split('،').map(function (item) {
                return String(item || '').trim();
            }).filter(function (item) { return item !== ''; });
        }

        if (types.length === 0 && labels.length === 0) {
            return '';
        }

        var output = [];
        var size = Math.max(types.length, labels.length);
        for (var i = 0; i < size; i += 1) {
            var label = labels[i] || labels[0] || types[i] || '';
            var type = types[i] || types[0] || '';
            output.push('<span class="badge ' + badgeClassForType(type) + '">' + escapeHtml(label) + '</span>');
        }
        return '<div class="d-flex flex-wrap gap-1 mt-1">' + output.join('') + '</div>';
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? String(meta.getAttribute('content') || '') : '';
    }

    function normalizePayload($container) {
        var raw = $container.find(':input[name]').serializeArray();
        var payload = {};
        raw.forEach(function (item) {
            payload[item.name] = item.value;
        });
        if (payload.active !== undefined) {
            payload.active = String(payload.active) === '1' ? 1 : 0;
        }
        return payload;
    }

    function renderErrors($box, errors) {
        if (!$box.length) {
            return;
        }
        var lines = [];
        if (errors && typeof errors === 'object') {
            Object.keys(errors).forEach(function (key) {
                var value = errors[key];
                if (Array.isArray(value)) {
                    value.forEach(function (msg) {
                        lines.push(String(msg));
                    });
                } else if (value) {
                    lines.push(String(value));
                }
            });
        }
        if (lines.length === 0) {
            $box.addClass('d-none').empty();
            return;
        }
        $box.removeClass('d-none').html('<ul class="mb-0"><li>' + lines.join('</li><li>') + '</li></ul>');
    }

    function bindPicker($picker) {
        if ($picker.data('salesCustomerPickerBound')) {
            return;
        }
        $picker.data('salesCustomerPickerBound', true);

        var placeholder = String($picker.data('placeholder') || '');
        var createUrl = String($picker.data('create-url') || '');
        var defaultCurrency = String($picker.data('default-currency') || '');
        var modalId = String($picker.data('modal-id') || '');
        var msgSaving = String($picker.data('msg-saving') || 'Saving...');
        var msgErrorGeneric = String($picker.data('msg-error-generic') || 'Operation failed.');
        var customerEditBaseUrl = String($picker.data('customer-edit-base-url') || '');
        var supplierCreateUrl = String($picker.data('supplier-create-url') || '');
        var $input = $picker.find('[data-search-input]').first();
        var $hidden = $picker.find('input[type="hidden"][name]').first();
        var $results = $picker.find('[data-search-results]').first();
        var $selectedBox = $picker.find('[data-selected-box]').first();
        var $selectedText = $picker.find('[data-selected-text]').first();
        var $selectedId = $picker.find('[data-selected-id]').first();
        var $customerEditLink = $picker.find('[data-customer-edit-link]').first();
        var $customerToSupplierLink = $picker.find('[data-customer-to-supplier-link]').first();
        var $clear = $picker.find('[data-clear-selection]').first();
        var $form = $picker.find('[data-sales-customer-create-form]').first();
        var $errors = $picker.find('[data-sales-customer-picker-errors]').first();
        var $submit = $picker.find('[data-sales-customer-picker-submit]').first();
        var $modal = modalId ? $('#' + modalId) : $();
        var $modalFields = $picker.find('[data-sales-customer-field]');
        var debounceTimer = null;
        var initialId = String($picker.data('initial-id') || '');
        var initialCustomerId = String($picker.data('initial-customer-id') || '');
        var initialText = String($picker.data('initial-text') || '');

        function hideResults() {
            $results.addClass('d-none').empty();
        }

        function buildCustomerEditUrl(id) {
            var clean = String(id || '').trim();
            if (!customerEditBaseUrl || !clean) {
                return '';
            }
            return customerEditBaseUrl.replace(/\/+$/, '') + '/' + encodeURIComponent(clean) + '/edit';
        }

        function buildCustomerToSupplierUrl(id) {
            var clean = String(id || '').trim();
            if (!supplierCreateUrl || !clean) {
                return '';
            }
            var separator = supplierCreateUrl.indexOf('?') === -1 ? '?' : '&';
            return supplierCreateUrl + separator + 'linked_customer_id=' + encodeURIComponent(clean);
        }

        function updateActionLinks(selectedCustomerIdValue) {
            var cleanCustomerId = String(selectedCustomerIdValue || '').trim();
            var editUrl = buildCustomerEditUrl(cleanCustomerId);
            if (editUrl) {
                $customerEditLink.attr('href', editUrl).removeClass('d-none');
            } else {
                $customerEditLink.attr('href', '#').addClass('d-none');
            }

            var toSupplierUrl = buildCustomerToSupplierUrl(cleanCustomerId);
            if (toSupplierUrl) {
                $customerToSupplierLink.attr('href', toSupplierUrl).removeClass('d-none');
            } else {
                $customerToSupplierLink.attr('href', '#').addClass('d-none');
            }
        }

        function setSelected(id, text, customerId, triggerChange) {
            var selectedIdValue = String(id || '').trim();
            var selectedTextValue = String(text || '').trim();
            var selectedCustomerIdValue = String(customerId || selectedIdValue).trim();
            var previous = String($hidden.val() || '').trim();
            $hidden.val(selectedIdValue);
            if (selectedIdValue === '') {
                $selectedBox.addClass('d-none');
                $selectedText.text('');
                $selectedId.text('');
                updateActionLinks('');
                return;
            }
            $selectedBox.removeClass('d-none');
            $selectedText.text(selectedTextValue !== '' ? selectedTextValue : ('#' + selectedIdValue));
            $selectedId.text('#' + selectedIdValue);
            updateActionLinks(selectedCustomerIdValue);
            if (triggerChange && previous !== selectedIdValue) {
                $hidden.trigger('change');
            }
        }

        function renderResults(rows) {
            if (!Array.isArray(rows) || rows.length === 0) {
                $results.removeClass('d-none').html('<div class="list-group-item text-muted text-center">نتیجه‌ای یافت نشد.</div>');
                return;
            }
            var html = rows.map(function (row) {
                var id = String((row && row.id) || '');
                var text = String((row && row.text) || ('#' + id));
                var badges = renderTypeBadges(row);
                return '<button type="button" class="list-group-item list-group-item-action text-start" data-result-id="'
                    + id.replace(/"/g, '&quot;')
                    + '" data-result-customer-id="'
                    + String((row && row.customer_id) || id).replace(/"/g, '&quot;')
                    + '" data-result-text="'
                    + text.replace(/"/g, '&quot;')
                    + '"><i class="ph-user me-2 text-primary"></i>'
                    + '<span>' + escapeHtml(text) + '</span>'
                    + badges
                    + '</button>';
            }).join('');
            $results.removeClass('d-none').html(html);
        }

        function doSearch(q) {
            if (!createUrl) {
                // no-op
            }
            $.ajax({
                url: String($picker.data('search-url') || ''),
                method: 'GET',
                dataType: 'json',
                data: { q: q, limit: 30 }
            }).done(function (res) {
                renderResults((res && res.results) ? res.results : []);
            }).fail(function () {
                $results.removeClass('d-none').html('<div class="list-group-item text-danger text-center">خطا در جستجو</div>');
            });
        }

        $input.attr('placeholder', placeholder);
        $input.on('input', function () {
            var q = String($input.val() || '').trim();
            if (debounceTimer) {
                clearTimeout(debounceTimer);
            }
            if (q.length < 1) {
                hideResults();
                return;
            }
            debounceTimer = setTimeout(function () {
                doSearch(q);
            }, 260);
        });

        $results.on('click', '[data-result-id]', function () {
            var $btn = $(this);
            setSelected($btn.data('result-id'), $btn.data('result-text'), $btn.data('result-customer-id'));
            $input.val('');
            hideResults();
        });

        $clear.on('click', function () {
            setSelected('', '');
            $input.val('').trigger('focus');
        });

        $picker.on('accounting:customer-picker:set', function (_event, payload) {
            payload = payload && typeof payload === 'object' ? payload : {};
            setSelected(payload.id || '', payload.text || '', payload.customerId || payload.id || '', true);
            $input.val('');
            hideResults();
        });

        $(document).on('click.salesCustomerPicker', function (e) {
            if (!$.contains($picker.get(0), e.target)) {
                hideResults();
            }
        });

        function toggleModalFields(enabled) {
            $modalFields.prop('disabled', !enabled);
        }

        function validatePayload(payload) {
            var errs = {};
            if (!String(payload.name || '').trim()) {
                errs.name = ['نام مشتری الزامی است.'];
            }
            if (!String(payload.type || '').trim()) {
                errs.type = ['نوع مشتری الزامی است.'];
            }
            return errs;
        }

        function submitQuickCreate() {
            if (!createUrl) {
                return;
            }

            toggleModalFields(true);
            renderErrors($errors, {});
            $submit.prop('disabled', true).data('origin-text', $submit.text()).text(msgSaving);
            var payload = normalizePayload($form);
            var clientErrors = validatePayload(payload);
            if (Object.keys(clientErrors).length > 0) {
                renderErrors($errors, clientErrors);
                var originTextEarly = $submit.data('origin-text');
                $submit.prop('disabled', false).text(originTextEarly || $submit.text());
                return;
            }

            $.ajax({
                url: createUrl,
                method: 'POST',
                dataType: 'json',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                data: payload
            }).done(function (res) {
                if (!res || !res.id) {
                    renderErrors($errors, { _error: [msgErrorGeneric] });
                    return;
                }

                var id = String(res.id);
                var text = String(res.text || res.customer && res.customer.name || ('#' + id));
                setSelected(id, text, id);
                $hidden.trigger('change');

                var formNode = $form.get(0);
                if (formNode && typeof formNode.reset === 'function') {
                    formNode.reset();
                } else {
                    $form.find(':input[name]').not(':checkbox,:radio').val('');
                }
                var active = $form.find('input[name="active"][type="checkbox"]');
                if (active.length) {
                    active.prop('checked', true);
                }

                if (modalId && window.bootstrap && typeof window.bootstrap.Modal === 'function') {
                    var el = document.getElementById(modalId);
                    if (el) {
                        var instance = window.bootstrap.Modal.getOrCreateInstance(el);
                        instance.hide();
                    }
                }
            }).fail(function (xhr) {
                if (xhr && xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    renderErrors($errors, xhr.responseJSON.errors);
                    return;
                }
                renderErrors($errors, { _error: [msgErrorGeneric] });
            }).always(function () {
                var originText = $submit.data('origin-text');
                $submit.prop('disabled', false).text(originText || $submit.text());
            });
        }

        $submit.on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            submitQuickCreate();
        });

        if ($modal.length) {
            $modal.on('keydown', 'input,select', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    e.stopPropagation();
                    submitQuickCreate();
                }
            });
            $modal.on('show.bs.modal', function () {
                if (defaultCurrency) {
                    var $currency = $form.find('select[name="default_currency_code"]').first();
                    if ($currency.length) {
                        var normalizedDefault = String(defaultCurrency).toUpperCase();
                        var matchedValue = '';
                        $currency.find('option').each(function () {
                            var optionValue = String($(this).val() || '').toUpperCase();
                            if (optionValue && optionValue === normalizedDefault) {
                                matchedValue = String($(this).val() || '');
                            }
                        });
                        if (matchedValue) {
                            $currency.val(matchedValue);
                        } else if ($currency.find('option[value="' + defaultCurrency + '"]').length) {
                            $currency.val(defaultCurrency);
                        }
                    }
                }
                toggleModalFields(true);
                renderErrors($errors, {});
            });
            $modal.on('hidden.bs.modal', function () {
                toggleModalFields(false);
                renderErrors($errors, {});
            });
        }

        toggleModalFields(false);
        setSelected(initialId, initialText, initialCustomerId || initialId);
    }

    function boot() {
        $('[data-sales-customer-picker]').each(function () {
            bindPicker($(this));
        });
    }

    $(boot);
}(window, window.jQuery));
