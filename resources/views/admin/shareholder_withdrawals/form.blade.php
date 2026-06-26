@extends('cms::admin.layout.index')
@section('title', trans('accounting::accounting.withdrawals.create'))
@section('content')
@php
    $journalDispWithdrawal = old('journal_date');
    $journalDispWithdrawal = ($journalDispWithdrawal !== null && trim((string) $journalDispWithdrawal) !== '')
        ? trim(\RMS\Helper\changeNumberToEn((string) $journalDispWithdrawal))
        : \RMS\Accounting\Support\AccountingDateUi::gregorianYmdToInputDisplay(now()->format('Y-m-d'));
    $baseCurrency = (string) ($baseCurrencyCode ?? 'IRR');
    $amountDecimals = (int) ($amountDecimalPlaces ?? 0);
@endphp
<div class="container-fluid">
    <h4 class="mb-3">{{ trans('accounting::accounting.withdrawals.create') }}</h4>

    @include('accounting::components.collapse_help_card', [
        'toggleLabel' => trans('accounting::accounting.withdrawals.title'),
        'title' => null,
        'paragraphs' => [
            trans('accounting::accounting.withdrawals.shareholder').' — '.trans('accounting::accounting.withdrawals.amount').' — '.trans('accounting::accounting.withdrawals.source_type'),
        ],
        'body_html' => '<p class="mb-0 small text-muted">'.e(trans('accounting::accounting.payroll_insurance.chart_note')).'</p>',
    ])

    @if($banks->isEmpty() && $cashBoxes->isEmpty())
        <div class="alert alert-warning mb-3">
            <div class="fw-semibold mb-1">{{ trans('accounting::accounting.withdrawals.no_channels_title') }}</div>
            <div class="small mb-2">{{ trans('accounting::accounting.withdrawals.no_channels_body') }}</div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('admin.accounting.banks.create') }}" class="btn btn-sm btn-outline-primary">{{ trans('accounting::accounting.withdrawals.link_create_bank') }}</a>
                <a href="{{ route('admin.accounting.cashboxes.create') }}" class="btn btn-sm btn-outline-secondary">{{ trans('accounting::accounting.withdrawals.link_create_cashbox') }}</a>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="post" action="{{ route('admin.accounting.shareholder-withdrawals.store') }}" id="withdrawal-form" data-base-currency="{{ $baseCurrency }}" data-decimal-places="{{ $amountDecimals }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">{{ trans('accounting::accounting.withdrawals.shareholder') }} <span class="text-danger">*</span></label>
                        <select name="shareholder_id" class="form-select" required>
                            <option value="">—</option>
                            @foreach($shareholders as $sh)
                                <option value="{{ $sh->id }}" @selected(old('shareholder_id') == $sh->id)>{{ $sh->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ trans('accounting::accounting.withdrawals.amount') }} <span class="text-danger">*</span></label>
                        <input type="text" inputmode="decimal" name="amount" class="form-control js-accounting-amount-input" value="{{ old('amount') }}" required>
                        <div class="form-text">{{ trans('accounting::accounting.withdrawals.amount_hint', ['currency' => $baseCurrency]) }}</div>
                    </div>
                    <x-accounting::date-field
                        name="journal_date"
                        :label="trans('accounting::accounting.withdrawals.journal_date') . ' <span class=\'text-danger\'>*</span>'"
                        :value="$journalDispWithdrawal"
                        :required="true"
                        col-class="col-md-6"
                        error-key="journal_date"
                    />
                    <div class="col-md-6">
                        <label class="form-label">{{ trans('accounting::accounting.withdrawals.source_type') }} <span class="text-danger">*</span></label>
                        <select name="source_type" id="source_type" class="form-select" required>
                            <option value="bank" @selected(old('source_type') === 'bank')>{{ trans('accounting::accounting.withdrawals.source_bank') }}</option>
                            <option value="cash" @selected(old('source_type') === 'cash')>{{ trans('accounting::accounting.withdrawals.source_cash') }}</option>
                        </select>
                    </div>
                    <div class="col-md-6" id="wrap-bank">
                        <label class="form-label">{{ trans('accounting::accounting.withdrawals.bank') }}</label>
                        <select name="bank_id" class="form-select">
                            <option value="">—</option>
                            @foreach($banks as $b)
                                <option value="{{ $b->id }}" @selected(old('bank_id') == $b->id)>{{ $b->label_for_select }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6" id="wrap-cash" style="display:none;">
                        <label class="form-label">{{ trans('accounting::accounting.withdrawals.cash_box') }}</label>
                        <select name="cash_box_id" class="form-select">
                            <option value="">—</option>
                            @foreach($cashBoxes as $c)
                                <option value="{{ $c->id }}" @selected(old('cash_box_id') == $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">{{ trans('accounting::accounting.withdrawals.description') }}</label>
                        <textarea name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
                    </div>
                </div>
                <div class="mt-3 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary" name="submit_mode" value="post">{{ trans('accounting::accounting.withdrawals.submit') }}</button>
                    <button type="submit" class="btn btn-outline-secondary" name="submit_mode" value="draft">{{ trans('accounting::accounting.withdrawals.save_draft') }}</button>
                    <a href="{{ route('admin.accounting.shareholder-withdrawals.index') }}" class="btn btn-light">{{ trans('accounting::accounting.shareholders.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</div>
@include('accounting::admin.partials.accounting-date-ui-script')
@endsection
