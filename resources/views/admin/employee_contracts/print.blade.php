@extends('cms::admin.layout.index')
@section('title', trans('accounting::accounting.employee_contracts.print_title'))
@section('content')
<div class="container-fluid py-3">
    <div class="card">
        <div class="card-body">
            <h4 class="mb-3">{{ trans('accounting::accounting.employee_contracts.print_title') }}</h4>
            <table class="table table-bordered">
                <tr>
                    <th style="width: 220px">{{ trans('accounting::accounting.employee_contracts.fields.contract_number') }}</th>
                    <td>{{ $contract->contract_number }}</td>
                </tr>
                <tr>
                    <th>{{ trans('accounting::accounting.employee_contracts.fields.employee') }}</th>
                    <td>{{ $contract->employee?->name }}</td>
                </tr>
                <tr>
                    <th>{{ trans('accounting::accounting.employee_contracts.fields.period') }}</th>
                    <td>{{ \RMS\Helper\persian_date($contract->effective_from, 'Y/m/d') }} - {{ $contract->effective_to ? \RMS\Helper\persian_date($contract->effective_to, 'Y/m/d') : '∞' }}</td>
                </tr>
                <tr>
                    <th>{{ trans('accounting::accounting.employee_contracts.fields.base_salary') }}</th>
                    <td>{{ number_format((float) $contract->base_salary) }}</td>
                </tr>
                <tr>
                    <th>{{ trans('accounting::accounting.employee_contracts.fields.seniority_monthly_default') }}</th>
                    <td>{{ $contract->seniority_monthly_default !== null ? number_format((float) $contract->seniority_monthly_default) : '—' }}</td>
                </tr>
                <tr>
                    <th>{{ trans('accounting::accounting.employee_contracts.fields.status') }}</th>
                    <td>{{ trans('accounting::accounting.employee_contracts.statuses.'.$contract->status) }}</td>
                </tr>
                <tr>
                    <th>{{ trans('accounting::accounting.employee_contracts.fields.notes') }}</th>
                    <td>{{ $contract->notes ?: '—' }}</td>
                </tr>
            </table>
            <button onclick="window.print()" class="btn btn-primary">{{ trans('accounting::accounting.employee_contracts.actions.print') }}</button>
        </div>
    </div>
</div>
@endsection
