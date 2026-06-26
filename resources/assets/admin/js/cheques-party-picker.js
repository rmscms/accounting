(function ($) {
    'use strict';

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
                ajax: {
                    url: url,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {q: params.term || '', limit: 30};
                    },
                    processResults: function (data) {
                        return {results: (data && data.results) ? data.results : []};
                    }
                }
            });
        });
    }

    function applyDynamicLabels($root) {
        var type = ($root.find('#fld-cheque_type').val() || '').toString();
        if (type !== 'issued') {
            type = 'received';
        }

        var $label = $root.find('[data-cheque-counterparty-label]');
        if ($label.length) {
            var text = type === 'issued' ? $label.data('label-issued') : $label.data('label-received');
            if (text) {
                $label.text(text);
            }
        }

        var $hint = $root.find('[data-cheque-counterparty-hint]');
        if ($hint.length) {
            var hint = type === 'issued' ? $hint.data('hint-issued') : $hint.data('hint-received');
            if (hint) {
                $hint.text(hint);
            }
        }
    }

    function showSetupConfirmModal($root) {
        var missing = String($root.data('cheque-setup-missing') || '0') === '1';
        if (!missing || !window.bootstrap || typeof window.bootstrap.Modal !== 'function') {
            return;
        }
        var modalId = String($root.data('cheque-setup-modal-id') || 'cheque-setup-missing-modal');
        var el = document.getElementById(modalId);
        if (!el) {
            return;
        }
        var modal = window.bootstrap.Modal.getOrCreateInstance(el);
        window.setTimeout(function () {
            modal.show();
        }, 180);
    }

    function boot() {
        var $root = $('.accounting-structured-form[data-form-slug="cheques"]');
        if (!$root.length) {
            return;
        }

        initPartySelect2($root);
        applyDynamicLabels($root);
        showSetupConfirmModal($root);

        $root.on('change', '#fld-cheque_type', function () {
            applyDynamicLabels($root);
        });
    }

    $(boot);
})(window.jQuery);

