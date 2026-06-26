{{-- Select2 AJAX برای تأمین‌کننده (مقدار = suppliers.id) --}}
@php
    $supplierSearchUrl = $supplierSearchUrl ?? route('admin.accounting.supplier-invoices.search-suppliers');
    $selVal = $val ?? null;
    $initText = $initialOptionText ?? '';
@endphp
<label class="form-label" for="fld-{{ $key }}">{{ $label }} @if($required)<span class="text-danger">*</span>@endif</label>
<select name="{{ $key }}" id="fld-{{ $key }}"
        class="form-select accounting-supplier-select2 @error($key) is-invalid @enderror"
        data-search-url="{{ $supplierSearchUrl }}"
        data-placeholder="{{ trans('accounting::accounting.structured_resource_forms.select_placeholder') }}"
        @if($required) required @endif>
    @if($selVal === null || $selVal === '')
        <option value="" disabled selected>{{ trans('accounting::accounting.structured_resource_forms.select_placeholder') }}</option>
    @else
        <option value="{{ $selVal }}" selected>{{ $initText !== '' ? $initText : ('#'.$selVal) }}</option>
    @endif
</select>
@error($key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
