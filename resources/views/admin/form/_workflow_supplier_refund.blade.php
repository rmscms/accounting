{{-- پیش‌نمایش فاکتور خرید مرتبط با برگشت از تأمین‌کننده --}}
@php
    /** @var \RMS\Accounting\Models\SupplierInvoice $invoice */
    $invoice = $invoice ?? null;
@endphp
@if($invoice)
    <div class="card border-0 shadow-sm mb-4 overflow-hidden border-start border-4 border-secondary">
        <div class="card-header bg-body-secondary border-0 py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-3">
                <span class="bg-secondary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width:2.5rem;height:2.5rem;">
                    <i class="ph-file-text"></i>
                </span>
                <div>
                    <h6 class="mb-0 fw-semibold">{{ trans('accounting::accounting.supplier_refund_workflow.invoice_card_title') }}</h6>
                    <small class="text-muted">{{ trans('accounting::accounting.supplier_refund_workflow.invoice_card_sub') }}</small>
                </div>
            </div>
            <a href="{{ route('admin.accounting.supplier-invoices.edit', ['supplier_invoice' => $invoice->getKey()]) }}" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener">
                <i class="ph-arrow-square-out me-1"></i>{{ trans('accounting::accounting.supplier_refund_workflow.open_invoice') }}
            </a>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">{{ trans('accounting::accounting.supplier_refund_workflow.settlement_hint') }}</p>
            <div class="row g-3 small mb-3">
                <div class="col-md-3">
                    <div class="text-muted text-uppercase fw-semibold" style="letter-spacing:.04em;">{{ trans('accounting::accounting.supplier_refund_workflow.invoice_number') }}</div>
                    <div class="fw-semibold">{{ $invoice->invoice_number ?: '—' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted text-uppercase fw-semibold" style="letter-spacing:.04em;">{{ trans('accounting::accounting.supplier_refund_workflow.invoice_date') }}</div>
                    <div class="fw-semibold">{{ $invoice->invoice_date?->format('Y-m-d') ?? '—' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted text-uppercase fw-semibold" style="letter-spacing:.04em;">{{ trans('accounting::accounting.supplier_refund_workflow.total') }}</div>
                    <div class="fw-semibold">{{ number_format((float) ($invoice->total_amount ?? 0), 0) }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted text-uppercase fw-semibold" style="letter-spacing:.04em;">{{ trans('accounting::accounting.supplier_refund_workflow.payment_status') }}</div>
                    <div class="fw-semibold">{{ (string) ($invoice->payment_status ?? '—') }}</div>
                </div>
            </div>
            @php
                $items = $invoice->relationLoaded('items') ? $invoice->items : $invoice->items()->orderBy('id')->get();
            @endphp
            <h6 class="fw-semibold border-top pt-3 mb-2">{{ trans('accounting::accounting.supplier_refund_workflow.lines_title') }}</h6>
            @if($items->isEmpty())
                <div class="alert alert-light border mb-0 small">{{ trans('accounting::accounting.supplier_invoice.items_empty') }}</div>
            @else
                <div class="table-responsive border rounded">
                    <table class="table table-sm table-striped mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px">#</th>
                                <th>{{ trans('accounting::accounting.supplier_invoice.item_product') }}</th>
                                <th class="text-end">{{ trans('accounting::accounting.supplier_invoice.item_qty') }}</th>
                                <th class="text-end">{{ trans('accounting::accounting.supplier_invoice.item_unit_price') }}</th>
                                <th class="text-end">{{ trans('accounting::accounting.supplier_invoice.item_total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $idx => $line)
                                <tr>
                                    <td class="text-muted">{{ $idx + 1 }}</td>
                                    <td>{{ $line->product_name ?: '—' }}</td>
                                    <td class="text-end">{{ number_format((float) $line->quantity, 2) }}</td>
                                    <td class="text-end">{{ number_format((float) $line->unit_price, 0) }}</td>
                                    <td class="text-end">{{ number_format((float) $line->total_price, 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endif
