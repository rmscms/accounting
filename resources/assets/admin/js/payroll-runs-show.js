(function () {
    'use strict';

    function confirmWithPlugin(title, message, confirmButtonText) {
        if (window.RMSConfirmModal && typeof window.RMSConfirmModal === 'function') {
            var modal = new window.RMSConfirmModal({
                title: title || '',
                message: message || '',
                icon: 'ph-warning',
                confirmText: confirmButtonText || 'Confirm',
                confirmClass: 'btn-danger',
                confirmIcon: 'ph-arrow-counter-clockwise',
                cancelText: 'Cancel',
                cancelClass: 'btn-outline-secondary',
                cancelIcon: 'ph-x',
                closeOnBackdrop: true,
                closeOnEscape: true,
                focusConfirm: false
            });
            return modal.show();
        }

        return Promise.resolve(window.confirm(String((message || title) || '')));
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.js-pr-reverse-form').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();

                var title = form.getAttribute('data-confirm-title') || '';
                var message = form.getAttribute('data-confirm-message') || '';
                var confirmButton = form.getAttribute('data-confirm-button') || '';

                confirmWithPlugin(title, message, confirmButton).then(function (ok) {
                    if (!ok) {
                        return;
                    }
                    form.submit();
                });
            });
        });

        document.querySelectorAll('.js-pr-payment-form').forEach(function (form) {
            var methodSelect = form.querySelector('.js-pr-payment-method');
            var bankWrapper = form.querySelector('.js-pr-payment-bank-field');
            var chequeWrapper = form.querySelector('.js-pr-payment-cheque-fields');
            if (!methodSelect || !bankWrapper || !chequeWrapper) {
                return;
            }

            var bankInput = bankWrapper.querySelector('[name="bank_id"]');
            var chequeBookInput = chequeWrapper.querySelector('[name="chequebook_id"]');
            var chequeNumberInput = chequeWrapper.querySelector('[name="cheque_number"]');
            var chequeDueDateInput = chequeWrapper.querySelector('[name="cheque_due_date"]');
            var chequePayeeInput = chequeWrapper.querySelector('[name="cheque_payee_name"]');

            var togglePaymentMethod = function () {
                var isCheque = String(methodSelect.value || '') === 'cheque';
                bankWrapper.classList.toggle('d-none', isCheque);
                chequeWrapper.classList.toggle('d-none', !isCheque);

                if (bankInput) {
                    bankInput.required = !isCheque;
                }
                if (chequeBookInput) {
                    chequeBookInput.required = isCheque;
                }
                if (chequeNumberInput) {
                    chequeNumberInput.required = isCheque;
                }
                if (chequeDueDateInput) {
                    chequeDueDateInput.required = isCheque;
                }
                if (chequePayeeInput) {
                    chequePayeeInput.required = isCheque;
                }
            };

            methodSelect.addEventListener('change', togglePaymentMethod);
            togglePaymentMethod();
        });
    });
})();
