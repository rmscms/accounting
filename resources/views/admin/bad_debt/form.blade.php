{{-- فرم اختصاصی ذخیره مطالبات مشکوک — پکیج accounting --}}
@extends('cms::admin.layout.index')

@section('title', filled($title ?? null) ? $title : trans('accounting::accounting.bad_debt_form.title_create'))

@section('content')
@php
    $isEdit = !empty($isEdit);
    /** @var \RMS\Accounting\Models\BadDebtProvision|null $provision */
    $provision = $provision ?? null;
    $amountDecimalPlaces = (int) ($amountDecimalPlaces ?? 0);
    $defaultCurrency = $defaultCurrency ?? 'IRR';

    $provisionDateOld = old('provision_date');
    if ($provisionDateOld !== null && trim((string) $provisionDateOld) !== '') {
        $pdProvision = trim(\RMS\Helper\changeNumberToEn((string) $provisionDateOld));
    } elseif ($isEdit && $provision && $provision->provision_date) {
        $pdProvision = \RMS\Accounting\Support\AccountingDateUi::gregorianYmdToInputDisplay(
            $provision->provision_date instanceof \DateTimeInterface
                ? $provision->provision_date->format('Y-m-d')
                : (string) $provision->provision_date
        );
    } else {
        $pdProvision = \RMS\Accounting\Support\AccountingDateUi::gregorianYmdToInputDisplay(now()->format('Y-m-d'));
    }

    $amountFieldDefault = '';
    if (! $isEdit) {
        $amountFieldDefault = old('provision_amount', '');
    } elseif ($provision && $provision->provision_amount !== null && $provision->provision_amount !== '') {
        $amountFieldDefault = number_format((float) $provision->provision_amount, $amountDecimalPlaces, '.', ',');
    }

    $methodOld = old('calculation_method', $provision?->calculation_method ?? 'aging_analysis');
    $searchCustomersUrl = route('admin.accounting.bad-debt.search-customers');
@endphp
<div class="container-fluid" data-role="bad-debt-provision-form-page">
    <div class="card border-primary border-opacity-25">
        <div class="card-header d-flex flex-wrap align-items-start justify-content-between gap-2">
            <div class="flex-grow-1">
                <h5 class="mb-1">
                    {{ $isEdit ? trans('accounting::accounting.bad_debt_form.title_edit') : trans('accounting::accounting.bad_debt_form.title_create') }}
                </h5>
                <small class="text-muted d-block">{{ trans('accounting::accounting.bad_debt_form.subtitle') }}</small>
                @if($isEdit && $provision)
                    <small class="text-muted d-block mt-1">
                        {{ trans('accounting::accounting.bad_debt_form.provision_number_label') }}:
                        <span class="font-monospace">{{ $provision->provision_number }}</span>
                    </small>
                @endif
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <a href="{{ route('admin.accounting.bad-debt.index') }}" class="btn btn-light btn-sm">{{ trans('accounting::accounting.bad_debt_form.back') }}</a>
            </div>
        </div>
        <div class="card-body">
            @if ($errors->has('_form'))
                <div class="alert alert-danger">{{ $errors->first('_form') }}</div>
            @endif

            @if($isEdit && $provision && $provision->accounting_document_id)
                <div class="alert alert-info small mb-3">
                    {{ trans('accounting::accounting.bad_debt_form.edit_amount_locked') }}
                </div>
            @endif

            @if($isEdit && $provision)
                <form method="post" action="{{ route('admin.accounting.bad-debt.update', $provision) }}" class="row g-3" data-bad-debt-form="1">
                    @csrf
                    @method('PUT')
            @else
                <form method="post" action="{{ route('admin.accounting.bad-debt.store') }}" class="row g-3" data-bad-debt-form="1">
                    @csrf
            @endif

                <div class="col-12">
                    <label class="form-label">{{ trans('accounting::accounting.bad_debt_form.customer') }}</label>
                    <select id="bad-debt-customer-select"
                            name="customer_id"
                            class="form-select @error('customer_id') is-invalid @enderror"
                            data-placeholder="{{ trans('accounting::accounting.bad_debt_form.customer_placeholder') }}"
                            data-search-url="{{ e($searchCustomersUrl) }}">
                        <option value=""></option>
                        @if($isEdit && $provision && $provision->customer)
                            <option value="{{ $provision->customer_id }}" selected>{{ $provision->customer->name }}</option>
                        @elseif(old('customer_id'))
                            <option value="{{ old('customer_id') }}" selected>{{ trans('accounting::accounting.bad_debt_form.customer_selected_id', ['id' => old('customer_id')]) }}</option>
                        @endif
                    </select>
                    @error('customer_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    <div class="form-text">{{ trans('accounting::accounting.bad_debt_form.customer_help') }}</div>
                </div>

                <x-accounting::date-field
                    name="provision_date"
                    :label="trans('accounting::accounting.bad_debt_form.provision_date') . ' <span class=\'text-danger\'>*</span>'"
                    :value="$pdProvision"
                    :required="true"
                    col-class="col-md-4"
                    error-key="provision_date"
                />

                <div class="col-md-4">
                    <label class="form-label">{{ trans('accounting::accounting.bad_debt_form.calculation_method') }} <span class="text-danger">*</span></label>
                    <select name="calculation_method" id="bad-debt-calculation-method" class="form-select @error('calculation_method') is-invalid @enderror" required>
                        @foreach(trans('accounting::accounting.bad_debt_form.method_options') as $value => $label)
                            <option value="{{ $value }}" @selected($methodOld === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('calculation_method')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4" id="bad-debt-percentage-wrap">
                    <label class="form-label">{{ trans('accounting::accounting.bad_debt_form.percentage_used') }}</label>
                    <input type="number" step="0.01" min="0" max="100" name="percentage_used"
                           class="form-control @error('percentage_used') is-invalid @enderror"
                           value="{{ old('percentage_used', $provision?->percentage_used) }}"
                           placeholder="{{ trans('accounting::accounting.bad_debt_form.percentage_placeholder') }}">
                    @error('percentage_used')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    <div class="form-text">{{ trans('accounting::accounting.bad_debt_form.percentage_help') }}</div>
                </div>

                <div class="col-md-4">
                    <label class="form-label">{{ trans('accounting::accounting.bad_debt_form.provision_amount', ['currency' => $defaultCurrency]) }} <span class="text-danger">{{ $isEdit ? '' : '*' }}</span></label>
                    @if($isEdit)
                        <input type="text" class="form-control" value="{{ $amountFieldDefault }}" readonly disabled>
                        <div class="form-text text-muted">{{ trans('accounting::accounting.bad_debt_form.amount_decimals_hint', ['n' => $amountDecimalPlaces]) }}</div>
                    @else
                        <input type="text" name="provision_amount" class="form-control amount-decimal @error('provision_amount') is-invalid @enderror"
                               data-type="amount-decimal" data-decimals="{{ $amountDecimalPlaces }}" value="{{ old('provision_amount', $amountFieldDefault) }}"
                               inputmode="decimal" autocomplete="off" required>
                        @error('provision_amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <div class="form-text">{{ trans('accounting::accounting.bad_debt_form.amount_decimals_hint', ['n' => $amountDecimalPlaces]) }}</div>
                    @endif
                </div>

                <div class="col-12">
                    <label class="form-label">{{ trans('accounting::accounting.bad_debt_form.notes') }}</label>
                    <textarea name="notes" rows="3" class="form-control @error('notes') is-invalid @enderror" placeholder="{{ trans('accounting::accounting.bad_debt_form.notes_placeholder') }}">{{ old('notes', $provision?->notes) }}</textarea>
                    @error('notes')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 d-flex gap-2 pt-2">
                    <button type="submit" class="btn btn-primary">{{ trans('accounting::accounting.bad_debt_form.save') }}</button>
                    <a href="{{ route('admin.accounting.bad-debt.index') }}" class="btn btn-light">{{ trans('accounting::accounting.bad_debt_form.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>

    @include('accounting::components.collapse_help_card', [
        'collapseId' => 'accounting-bad-debt-form-help',
        'toggleLabel' => trans('accounting::accounting.bad_debt_form.help_toggle'),
        'title' => trans('accounting::accounting.bad_debt_form.help_title'),
        'body_html' => trans('accounting::accounting.bad_debt_form.help_body_html', ['currency' => $defaultCurrency]),
        'cardClass' => 'mt-3',
    ])
</div>
<script>
(function () {
    var root = document.querySelector('[data-role="bad-debt-provision-form-page"]');
    var form = root ? root.querySelector('form[data-bad-debt-form="1"]') : null;
    if (!form) return;

    var methodEl = form.querySelector('#bad-debt-calculation-method');
    var pctWrap = document.getElementById('bad-debt-percentage-wrap');
    var customerEl = document.getElementById('bad-debt-customer-select');
    var searchUrl = customerEl ? customerEl.getAttribute('data-search-url') : '';

    function syncPercentageVisibility() {
        if (!methodEl || !pctWrap) return;
        var show = methodEl.value === 'percentage_sales';
        pctWrap.classList.toggle('d-none', !show);
        var input = pctWrap.querySelector('input[name="percentage_used"]');
        if (input) {
            input.required = show;
            input.disabled = !show;
        }
    }

    if (methodEl) methodEl.addEventListener('change', syncPercentageVisibility);
    syncPercentageVisibility();

    function initCustomerSelect2() {
        if (!customerEl || !searchUrl) return;
        var $ = window.jQuery;
        if (!$ || !$.fn || !$.fn.select2) return;
        $(customerEl).select2({
            dir: 'rtl',
            width: '100%',
            allowClear: true,
            placeholder: customerEl.getAttribute('data-placeholder') || '',
            minimumInputLength: 1,
            language: {
                inputTooShort: function () {
                    return @json(trans('accounting::accounting.bad_debt_form.select2_input_too_short'));
                },
                searching: function () {
                    return @json(trans('accounting::accounting.bad_debt_form.select2_searching'));
                },
                noResults: function () {
                    return @json(trans('accounting::accounting.bad_debt_form.select2_no_results'));
                }
            },
            ajax: {
                url: searchUrl,
                dataType: 'json',
                delay: 280,
                data: function (params) {
                    return { q: params.term || '' };
                },
                processResults: function (data) {
                    return { results: (data && data.results) ? data.results : [] };
                },
                cache: true
            }
        });
    }

    if (typeof window.jQuery !== 'undefined') {
        window.jQuery(initCustomerSelect2);
    } else {
        document.addEventListener('DOMContentLoaded', function () {
            var tries = 0;
            (function waitSelect2() {
                tries++;
                if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
                    initCustomerSelect2();
                } else if (tries < 40) {
                    setTimeout(waitSelect2, 50);
                }
            })();
        });
    }
})();
</script>
@include('accounting::admin.partials.accounting-date-ui-script')
@endsection
