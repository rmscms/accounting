@extends('cms::admin.layout.index')
@section('title', trans('accounting::accounting.employee_loans.title'))
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">{{ trans('accounting::accounting.employee_loans.title') }}</h4>
        <a href="{{ route('admin.accounting.employee-loans.create') }}" class="btn btn-primary">{{ trans('accounting::accounting.employee_loans.actions.create') }}</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ trans('accounting::accounting.employee_loans.fields.loan_number') }}</th>
                        <th>{{ trans('accounting::accounting.employee_loans.fields.employee') }}</th>
                        <th>{{ trans('accounting::accounting.employee_loans.fields.principal_amount') }}</th>
                        <th>{{ trans('accounting::accounting.employee_loans.fields.remaining_total') }}</th>
                        <th>{{ trans('accounting::accounting.employee_loans.fields.status') }}</th>
                        <th>{{ trans('accounting::accounting.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($loans as $loan)
                        <tr>
                            <td>{{ $loan->id }}</td>
                            <td>{{ $loan->loan_number }}</td>
                            <td>{{ $loan->employee?->name }}</td>
                            <td>{{ number_format((float) $loan->principal_amount, 0) }}</td>
                            <td>{{ number_format((float) $loan->remaining_total, 0) }}</td>
                            <td><span class="badge bg-secondary">{{ trans('accounting::accounting.employee_loans.statuses.'.$loan->status) }}</span></td>
                            <td>
                                <a href="{{ route('admin.accounting.employee-loans.show', $loan->id) }}" class="btn btn-sm btn-outline-primary">{{ trans('accounting::accounting.employee_loans.actions.show') }}</a>
                                @if((int) ($loan->payments_count ?? 0) === 0)
                                    <a href="{{ route('admin.accounting.employee-loans.edit', $loan->id) }}" class="btn btn-sm btn-outline-warning">{{ trans('accounting::accounting.employee_loans.actions.edit') }}</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">{{ trans('accounting::accounting.employee_loans.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $loans->links() }}</div>
    </div>
</div>
@endsection
