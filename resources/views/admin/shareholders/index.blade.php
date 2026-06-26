@extends('cms::admin.layout.index')
@section('title', trans('accounting::accounting.shareholders.title'))
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">{{ trans('accounting::accounting.shareholders.title') }}</h4>
        <a href="{{ route('admin.accounting.shareholders.create') }}" class="btn btn-primary">
            {{ trans('accounting::accounting.shareholders.create') }}
        </a>
    </div>
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    <div class="card">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>{{ trans('accounting::accounting.common.id') }}</th>
                        <th>{{ trans('accounting::accounting.shareholders.name') }}</th>
                        <th>{{ trans('accounting::accounting.shareholders.capital_account') }}</th>
                        <th>{{ trans('accounting::accounting.shareholders.drawings_account') }}</th>
                        <th>{{ trans('accounting::accounting.common.active') }}</th>
                        <th>{{ trans('accounting::accounting.shareholders.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($shareholders as $row)
                        <tr>
                            <td>{{ $row->id }}</td>
                            <td>{{ $row->name }}</td>
                            <td>{{ $row->capital_account_id ?? '—' }}</td>
                            <td>{{ $row->drawings_account_id ?? '—' }}</td>
                            <td>{{ $row->active ? trans('accounting::accounting.common.active') : trans('accounting::accounting.common.inactive') }}</td>
                            <td class="text-nowrap">
                                <a href="{{ route('admin.accounting.shareholders.edit', $row->id) }}" class="btn btn-sm btn-outline-secondary">{{ trans('accounting::accounting.shareholders.edit') }}</a>
                                <form action="{{ route('admin.accounting.shareholders.destroy', $row->id) }}" method="post" class="d-inline" onsubmit="return confirm(@json(trans('accounting::accounting.shareholders.confirm_delete')));">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">{{ trans('accounting::accounting.shareholders.delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">{{ trans('accounting::accounting.shareholders.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $shareholders->links() }}</div>
    </div>
</div>
@endsection
