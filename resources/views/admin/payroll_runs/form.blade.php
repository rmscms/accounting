@extends('cms::admin.layout.index')
@section('title', trans('accounting::accounting.payroll_runs.form_title_'.$mode))
@section('content')
@php
    $periodStart = old('period_start', $run->period_start ? $run->period_start->format('Y-m-d') : now()->startOfMonth()->format('Y-m-d'));
    $periodEnd = old('period_end', $run->period_end ? $run->period_end->format('Y-m-d') : now()->endOfMonth()->format('Y-m-d'));
    $journalDate = old('journal_date', $run->journal_date ? $run->journal_date->format('Y-m-d') : now()->format('Y-m-d'));
    $defaultPolicy = [
        'employee_insurance_rate' => old('policy.employee_insurance_rate', 7),
        'employer_insurance_rate' => old('policy.employer_insurance_rate', 23),
        'tax_rate' => old('policy.tax_rate', 0),
    ];
    $lines = old(
        'lines',
        $run->relationLoaded('lines')
            ? $run->lines->map(static function ($line) {
                return array_merge(
                    $line->toArray(),
                    [
                        'employee_insurance_manual' => false,
                        'employer_insurance_manual' => false,
                        'tax_manual' => false,
                        'items' => method_exists($line, 'items') && $line->relationLoaded('items') ? $line->items->toArray() : [],
                    ]
                );
            })->toArray()
            : []
    );
    if (empty($lines)) {
        $lines = [[
            'employee_id' => '',
            'base_salary' => '',
            'benefits' => '',
            'seniority' => '',
            'employee_insurance' => '',
            'employer_insurance' => '',
            'tax' => '',
            'other_deductions' => '',
            'employee_insurance_manual' => false,
            'employer_insurance_manual' => false,
            'tax_manual' => false,
            'skip_loan_deduction' => false,
            'items' => [],
            'description' => '',
        ]];
    }
    $loanPreviewByEmployee = (array) ($loanPreviewByEmployee ?? []);
    $effectivePeriodEnd = (string) ($effectivePeriodEnd ?? $periodEnd);
    $minimumWageValue = (float) ($payrollMinimumWage ?? 0);
    $minimumWageFormatted = number_format($minimumWageValue, 0);
    $wageGuardConfig = [
        'minimum_wage' => $minimumWageValue,
        'warning_title' => trans('accounting::accounting.payroll_runs.actions.minimum_wage_override_title'),
        'warning_message' => trans('accounting::accounting.payroll_runs.actions.minimum_wage_override_message', ['minimum_wage' => $minimumWageFormatted]),
        'warning_confirm' => trans('accounting::accounting.payroll_runs.actions.minimum_wage_override_confirm'),
    ];
    $netGuardConfig = [
        'zero_warning_title' => trans('accounting::accounting.payroll_runs.actions.zero_net_override_title'),
        'zero_warning_message' => trans('accounting::accounting.payroll_runs.actions.zero_net_override_message'),
        'zero_warning_confirm' => trans('accounting::accounting.payroll_runs.actions.zero_net_override_confirm'),
        'negative_block_title' => trans('accounting::accounting.payroll_runs.actions.negative_net_block_title'),
        'negative_block_message' => trans('accounting::accounting.payroll_runs.actions.negative_net_block_message'),
    ];
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">{{ trans('accounting::accounting.payroll_runs.form_title_'.$mode) }}</h4>
        <a href="{{ route('admin.accounting.payroll-runs.index') }}" class="btn btn-light">
            {{ trans('accounting::accounting.common.back') }}
        </a>
    </div>

    <form method="post" action="{{ $mode === 'create' ? route('admin.accounting.payroll-runs.store') : route('admin.accounting.payroll-runs.update', $run->id) }}">
        @csrf
        @if($mode === 'edit')
            @method('PUT')
        @endif
        <input type="hidden" name="confirm_min_wage_override" value="{{ old('confirm_min_wage_override', '0') }}" data-pr-min-wage-override>
        <input type="hidden" name="confirm_zero_net_override" value="{{ old('confirm_zero_net_override', '0') }}" data-pr-zero-net-override>

        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">{{ trans('accounting::accounting.payroll_runs.fields.title') }}</h6>
            </div>
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label">{{ trans('accounting::accounting.payroll_runs.fields.title') }}</label>
                    <input type="text" name="title" class="form-control" value="{{ old('title', $run->title) }}" required>
                </div>
                <x-accounting::date-field name="period_start" :label="trans('accounting::accounting.payroll_runs.fields.period_start')" :value="$periodStart" :required="true" col-class="col-md-3" />
                <x-accounting::date-field name="period_end" :label="trans('accounting::accounting.payroll_runs.fields.period_end')" :value="$periodEnd" :required="true" col-class="col-md-3" />
                <x-accounting::date-field name="journal_date" :label="trans('accounting::accounting.payroll_runs.fields.journal_date')" :value="$journalDate" :required="true" col-class="col-md-2" />
                <div class="col-md-4">
                    <label class="form-label">{{ trans('accounting::accounting.payroll_runs.policy.employee_insurance_rate') }}</label>
                    <input type="number" name="policy[employee_insurance_rate]" min="0" max="100" step="0.0001" class="form-control" value="{{ $defaultPolicy['employee_insurance_rate'] }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ trans('accounting::accounting.payroll_runs.policy.employer_insurance_rate') }}</label>
                    <input type="number" name="policy[employer_insurance_rate]" min="0" max="100" step="0.0001" class="form-control" value="{{ $defaultPolicy['employer_insurance_rate'] }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ trans('accounting::accounting.payroll_runs.policy.tax_rate') }}</label>
                    <input type="number" name="policy[tax_rate]" min="0" max="100" step="0.0001" class="form-control" value="{{ $defaultPolicy['tax_rate'] }}">
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>{{ trans('accounting::accounting.payroll_runs.lines.title') }}</span>
                <button type="button" id="add-line-btn" class="btn btn-sm btn-outline-primary">{{ trans('accounting::accounting.payroll_runs.lines.add') }}</button>
            </div>
            <div class="card-body">
                <div class="alert alert-info py-2 mb-3">
                    <small>{{ trans('accounting::accounting.employee_loans.form.payroll_auto_deduction_hint') }}</small>
                </div>
                <div class="alert alert-warning py-2 mb-3">
                    <small>{{ trans('accounting::accounting.payroll_runs.fields.seniority_non_insurable_hint') }}</small>
                </div>
                @if(!empty($attendanceFeatureEnabled))
                    <div class="alert alert-primary py-2 mb-3">
                        <small>{{ trans('accounting::accounting.attendance.payroll_proration_hint') }}</small>
                    </div>
                @endif
                <div class="alert alert-light py-2 mb-3">
                    <small>{{ trans('accounting::accounting.payroll_runs.fields.loan_preview_hint', ['date' => ($effectivePeriodEndDisplay ?? $effectivePeriodEnd)]) }}</small>
                </div>
                <div id="payroll-lines-wrapper">
                    @foreach($lines as $i => $line)
                        <div class="border rounded p-3 mb-3 payroll-line-row">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">{{ trans('accounting::accounting.payroll_runs.fields.employee') }}</label>
                                    <select class="form-select js-pr-employee" name="lines[{{ $i }}][employee_id]" required>
                                        <option value="">--</option>
                                        @foreach($employees as $employee)
                                            <option value="{{ $employee->id }}" @selected((int) ($line['employee_id'] ?? 0) === (int) $employee->id)>
                                                {{ $employee->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">{{ trans('accounting::accounting.payroll_runs.fields.base_salary') }}</label>
                                    <input type="text" inputmode="decimal" class="form-control js-accounting-amount-input js-pr-base" name="lines[{{ $i }}][base_salary]" value="{{ $line['base_salary'] ?? '' }}" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">{{ trans('accounting::accounting.payroll_runs.fields.benefits') }}</label>
                                    <input type="text" inputmode="decimal" class="form-control js-accounting-amount-input js-pr-benefits" name="lines[{{ $i }}][benefits]" value="{{ $line['benefits'] ?? '' }}">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">{{ trans('accounting::accounting.payroll_runs.fields.seniority') }}</label>
                                    <input type="text" inputmode="decimal" class="form-control js-accounting-amount-input js-pr-seniority" name="lines[{{ $i }}][seniority]" value="{{ $line['seniority'] ?? '' }}">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">{{ trans('accounting::accounting.payroll_runs.fields.gross_salary') }}</label>
                                    <input type="text" class="form-control js-pr-gross-preview" value="" readonly>
                                </div>
                                <div class="col-md-1 d-flex align-items-end">
                                    <button type="button" class="btn btn-outline-danger btn-sm remove-line-btn w-100">{{ trans('accounting::accounting.payroll_runs.lines.remove') }}</button>
                                </div>
                                <div class="col-12">
                                    <div class="card border-primary bg-primary bg-opacity-10">
                                        <div class="card-body py-2 px-3">
                                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class="ph-hand-coins text-primary"></i>
                                                    <strong class="small mb-0">{{ trans('accounting::accounting.payroll_runs.fields.loan_preview') }}</strong>
                                                </div>
                                                <div class="form-check form-switch m-0">
                                                    <input class="form-check-input js-pr-skip-loan" type="checkbox" role="switch" name="lines[{{ $i }}][skip_loan_deduction]" value="1" @checked(filter_var($line['skip_loan_deduction'] ?? false, FILTER_VALIDATE_BOOLEAN))>
                                                    <label class="form-check-label small">{{ trans('accounting::accounting.payroll_runs.fields.skip_loan_deduction') }}</label>
                                                </div>
                                            </div>
                                            <div class="js-pr-loan-preview"
                                                data-empty-text="{{ trans('accounting::accounting.payroll_runs.fields.loan_preview_empty') }}"
                                                data-label-due="{{ trans('accounting::accounting.payroll_runs.fields.loan_due_total') }}"
                                                data-label-remaining="{{ trans('accounting::accounting.payroll_runs.fields.loan_remaining_total') }}"
                                                data-label-next-due="{{ trans('accounting::accounting.payroll_runs.fields.loan_next_due_date') }}"
                                                style="min-height: 44px;">
                                                <span class="text-muted small">{{ trans('accounting::accounting.payroll_runs.fields.loan_preview_empty') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">{{ trans('accounting::accounting.payroll_runs.fields.employee_insurance') }}</label>
                                    <input type="text" inputmode="decimal" class="form-control js-accounting-amount-input js-pr-employee-insurance" name="lines[{{ $i }}][employee_insurance]" value="{{ $line['employee_insurance'] ?? '' }}">
                                    <div class="form-check form-switch mt-1">
                                        <input class="form-check-input js-pr-manual-toggle" type="checkbox" role="switch" data-target="employee_insurance" name="lines[{{ $i }}][employee_insurance_manual]" value="1" @checked(filter_var($line['employee_insurance_manual'] ?? false, FILTER_VALIDATE_BOOLEAN))>
                                        <label class="form-check-label small">{{ trans('accounting::accounting.payroll_runs.fields.manual_override') }}</label>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">{{ trans('accounting::accounting.payroll_runs.fields.employer_insurance') }}</label>
                                    <input type="text" inputmode="decimal" class="form-control js-accounting-amount-input js-pr-employer-insurance" name="lines[{{ $i }}][employer_insurance]" value="{{ $line['employer_insurance'] ?? '' }}">
                                    <div class="form-check form-switch mt-1">
                                        <input class="form-check-input js-pr-manual-toggle" type="checkbox" role="switch" data-target="employer_insurance" name="lines[{{ $i }}][employer_insurance_manual]" value="1" @checked(filter_var($line['employer_insurance_manual'] ?? false, FILTER_VALIDATE_BOOLEAN))>
                                        <label class="form-check-label small">{{ trans('accounting::accounting.payroll_runs.fields.manual_override') }}</label>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">{{ trans('accounting::accounting.payroll_runs.fields.tax') }}</label>
                                    <input type="text" inputmode="decimal" class="form-control js-accounting-amount-input js-pr-tax" name="lines[{{ $i }}][tax]" value="{{ $line['tax'] ?? '' }}">
                                    <div class="form-check form-switch mt-1">
                                        <input class="form-check-input js-pr-manual-toggle" type="checkbox" role="switch" data-target="tax" name="lines[{{ $i }}][tax_manual]" value="1" @checked(filter_var($line['tax_manual'] ?? false, FILTER_VALIDATE_BOOLEAN))>
                                        <label class="form-check-label small">{{ trans('accounting::accounting.payroll_runs.fields.manual_override') }}</label>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">{{ trans('accounting::accounting.payroll_runs.fields.other_deductions') }}</label>
                                    <input type="text" inputmode="decimal" class="form-control js-accounting-amount-input js-pr-other-deductions" name="lines[{{ $i }}][other_deductions]" value="{{ $line['other_deductions'] ?? '' }}">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">{{ trans('accounting::accounting.payroll_runs.fields.net_salary') }}</label>
                                    <input type="text" class="form-control js-pr-net-preview" value="" readonly>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">{{ trans('accounting::accounting.payroll_runs.fields.description') }}</label>
                                    <input type="text" class="form-control" name="lines[{{ $i }}][description]" value="{{ $line['description'] ?? '' }}">
                                </div>
                                <div class="col-12">
                                    <div class="border rounded p-2 bg-light">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <strong class="small">{{ trans('accounting::accounting.payroll_runs.items.title') }}</strong>
                                            <button type="button" class="btn btn-xs btn-outline-secondary js-pr-add-item">
                                                {{ trans('accounting::accounting.payroll_runs.items.add') }}
                                            </button>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-sm align-middle mb-0 js-pr-items-table">
                                                <thead>
                                                    <tr>
                                                        <th>{{ trans('accounting::accounting.payroll_runs.items.fields.type') }}</th>
                                                        <th>{{ trans('accounting::accounting.payroll_runs.items.fields.code') }}</th>
                                                        <th>{{ trans('accounting::accounting.payroll_runs.items.fields.title') }}</th>
                                                        <th>{{ trans('accounting::accounting.payroll_runs.items.fields.amount') }}</th>
                                                        <th>{{ trans('accounting::accounting.payroll_runs.items.fields.notes') }}</th>
                                                        <th></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach((array) ($line['items'] ?? []) as $itemIndex => $item)
                                                        <tr class="js-pr-item-row">
                                                            <td>
                                                                <select class="form-select form-select-sm" name="lines[{{ $i }}][items][{{ $itemIndex }}][type]">
                                                                    <option value="earning" @selected(($item['type'] ?? '') === 'earning')>{{ trans('accounting::accounting.payroll_runs.items.types.earning') }}</option>
                                                                    <option value="deduction" @selected(($item['type'] ?? '') === 'deduction')>{{ trans('accounting::accounting.payroll_runs.items.types.deduction') }}</option>
                                                                    <option value="employer_contribution" @selected(($item['type'] ?? '') === 'employer_contribution')>{{ trans('accounting::accounting.payroll_runs.items.types.employer_contribution') }}</option>
                                                                </select>
                                                            </td>
                                                            <td><input type="text" class="form-control form-control-sm" name="lines[{{ $i }}][items][{{ $itemIndex }}][code]" value="{{ $item['code'] ?? '' }}"></td>
                                                            <td><input type="text" class="form-control form-control-sm" name="lines[{{ $i }}][items][{{ $itemIndex }}][title]" value="{{ $item['title'] ?? '' }}"></td>
                                                            <td><input type="text" inputmode="decimal" class="form-control form-control-sm js-accounting-amount-input" name="lines[{{ $i }}][items][{{ $itemIndex }}][amount]" value="{{ $item['amount'] ?? '' }}"></td>
                                                            <td><input type="text" class="form-control form-control-sm" name="lines[{{ $i }}][items][{{ $itemIndex }}][notes]" value="{{ $item['notes'] ?? '' }}"></td>
                                                            <td>
                                                                <button type="button" class="btn btn-sm btn-outline-danger js-pr-remove-item">
                                                                    {{ trans('accounting::accounting.payroll_runs.lines.remove') }}
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">{{ trans('accounting::accounting.payroll_runs.actions.save') }}</button>
        <a href="{{ route('admin.accounting.payroll-runs.index') }}" class="btn btn-secondary">{{ trans('accounting::accounting.common.cancel') }}</a>
    </form>
</div>

<template id="payroll-line-template">
    <div class="border rounded p-3 mb-3 payroll-line-row">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">{{ trans('accounting::accounting.payroll_runs.fields.employee') }}</label>
                <select class="form-select js-pr-employee" data-name="employee_id" required>
                    <option value="">--</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ trans('accounting::accounting.payroll_runs.fields.base_salary') }}</label>
                <input type="text" inputmode="decimal" class="form-control js-accounting-amount-input js-pr-base" data-name="base_salary" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ trans('accounting::accounting.payroll_runs.fields.benefits') }}</label>
                <input type="text" inputmode="decimal" class="form-control js-accounting-amount-input js-pr-benefits" data-name="benefits">
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ trans('accounting::accounting.payroll_runs.fields.seniority') }}</label>
                <input type="text" inputmode="decimal" class="form-control js-accounting-amount-input js-pr-seniority" data-name="seniority">
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ trans('accounting::accounting.payroll_runs.fields.gross_salary') }}</label>
                <input type="text" class="form-control js-pr-gross-preview" readonly>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="button" class="btn btn-outline-danger btn-sm remove-line-btn w-100">{{ trans('accounting::accounting.payroll_runs.lines.remove') }}</button>
            </div>
            <div class="col-12">
                <div class="card border-primary bg-primary bg-opacity-10">
                    <div class="card-body py-2 px-3">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <i class="ph-hand-coins text-primary"></i>
                                <strong class="small mb-0">{{ trans('accounting::accounting.payroll_runs.fields.loan_preview') }}</strong>
                            </div>
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input js-pr-skip-loan" type="checkbox" role="switch" data-name="skip_loan_deduction" value="1">
                                <label class="form-check-label small">{{ trans('accounting::accounting.payroll_runs.fields.skip_loan_deduction') }}</label>
                            </div>
                        </div>
                        <div class="js-pr-loan-preview"
                            data-empty-text="{{ trans('accounting::accounting.payroll_runs.fields.loan_preview_empty') }}"
                            data-label-due="{{ trans('accounting::accounting.payroll_runs.fields.loan_due_total') }}"
                            data-label-remaining="{{ trans('accounting::accounting.payroll_runs.fields.loan_remaining_total') }}"
                            data-label-next-due="{{ trans('accounting::accounting.payroll_runs.fields.loan_next_due_date') }}"
                            style="min-height: 44px;">
                            <span class="text-muted small">{{ trans('accounting::accounting.payroll_runs.fields.loan_preview_empty') }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ trans('accounting::accounting.payroll_runs.fields.employee_insurance') }}</label>
                <input type="text" inputmode="decimal" class="form-control js-accounting-amount-input js-pr-employee-insurance" data-name="employee_insurance">
                <div class="form-check form-switch mt-1">
                    <input class="form-check-input js-pr-manual-toggle" type="checkbox" role="switch" data-target="employee_insurance" data-name="employee_insurance_manual" value="1">
                    <label class="form-check-label small">{{ trans('accounting::accounting.payroll_runs.fields.manual_override') }}</label>
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ trans('accounting::accounting.payroll_runs.fields.employer_insurance') }}</label>
                <input type="text" inputmode="decimal" class="form-control js-accounting-amount-input js-pr-employer-insurance" data-name="employer_insurance">
                <div class="form-check form-switch mt-1">
                    <input class="form-check-input js-pr-manual-toggle" type="checkbox" role="switch" data-target="employer_insurance" data-name="employer_insurance_manual" value="1">
                    <label class="form-check-label small">{{ trans('accounting::accounting.payroll_runs.fields.manual_override') }}</label>
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ trans('accounting::accounting.payroll_runs.fields.tax') }}</label>
                <input type="text" inputmode="decimal" class="form-control js-accounting-amount-input js-pr-tax" data-name="tax">
                <div class="form-check form-switch mt-1">
                    <input class="form-check-input js-pr-manual-toggle" type="checkbox" role="switch" data-target="tax" data-name="tax_manual" value="1">
                    <label class="form-check-label small">{{ trans('accounting::accounting.payroll_runs.fields.manual_override') }}</label>
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ trans('accounting::accounting.payroll_runs.fields.other_deductions') }}</label>
                <input type="text" inputmode="decimal" class="form-control js-accounting-amount-input js-pr-other-deductions" data-name="other_deductions">
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ trans('accounting::accounting.payroll_runs.fields.net_salary') }}</label>
                <input type="text" class="form-control js-pr-net-preview" readonly>
            </div>
            <div class="col-md-8">
                <label class="form-label">{{ trans('accounting::accounting.payroll_runs.fields.description') }}</label>
                <input type="text" class="form-control" data-name="description">
            </div>
            <div class="col-12">
                <div class="border rounded p-2 bg-light">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong class="small">{{ trans('accounting::accounting.payroll_runs.items.title') }}</strong>
                        <button type="button" class="btn btn-xs btn-outline-secondary js-pr-add-item">
                            {{ trans('accounting::accounting.payroll_runs.items.add') }}
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 js-pr-items-table">
                            <thead>
                                <tr>
                                    <th>{{ trans('accounting::accounting.payroll_runs.items.fields.type') }}</th>
                                    <th>{{ trans('accounting::accounting.payroll_runs.items.fields.code') }}</th>
                                    <th>{{ trans('accounting::accounting.payroll_runs.items.fields.title') }}</th>
                                    <th>{{ trans('accounting::accounting.payroll_runs.items.fields.amount') }}</th>
                                    <th>{{ trans('accounting::accounting.payroll_runs.items.fields.notes') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<template id="payroll-item-template">
    <tr class="js-pr-item-row">
        <td>
            <select class="form-select form-select-sm" data-item-name="type">
                <option value="earning">{{ trans('accounting::accounting.payroll_runs.items.types.earning') }}</option>
                <option value="deduction">{{ trans('accounting::accounting.payroll_runs.items.types.deduction') }}</option>
                <option value="employer_contribution">{{ trans('accounting::accounting.payroll_runs.items.types.employer_contribution') }}</option>
            </select>
        </td>
        <td><input type="text" class="form-control form-control-sm" data-item-name="code"></td>
        <td><input type="text" class="form-control form-control-sm" data-item-name="title"></td>
        <td><input type="text" inputmode="decimal" class="form-control form-control-sm js-accounting-amount-input" data-item-name="amount"></td>
        <td><input type="text" class="form-control form-control-sm" data-item-name="notes"></td>
        <td>
            <button type="button" class="btn btn-sm btn-outline-danger js-pr-remove-item">
                {{ trans('accounting::accounting.payroll_runs.lines.remove') }}
            </button>
        </td>
    </tr>
</template>

<script id="pr-loan-preview-data" type="application/json">@json($loanPreviewByEmployee)</script>
<script id="pr-employee-salary-defaults" type="application/json">@json($employeeSalaryDefaults ?? [])</script>
<script id="pr-format-config" type="application/json">@json(['decimal_places' => (int) ($decimalPlaces ?? 0)])</script>
<script id="pr-wage-guard-config" type="application/json">@json($wageGuardConfig)</script>
<script id="pr-net-guard-config" type="application/json">@json($netGuardConfig)</script>

@include('accounting::admin.partials.accounting-date-ui-script')
@endsection
