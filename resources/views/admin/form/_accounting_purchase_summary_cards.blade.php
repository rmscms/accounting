{{-- خلاصهٔ مبالغ (الگوی بصری مشابه اپ خرید؛ فقط فرم‌های حسابداری) --}}
@php
    use RMS\Accounting\Models\PurchaseOrder;
    use RMS\Accounting\Models\CustomerInvoice;
    use RMS\Accounting\Models\SupplierInvoice;

    $showPo = ($formSlug ?? '') === 'purchase_orders' && ! empty($isEdit) && isset($model) && $model instanceof PurchaseOrder;
    $showSi = ($formSlug ?? '') === 'supplier_invoices' && ! empty($isEdit) && isset($model) && $model instanceof SupplierInvoice;
    $showCi = ($formSlug ?? '') === 'customer_invoices' && ! empty($isEdit) && isset($model) && $model instanceof CustomerInvoice;

    $acctSummaryInitial = null;
    if ($showSi && isset($model) && $model instanceof SupplierInvoice) {
        $inv = $model;
        if (! $inv->relationLoaded('items')) {
            $inv->load(['items' => static fn ($q) => $q->orderBy('id')]);
        }
        $gross = 0.0;
        $discFromLines = 0.0;
        foreach ($inv->items as $it) {
            $gross += (float) $it->quantity * (float) $it->unit_price;
            $discFromLines += (float) ($it->discount_amount ?? 0);
        }
        if ($inv->items->isEmpty()) {
            $d = (float) ($inv->discount_amount ?? 0);
            $acctSummaryInitial = [
                'gross' => (float) ($inv->subtotal ?? 0) + $d,
                'discount' => $d,
                'tax' => (float) ($inv->tax_amount ?? 0),
                'total' => (float) ($inv->total_amount ?? 0),
                'balance' => (float) ($inv->balance_due ?? 0),
            ];
        } else {
            $acctSummaryInitial = [
                'gross' => $gross,
                'discount' => $discFromLines > 0 ? $discFromLines : (float) ($inv->discount_amount ?? 0),
                'tax' => (float) ($inv->tax_amount ?? 0),
                'total' => (float) ($inv->total_amount ?? 0),
                'balance' => (float) ($inv->balance_due ?? 0),
            ];
        }
    } elseif ($showCi && isset($model) && $model instanceof CustomerInvoice) {
        $inv = $model;
        if (! $inv->relationLoaded('items')) {
            $inv->load(['items' => static fn ($q) => $q->orderBy('id')]);
        }
        $gross = 0.0;
        $discFromLines = 0.0;
        foreach ($inv->items as $it) {
            $gross += (float) $it->quantity * (float) $it->price;
            $discFromLines += (float) ($it->discount_amount ?? 0);
        }
        $acctSummaryInitial = [
            'gross' => $gross > 0 ? $gross : (float) ($inv->subtotal ?? 0),
            'discount' => $discFromLines > 0 ? $discFromLines : (float) ($inv->discount_amount ?? 0),
            'tax' => (float) ($inv->tax_amount ?? 0),
            'total' => (float) ($inv->total_amount ?? 0),
            'balance' => (float) ($inv->balance_due ?? 0),
        ];
    } elseif ($showPo && isset($model) && $model instanceof PurchaseOrder) {
        $po = $model;
        if (! $po->relationLoaded('items')) {
            $po->load(['items' => static fn ($q) => $q->orderBy('id')]);
        }
        $gross = 0.0;
        $discFromLines = 0.0;
        foreach ($po->items as $it) {
            $gross += (float) $it->quantity * (float) $it->unit_price;
            $discFromLines += (float) ($it->discount_amount ?? 0);
        }
        $headerGross = (float) ($po->subtotal ?? 0);
        $headerDisc = (float) ($po->discount_amount ?? 0);
        if ($po->items->isEmpty()) {
            $acctSummaryInitial = [
                'gross' => $headerGross,
                'discount' => $headerDisc,
                'tax' => (float) ($po->tax_amount ?? 0),
                'total' => (float) ($po->total_amount ?? 0),
            ];
        } else {
            $acctSummaryInitial = [
                'gross' => $gross > 0 ? $gross : $headerGross,
                'discount' => $discFromLines > 0 ? $discFromLines : $headerDisc,
                'tax' => (float) ($po->tax_amount ?? 0),
                'total' => (float) ($po->total_amount ?? 0),
            ];
        }
    }

    $fmtAcct = static function (float $n): string {
        return number_format($n, 0, '.', ',');
    };
@endphp
@if($showPo || $showSi || $showCi)
    <div class="card border-0 shadow-sm border-start border-4 border-primary border-opacity-25 mt-3 acct-purchase-summary-wrap">
        <div class="card-body py-3">
            <div
                class="row row-cols-2 row-cols-md-3 {{ ($showSi || $showCi) ? 'row-cols-xl-5' : 'row-cols-xl-3' }} g-3"
                data-acct-purchase-summary
                data-variant="{{ $showSi ? 'si' : ($showCi ? 'ci' : 'po') }}"
                @if($acctSummaryInitial !== null)
                    data-acct-summary-initial="{{ e(json_encode($acctSummaryInitial, JSON_UNESCAPED_UNICODE)) }}"
                @endif
            >
                <div class="col">
                    <div class="acct-purchase-summary-card acct-purchase-summary-card--gross">
                        <div class="acct-purchase-summary-label">{{ trans('accounting::accounting.structured_resource_forms.summary_gross') }}</div>
                        <div class="acct-purchase-summary-value" data-role="acct-summary-gross">{{ $acctSummaryInitial !== null ? $fmtAcct((float) $acctSummaryInitial['gross']) : '0' }}</div>
                    </div>
                </div>
                <div class="col">
                    <div class="acct-purchase-summary-card acct-purchase-summary-card--discount">
                        <div class="acct-purchase-summary-label">{{ trans('accounting::accounting.structured_resource_forms.summary_discount') }}</div>
                        <div class="acct-purchase-summary-value" data-role="acct-summary-discount">{{ $acctSummaryInitial !== null ? $fmtAcct((float) $acctSummaryInitial['discount']) : '0' }}</div>
                    </div>
                </div>
                @if($showSi || $showCi)
                    <div class="col" data-acct-summary-tax-wrap>
                        <div class="acct-purchase-summary-card acct-purchase-summary-card--tax">
                            <div class="acct-purchase-summary-label">{{ trans('accounting::accounting.structured_resource_forms.summary_tax') }}</div>
                            <div class="acct-purchase-summary-value" data-role="acct-summary-tax">{{ $acctSummaryInitial !== null ? $fmtAcct((float) $acctSummaryInitial['tax']) : '0' }}</div>
                        </div>
                    </div>
                @endif
                <div class="col">
                    <div class="acct-purchase-summary-card acct-purchase-summary-card--total">
                        <div class="acct-purchase-summary-label">{{ trans('accounting::accounting.structured_resource_forms.summary_total') }}</div>
                        <div class="acct-purchase-summary-value" data-role="acct-summary-total">{{ $acctSummaryInitial !== null ? $fmtAcct((float) $acctSummaryInitial['total']) : '0' }}</div>
                    </div>
                </div>
                @if($showSi || $showCi)
                    <div class="col" data-acct-summary-balance-wrap>
                        <div class="acct-purchase-summary-card acct-purchase-summary-card--balance">
                            <div class="acct-purchase-summary-label">{{ trans('accounting::accounting.structured_resource_forms.summary_balance_due') }}</div>
                            <div class="acct-purchase-summary-value" data-role="acct-summary-balance">{{ $acctSummaryInitial !== null ? $fmtAcct((float) $acctSummaryInitial['balance']) : '0' }}</div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif
