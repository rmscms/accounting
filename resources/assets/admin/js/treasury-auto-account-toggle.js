(function () {
    'use strict';

    function isChecked(input) {
        if (!input) {
            return false;
        }

        return !!input.checked;
    }

    function toggleManualAccountVisibility(checkbox, wrapper, accountInput) {
        if (!checkbox || !wrapper || !accountInput) {
            return;
        }

        var autoEnabled = isChecked(checkbox);
        wrapper.classList.toggle('d-none', autoEnabled);

        if (autoEnabled) {
            accountInput.removeAttribute('required');
        } else {
            accountInput.setAttribute('required', 'required');
        }
    }

    function bindBankFormToggle() {
        var checkbox = document.querySelector('#bank-auto-create-account');
        var wrapper = document.querySelector('[data-bank-manual-account-wrap]');
        var accountInput = document.querySelector('#bank-account-main');

        if (!checkbox || !wrapper || !accountInput) {
            return;
        }

        var sync = function () {
            toggleManualAccountVisibility(checkbox, wrapper, accountInput);
        };

        sync();
        checkbox.addEventListener('change', sync);
    }

    function bindCashboxStructuredFormToggle() {
        var page = document.querySelector('.accounting-structured-form[data-form-slug="cashboxes"]');
        if (!page) {
            return;
        }

        var checkbox = page.querySelector('#fld-auto_create_account');
        var wrapper = page.querySelector('.js-treasury-manual-account-wrap');
        var accountInput = page.querySelector('#fld-account_id');

        if (!checkbox || !wrapper || !accountInput) {
            return;
        }

        var sync = function () {
            toggleManualAccountVisibility(checkbox, wrapper, accountInput);
        };

        sync();
        checkbox.addEventListener('change', sync);
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindBankFormToggle();
        bindCashboxStructuredFormToggle();
    });
})();

