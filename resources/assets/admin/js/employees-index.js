(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.querySelector('.js-employees-index');
        if (!root) {
            return;
        }

        var forms = root.querySelectorAll('.js-employee-delete-form');
        if (!forms.length) {
            return;
        }

        var modalTitle = (root.dataset.confirmTitle || '').trim();
        var message = (root.dataset.confirmMessage || '').trim();
        var namedMessageTemplate = (root.dataset.confirmMessageNamed || '').trim();
        var description = (root.dataset.confirmDescription || '').trim();
        var confirmButton = (root.dataset.confirmButton || '').trim();

        forms.forEach(function (form) {
            form.addEventListener('submit', async function (event) {
                event.preventDefault();

                var employeeName = String(form.dataset.employeeName || '').trim();
                var resolvedMessage = message;
                if (employeeName !== '' && namedMessageTemplate !== '') {
                    resolvedMessage = namedMessageTemplate.replace(':name', employeeName);
                }

                if (typeof window.RMSConfirmModal !== 'function') {
                    console.error('RMS confirm-modal plugin is required; delete action blocked.');
                    return;
                }

                var options = {
                    icon: 'ph-trash',
                    confirmClass: 'btn-danger',
                    confirmIcon: 'ph-trash',
                };
                if (description !== '') {
                    options.description = description;
                }
                if (confirmButton !== '') {
                    options.confirmText = confirmButton;
                }

                var modal = new window.RMSConfirmModal({
                    title: modalTitle || 'حذف',
                    message: resolvedMessage || 'آیا مطمئن هستید؟',
                    icon: options.icon,
                    confirmClass: options.confirmClass,
                    confirmIcon: options.confirmIcon,
                    description: options.description,
                    confirmText: options.confirmText,
                });
                var confirmed = await modal.show();
                if (confirmed) {
                    form.submit();
                }
            });
        });
    });
})();
