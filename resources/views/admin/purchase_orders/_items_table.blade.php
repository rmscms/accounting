@php
    /** @var \RMS\Accounting\Models\PurchaseOrder $order */
    $items = $order->relationLoaded('items') ? $order->items : $order->items()->orderBy('id')->get();
    $storeUrl = route('admin.accounting.purchase-orders.items.store', ['purchase_order' => $order->getKey()]);
    $warehouseReceiptPdfUrl = $warehouseReceiptPdfUrl ?? null;
    $linesCurrency = strtoupper((string) ($order->currency_code ?: config('accounting.defaults.currency', 'IRR')));
    $baseCurrency = strtoupper((string) config('accounting.defaults.currency', 'IRR'));
@endphp
@if(! empty($warehouseReceiptPdfUrl))
    <div class="d-flex justify-content-end mb-2">
        <a href="{{ $warehouseReceiptPdfUrl }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">
            <i class="ph-file-pdf me-1"></i>{{ trans('accounting::accounting.purchase_order.warehouse_receipt_btn') }}
        </a>
    </div>
@endif
<div class="purchase-order-lines-editor"
     data-line-items-editor="1"
     data-store-url="{{ $storeUrl }}"
     data-csrf="{{ csrf_token() }}">
    <div class="alert alert-info border mb-3 small">
        {{ trans('accounting::accounting.purchase_order.items_currency_help', ['currency' => $linesCurrency, 'base_currency' => $baseCurrency]) }}
    </div>
    <div class="alert alert-light border mb-3 po-lines-empty {{ $items->isEmpty() ? '' : 'd-none' }}">{{ trans('accounting::accounting.purchase_order.items_empty') }}</div>
    <div class="table-responsive">
        <table class="table table-sm table-striped mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width:36px">#</th>
                    <th>{{ trans('accounting::accounting.purchase_order.item_product') }}</th>
                    <th class="text-end" style="min-width:90px">{{ trans('accounting::accounting.purchase_order.item_qty') }}</th>
                    <th class="text-end" style="min-width:110px">{{ trans('accounting::accounting.purchase_order.item_unit_price') }}</th>
                    <th class="text-end" style="min-width:100px">{{ trans('accounting::accounting.purchase_order.item_discount') }}</th>
                    <th class="text-end" style="min-width:100px">{{ trans('accounting::accounting.purchase_order.item_total') }}</th>
                    <th style="width:120px"></th>
                </tr>
            </thead>
            <tbody class="po-lines-tbody">
                @foreach($items as $idx => $line)
                    <tr data-line-id="{{ $line->id }}">
                        <td class="text-muted">{{ $idx + 1 }}</td>
                        <td><input type="text" class="form-control form-control-sm fld-product_name" value="{{ e($line->product_name) }}"></td>
                        <td><input type="text" class="form-control form-control-sm text-end fld-quantity" inputmode="decimal" value="{{ e((string) $line->quantity) }}"></td>
                        <td><input type="text" class="form-control form-control-sm text-end fld-unit_price" inputmode="decimal" value="{{ e((string) $line->unit_price) }}"></td>
                        <td><input type="text" class="form-control form-control-sm text-end fld-discount_amount" inputmode="decimal" value="{{ e((string) $line->discount_amount) }}"></td>
                        <td class="text-end line-total-display">{{ number_format((float) $line->total_price, 0) }}</td>
                        <td class="text-nowrap">
                            <button type="button" class="btn btn-sm btn-primary js-line-save">{{ trans('accounting::accounting.line_editor.save') }}</button>
                            <button type="button" class="btn btn-sm btn-outline-danger js-line-delete">{{ trans('accounting::accounting.line_editor.delete') }}</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-2">
        <button type="button" class="btn btn-sm btn-outline-primary js-line-add">
            <i class="ph-plus me-1"></i>{{ trans('accounting::accounting.line_editor.add_line') }}
        </button>
    </div>
</div>
