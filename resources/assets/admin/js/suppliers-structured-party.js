/**
 * فرم ساختاریافتهٔ تأمین‌کننده: Select2 AJAX برای مشتری (linked_customer_id) و Party (party_id) + پر کردن فیلدها.
 */
(function ($) {
    'use strict';

    function setIfEmpty(selector, value) {
        if (!value) {
            return;
        }
        var $t = $(selector);
        if (!$t.length) {
            return;
        }
        var v = ($t.val() || '').toString().trim();
        if (v === '') {
            $t.val(value);
        }
    }

    function clearSelect2($el) {
        if (!$el || !$el.length || typeof $.fn.select2 !== 'function') {
            return;
        }
        if ($el.data('select2')) {
            $el.val(null).trigger('change');
        }
    }

    function initPartySelect2($root) {
        if (!$ || typeof $.fn.select2 !== 'function') {
            return;
        }
        $root.find('select.accounting-party-supplier-select2').each(function () {
            var $el = $(this);
            if ($el.data('select2')) {
                return;
            }
            var url = $el.data('search-url');
            if (!url) {
                return;
            }
            var placeholder = $el.data('placeholder') || '';
            $el.select2({
                width: '100%',
                placeholder: placeholder,
                allowClear: true,
                minimumInputLength: 0,
                language: {
                    inputTooShort: function () {
                        return placeholder;
                    }
                },
                ajax: {
                    url: url,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return { q: params.term || '', limit: 30 };
                    },
                    processResults: function (data) {
                        var rows = (data && data.results) ? data.results : [];
                        return { results: rows };
                    }
                }
            });

            $el.on('select2:select', function (e) {
                clearSelect2($root.find('select.accounting-customer-supplier-select2'));
                var d = e.params && e.params.data ? e.params.data : null;
                if (!d) {
                    return;
                }
                setIfEmpty('#fld-name', d.name);
                setIfEmpty('#fld-phone', d.phone);
                setIfEmpty('#fld-email', d.email);
                setIfEmpty('#fld-tax_number', d.tax_number);
                setIfEmpty('#fld-address', d.address);
            });
        });
    }

    function initCustomerSelect2($root) {
        if (!$ || typeof $.fn.select2 !== 'function') {
            return;
        }
        $root.find('select.accounting-customer-supplier-select2').each(function () {
            var $el = $(this);
            if ($el.data('select2')) {
                return;
            }
            var url = $el.data('search-url');
            if (!url) {
                return;
            }
            var placeholder = $el.data('placeholder') || '';
            $el.select2({
                width: '100%',
                placeholder: placeholder,
                allowClear: true,
                minimumInputLength: 0,
                language: {
                    inputTooShort: function () {
                        return placeholder;
                    }
                },
                ajax: {
                    url: url,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return { q: params.term || '', limit: 30 };
                    },
                    processResults: function (data) {
                        var rows = (data && data.results) ? data.results : [];
                        return { results: rows };
                    }
                }
            });

            $el.on('select2:select', function (e) {
                clearSelect2($root.find('select.accounting-party-supplier-select2'));
                var d = e.params && e.params.data ? e.params.data : null;
                if (!d) {
                    return;
                }
                setIfEmpty('#fld-name', d.name);
                setIfEmpty('#fld-phone', d.phone);
                setIfEmpty('#fld-email', d.email);
                setIfEmpty('#fld-tax_number', d.tax_number);
                setIfEmpty('#fld-address', d.address);
            });
        });
    }

    function boot() {
        var $root = $('.accounting-structured-form[data-form-slug="suppliers"]');
        if (!$root.length) {
            return;
        }
        initCustomerSelect2($root);
        initPartySelect2($root);
    }

    $(boot);
})(window.jQuery);
