@extends('cms::admin.layout.index')
@section('title', trans('accounting::accounting.employee_contracts.show_title'))
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">{{ trans('accounting::accounting.employee_contracts.show_title') }}</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.accounting.employee-contracts.print', $contract->id) }}" class="btn btn-outline-dark">
                {{ trans('accounting::accounting.employee_contracts.actions.print') }}
            </a>
            <a href="{{ route('admin.accounting.employee-contracts.edit', $contract->id) }}" class="btn btn-outline-primary">
                {{ trans('accounting::accounting.employee_contracts.actions.edit') }}
            </a>
            <a href="{{ route('admin.accounting.employee-contracts.index') }}" class="btn btn-light">
                {{ trans('accounting::accounting.common.back') }}
            </a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">{{ trans('accounting::accounting.employee_contracts.fields.employee') }}</div>
                    <strong>{{ $contract->employee?->name }}</strong>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">{{ trans('accounting::accounting.employee_contracts.fields.base_salary') }}</div>
                    <strong>{{ number_format((float) $contract->base_salary) }}</strong>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">{{ trans('accounting::accounting.employee_contracts.fields.seniority_monthly_default') }}</div>
                    <strong>{{ $contract->seniority_monthly_default !== null ? number_format((float) $contract->seniority_monthly_default) : '—' }}</strong>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">{{ trans('accounting::accounting.employee_contracts.fields.period') }}</div>
                    <strong>{{ \RMS\Helper\persian_date($contract->effective_from, 'Y/m/d') }} - {{ $contract->effective_to ? \RMS\Helper\persian_date($contract->effective_to, 'Y/m/d') : '∞' }}</strong>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">{{ trans('accounting::accounting.employee_contracts.fields.status') }}</div>
                    <strong>{{ trans('accounting::accounting.employee_contracts.statuses.'.$contract->status) }}</strong>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">{{ trans('accounting::accounting.employee_contracts.fields.notes') }}</div>
        <div class="card-body">
            {{ $contract->notes ?: '—' }}
        </div>
    </div>

    @if($contract->status === \RMS\Accounting\Models\EmployeeContract::STATUS_ACTIVE)
    <div class="card mb-3">
        <div class="card-header">{{ trans('accounting::accounting.employee_contracts.actions.manage_status') }}</div>
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-7">
                    <form method="post" action="{{ route('admin.accounting.employee-contracts.end', $contract->id) }}" class="row g-2">
                        @csrf
                        <div class="col-md-7">
                            <label class="form-label">{{ trans('accounting::accounting.employee_contracts.fields.effective_to') }}</label>
                            <input type="text" name="effective_to" class="form-control js-accounting-date-input" data-format="YYYY/MM/DD" value="{{ now()->format('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-5 d-flex align-items-end">
                            <button type="submit" class="btn btn-outline-warning w-100">{{ trans('accounting::accounting.employee_contracts.actions.end') }}</button>
                        </div>
                    </form>
                </div>
                <div class="col-md-5 d-flex align-items-end">
                    <form method="post" action="{{ route('admin.accounting.employee-contracts.cancel', $contract->id) }}" onsubmit="return confirm(@json(trans('accounting::accounting.employee_contracts.confirm_cancel')));">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger">{{ trans('accounting::accounting.employee_contracts.actions.cancel_contract') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endif

    @include('vendor.cms.admin.components.document-attachments-box', [
        'attachable_type' => \RMS\Accounting\Models\EmployeeContract::class,
        'attachable_id' => $contract->id,
        'document_types' => \App\Models\DocumentAttachment::documentTypes(),
    ])

    <div class="card mt-3">
        <div class="card-header">{{ trans('accounting::accounting.employee_contracts.timeline_title') }}</div>
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th>{{ trans('accounting::accounting.employee_contracts.fields.contract_number') }}</th>
                        <th>{{ trans('accounting::accounting.employee_contracts.fields.period') }}</th>
                        <th>{{ trans('accounting::accounting.employee_contracts.fields.base_salary') }}</th>
                        <th>{{ trans('accounting::accounting.employee_contracts.fields.seniority_monthly_default') }}</th>
                        <th>{{ trans('accounting::accounting.employee_contracts.fields.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($timeline as $row)
                        <tr @if((int) $row->id === (int) $contract->id) class="table-primary" @endif>
                            <td>{{ $row->contract_number }}</td>
                            <td>{{ \RMS\Helper\persian_date($row->effective_from, 'Y/m/d') }} - {{ $row->effective_to ? \RMS\Helper\persian_date($row->effective_to, 'Y/m/d') : '∞' }}</td>
                            <td>{{ number_format((float) $row->base_salary) }}</td>
                            <td>{{ $row->seniority_monthly_default !== null ? number_format((float) $row->seniority_monthly_default) : '—' }}</td>
                            <td>{{ trans('accounting::accounting.employee_contracts.statuses.'.$row->status) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">—</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@include('accounting::admin.partials.accounting-date-ui-script')
