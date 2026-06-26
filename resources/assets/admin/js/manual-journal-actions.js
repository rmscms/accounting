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
        document.querySelectorAll('.js-mj-reverse-form').forEach(function (form) {
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
    });
})();
