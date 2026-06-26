@extends('cms::admin.layout.index')
@section('title', trans('accounting::accounting.employee_loans.create_title'))
@section('content')
@php
    $today = \RMS\Accounting\Support\AccountingDateUi::gregorianYmdToInputDisplay(now()->format('Y-m-d'));
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">{{ trans('accounting::accounting.employee_loans.create_title') }}</h4>
        <a href="{{ route('admin.accounting.employee-loans.index') }}" class="btn btn-light">{{ trans('accounting::accounting.common.back') }}</a>
    </div>

    <form method="post" action="{{ route('admin.accounting.employee-loans.store') }}" data-decimal-places="{{ (int) ($decimalPlaces ?? 0) }}">
        @csrf
        <div class="card">
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label">{{ trans('accounting::accounting.employee_loans.fields.employee') }}</label>
                    <select name="employee_id" class="form-select" required>
                        <option value="">--</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" @selected((int) old('employee_id') === (int) $employee->id)>{{ $employee->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ trans('accounting::accounting.employee_loans.fields.disbursement_bank') }}</label>
                    <select name="disbursement_bank_id" class="form-select" required>
                        <option value="">--</option>
                        @foreach($banks as $bank)
                            <option value="{{ $bank->id }}" @selected((int) old('disbursement_bank_id') === (int) $bank->id)>{{ $bank->label_for_select }}</option>
                        @endforeach
                    </select>
                </div>
                <x-accounting::date-field name="disbursement_date" :label="trans('accounting::accounting.employee_loans.fields.disbursement_date')" :value="old('disbursement_date', $today)" :required="true" col-class="col-md-2" />
                <x-accounting::date-field name="first_due_date" :label="trans('accounting::accounting.employee_loans.fields.first_due_date')" :value="old('first_due_date', $today)" :required="true" col-class="col-md-2" />

                <div class="col-md-4">
                    <label class="form-label">{{ trans('accounting::accounting.employee_loans.fields.principal_amount') }}</label>
                    <input type="text" inputmode="decimal" name="principal_amount" class="form-control js-accounting-amount-input js-loan-principal" value="{{ old('principal_amount') }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ trans('accounting::accounting.employee_loans.fields.annual_interest_rate') }}</label>
                    <input type="number" step="0.0001" min="0" max="100" name="annual_interest_rate" class="form-control js-loan-rate" value="{{ old('annual_interest_rate', 0) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ trans('accounting::accounting.employee_loans.fields.installments_count') }}</label>
                    <input type="number" min="1" max="240" name="installments_count" class="form-control js-loan-months" value="{{ old('installments_count', 12) }}" required>
                </div>

                <div class="col-12">
                    <div
                        class="row g-3"
                        data-loan-calc-cards
                        data-card-monthly-label="{{ trans('accounting::accounting.employee_loans.form.monthly_installment') }}"
                        data-card-interest-label="{{ trans('accounting::accounting.employee_loans.form.total_interest') }}"
                        data-card-total-label="{{ trans('accounting::accounting.employee_loans.form.total_repayment') }}"
                    >
                        <div class="col-md-4">
                            <div class="card border-primary bg-primary bg-opacity-10 h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <div class="text-muted small">{{ trans('accounting::accounting.employee_loans.form.monthly_installment') }}</div>
                                        <i class="ph-calendar-check text-primary"></i>
                                    </div>
                                    <div class="fs-5 fw-semibold js-loan-monthly-card">0</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-info bg-info bg-opacity-10 h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <div class="text-muted small">{{ trans('accounting::accounting.employee_loans.form.total_interest') }}</div>
                                        <i class="ph-percent text-info"></i>
                                    </div>
                                    <div class="fs-5 fw-semibold js-loan-interest-card">0</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-success bg-success bg-opacity-10 h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <div class="text-muted small">{{ trans('accounting::accounting.employee_loans.form.total_repayment') }}</div>
                                        <i class="ph-wallet text-success"></i>
                                    </div>
                                    <div class="fs-5 fw-semibold js-loan-total-card">0</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card border-secondary">
                        <div class="card-header py-2">
                            <strong class="small">{{ trans('accounting::accounting.employee_loans.form.preview_title') }}</strong>
                        </div>
                        <div
                            class="card-body p-0"
                            data-loan-preview
                            data-preview-empty="{{ trans('accounting::accounting.employee_loans.form.preview_empty') }}"
                        >
                            <div class="p-3 text-muted small js-loan-preview-empty">
                                {{ trans('accounting::accounting.employee_loans.form.preview_empty') }}
                            </div>
                            <div class="table-responsive d-none js-loan-preview-table-wrap">
                                <table class="table table-sm table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>{{ trans('accounting::accounting.employee_loans.form.preview_installment') }}</th>
                                            <th>{{ trans('accounting::accounting.employee_loans.form.preview_monthly_amount') }}</th>
                                            <th>{{ trans('accounting::accounting.employee_loans.form.preview_principal') }}</th>
                                            <th>{{ trans('accounting::accounting.employee_loans.form.preview_interest') }}</th>
                                            <th>{{ trans('accounting::accounting.employee_loans.form.preview_remaining') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="js-loan-preview-body"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-12">
                    <label class="form-label">{{ trans('accounting::accounting.employee_loans.fields.description') }}</label>
                    <input type="text" name="description" class="form-control" value="{{ old('description') }}">
                </div>
                <div class="col-md-12">
                    <label class="form-label">{{ trans('accounting::accounting.employee_loans.fields.notes') }}</label>
                    <textarea name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
                </div>
            </div>
            <div class="card-footer text-end">
                <button type="submit" class="btn btn-primary">{{ trans('accounting::accounting.employee_loans.actions.save') }}</button>
            </div>
        </div>
    </form>
</div>
@include('accounting::admin.partials.accounting-date-ui-script')
@endsection
