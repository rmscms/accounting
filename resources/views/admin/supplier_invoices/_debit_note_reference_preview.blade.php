{{-- پیش‌نمایش فقط‌خواندنی برای فرم یادداشت بدهکار --}}
@php
    /** @var \RMS\Accounting\Models\SupplierInvoice $invoice */
    $items = $invoice->relationLoaded('items') ? $invoice->items : $invoice->items()->orderBy('id')->get();
    $supplierLabel = (string) ($invoice->supplier?->party?->name ?: $invoice->supplier?->name ?: '—');
    $payKey = (string) ($invoice->payment_status ?? '');
    $payLabel = match ($payKey) {
        \RMS\Accounting\Models\SupplierInvoice::STATUS_UNPAID => trans('accounting::accounting.supplier_invoice.payment_status_unpaid'),
        \RMS\Accounting\Models\SupplierInvoice::STATUS_PARTIALLY_PAID => trans('accounting::accounting.supplier_invoice.payment_status_partial'),
        \RMS\Accounting\Models\SupplierInvoice::STATUS_PAID => trans('accounting::accounting.supplier_invoice.payment_status_paid'),
        default => $payKey !== '' ? $payKey : '—',
    };
@endphp
<div class="debit-note-ref-invoice-preview border-top pt-3 mt-2">
    <div class="row g-3 small mb-3">
        <div class="col-md-4">
            <div class="text-muted text-uppercase fw-semibold" style="font-size:0.7rem;">{{ trans('accounting::accounting.supplier_invoice.invoice_number') }}</div>
            <div class="fw-semibold">{{ e((string) $invoice->invoice_number) }}</div>
        </div>
        <div class="col-md-4">
            <div class="text-muted text-uppercase fw-semibold" style="font-size:0.7rem;">{{ trans('accounting::accounting.supplier_invoice.invoice_date') }}</div>
            <div>{{ $invoice->invoice_date ? $invoice->invoice_date->format('Y-m-d') : '—' }}</div>
        </div>
        <div class="col-md-4">
            <div class="text-muted text-uppercase fw-semibold" style="font-size:0.7rem;">{{ trans('accounting::accounting.supplier.name') }}</div>
            <div>{{ e($supplierLabel) }}</div>
        </div>
        <div class="col-md-4">
            <div class="text-muted text-uppercase fw-semibold" style="font-size:0.7rem;">{{ trans('accounting::accounting.supplier_invoice.total_amount') }}</div>
            <div>{{ number_format((float) $invoice->total_amount, 0) }}</div>
        </div>
        <div class="col-md-4">
            <div class="text-muted text-uppercase fw-semibold" style="font-size:0.7rem;">{{ trans('accounting::accounting.supplier_invoice.balance_due') }}</div>
            <div>{{ number_format((float) ($invoice->balance_due ?? 0), 0) }}</div>
        </div>
        <div class="col-md-4">
            <div class="text-muted text-uppercase fw-semibold" style="font-size:0.7rem;">{{ trans('accounting::accounting.supplier_invoice.payment_status') }}</div>
            <div>{{ $payLabel }}</div>
        </div>
    </div>

    @if($items->isEmpty())
        <div class="alert alert-light border mb-0 small">{{ trans('accounting::accounting.supplier_invoice.items_empty') }}</div>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>{{ trans('accounting::accounting.supplier_invoice.item_product') }}</th>
                        <th class="text-end">{{ trans('accounting::accounting.supplier_invoice.item_qty') }}</th>
                        <th class="text-end">{{ trans('accounting::accounting.supplier_invoice.item_unit_price') }}</th>
                        <th class="text-end">{{ trans('accounting::accounting.supplier_invoice.item_total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $idx => $line)
                        <tr>
                            <td>{{ $idx + 1 }}</td>
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
