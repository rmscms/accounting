/**
 * ویرایشگر AJAX اقلام فاکتور خرید / سفارش خرید (پس از inject فرگمنت HTML).
 */
(function ($) {
    'use strict';

    function csrf() {
        return $('meta[name="csrf-token"]').attr('content') || '';
    }

    function parseNum(v) {
        var s = (v || '').toString().replace(/,/g, '').trim();
        if (s === '') {
            return 0;
        }
        var n = parseFloat(s);
        return isNaN(n) ? 0 : n;
    }

    function formatMoney(n) {
        var x = Number(n) || 0;
        return x.toLocaleString('en-US', { maximumFractionDigits: 0 });
    }

    function taxMethodOf($root) {
        var method = (($root.data('tax-method') || '').toString().trim().toLowerCase());
        return method === 'inclusive' ? 'inclusive' : 'exclusive';
    }

    function lineAmounts($tr, $root) {
        var q = parseNum($tr.find('.fld-quantity').val());
        var u = parseNum($tr.find('.fld-unit_price').val());
        var d = parseNum($tr.find('.fld-discount_amount').val());
        var r = parseNum($tr.find('.fld-tax_rate').val());
        var net = Math.max(0, q * u - d);
        var tax = 0;
        var total = net;
        if (r > 0) {
            if (taxMethodOf($root) === 'inclusive') {
                var base = net / (1 + (r / 100));
                tax = Math.max(0, net - base);
                total = net;
            } else {
                tax = net * (r / 100);
                total = net + tax;
            }
        }
        return { net: net, tax: tax, total: total };
    }

    function readRowPayload($tr, $root) {
        var payload = {
            product_name: ($tr.find('.fld-product_name').val() || '').toString().trim(),
            quantity: parseNum($tr.find('.fld-quantity').val()),
            unit_price: parseNum($tr.find('.fld-unit_price').val()),
            discount_amount: parseNum($tr.find('.fld-discount_amount').val()),
            tax_method: taxMethodOf($root)
        };
        var $taxRate = $tr.find('.fld-tax_rate');
        if ($taxRate.length) {
            payload.tax_rate = parseNum($taxRate.val());
        }

        return payload;
    }

    function lineTotalDisplay($tr, $root) {
        var totals = lineAmounts($tr, $root);
        $tr.find('.line-total-display').text(totals.total.toLocaleString('en-US', { maximumFractionDigits: 0 }));
    }

    function setFld($pageRoot, key, val) {
        if (typeof val === 'undefined' || val === null) {
            return;
        }
        var $el = $pageRoot.find('#fld-' + key);
        if ($el.length) {
            $el.val(val);
        }
    }

    function setAcctCard($pageRoot, role, text) {
        var $el = $pageRoot.find('[data-acct-purchase-summary] [data-role="' + role + '"]');
        if ($el.length) {
            $el.text(text);
        }
    }

    function initSummaryFromAttribute($pageRoot) {
        if (!$pageRoot || !$pageRoot.length) {
            return;
        }
        var $sum = $pageRoot.find('[data-acct-purchase-summary]');
        if (!$sum.length) {
            return;
        }
        var raw = $sum.attr('data-acct-summary-initial');
        if (!raw) {
            return;
        }
        try {
            var p = JSON.parse(raw);
            if (!p || typeof p !== 'object') {
                return;
            }
            setAcctCard($pageRoot, 'acct-summary-gross', formatMoney(p.gross));
            setAcctCard($pageRoot, 'acct-summary-discount', formatMoney(p.discount));
            setAcctCard($pageRoot, 'acct-summary-tax', formatMoney(p.tax));
            setAcctCard($pageRoot, 'acct-summary-total', formatMoney(p.total));
            if (typeof p.balance !== 'undefined') {
                setAcctCard($pageRoot, 'acct-summary-balance', formatMoney(p.balance));
            }
        } catch (e) {
            // ignore
        }
    }

    function sumRowsFromDom($root) {
        var gross = 0;
        var disc = 0;
        var tax = 0;
        var total = 0;
        $root.find('tbody tr').each(function () {
            var $tr = $(this);
            gross += parseNum($tr.find('.fld-quantity').val()) * parseNum($tr.find('.fld-unit_price').val());
            disc += parseNum($tr.find('.fld-discount_amount').val());
            var amounts = lineAmounts($tr, $root);
            tax += amounts.tax;
            total += amounts.total;
        });
        return { gross: gross, disc: disc, net: Math.max(0, gross - disc), tax: tax, total: total };
    }

    function refreshAcctSummaryLiveFromTable($root, $pageRoot) {
        var $sum = $pageRoot.find('[data-acct-purchase-summary]');
        if (!$sum.length) {
            return;
        }
        if (!$root.find('.fld-quantity').length) {
            initSummaryFromAttribute($pageRoot);
            return;
        }
        var variant = ($sum.data('variant') || '').toString();
        var s = sumRowsFromDom($root);
        setAcctCard($pageRoot, 'acct-summary-gross', formatMoney(s.gross));
        setAcctCard($pageRoot, 'acct-summary-discount', formatMoney(s.disc));
        if (variant === 'po') {
            setFld($pageRoot, 'subtotal', s.gross);
            setFld($pageRoot, 'discount_amount', s.disc);
            setFld($pageRoot, 'tax_amount', 0);
            setFld($pageRoot, 'total_amount', s.net);
            setAcctCard($pageRoot, 'acct-summary-total', formatMoney(s.net));
        } else {
            setFld($pageRoot, 'subtotal', s.net);
            setFld($pageRoot, 'tax_amount', s.tax);
            setFld($pageRoot, 'total_amount', s.total);
            setFld($pageRoot, 'discount_amount', s.disc);
            setAcctCard($pageRoot, 'acct-summary-tax', formatMoney(s.tax));
            setAcctCard($pageRoot, 'acct-summary-total', formatMoney(s.total));
        }
    }

    function updateAcctSummaryCards($pageRoot, payload) {
        var $sum = $pageRoot.find('[data-acct-purchase-summary]');
        if (!$sum.length) {
            return;
        }
        var inv = payload && payload.invoice;
        var ord = payload && payload.order;
        if (inv) {
            var gInv = inv.gross_before_discount != null ? inv.gross_before_discount : inv.subtotal;
            setAcctCard($pageRoot, 'acct-summary-gross', formatMoney(gInv));
            setAcctCard($pageRoot, 'acct-summary-discount', formatMoney(inv.discount_amount || 0));
            setAcctCard($pageRoot, 'acct-summary-tax', formatMoney(inv.tax_amount || 0));
            setAcctCard($pageRoot, 'acct-summary-total', formatMoney(inv.total_amount || 0));
            setAcctCard($pageRoot, 'acct-summary-balance', formatMoney(inv.balance_due || 0));
        } else if (ord) {
            var gOrd = ord.gross_before_discount != null ? ord.gross_before_discount : ord.subtotal;
            setAcctCard($pageRoot, 'acct-summary-gross', formatMoney(gOrd));
            setAcctCard($pageRoot, 'acct-summary-discount', formatMoney(ord.discount_amount || 0));
            setAcctCard($pageRoot, 'acct-summary-total', formatMoney(ord.total_amount || 0));
        }
    }

    function updateStructuredFormTotals($pageRoot, payload) {
        if (!payload || !$pageRoot || !$pageRoot.length) {
            return;
        }
        var inv = payload.invoice;
        var ord = payload.order;
        if (inv) {
            setFld($pageRoot, 'subtotal', inv.subtotal);
            setFld($pageRoot, 'tax_amount', inv.tax_amount);
            setFld($pageRoot, 'discount_amount', inv.discount_amount);
            setFld($pageRoot, 'total_amount', inv.total_amount);
            setFld($pageRoot, 'balance_due', inv.balance_due);
        }
        if (ord) {
            setFld($pageRoot, 'subtotal', ord.subtotal);
            setFld($pageRoot, 'tax_amount', ord.tax_amount);
            setFld($pageRoot, 'discount_amount', ord.discount_amount);
            setFld($pageRoot, 'total_amount', ord.total_amount);
        }
        updateAcctSummaryCards($pageRoot, payload);
    }

    function showRowErrors($tr, errors) {
        $tr.find('.is-invalid').removeClass('is-invalid');
        if (!errors || typeof errors !== 'object') {
            return;
        }
        Object.keys(errors).forEach(function (k) {
            var map = {
                product_name: '.fld-product_name',
                quantity: '.fld-quantity',
                unit_price: '.fld-unit_price',
                discount_amount: '.fld-discount_amount',
                tax_rate: '.fld-tax_rate'
            };
            var sel = map[k];
            if (sel) {
                $tr.find(sel).addClass('is-invalid');
            }
        });
    }

    function bind($mount) {
        if (!$ || !$mount || !$mount.length) {
            return;
        }
        var $pageRoot = $mount.closest('.accounting-structured-form');
        var $root = $mount.find('[data-line-items-editor]');
        if (!$root.length) {
            initSummaryFromAttribute($pageRoot);
            return;
        }
        if ($root.data('linesEditorBound')) {
            return;
        }
        $root.data('linesEditorBound', 1);

        var storeUrl = ($root.data('store-url') || '').toString();
        var token = ($root.data('csrf') || csrf()).toString();

        function itemUrl(id) {
            return storeUrl.replace(/\/?$/, '') + '/' + encodeURIComponent(id);
        }

        $root.on('change', '.js-tax-method-toggle', function () {
            var method = $(this).is(':checked') ? 'inclusive' : 'exclusive';
            $root.attr('data-tax-method', method).data('tax-method', method);
            setFld($pageRoot, 'tax_method', method);
            $root.find('tbody tr').each(function () {
                lineTotalDisplay($(this), $root);
            });
            refreshAcctSummaryLiveFromTable($root, $pageRoot);
        });

        $root.on('input change', '.fld-quantity, .fld-unit_price, .fld-discount_amount, .fld-tax_rate', function () {
            var $tr = $(this).closest('tr');
            lineTotalDisplay($tr, $root);
            refreshAcctSummaryLiveFromTable($root, $pageRoot);
        });

        $root.on('click', '.js-line-save', function () {
            var $tr = $(this).closest('tr');
            var id = $tr.data('line-id');
            var body = readRowPayload($tr, $root);
            var url = id ? itemUrl(id) : storeUrl;
            var method = id ? 'PUT' : 'POST';
            $.ajax({
                url: url,
                method: method,
                dataType: 'json',
                headers: {
                    'X-CSRF-TOKEN': token,
                    Accept: 'application/json'
                },
                data: body
            })
                .done(function (res) {
                    $tr.find('.is-invalid').removeClass('is-invalid');
                    if (res && res.item && res.item.id) {
                        $tr.attr('data-line-id', res.item.id);
                    }
                    if (res && res.item) {
                        lineTotalDisplay($tr, $root);
                    }
                    $root.find('.si-lines-empty, .po-lines-empty').addClass('d-none');
                    updateStructuredFormTotals($pageRoot, res);
                })
                .fail(function (xhr) {
                    if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                        showRowErrors($tr, xhr.responseJSON.errors);
                    }
                });
        });

        $root.on('click', '.js-line-delete', function () {
            var $tr = $(this).closest('tr');
            var id = $tr.data('line-id');
            if (!id) {
                $tr.remove();
                refreshAcctSummaryLiveFromTable($root, $pageRoot);
                return;
            }
            $.ajax({
                url: itemUrl(id),
                method: 'DELETE',
                dataType: 'json',
                headers: {
                    'X-CSRF-TOKEN': token,
                    Accept: 'application/json'
                },
                data: { tax_method: taxMethodOf($root) }
            })
                .done(function (res) {
                    $tr.remove();
                    updateStructuredFormTotals($pageRoot, res);
                    refreshAcctSummaryLiveFromTable($root, $pageRoot);
                    var $tb = $root.find('tbody');
                    if ($tb.length && $tb.find('tr').length === 0) {
                        $root.find('.si-lines-empty, .po-lines-empty').removeClass('d-none');
                    }
                });
        });

        $root.on('click', '.js-line-add', function () {
            var $tb = $root.find('tbody').first();
            if (!$tb.length) {
                return;
            }
            var $tr = $('<tr/>');
            $tr.append('<td class="text-muted">+</td>');
            $tr.append(
                '<td><input type="text" class="form-control form-control-sm fld-product_name" value=""></td>'
            );
            $tr.append(
                '<td><input type="text" class="form-control form-control-sm text-end fld-quantity" inputmode="decimal" value="1"></td>'
            );
            $tr.append(
                '<td><input type="text" class="form-control form-control-sm text-end fld-unit_price" inputmode="decimal" value="0"></td>'
            );
            $tr.append(
                '<td><input type="text" class="form-control form-control-sm text-end fld-discount_amount" inputmode="decimal" value="0"></td>'
            );
            $tr.append(
                '<td><input type="text" class="form-control form-control-sm text-end fld-tax_rate" inputmode="decimal" value="' +
                    parseNum($root.data('default-tax-rate')) +
                    '"></td>'
            );
            $tr.append('<td class="text-end line-total-display">0</td>');
            $tr.append(
                '<td class="text-nowrap"><button type="button" class="btn btn-sm btn-primary js-line-save">' +
                    ($root.data('label-save') || 'Save') +
                    '</button> <button type="button" class="btn btn-sm btn-outline-danger js-line-delete">' +
                    ($root.data('label-delete') || 'Delete') +
                    '</button></td>'
            );
            $tb.append($tr);
            lineTotalDisplay($tr, $root);
            refreshAcctSummaryLiveFromTable($root, $pageRoot);
        });

        refreshAcctSummaryLiveFromTable($root, $pageRoot);
    }

    window.AccountingLineItemsEditor = {
        bind: bind,
        initSummaryFromAttribute: initSummaryFromAttribute
    };
})(window.jQuery);
