@php
    /** @var \RMS\Accounting\Models\PurchaseOrder $order */
    $supplier = $order->supplier;
    $supplierName = $supplier ? (string) ($supplier->party?->name ?: $supplier->name) : '—';
    $orderDate = $order->order_date ? \Carbon\Carbon::parse($order->order_date)->toDateString() : '';
    if (function_exists('persian_date') && $order->order_date) {
        try {
            $orderDate = \RMS\Helper\persian_date(\Carbon\Carbon::parse($order->order_date), 'Y/m/d');
        } catch (\Throwable $e) {
            // keep ISO
        }
    }
@endphp
<h1>{{ trans('accounting::accounting.purchase_order.warehouse_receipt_title') }}</h1>
<div class="meta">
    {{ trans('accounting::accounting.purchase_order.po_number') }}: <strong>{{ $order->po_number }}</strong>
    —
    {{ trans('accounting::accounting.purchase_order.order_date') }}: {{ $orderDate }}
</div>
<div class="meta">
    {{ trans('accounting::accounting.purchase_order.supplier_id') }}: {{ $supplierName }}
</div>
@if($order->notes)
    <div class="meta">{{ trans('accounting::accounting.purchase_order.notes') }}: {{ $order->notes }}</div>
@endif

<table>
    <thead>
        <tr>
            <th style="width:32px">#</th>
            <th>{{ trans('accounting::accounting.purchase_order.item_product') }}</th>
            <th class="num">{{ trans('accounting::accounting.purchase_order.item_qty') }}</th>
            <th class="num">{{ trans('accounting::accounting.purchase_order.item_unit_price') }}</th>
            <th class="num">{{ trans('accounting::accounting.purchase_order.item_discount') }}</th>
            <th class="num">{{ trans('accounting::accounting.purchase_order.item_total') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($order->items as $idx => $line)
            <tr>
                <td>{{ $idx + 1 }}</td>
                <td>{{ $line->product_name }}</td>
                <td class="num">{{ $line->quantity }}</td>
                <td class="num">{{ number_format((float) $line->unit_price, 0, '.', ',') }}</td>
                <td class="num">{{ number_format((float) $line->discount_amount, 0, '.', ',') }}</td>
                <td class="num">{{ number_format((float) $line->total_price, 0, '.', ',') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="sign">
    {{ trans('accounting::accounting.purchase_order.warehouse_receipt_signature') }}
</div>
