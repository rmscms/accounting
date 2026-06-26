@php
    /** @var \RMS\Accounting\Models\CustomerInvoice $invoice */
    $items = $invoice->relationLoaded('items') ? $invoice->items : $invoice->items()->orderBy('id')->get();
    $locked = (bool) $invoice->document_id;
    $storeUrl = route('admin.accounting.customer-invoices.items.store', ['customer_invoice' => $invoice->getKey()]);
    $taxMethod = in_array((string) ($invoice->tax_method ?? ''), ['inclusive', 'exclusive'], true)
        ? (string) $invoice->tax_method
        : (function_exists('tax_calculation_method') ? tax_calculation_method() : 'exclusive');
@endphp

@if($locked)
    <div class="alert alert-warning border mb-3">
        {{ trans('accounting::accounting.customer_invoice.items_locked_document') }}
    </div>
@endif

@if($locked)
    @if($items->isEmpty())
        <div class="alert alert-light border mb-0">{{ trans('accounting::accounting.customer_invoice.items_empty') }}</div>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>{{ trans('accounting::accounting.customer_invoice.item_product') }}</th>
                        <th class="text-end">{{ trans('accounting::accounting.customer_invoice.item_qty') }}</th>
                        <th class="text-end">{{ trans('accounting::accounting.customer_invoice.item_unit_price') }}</th>
                        <th class="text-end">{{ trans('accounting::accounting.customer_invoice.item_tax_rate') }}</th>
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
                            <td class="text-end">{{ rtrim(rtrim(number_format((float) ($line->tax_rate ?? 0), 4, '.', ''), '0'), '.') }}%</td>
                            <td class="text-end">{{ number_format((float) $line->total, 0) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@else
    <div class="customer-invoice-lines-editor"
         data-line-items-editor="1"
         data-store-url="{{ $storeUrl }}"
         data-csrf="{{ csrf_token() }}"
         data-default-tax-rate="{{ is_vat_enabled() ? (float) \RMS\Core\Models\Setting::get('accounting.vat.rate', 9) : 0 }}"
         data-tax-method="{{ $taxMethod }}">
        <div class="form-check form-switch mb-3">
            <input class="form-check-input js-tax-method-toggle" type="checkbox" id="ci-tax-method-toggle-{{ $invoice->getKey() }}" {{ $taxMethod === 'inclusive' ? 'checked' : '' }}>
            <label class="form-check-label" for="ci-tax-method-toggle-{{ $invoice->getKey() }}">
                {{ trans('accounting::accounting.customer_invoice.tax_mode_toggle') }}
            </label>
        </div>
        <div class="alert alert-light border mb-3 si-lines-empty {{ $items->isEmpty() ? '' : 'd-none' }}">{{ trans('accounting::accounting.customer_invoice.items_empty') }}</div>
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width:36px">#</th>
                        <th>{{ trans('accounting::accounting.customer_invoice.item_product') }}</th>
                        <th class="text-end" style="min-width:90px">{{ trans('accounting::accounting.customer_invoice.item_qty') }}</th>
                        <th class="text-end" style="min-width:110px">{{ trans('accounting::accounting.customer_invoice.item_unit_price') }}</th>
                        <th class="text-end" style="min-width:100px">{{ trans('accounting::accounting.customer_invoice.item_discount') }}</th>
                        <th class="text-end" style="min-width:90px">{{ trans('accounting::accounting.customer_invoice.item_tax_rate') }}</th>
                        <th class="text-end" style="min-width:100px">{{ trans('accounting::accounting.customer_invoice.item_total') }}</th>
                        <th style="width:120px"></th>
                    </tr>
                </thead>
                <tbody class="si-lines-tbody">
                    @foreach($items as $idx => $line)
                        <tr data-line-id="{{ $line->id }}">
                            <td class="text-muted">{{ $idx + 1 }}</td>
                            <td><input type="text" class="form-control form-control-sm fld-product_name" value="{{ e($line->product_name) }}"></td>
                            <td><input type="text" class="form-control form-control-sm text-end fld-quantity" inputmode="decimal" value="{{ e((string) $line->quantity) }}"></td>
                            <td><input type="text" class="form-control form-control-sm text-end fld-unit_price" inputmode="decimal" value="{{ e((string) $line->price) }}"></td>
                            <td><input type="text" class="form-control form-control-sm text-end fld-discount_amount" inputmode="decimal" value="{{ e((string) $line->discount_amount) }}"></td>
                            <td><input type="text" class="form-control form-control-sm text-end fld-tax_rate" inputmode="decimal" value="{{ e((string) ($line->tax_rate ?? 0)) }}"></td>
                            <td class="text-end line-total-display">{{ number_format((float) $line->total, 0) }}</td>
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
@endif
