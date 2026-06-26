@extends('cms::admin.layout.index')
@section('title', trans('accounting::accounting.employee_loans.show_title'))
@section('content')
@php
    $today = \RMS\Accounting\Support\AccountingDateUi::gregorianYmdToInputDisplay(now()->format('Y-m-d'));
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">{{ $loan->loan_number }} - {{ $loan->employee?->name }}</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.accounting.employee-loans.installments-print', $loan->id) }}" target="_blank" class="btn btn-dark">{{ trans('accounting::accounting.employee_loans.actions.print_installments') }}</a>
            @if($loan->status !== 'cancelled' && $loan->status !== 'closed' && $loan->payments->isEmpty())
                <a href="{{ route('admin.accounting.employee-loans.edit', $loan->id) }}" class="btn btn-warning text-dark">{{ trans('accounting::accounting.employee_loans.actions.edit') }}</a>
            @endif
            <a href="{{ route('admin.accounting.employee-loans.index') }}" class="btn btn-light">{{ trans('accounting::accounting.common.back') }}</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted d-block">{{ trans('accounting::accounting.employee_loans.fields.principal_amount') }}</small><strong>{{ number_format((float) $loan->principal_amount, 0) }}</strong></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted d-block">{{ trans('accounting::accounting.employee_loans.fields.total_interest_amount') }}</small><strong>{{ number_format((float) $loan->total_interest_amount, 0) }}</strong></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted d-block">{{ trans('accounting::accounting.employee_loans.fields.remaining_total') }}</small><strong>{{ number_format((float) $loan->remaining_total, 0) }}</strong></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted d-block">{{ trans('accounting::accounting.employee_loans.fields.status') }}</small><strong>{{ trans('accounting::accounting.employee_loans.statuses.'.$loan->status) }}</strong></div></div></div>
    </div>

    <div class="card mb-3">
        <div class="card-header">{{ trans('accounting::accounting.employee_loans.installments_title') }}</div>
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ trans('accounting::accounting.employee_loans.fields.due_date') }}</th>
                        <th>{{ trans('accounting::accounting.employee_loans.fields.principal_part') }}</th>
                        <th>{{ trans('accounting::accounting.employee_loans.fields.interest_part') }}</th>
                        <th>{{ trans('accounting::accounting.employee_loans.fields.installment_amount') }}</th>
                        <th>{{ trans('accounting::accounting.employee_loans.fields.remaining_amount') }}</th>
                        <th>{{ trans('accounting::accounting.employee_loans.fields.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($loan->installments as $installment)
                        <tr>
                            <td>{{ $installment->installment_number }}</td>
                            <td>{{ $installment->due_date?->format('Y-m-d') }}</td>
                            <td>{{ number_format((float) $installment->principal_amount, 0) }}</td>
                            <td>{{ number_format((float) $installment->interest_amount, 0) }}</td>
                            <td>{{ number_format((float) $installment->installment_amount, 0) }}</td>
                            <td>{{ number_format((float) $installment->remaining_amount, 0) }}</td>
                            <td>{{ trans('accounting::accounting.employee_loans.installment_statuses.'.$installment->status) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">{{ trans('accounting::accounting.employee_loans.actions.post_manual_payment') }}</div>
        <div class="card-body">
            <form method="post" action="{{ route('admin.accounting.employee-loans.manual-payment', $loan->id) }}" class="row g-3">
                @csrf
                <x-accounting::date-field name="payment_date" :label="trans('accounting::accounting.employee_loans.fields.payment_date')" :value="$today" :required="true" col-class="col-md-3" />
                <div class="col-md-3">
                    <label class="form-label">{{ trans('accounting::accounting.payroll_runs.fields.bank') }}</label>
                    <select class="form-select" name="bank_id" required>
                        <option value="">--</option>
                        @foreach($banks as $bank)
                            <option value="{{ $bank->id }}">{{ $bank->label_for_select }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ trans('accounting::accounting.employee_loans.fields.payment_amount') }}</label>
                    <input type="text" inputmode="decimal" name="amount" class="form-control js-accounting-amount-input" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ trans('accounting::accounting.employee_loans.fields.description') }}</label>
                    <input type="text" name="description" class="form-control">
                </div>
                <div class="col-md-12 text-end">
                    <button class="btn btn-success" type="submit">{{ trans('accounting::accounting.payroll_runs.actions.post') }}</button>
                </div>
            </form>
        </div>
    </div>
    @if($loan->status !== 'cancelled' && $loan->status !== 'closed' && $loan->payments->isEmpty())
    <div class="card border-danger">
        <div class="card-header bg-danger text-white">{{ trans('accounting::accounting.employee_loans.actions.cancel') }}</div>
        <div class="card-body">
            <form method="post" action="{{ route('admin.accounting.employee-loans.cancel', $loan->id) }}" class="row g-3">
                @csrf
                <div class="col-md-10">
                    <label class="form-label">{{ trans('accounting::accounting.employee_loans.fields.cancel_reason') }}</label>
                    <input type="text" name="reason" class="form-control" required>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-danger w-100">{{ trans('accounting::accounting.employee_loans.actions.cancel') }}</button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
@include('accounting::admin.partials.accounting-date-ui-script')
@endsection
