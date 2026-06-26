/**
 * دریافت از مشتری: باکس انتخاب مشتری + یکتایی شماره پرداخت.
 */
(function ($) {
    'use strict';

    function initCustomerPickerCard($root) {
        $root.find('.js-customer-payment-customer-picker').each(function () {
            var $picker = $(this);
            if ($picker.data('pickerBound')) {
                return;
            }
            $picker.data('pickerBound', true);

            var url = String($picker.data('search-url') || '');
            if (!url) {
                return;
            }
            var placeholder = String($picker.data('placeholder') || '');
            var $input = $picker.find('[data-search-input]').first();
            var $hidden = $picker.find('input[type="hidden"][name]').first();
            var $results = $picker.find('[data-search-results]').first();
            var $selectedBox = $picker.find('[data-selected-box]').first();
            var $selectedText = $picker.find('[data-selected-text]').first();
            var $selectedId = $picker.find('[data-selected-id]').first();
            var $clear = $picker.find('[data-clear-selection]').first();
            var debounceTimer = null;
            var initialId = String($picker.data('initial-id') || '');
            var initialText = String($picker.data('initial-text') || '');

            function hideResults() {
                $results.addClass('d-none').empty();
            }

            function setSelected(id, text) {
                var selectedIdValue = String(id || '').trim();
                var selectedTextValue = String(text || '').trim();
                $hidden.val(selectedIdValue);
                if (selectedIdValue === '') {
                    $selectedBox.addClass('d-none');
                    $selectedText.text('');
                    $selectedId.text('');
                    return;
                }
                $selectedBox.removeClass('d-none');
                $selectedText.text(selectedTextValue !== '' ? selectedTextValue : ('#' + selectedIdValue));
                $selectedId.text('#' + selectedIdValue);
            }

            function renderResults(rows) {
                if (!Array.isArray(rows) || rows.length === 0) {
                    $results.removeClass('d-none').html('<div class="list-group-item text-muted text-center">نتیجه‌ای یافت نشد.</div>');
                    return;
                }
                var html = rows.map(function (row) {
                    var id = String((row && row.id) || '');
                    var text = String((row && row.text) || ('#' + id));
                    return '<button type="button" class="list-group-item list-group-item-action text-start" data-result-id="'
                        + id.replace(/"/g, '&quot;')
                        + '" data-result-text="'
                        + text.replace(/"/g, '&quot;')
                        + '"><i class="ph-user me-2 text-primary"></i>'
                        + text
                        + '</button>';
                }).join('');
                $results.removeClass('d-none').html(html);
            }

            function doSearch(q) {
                $.ajax({
                    url: url,
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
                setSelected($btn.data('result-id'), $btn.data('result-text'));
                $input.val('');
                hideResults();
            });

            $clear.on('click', function () {
                setSelected('', '');
                $input.val('').trigger('focus');
            });

            $(document).on('click.customerPaymentPicker', function (e) {
                if (!$.contains($picker.get(0), e.target)) {
                    hideResults();
                }
            });

            setSelected(initialId, initialText);
        });
    }

    function wirePaymentNumberUniqueness($root) {
        var $inp = $root.find('#fld-payment_number');
        if (!$inp.length) {
            return;
        }
        var checkUrl = $inp.data('payment-number-unique-url');
        if (!checkUrl) {
            return;
        }
        var $fbBad = $root.find('#fld-payment_number-unique-feedback');
        var $fbOk = $root.find('#fld-payment_number-unique-success');
        var takenMsg = ($root.data('payment-number-msg-taken') || '').toString();
        var availMsg = ($root.data('payment-number-msg-available') || '').toString();

        function clearFeedback() {
            $inp.removeClass('is-invalid is-valid');
            if ($fbBad.length) {
                $fbBad.addClass('d-none').text('');
            }
            if ($fbOk.length) {
                $fbOk.addClass('d-none').empty();
            }
        }

        function setTaken(msg) {
            $inp.removeClass('is-valid').addClass('is-invalid');
            if ($fbOk.length) {
                $fbOk.addClass('d-none').empty();
            }
            if ($fbBad.length) {
                $fbBad.removeClass('d-none').text(msg || takenMsg);
            }
        }

        function setAvailable() {
            $inp.removeClass('is-invalid').addClass('is-valid');
            if ($fbBad.length) {
                $fbBad.addClass('d-none').text('');
            }
            if ($fbOk.length) {
                $fbOk.removeClass('d-none').empty()
                    .append($('<i class="ph-check-circle me-1" aria-hidden="true"></i>'))
                    .append($('<span></span>').text(availMsg));
            }
        }

        function runCheck() {
            var num = ($inp.val() || '').toString().trim();
            if (num === '') {
                clearFeedback();
                return;
            }
            var exclude = $inp.data('payment-number-exclude-id');
            $.ajax({
                url: checkUrl,
                method: 'GET',
                dataType: 'json',
                data: { number: num, exclude_id: exclude || 0 }
            }).done(function (res) {
                if (res && res.available) {
                    setAvailable();
                } else {
                    setTaken((res && res.message) ? String(res.message) : '');
                }
            }).fail(function () {
                clearFeedback();
            });
        }

        $inp.on('blur', function () {
            runCheck();
        });
    }

    function boot() {
        $('.accounting-structured-form[data-form-slug="customer_payments"], .accounting-structured-form[data-form-slug="credit_notes"], .accounting-structured-form[data-form-slug="customer_invoices"]').each(function () {
            var $root = $(this);
            initCustomerPickerCard($root);
            wirePaymentNumberUniqueness($root);
        });
    }

    $(boot);
}(window.jQuery));
