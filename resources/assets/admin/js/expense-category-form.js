(function ($, window) {
    'use strict';

    var RMS = window.RMS || {};
    var url = RMS.expenseCategoryCheckCodeUrl;
    var exceptId = RMS.expenseCategoryExceptId;
    var debounceMs = parseInt(RMS.expenseCategoryDebounceMs, 10) || 400;

    if (!url) {
        return;
    }

    function debounce(fn, wait) {
        var t;
        return function () {
            var ctx = this;
            var args = arguments;
            clearTimeout(t);
            t = setTimeout(function () {
                fn.apply(ctx, args);
            }, wait);
        };
    }

    function setState($input, $fb, state, message) {
        $input.removeClass('is-valid is-invalid');
        if (state === 'ok') {
            $input.addClass('is-valid');
        } else if (state === 'bad') {
            $input.addClass('is-invalid');
        }
        if ($fb && $fb.length) {
            $fb.text(message || '');
        }
    }

    function checkCode(raw, $input, $fb) {
        var params = { code: raw };
        if (exceptId) {
            params.except_id = exceptId;
        }
        $.getJSON(url, params)
            .done(function (data) {
                if (!data || typeof data.available === 'undefined') {
                    setState($input, $fb, '', '');
                    return;
                }
                var norm = data.normalized || '';
                if (data.available && (!data.message || data.message === null)) {
                    setState($input, $fb, 'ok', norm ? '' : '');
                } else {
                    setState($input, $fb, 'bad', data.message || '');
                }
            })
            .fail(function () {
                setState($input, $fb, '', '');
            });
    }

    $(function () {
        var $input = $('[data-expense-category-code-input]');
        if (!$input.length) {
            return;
        }
        var $fb = $('[data-expense-category-code-feedback]');
        var run = debounce(function () {
            var v = ($input.val() || '').trim();
            if (!v) {
                setState($input, $fb, '', '');
                return;
            }
            checkCode(v, $input, $fb);
        }, debounceMs);

        $input.on('input change blur', function () {
            run();
        });
    });
})(window.jQuery, window);
