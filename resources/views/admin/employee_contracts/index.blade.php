@extends('cms::admin.layout.index')
@section('title', trans('accounting::accounting.employee_contracts.title'))
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">{{ trans('accounting::accounting.employee_contracts.title') }}</h4>
        <a href="{{ route('admin.accounting.employee-contracts.create') }}" class="btn btn-primary">
            {{ trans('accounting::accounting.employee_contracts.actions.create') }}
        </a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="get" class="row g-2">
                <div class="col-md-4">
                    <label class="form-label">{{ trans('accounting::accounting.employee_contracts.fields.employee') }}</label>
                    <select name="employee_id" class="form-select">
                        <option value="">{{ trans('accounting::accounting.common.all') }}</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" @selected((int) ($filters['employee_id'] ?? 0) === (int) $employee->id)>{{ $employee->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ trans('accounting::accounting.employee_contracts.fields.status') }}</label>
                    <select name="status" class="form-select">
                        <option value="">{{ trans('accounting::accounting.common.all') }}</option>
                        @foreach(\RMS\Accounting\Models\EmployeeContract::statuses() as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>
                                {{ trans('accounting::accounting.employee_contracts.statuses.'.$status) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end gap-2">
                    <button class="btn btn-outline-primary">{{ trans('accounting::accounting.common.filter') }}</button>
                    <a href="{{ route('admin.accounting.employee-contracts.index') }}" class="btn btn-light">{{ trans('accounting::accounting.common.reset') }}</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>{{ trans('accounting::accounting.common.id') }}</th>
                        <th>{{ trans('accounting::accounting.employee_contracts.fields.contract_number') }}</th>
                        <th>{{ trans('accounting::accounting.employee_contracts.fields.employee') }}</th>
                        <th>{{ trans('accounting::accounting.employee_contracts.fields.base_salary') }}</th>
                        <th>{{ trans('accounting::accounting.employee_contracts.fields.period') }}</th>
                        <th>{{ trans('accounting::accounting.employee_contracts.fields.status') }}</th>
                        <th>{{ trans('accounting::accounting.employee_contracts.actions.title') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($contracts as $row)
                        <tr>
                            <td>{{ $row->id }}</td>
                            <td>{{ $row->contract_number }}</td>
                            <td>{{ $row->employee?->name }}</td>
                            <td>{{ number_format((float) $row->base_salary) }}</td>
                            <td>
                                {{ \RMS\Helper\persian_date($row->effective_from, 'Y/m/d') }}
                                —
                                {{ $row->effective_to ? \RMS\Helper\persian_date($row->effective_to, 'Y/m/d') : '∞' }}
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    {{ trans('accounting::accounting.employee_contracts.statuses.'.$row->status) }}
                                </span>
                            </td>
                            <td class="text-nowrap">
                                <a href="{{ route('admin.accounting.employee-contracts.show', $row->id) }}" class="btn btn-sm btn-outline-primary">
                                    {{ trans('accounting::accounting.employee_contracts.actions.show') }}
                                </a>
                                <a href="{{ route('admin.accounting.employee-contracts.edit', $row->id) }}" class="btn btn-sm btn-outline-secondary">
                                    {{ trans('accounting::accounting.employee_contracts.actions.edit') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-4 text-muted">{{ trans('accounting::accounting.employee_contracts.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $contracts->withQueryString()->links() }}</div>
    </div>
</div>
@endsection
