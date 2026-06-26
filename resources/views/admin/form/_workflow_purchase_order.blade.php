{{-- راهنمای گام‌ها و تأیید سفارش خرید (Limitless / BS5) --}}
@php
    $po = ($isEdit && isset($model) && $model instanceof \RMS\Accounting\Models\PurchaseOrder) ? $model : null;
    $isDraft = $po && (string) $po->status === \RMS\Accounting\Models\PurchaseOrder::STATUS_DRAFT;
    $showPaymentNext = $po
        && ! $isDraft
        && (string) $po->status !== \RMS\Accounting\Models\PurchaseOrder::STATUS_CANCELLED;
@endphp
<div class="card border-0 shadow-sm mb-3 overflow-hidden">
    <div class="card-header bg-primary bg-opacity-10 border-0 py-3 d-flex align-items-center gap-3">
        <span class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width:2.5rem;height:2.5rem;">
            <i class="ph-path"></i>
        </span>
        <div>
            <h6 class="mb-0 fw-semibold">{{ trans('accounting::accounting.structured_workflow.purchase_order.card_title') }}</h6>
            <small class="text-muted">{{ trans('accounting::accounting.structured_workflow.purchase_order.card_sub') }}</small>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="d-flex gap-3 p-3 rounded border h-100 bg-light bg-opacity-50">
                    <span class="badge bg-primary bg-opacity-15 text-primary rounded-pill align-self-start px-2 py-1">1</span>
                    <div class="small lh-lg text-body-secondary">{{ trans('accounting::accounting.structured_workflow.purchase_order.step1') }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex gap-3 p-3 rounded border h-100 bg-light bg-opacity-50">
                    <span class="badge bg-primary bg-opacity-15 text-primary rounded-pill align-self-start px-2 py-1">2</span>
                    <div class="small lh-lg text-body-secondary">{{ trans('accounting::accounting.structured_workflow.purchase_order.step2') }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex gap-3 p-3 rounded border h-100 bg-light bg-opacity-50">
                    <span class="badge bg-primary bg-opacity-15 text-primary rounded-pill align-self-start px-2 py-1">3</span>
                    <div class="small lh-lg text-body-secondary">{{ trans('accounting::accounting.structured_workflow.purchase_order.step3') }}</div>
                </div>
            </div>
        </div>

        <x-accounting::page-description class="mb-0 border" :title="trans('accounting::accounting.structured_workflow.purchase_order.tax_shipping_title')">
            <p class="mb-0 small">{{ trans('accounting::accounting.structured_workflow.purchase_order.tax_shipping_body') }}</p>
        </x-accounting::page-description>
    </div>
</div>

@if($isDraft && $po)
    <div class="card border-0 shadow-sm mb-3 border-start border-success border-4">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3 py-3">
            <div class="d-flex align-items-start gap-3">
                <div class="rounded-circle bg-success bg-opacity-10 text-success p-2 d-inline-flex">
                    <i class="ph-seal-check fs-4"></i>
                </div>
                <div>
                    <h6 class="fw-semibold mb-1">{{ trans('accounting::accounting.structured_workflow.purchase_order.confirm_title') }}</h6>
                    <p class="text-muted small mb-0">{{ trans('accounting::accounting.structured_workflow.purchase_order.confirm_body') }}</p>
                    <p class="text-muted small mb-0 mt-1">{{ trans('accounting::accounting.structured_workflow.purchase_order.status_hint') }}</p>
                </div>
            </div>
            <form method="post" action="{{ route('admin.accounting.purchase-orders.confirm', ['id' => $po->getKey()]) }}" class="m-0">
                @csrf
                <button type="submit" class="btn btn-success btn-lg px-4">
                    <i class="ph-check-circle me-2"></i>{{ trans('accounting::accounting.structured_workflow.purchase_order.confirm_btn') }}
                </button>
            </form>
        </div>
    </div>
@endif

@if($showPaymentNext)
    <div class="card border-0 shadow-sm mb-3 border-start border-primary border-4">
        <div class="card-body py-3">
            <div class="d-flex align-items-start gap-3 mb-3">
                <div class="rounded-circle bg-primary bg-opacity-10 text-primary p-2 d-inline-flex">
                    <i class="ph-bank fs-4"></i>
                </div>
                <div class="flex-grow-1">
                    <h6 class="fw-semibold mb-1">{{ trans('accounting::accounting.structured_workflow.purchase_order.payment_next_title') }}</h6>
                    <p class="text-muted small mb-0 lh-lg">{{ trans('accounting::accounting.structured_workflow.purchase_order.payment_next_body') }}</p>
                </div>
            </div>
            @if($po->supplier_id)
                @php
                    // purchase_order_id تا فرم پرداخت مبلغ، مخفی PO و تأمین‌کننده را از سفارش پیش‌پر کند (فقط supplier_id کافی نیست).
                    $payUrl = route('admin.accounting.supplier-payments.create', [
                        'purchase_order_id' => $po->getKey(),
                    ]);
                @endphp
                <div class="d-flex flex-wrap align-items-center gap-2 pt-2 border-top">
                    @php
                        $poInvoiceGate = $purchaseOrderFromPoInvoiceGate ?? ['can' => false, 'reason' => null, 'existing_invoice_id' => null];
                        $invoiceCreateQuery = [
                            'supplier_id' => (string) $po->supplier_id,
                            'purchase_order_id' => (string) $po->getKey(),
                        ];
                    @endphp
                    @if(! empty($poInvoiceGate['can']))
                        <form method="post" action="{{ route('admin.accounting.purchase-orders.supplier-invoices.from-purchase-order', ['purchase_order' => $po->getKey()]) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-primary">
                                <i class="ph-invoice me-2"></i>{{ trans('accounting::accounting.purchase_order.invoice_from_po_btn') }}
                            </button>
                        </form>
                    @endif
                    <a href="{{ route('admin.accounting.supplier-invoices.create', $invoiceCreateQuery) }}" class="btn btn-light border @if(! empty($poInvoiceGate['can'])) btn-sm @endif">
                        <i class="ph-file-plus me-2"></i>{{ trans('accounting::accounting.structured_workflow.purchase_order.payment_next_invoice_btn') }}
                    </a>
                    @if(! empty($poInvoiceGate['reason']) && empty($poInvoiceGate['can']))
                        <div class="w-100 small text-muted mt-1">{{ $poInvoiceGate['reason'] }}</div>
                        @if(! empty($poInvoiceGate['existing_invoice_id']))
                            <a href="{{ route('admin.accounting.supplier-invoices.edit', ['supplier_invoice' => $poInvoiceGate['existing_invoice_id']]) }}" class="btn btn-sm btn-outline-primary">
                                <i class="ph-arrow-square-out me-1"></i>{{ trans('accounting::accounting.purchase_order.invoice_from_po_open_existing') }}
                            </a>
                        @endif
                    @endif
                    <a href="{{ $payUrl }}" class="btn btn-success">
                        <i class="ph-currency-circle-dollar me-2"></i>{{ trans('accounting::accounting.structured_workflow.purchase_order.payment_next_payment_btn') }}
                    </a>
                    <a href="{{ route('admin.accounting.supplier-payments.index') }}" class="btn btn-light btn-sm border">
                        <i class="ph-list me-1"></i>{{ trans('accounting::accounting.structured_workflow.purchase_order.payment_next_payment_list') }}
                    </a>
                    @php
                        $latestPoInvoiceId = \RMS\Accounting\Models\SupplierInvoice::query()
                            ->where('purchase_order_id', $po->getKey())
                            ->whereNull('deleted_at')
                            ->orderByDesc('id')
                            ->value('id');
                        $poDnUrl = route('admin.accounting.debit-notes.create', [], false)
                            . '?supplier_id=' . urlencode((string) $po->supplier_id)
                            . '&purchase_order_id=' . urlencode((string) $po->getKey());
                        if ($latestPoInvoiceId) {
                            $poDnUrl .= '&supplier_invoice_id=' . urlencode((string) $latestPoInvoiceId);
                        }
                        $poRefundUrl = route('admin.accounting.supplier-refunds.create', [], false)
                            . '?supplier_id=' . urlencode((string) $po->supplier_id)
                            . '&purchase_order_id=' . urlencode((string) $po->getKey());
                        if ($latestPoInvoiceId) {
                            $poRefundUrl .= '&supplier_invoice_id=' . urlencode((string) $latestPoInvoiceId);
                        }
                    @endphp
                    <a href="{{ $poDnUrl }}" class="btn btn-warning btn-sm">
                        <i class="ph-note-pencil me-1"></i>{{ trans('accounting::accounting.structured_workflow.purchase_order.debit_note_cta') }}
                    </a>
                    <a href="{{ $poRefundUrl }}" class="btn btn-outline-secondary btn-sm border">
                        <i class="ph-arrow-u-up-left me-1"></i>{{ trans('accounting::accounting.structured_workflow.supplier_invoice.supplier_refund_cta') }}
                    </a>
                </div>
                @php
                    $poReturnPolicy = (string) config('accounting.purchases.return_po_policy', 'link_only');
                @endphp
                <div class="alert alert-secondary border small mb-0 mt-3" role="note">
                    {{ trans('accounting::accounting.structured_workflow.purchase_order.return_po_policy_'.$poReturnPolicy) }}
                </div>
            @else
                <div class="alert alert-light border mb-0 small">
                    {{ trans('accounting::accounting.structured_workflow.purchase_order.payment_next_no_supplier') }}
                </div>
            @endif
        </div>
    </div>
@endif
