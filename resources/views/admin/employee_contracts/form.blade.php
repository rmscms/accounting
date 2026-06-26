@extends('cms::admin.layout.index')
@section('title', $mode === 'create' ? trans('accounting::accounting.employee_contracts.create_title') : trans('accounting::accounting.employee_contracts.edit_title'))
@section('content')
@php
    $effectiveFrom = old('effective_from', $contract->effective_from ? $contract->effective_from->format('Y-m-d') : now()->format('Y-m-d'));
    $effectiveTo = old('effective_to', $contract->effective_to ? $contract->effective_to->format('Y-m-d') : '');
    $signedAt = old('signed_at', $contract->signed_at ? $contract->signed_at->format('Y-m-d') : '');
    $status = old('status', $contract->status ?: \RMS\Accounting\Models\EmployeeContract::STATUS_DRAFT);
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">{{ $mode === 'create' ? trans('accounting::accounting.employee_contracts.create_title') : trans('accounting::accounting.employee_contracts.edit_title') }}</h4>
        <a href="{{ route('admin.accounting.employee-contracts.index') }}" class="btn btn-light">{{ trans('accounting::accounting.common.back') }}</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="post" action="{{ $mode === 'create' ? route('admin.accounting.employee-contracts.store') : route('admin.accounting.employee-contracts.update', $contract->id) }}">
                @csrf
                @if($mode === 'edit')
                    @method('PUT')
                @endif
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">{{ trans('accounting::accounting.employee_contracts.fields.employee') }}</label>
                        <select name="employee_id" class="form-select" required>
                            <option value="">--</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}" @selected((int) old('employee_id', $contract->employee_id) === (int) $employee->id)>
                                    {{ $employee->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ trans('accounting::accounting.employee_contracts.fields.contract_number') }}</label>
                        <input type="text" name="contract_number" class="form-control" value="{{ old('contract_number', $contract->contract_number) }}" placeholder="{{ trans('accounting::accounting.employee_contracts.fields.auto_number_hint') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ trans('accounting::accounting.employee_contracts.fields.status') }}</label>
                        <select name="status" class="form-select" required>
                            @foreach(\RMS\Accounting\Models\EmployeeContract::statuses() as $rowStatus)
                                <option value="{{ $rowStatus }}" @selected($status === $rowStatus)>
                                    {{ trans('accounting::accounting.employee_contracts.statuses.'.$rowStatus) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <x-accounting::date-field name="effective_from" :label="trans('accounting::accounting.employee_contracts.fields.effective_from')" :value="$effectiveFrom" :required="true" col-class="col-md-4" />
                    <x-accounting::date-field name="effective_to" :label="trans('accounting::accounting.employee_contracts.fields.effective_to')" :value="$effectiveTo" col-class="col-md-4" />
                    <x-accounting::date-field name="signed_at" :label="trans('accounting::accounting.employee_contracts.fields.signed_at')" :value="$signedAt" col-class="col-md-4" />

                    <div class="col-md-4">
                        <label class="form-label">{{ trans('accounting::accounting.employee_contracts.fields.base_salary') }}</label>
                        <input type="text"
                               inputmode="decimal"
                               name="base_salary"
                               class="form-control js-accounting-amount-input amount-decimal"
                               data-type="amount-decimal"
                               data-decimals="0"
                               value="{{ old('base_salary', $contract->base_salary) }}"
                               placeholder="15,000,000"
                               autocomplete="off"
                               required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ trans('accounting::accounting.employee_contracts.fields.seniority_monthly_default') }}</label>
                        <input type="text"
                               inputmode="decimal"
                               name="seniority_monthly_default"
                               class="form-control js-accounting-amount-input amount-decimal"
                               data-type="amount-decimal"
                               data-decimals="0"
                               value="{{ old('seniority_monthly_default', $contract->seniority_monthly_default ?? 0) }}"
                               placeholder="0"
                               autocomplete="off">
                        <div class="form-text">{{ trans('accounting::accounting.employee_contracts.fields.seniority_monthly_default_hint') }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ trans('accounting::accounting.employee_contracts.fields.salary_cycle') }}</label>
                        <select name="salary_cycle" class="form-select">
                            <option value="monthly" @selected(old('salary_cycle', $contract->salary_cycle ?: 'monthly') === 'monthly')>{{ trans('accounting::accounting.employee_contracts.salary_cycles.monthly') }}</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ trans('accounting::accounting.employee_contracts.fields.tax_rate') }}</label>
                        <input type="number" name="tax_rate" min="0" max="100" step="0.0001" class="form-control" value="{{ old('tax_rate', $contract->tax_rate) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ trans('accounting::accounting.employee_contracts.fields.employee_insurance_rate') }}</label>
                        <input type="number" name="employee_insurance_rate" min="0" max="100" step="0.0001" class="form-control" value="{{ old('employee_insurance_rate', $contract->employee_insurance_rate) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ trans('accounting::accounting.employee_contracts.fields.employer_insurance_rate') }}</label>
                        <input type="number" name="employer_insurance_rate" min="0" max="100" step="0.0001" class="form-control" value="{{ old('employer_insurance_rate', $contract->employer_insurance_rate) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">{{ trans('accounting::accounting.employee_contracts.fields.notes') }}</label>
                        <textarea name="notes" class="form-control" rows="3">{{ old('notes', $contract->notes) }}</textarea>
                    </div>
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">{{ trans('accounting::accounting.employee_contracts.actions.save') }}</button>
                    <a href="{{ route('admin.accounting.employee-contracts.index') }}" class="btn btn-light">{{ trans('accounting::accounting.common.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@include('accounting::admin.partials.accounting-date-ui-script')
