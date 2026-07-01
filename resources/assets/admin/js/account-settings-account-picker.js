(function () {
    'use strict';

    function initInventoryAccountPicker() {
        if (typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.select2 !== 'function') {
            return;
        }

        var $ = window.jQuery;
        $('.js-settings-inventory-account-select').each(function () {
            var $el = $(this);
            if ($el.data('select2')) {
                return;
            }

            var searchUrl = String($el.data('search-url') || '').trim();
            if (searchUrl === '') {
                return;
            }

            $el.select2({
                width: '100%',
                dir: 'rtl',
                allowClear: true,
                minimumInputLength: 2,
                placeholder: String($el.data('placeholder') || '').trim(),
                ajax: {
                    url: searchUrl,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return { q: (params.term != null ? params.term : '') || '' };
                    },
                    processResults: function (data) {
                        return { results: data && Array.isArray(data.results) ? data.results : [] };
                    },
                    cache: true
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', initInventoryAccountPicker);
})();
