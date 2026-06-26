(function () {
    'use strict';

    function getRoot() {
        return document.querySelector('.js-employees-index');
    }

    function buildDeleteMessage(root, form) {
        var employeeName = String(form.dataset.employeeName || '').trim();
        var fallbackMessage = (root.dataset.confirmMessage || '').trim();
        var namedTemplate = (root.dataset.confirmMessageNamed || '').trim();

        if (employeeName !== '' && namedTemplate !== '') {
            return namedTemplate.replace(':name', employeeName);
        }

        return fallbackMessage || 'آیا مطمئن هستید؟';
    }

    function buildConfirmOptions(root) {
        var options = {
            icon: 'ph-trash',
            confirmClass: 'btn-danger',
            confirmIcon: 'ph-trash',
        };

        var description = (root.dataset.confirmDescription || '').trim();
        var confirmText = (root.dataset.confirmButton || '').trim();

        if (description !== '') {
            options.description = description;
        }
        if (confirmText !== '') {
            options.confirmText = confirmText;
        }

        return options;
    }

    async function showDeleteConfirm(title, message, options) {
        if (typeof window.RMSConfirmModal !== 'function') {
            console.error('RMS confirm-modal plugin is required; delete action blocked.');
            return false;
        }

        var modal = new window.RMSConfirmModal({
            title: title,
            message: message,
            icon: options.icon,
            confirmClass: options.confirmClass,
            confirmIcon: options.confirmIcon,
            description: options.description,
            confirmText: options.confirmText,
        });

        return await modal.show();
    }

    async function onSubmit(event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.classList.contains('js-employee-delete-form')) {
            return;
        }
        if (form.dataset.confirmedSubmit === '1') {
            return;
        }

        event.preventDefault();

        var root = getRoot();
        if (!root) {
            console.error('Employees root container not found; delete action blocked.');
            return;
        }

        var title = (root.dataset.confirmTitle || '').trim() || 'حذف';
        var message = buildDeleteMessage(root, form);
        var options = buildConfirmOptions(root);
        var confirmed = await showDeleteConfirm(title, message, options);

        if (!confirmed) {
            return;
        }

        form.dataset.confirmedSubmit = '1';
        HTMLFormElement.prototype.submit.call(form);
    }

    document.addEventListener('submit', onSubmit, true);
})();
