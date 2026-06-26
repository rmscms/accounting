@extends('cms::admin.layout.index')
@section('title', trans('accounting::accounting.employee_loans.print_title'))
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 d-print-none">
        <h4 class="mb-0">{{ trans('accounting::accounting.employee_loans.print_title') }}</h4>
        <button type="button" class="btn btn-dark" onclick="window.print()">{{ trans('accounting::accounting.payroll_runs.actions.print') }}</button>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-3"><strong>{{ trans('accounting::accounting.employee_loans.fields.loan_number') }}:</strong> {{ $loan->loan_number }}</div>
                <div class="col-md-3"><strong>{{ trans('accounting::accounting.employee_loans.fields.employee') }}:</strong> {{ $loan->employee?->name }}</div>
                <div class="col-md-3"><strong>{{ trans('accounting::accounting.employee_loans.fields.principal_amount') }}:</strong> {{ number_format((float) $loan->principal_amount, 0) }}</div>
                <div class="col-md-3"><strong>{{ trans('accounting::accounting.employee_loans.fields.annual_interest_rate') }}:</strong> {{ number_format((float) $loan->annual_interest_rate, 2) }}%</div>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-sm">
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
    </div>
</div>
@endsection
