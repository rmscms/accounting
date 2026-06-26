@extends('cms::admin.layout.index')
@section('title', trans('accounting::accounting.employees.title'))
@section('content')
<div class="container-fluid js-employees-index"
     data-confirm-title="{{ trans('accounting::accounting.employees.confirm_modal_title') }}"
     data-confirm-message="{{ trans('accounting::accounting.employees.confirm_delete') }}"
     data-confirm-message-named="{{ trans('accounting::accounting.employees.confirm_delete_named', ['name' => ':name']) }}"
     data-confirm-description="{{ trans('accounting::accounting.employees.confirm_modal_description') }}"
     data-confirm-button="{{ trans('accounting::accounting.employees.confirm_modal_button') }}">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">{{ trans('accounting::accounting.employees.title') }}</h4>
        <a href="{{ route('admin.accounting.employees.create') }}" class="btn btn-primary">{{ trans('accounting::accounting.employees.create') }}</a>
    </div>
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    <p class="text-muted small">{{ trans('accounting::accounting.payroll_insurance.chart_note') }}</p>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>{{ trans('accounting::accounting.common.id') }}</th>
                        <th>{{ trans('accounting::accounting.employees.name') }}</th>
                        <th>{{ trans('accounting::accounting.employees.expense_account') }}</th>
                        <th>{{ trans('accounting::accounting.employees.payable_account') }}</th>
                        <th>{{ trans('accounting::accounting.common.active') }}</th>
                        <th>{{ trans('accounting::accounting.employees.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employees as $row)
                        <tr>
                            <td>{{ $row->id }}</td>
                            <td>{{ $row->name }}</td>
                            <td>{{ $row->payroll_expense_account_id ?? '—' }}</td>
                            <td>{{ $row->wages_payable_account_id ?? '—' }}</td>
                            <td>{{ $row->active ? trans('accounting::accounting.common.active') : trans('accounting::accounting.common.inactive') }}</td>
                            <td class="text-nowrap">
                                <a href="{{ route('admin.accounting.employees.edit', $row->id) }}" class="btn btn-sm btn-outline-secondary">{{ trans('accounting::accounting.employees.edit') }}</a>
                                <form action="{{ route('admin.accounting.employees.destroy', $row->id) }}"
                                      method="post"
                                      class="d-inline js-employee-delete-form"
                                      onsubmit="return false;"
                                      data-employee-name="{{ $row->name }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">{{ trans('accounting::accounting.employees.delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">—</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $employees->links() }}</div>
    </div>
</div>
@endsection
