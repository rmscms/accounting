/**
 * ویجت‌های مشترک Select2: تأمین‌کننده (AJAX) و فاکتور خرید وابسته به تأمین‌کننده.
 */
(function (window, $) {
    'use strict';

    var ns = window.AccountingAjaxSupplierWidgets = window.AccountingAjaxSupplierWidgets || {};

    ns.initSupplierSelect2 = function ($root) {
        if (!$ || typeof $.fn.select2 !== 'function') {
            return;
        }
        $root.find('select.accounting-supplier-select2').each(function () {
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
                allowClear: !($el.prop('required')),
                minimumInputLength: 1,
                language: {
                    inputTooShort: function () {
                        return placeholder;
                    }
                },
                ajax: {
                    url: url,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return { q: params.term || '', limit: 30 };
                    },
                    processResults: function (data) {
                        var rows = (data && data.results) ? data.results : [];
                        return { results: rows };
                    }
                }
            });
        });
    };

    ns.initSupplierInvoiceSelect2 = function ($root) {
        if (!$ || typeof $.fn.select2 !== 'function') {
            return;
        }
        $root.find('select.accounting-supplier-invoice-select2').each(function () {
            var $inv = $(this);
            if ($inv.data('select2')) {
                return;
            }
            var baseUrl = $inv.data('search-url');
            var depSel = $inv.data('depends-on');
            if (!baseUrl || !depSel) {
                return;
            }

            function $depEl() {
                var $d = $root.find(depSel);
                return $d.length ? $d : $(depSel);
            }

            function supplierId() {
                var v = $depEl().val();
                return v && String(v) !== '0' ? String(v) : '';
            }

            var phNoSupplier = String($inv.data('placeholder-no-supplier') || '');
            var phInvoice = String($inv.data('placeholder') || '');

            $inv.select2({
                width: '100%',
                placeholder: phNoSupplier,
                allowClear: true,
                minimumInputLength: 0,
                language: {
                    inputTooShort: function () {
                        return supplierId() ? phInvoice : phNoSupplier;
                    }
                },
                ajax: {
                    url: baseUrl,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            q: params.term || '',
                            supplier_id: supplierId(),
                            limit: 30
                        };
                    },
                    processResults: function (data) {
                        var rows = (data && data.results) ? data.results : [];
                        return { results: rows };
                    }
                }
            });

            $depEl().on('change.accountingSupplierInvoice', function () {
                if (!supplierId()) {
                    $inv.val(null).trigger('change');
                    return;
                }
                var current = $inv.val();
                if (!current) {
                    return;
                }
                $.ajax({
                    url: baseUrl,
                    dataType: 'json',
                    data: { supplier_id: supplierId(), q: '', limit: 50 }
                }).done(function (data) {
                    var rows = (data && data.results) ? data.results : [];
                    var ok = false;
                    var i;
                    for (i = 0; i < rows.length; i++) {
                        if (String(rows[i].id) === String(current)) {
                            ok = true;
                            break;
                        }
                    }
                    if (!ok) {
                        $inv.val(null).trigger('change');
                    }
                });
            });
        });
    };
}(window, window.jQuery));
