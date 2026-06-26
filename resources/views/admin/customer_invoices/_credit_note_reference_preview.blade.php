@php
    /** @var \RMS\Accounting\Models\CustomerInvoice $invoice */
    $items = $invoice->relationLoaded('items') ? $invoice->items : $invoice->items()->orderBy('id')->get();
    $customerLabel = (string) ($invoice->customer?->name ?: '—');
    $paymentKey = (string) ($invoice->payment_status ?? '');
    $paymentLabel = match ($paymentKey) {
        \RMS\Accounting\Models\CustomerInvoice::STATUS_UNPAID => trans('accounting::accounting.statuses.unpaid'),
        \RMS\Accounting\Models\CustomerInvoice::STATUS_PARTIALLY_PAID => trans('accounting::accounting.statuses.partially_paid'),
        \RMS\Accounting\Models\CustomerInvoice::STATUS_PAID => trans('accounting::accounting.statuses.paid'),
        default => $paymentKey !== '' ? $paymentKey : '—',
    };
@endphp
<div class="credit-note-ref-invoice-preview border-top pt-3 mt-2">
    <div class="row g-3 small mb-3">
        <div class="col-md-4">
            <div class="text-muted text-uppercase fw-semibold" style="font-size:0.7rem;">{{ trans('accounting::accounting.invoice.invoice_number') }}</div>
            <div class="fw-semibold">{{ e((string) $invoice->invoice_number) }}</div>
        </div>
        <div class="col-md-4">
            <div class="text-muted text-uppercase fw-semibold" style="font-size:0.7rem;">{{ trans('accounting::accounting.invoice.invoice_date') }}</div>
            <div>{{ $invoice->invoice_date ? $invoice->invoice_date->format('Y-m-d') : '—' }}</div>
        </div>
        <div class="col-md-4">
            <div class="text-muted text-uppercase fw-semibold" style="font-size:0.7rem;">{{ trans('accounting::accounting.invoice.customer_id') }}</div>
            <div>{{ e($customerLabel) }}</div>
        </div>
        <div class="col-md-4">
            <div class="text-muted text-uppercase fw-semibold" style="font-size:0.7rem;">{{ trans('accounting::accounting.invoice.total_amount') }}</div>
            <div>{{ number_format((float) $invoice->total_amount, 0) }}</div>
        </div>
        <div class="col-md-4">
            <div class="text-muted text-uppercase fw-semibold" style="font-size:0.7rem;">{{ trans('accounting::accounting.invoice.balance_due') }}</div>
            <div>{{ number_format((float) ($invoice->balance_due ?? 0), 0) }}</div>
        </div>
        <div class="col-md-4">
            <div class="text-muted text-uppercase fw-semibold" style="font-size:0.7rem;">{{ trans('accounting::accounting.invoice.payment_status') }}</div>
            <div>{{ $paymentLabel }}</div>
        </div>
    </div>

    @if($items->isEmpty())
        <div class="alert alert-light border mb-0 small">{{ trans('accounting::accounting.customer_invoice.items_empty') }}</div>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>{{ trans('accounting::accounting.customer_invoice.item_product') }}</th>
                        <th class="text-end">{{ trans('accounting::accounting.customer_invoice.item_qty') }}</th>
                        <th class="text-end">{{ trans('accounting::accounting.customer_invoice.item_unit_price') }}</th>
                        <th class="text-end">{{ trans('accounting::accounting.customer_invoice.item_total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $idx => $line)
                        <tr>
                            <td>{{ $idx + 1 }}</td>
                            <td>{{ $line->product_name ?: '—' }}</td>
                            <td class="text-end">{{ number_format((float) $line->quantity, 2) }}</td>
                            <td class="text-end">{{ number_format((float) $line->price, 0) }}</td>
                            <td class="text-end">{{ number_format((float) $line->total, 0) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
