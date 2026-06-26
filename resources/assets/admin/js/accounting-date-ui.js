/**
 * تقویم ادمین حسابداری: فیلدهای جلالی با کلاس persian-datepicker (همان الگوی فیلتر لیست RMS)
 * + دکمه‌های دفتر روزنامه + export گزارش‌ها
 */
(function (window, document) {
    'use strict';

    function isRms2PersianDateField(el) {
        return el.hasAttribute('data-persian-date') || el.classList.contains('persian-datepicker');
    }

    function bindPersianDatepicker($input) {
        if (typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.persianDatepicker !== 'function') {
            return;
        }
        if ($input.data('accountingDatepickerBound')) {
            return;
        }
        $input.data('accountingDatepickerBound', true);
        $input.persianDatepicker({
            format: 'YYYY-MM-DD',
            autoClose: true,
            initialValue: !!String($input.val() || '').trim(),
            observer: true,
            calendar: {
                persian: {
                    locale: 'fa',
                },
            },
            toolbox: {
                calendarSwitch: {
                    enabled: false,
                },
            },
        });
    }

    /**
     * همان API رسمی RMS2 (rms2-persian-datepicker.js) — بدون وابستگی به ترتیب jQuery.ready
     */
    function bindAccountingRms2Datepickers(root) {
        var scope = root || document;
        if (typeof window.jQuery === 'undefined') {
            return;
        }
        var $ = window.jQuery;
        var rms = window.RMS2PersianDatePicker;
        if (!rms || typeof rms.initElement !== 'function') {
            return;
        }
        var nodes = scope.querySelectorAll('input.accounting-date-field[data-calendar="jalali"]');
        for (var i = 0; i < nodes.length; i++) {
            rms.initElement($(nodes[i]));
        }
    }

    function initAccountingDateFields(root) {
        var scope = root || document;
        var fields = scope.querySelectorAll('input.accounting-date-field[data-calendar]');
        for (var i = 0; i < fields.length; i++) {
            var el = fields[i];
            if (isRms2PersianDateField(el)) {
                continue;
            }
            var mode = el.getAttribute('data-calendar') || 'jalali';
            if (mode !== 'jalali') {
                continue;
            }
            if (typeof window.jQuery === 'undefined') {
                continue;
            }
            bindPersianDatepicker(window.jQuery(el));
        }
        bindAccountingRms2Datepickers(scope);
    }

    window.initAccountingDateFields = initAccountingDateFields;

    function initLedgerFilterButtonsOnce() {
        if (typeof window.jQuery === 'undefined') {
            return;
        }
        if (window.__accountingDateUiLedgerBound) {
            return;
        }
        window.__accountingDateUiLedgerBound = true;
        var $ = window.jQuery;

        $(document).on('click', '.btn-filter-ledger', function () {
            var fromDate = $('input[name="from_date"]').val();
            var toDate = $('input[name="to_date"]').val();
            var accountId = $('select[name="account_id"]').val();
            var documentId = $('input[name="document_id"]').val();

            var params = new URLSearchParams();
            if (fromDate) {
                params.append('from_date', fromDate);
            }
            if (toDate) {
                params.append('to_date', toDate);
            }
            if (accountId) {
                params.append('account_id', accountId);
            }
            if (documentId) {
                params.append('document_id', documentId);
            }

            var queryString = params.toString();
            window.location.href = window.location.pathname + (queryString ? '?' + queryString : '');
        });

        $(document).on('click', '.btn-reset-filter', function () {
            $('input[name="from_date"]').val('');
            $('input[name="to_date"]').val('');
            $('select[name="account_id"]').val('');
            $('input[name="document_id"]').val('');
            window.location.href = window.location.pathname;
        });

        $(document).on('keypress', 'input[name="from_date"], input[name="to_date"], select[name="account_id"]', function (e) {
            if (e.which === 13) {
                $('.btn-filter-ledger').trigger('click');
            }
        });
    }

    function initVatQuickPeriodSelectOnce() {
        if (typeof window.jQuery === 'undefined') {
            return;
        }
        if (window.__accountingVatQuickPeriodBound) {
            return;
        }
        window.__accountingVatQuickPeriodBound = true;
        var $ = window.jQuery;

        $(document).on('change', '.js-vat-quick-period-select', function () {
            var value = String($(this).val() || '').trim();
            if (!value) {
                return;
            }

            var parts = value.split('|');
            if (parts.length !== 2) {
                return;
            }

            var fromDate = String(parts[0] || '').trim();
            var toDate = String(parts[1] || '').trim();
            if (!fromDate || !toDate) {
                return;
            }

            var form = this.form || document.getElementById('accounting-reports-filter-form');
            if (!form) {
                return;
            }

            var fromInput = form.querySelector('input[name="from_date"]');
            var toInput = form.querySelector('input[name="to_date"]');
            if (!fromInput || !toInput) {
                return;
            }

            fromInput.value = fromDate;
            toInput.value = toDate;
            form.submit();
        });
    }

    function accountingReportsExport(format) {
        var form =
            document.getElementById('accounting-reports-filter-form') ||
            document.querySelector('.container-fluid form') ||
            document.querySelector('form');
        var extra = '';
        if (form && typeof window.jQuery !== 'undefined') {
            extra = window.jQuery(form).serialize();
        } else if (form) {
            extra = new URLSearchParams(new FormData(form)).toString();
        }
        var path = window.location.pathname.replace(/\/$/, '');
        var url = path + '/export?format=' + encodeURIComponent(format);
        if (extra) {
            url += '&' + extra;
        }
        window.location.href = url;
    }

    window.accountingReportsExport = accountingReportsExport;

    window.exportExcel = function () {
        accountingReportsExport('excel');
    };

    window.exportPDF = function () {
        accountingReportsExport('pdf');
    };

    function bootAccountingDateUi() {
        initLedgerFilterButtonsOnce();
        initVatQuickPeriodSelectOnce();
        initAccountingDateFields(document);
        if (typeof window.jQuery !== 'undefined' && typeof window.initPersianDatePickers === 'function') {
            window.jQuery(function () {
                window.initPersianDatePickers();
            });
        }
    }

    function scheduleRetries() {
        var delays = [0, 120, 320, 700];
        for (var d = 0; d < delays.length; d++) {
            (function (ms) {
                window.setTimeout(function () {
                    bindAccountingRms2Datepickers(document);
                }, ms);
            })(delays[d]);
        }
    }

    function scheduleBoot() {
        function run() {
            bootAccountingDateUi();
            scheduleRetries();
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', run);
        } else {
            window.setTimeout(run, 0);
        }
        window.addEventListener('load', function onLoad() {
            window.removeEventListener('load', onLoad);
            bindAccountingRms2Datepickers(document);
        });
    }

    scheduleBoot();
})(window, document);

