@props([
    'fromValue' => '',
    'toValue' => '',
    'fromName' => 'from_date',
    'toName' => 'to_date',
    'fromLabel' => 'از تاریخ',
    'toLabel' => 'تا تاریخ',
    'fromColClass' => 'col-md-3',
    'toColClass' => 'col-md-3',
])
@php
    $cal = \RMS\Accounting\Support\AccountingDateUi::calendarModeForDom();
    $inputType = $cal === \RMS\Accounting\Support\AccountingDateUi::MODE_GREGORIAN ? 'date' : 'text';
    $placeholder = '2026-01-01';
    $isJalali = $cal === \RMS\Accounting\Support\AccountingDateUi::MODE_JALALI;
@endphp
<div class="{{ $fromColClass }} mb-2">
    <label class="form-label">{{ $fromLabel }}</label>
    @if($isJalali)
        <div class="input-group">
            <span class="input-group-text"><i class="ph-calendar"></i></span>
            <input
                type="{{ $inputType }}"
                name="{{ $fromName }}"
                class="form-control persian-datepicker accounting-date-field"
                value="{{ $fromValue }}"
                data-calendar="{{ $cal }}"
                data-persian-date
                data-format="YYYY-MM-DD"
                autocomplete="off"
                placeholder="{{ $placeholder }}"
            />
        </div>
    @else
        <input
            type="{{ $inputType }}"
            name="{{ $fromName }}"
            class="form-control accounting-date-field"
            value="{{ $fromValue }}"
            data-calendar="{{ $cal }}"
            autocomplete="off"
            placeholder="{{ $placeholder }}"
        />
    @endif
</div>
<div class="{{ $toColClass }} mb-2">
    <label class="form-label">{{ $toLabel }}</label>
    @if($isJalali)
        <div class="input-group">
            <span class="input-group-text"><i class="ph-calendar"></i></span>
            <input
                type="{{ $inputType }}"
                name="{{ $toName }}"
                class="form-control persian-datepicker accounting-date-field"
                value="{{ $toValue }}"
                data-calendar="{{ $cal }}"
                data-persian-date
                data-format="YYYY-MM-DD"
                autocomplete="off"
                placeholder="{{ $placeholder }}"
            />
        </div>
    @else
        <input
            type="{{ $inputType }}"
            name="{{ $toName }}"
            class="form-control accounting-date-field"
            value="{{ $toValue }}"
            data-calendar="{{ $cal }}"
            autocomplete="off"
            placeholder="{{ $placeholder }}"
        />
    @endif
</div>
