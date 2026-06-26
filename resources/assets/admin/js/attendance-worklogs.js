/* global confirmAction */
(function () {
    'use strict';

    document.querySelectorAll('form[action*="/attendance-worklogs/"][action*="/lock"], form[action*="/attendance-worklogs/"][action*="/unlock"]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var reasonInput = form.querySelector('input[name="reason"]');
            if (!reasonInput || !reasonInput.value.trim()) {
                return;
            }

            if (typeof confirmAction !== 'function') {
                return;
            }

            event.preventDefault();
            var isUnlock = form.action.indexOf('/unlock') !== -1;
            confirmAction(
                isUnlock ? 'Unlock Attendance Period' : 'Lock Attendance Period',
                reasonInput.value.trim(),
                {
                    confirmText: isUnlock ? 'Unlock' : 'Lock',
                    confirmClass: isUnlock ? 'btn-dark' : 'btn-warning',
                    icon: isUnlock ? 'ph-lock-open' : 'ph-lock'
                }
            ).then(function (confirmed) {
                if (confirmed) {
                    form.submit();
                }
            });
        });
    });
})();
