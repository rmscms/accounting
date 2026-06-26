@extends('cms::admin.layout.index')
@section('title', trans('accounting::accounting.capital.create'))
@section('content')
@php
    $journalDispCapital = old('journal_date');
    $journalDispCapital = ($journalDispCapital !== null && trim((string) $journalDispCapital) !== '')
        ? trim(\RMS\Helper\changeNumberToEn((string) $journalDispCapital))
        : \RMS\Accounting\Support\AccountingDateUi::gregorianYmdToInputDisplay(now()->format('Y-m-d'));
    $baseCurrency = (string) ($baseCurrencyCode ?? 'IRR');
    $amountDecimals = (int) ($amountDecimalPlaces ?? 0);
@endphp
<div class="container-fluid">
    <h4 class="mb-3">{{ trans('accounting::accounting.capital.create') }}</h4>
    @include('accounting::components.collapse_help_card', [
        'toggleLabel' => trans('accounting::accounting.capital.title'),
        'paragraphs' => [trans('accounting::accounting.capital.form_help_body')],
    ])
    <div class="card">
        <div class="card-body">
            <form method="post" action="{{ route('admin.accounting.shareholder-capital-contributions.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">{{ trans('accounting::accounting.capital.shareholder') }} <span class="text-danger">*</span></label>
                        <select name="shareholder_id" class="form-select" required>
                            <option value="">—</option>
                            @foreach($shareholders as $sh)
                                <option value="{{ $sh->id }}" @selected(old('shareholder_id') == $sh->id)>{{ $sh->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ trans('accounting::accounting.capital.amount') }} <span class="text-danger">*</span></label>
                        <input type="text" name="amount" class="form-control amount-decimal @error('amount') is-invalid @enderror"
                               data-type="amount-decimal" data-decimals="{{ $amountDecimals }}" value="{{ old('amount') }}"
                               inputmode="decimal" autocomplete="off" required>
                        <div class="form-text">{{ trans('accounting::accounting.withdrawals.amount_hint', ['currency' => $baseCurrency]) }}</div>
                        @error('amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <x-accounting::date-field
                        name="journal_date"
                        :label="trans('accounting::accounting.capital.journal_date') . ' <span class=\'text-danger\'>*</span>'"
                        :value="$journalDispCapital"
                        :required="true"
                        col-class="col-md-6"
                        error-key="journal_date"
                    />
                    <div class="col-md-6">
                        <label class="form-label">{{ trans('accounting::accounting.capital.source_type') }} <span class="text-danger">*</span></label>
                        <select name="source_type" id="source_type" class="form-select" required>
                            <option value="bank" @selected(old('source_type') === 'bank')>{{ trans('accounting::accounting.capital.bank') }}</option>
                            <option value="cash" @selected(old('source_type') === 'cash')>{{ trans('accounting::accounting.capital.cash_box') }}</option>
                        </select>
                    </div>
                    <div class="col-md-6" id="wrap-bank">
                        <label class="form-label">{{ trans('accounting::accounting.capital.bank') }}</label>
                        <select name="bank_id" class="form-select">
                            <option value="">—</option>
                            @foreach($banks as $b)
                                <option value="{{ $b->id }}" @selected(old('bank_id') == $b->id)>{{ $b->label_for_select }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6" id="wrap-cash" style="display:none;">
                        <label class="form-label">{{ trans('accounting::accounting.capital.cash_box') }}</label>
                        <select name="cash_box_id" class="form-select">
                            <option value="">—</option>
                            @foreach($cashBoxes as $c)
                                <option value="{{ $c->id }}" @selected(old('cash_box_id') == $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">{{ trans('accounting::accounting.capital.description') }}</label>
                        <textarea name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">{{ trans('accounting::accounting.capital.submit') }}</button>
                    <a href="{{ route('admin.accounting.shareholder-capital-contributions.index') }}" class="btn btn-light">{{ trans('accounting::accounting.shareholders.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const sel = document.getElementById('source_type');
    const wb = document.getElementById('wrap-bank');
    const wc = document.getElementById('wrap-cash');
    function sync() {
        if (sel.value === 'cash') { wb.style.display = 'none'; wc.style.display = ''; }
        else { wb.style.display = ''; wc.style.display = 'none'; }
    }
    sel.addEventListener('change', sync);
    sync();
});
</script>
@include('accounting::admin.partials.accounting-date-ui-script')
@endsection
