/**
 * گزارش دفتر کل — بارگذاری تنبل زیرشاخه‌ها با AJAX هنگام باز کردن collapse
 */
(function () {
    'use strict';

    function initGlBranchLazyLoad() {
        document.addEventListener('show.bs.collapse', function (ev) {
            var el = ev.target;
            if (!el.classList.contains('gl-branch-load')) {
                return;
            }
            if (el.getAttribute('data-loaded') === '1' || el.getAttribute('data-loading') === '1') {
                return;
            }
            var parentId = el.getAttribute('data-parent-id');
            if (!parentId) {
                return;
            }
            var meta = document.getElementById('gl-report-ajax-meta');
            if (!meta) {
                return;
            }
            var url = meta.getAttribute('data-branch-url');
            if (!url) {
                return;
            }
            el.setAttribute('data-loading', '1');
            el.innerHTML =
                '<div class="px-3 py-3 text-center text-muted border-top bg-light"><span class="spinner-border spinner-border-sm me-2" role="status"></span>در حال بارگذاری…</div>';

            var params = new URLSearchParams();
            params.set('account_id', parentId);
            var fd = meta.getAttribute('data-from-date');
            var td = meta.getAttribute('data-to-date');
            if (fd) {
                params.set('from_date', fd);
            }
            if (td) {
                params.set('to_date', td);
            }
            var sd = meta.getAttribute('data-start-date');
            var ed = meta.getAttribute('data-end-date');
            if (!fd && sd) {
                params.set('start_date', sd);
            }
            if (!td && ed) {
                params.set('end_date', ed);
            }

            fetch(url + '?' + params.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
            })
                .then(function (r) {
                    if (!r.ok) {
                        throw new Error('HTTP ' + r.status);
                    }
                    return r.json();
                })
                .then(function (data) {
                    el.innerHTML = data.html || '';
                    el.removeAttribute('data-loading');
                    el.setAttribute('data-loaded', '1');
                    if (typeof bootstrap !== 'undefined' && el.querySelectorAll) {
                        el.querySelectorAll('.collapse.gl-branch-load').forEach(function (c) {
                            bootstrap.Collapse.getOrCreateInstance(c, { toggle: false });
                        });
                    }
                })
                .catch(function () {
                    el.removeAttribute('data-loading');
                    el.setAttribute('data-loaded', '0');
                    el.innerHTML =
                        '<div class="alert alert-danger m-2 mb-0 small">خطا در بارگذاری زیرشاخه‌ها. دوباره تلاش کنید.</div>';
                });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initGlBranchLazyLoad);
    } else {
        initGlBranchLazyLoad();
    }
})();
