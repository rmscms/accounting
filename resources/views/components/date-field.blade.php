@props([
    'name',
    'label' => '',
    'value' => '',
    'required' => false,
    'id' => null,
    'colClass' => 'col-md-4',
    'errorKey' => null,
])
@php
    $fieldId = $id ?? $name;
    $errorKey = $errorKey ?? $name;
    $cal = \RMS\Accounting\Support\AccountingDateUi::calendarModeForDom();
    $inputType = $cal === \RMS\Accounting\Support\AccountingDateUi::MODE_GREGORIAN ? 'date' : 'text';
    $placeholder = '2026-01-01';
    $isJalali = $cal === \RMS\Accounting\Support\AccountingDateUi::MODE_JALALI;
@endphp
<div class="{{ $colClass }} mb-2">
    @if($label !== '')
        <label class="form-label" for="{{ $fieldId }}">{!! $label !!}</label>
    @endif
    @if($isJalali)
        <div class="input-group">
            <span class="input-group-text"><i class="ph-calendar"></i></span>
            <input
                type="{{ $inputType }}"
                name="{{ $name }}"
                id="{{ $fieldId }}"
                class="form-control persian-datepicker accounting-date-field @error($errorKey) is-invalid @enderror"
                value="{{ $value }}"
                data-calendar="{{ $cal }}"
                data-persian-date
                data-format="YYYY-MM-DD"
                autocomplete="off"
                placeholder="{{ $placeholder }}"
                @if($required) required @endif
            />
        </div>
    @else
        <input
            type="{{ $inputType }}"
            name="{{ $name }}"
            id="{{ $fieldId }}"
            class="form-control accounting-date-field @error($errorKey) is-invalid @enderror"
            value="{{ $value }}"
            data-calendar="{{ $cal }}"
            autocomplete="off"
            placeholder="{{ $placeholder }}"
            @if($required) required @endif
        />
    @endif
    @error($errorKey)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
