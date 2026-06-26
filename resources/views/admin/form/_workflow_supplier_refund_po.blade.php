{{-- پیش‌نمایش سفارش خرید (انبار / عملیاتی) وقتی هنوز فاکتور خرید برای کارت برگشت نداریم --}}
@php
    /** @var \RMS\Accounting\Models\PurchaseOrder $purchaseOrder */
    $purchaseOrder = $purchaseOrder ?? null;
    $st = (string) ($purchaseOrder?->status ?? '');
    $stKey = 'accounting::accounting.purchase_order_status.'.$st;
    $stLabel = \Illuminate\Support\Facades\Lang::has($stKey) ? trans($stKey) : ($st !== '' ? $st : '—');
@endphp
@if($purchaseOrder)
    <div class="card border-0 shadow-sm mb-4 overflow-hidden border-start border-4 border-warning border-opacity-75">
        <div class="card-header bg-body-secondary border-0 py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-3">
                <span class="bg-warning text-dark rounded-circle d-inline-flex align-items-center justify-content-center" style="width:2.5rem;height:2.5rem;">
                    <i class="ph-shopping-cart"></i>
                </span>
                <div>
                    <h6 class="mb-0 fw-semibold">{{ trans('accounting::accounting.supplier_refund_workflow.po_card_title') }}</h6>
                    <small class="text-muted">{{ trans('accounting::accounting.supplier_refund_workflow.po_card_sub') }}</small>
                </div>
            </div>
            <a href="{{ route('admin.accounting.purchase-orders.edit', ['purchase_order' => $purchaseOrder->getKey()]) }}" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener">
                <i class="ph-arrow-square-out me-1"></i>{{ trans('accounting::accounting.supplier_refund_workflow.open_po') }}
            </a>
        </div>
        <div class="card-body">
            <div class="alert alert-light border small mb-3 mb-md-4" role="note">
                {{ trans('accounting::accounting.supplier_refund_workflow.po_accounting_note') }}
            </div>
            <div class="row g-3 small mb-3">
                <div class="col-md-3">
                    <div class="text-muted text-uppercase fw-semibold" style="letter-spacing:.04em;">{{ trans('accounting::accounting.purchase_order.po_number') }}</div>
                    <div class="fw-semibold">{{ $purchaseOrder->po_number ?: '—' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted text-uppercase fw-semibold" style="letter-spacing:.04em;">{{ trans('accounting::accounting.purchase_order.order_date') }}</div>
                    <div class="fw-semibold">{{ $purchaseOrder->order_date?->format('Y-m-d') ?? '—' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted text-uppercase fw-semibold" style="letter-spacing:.04em;">{{ trans('accounting::accounting.purchase_order.total_amount') }}</div>
                    <div class="fw-semibold">{{ number_format((float) ($purchaseOrder->total_amount ?? 0), 0) }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted text-uppercase fw-semibold" style="letter-spacing:.04em;">{{ trans('accounting::accounting.purchase_order.status') }}</div>
                    <div class="fw-semibold">{{ $stLabel }}</div>
                </div>
            </div>
            @php
                $items = $purchaseOrder->relationLoaded('items') ? $purchaseOrder->items : $purchaseOrder->items()->orderBy('id')->get();
            @endphp
            <h6 class="fw-semibold border-top pt-3 mb-2">{{ trans('accounting::accounting.supplier_refund_workflow.po_lines_title') }}</h6>
            @if($items->isEmpty())
                <div class="alert alert-light border mb-0 small">{{ trans('accounting::accounting.purchase_order.items_empty') }}</div>
            @else
                <div class="table-responsive border rounded">
                    <table class="table table-sm table-striped mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px">#</th>
                                <th>{{ trans('accounting::accounting.purchase_order.item_product') }}</th>
                                <th class="text-end">{{ trans('accounting::accounting.purchase_order.item_qty') }}</th>
                                <th class="text-end">{{ trans('accounting::accounting.purchase_order.item_unit_price') }}</th>
                                <th class="text-end">{{ trans('accounting::accounting.purchase_order.item_total') }}</th>
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
