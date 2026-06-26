@extends('cms::admin.layout.index')
@section('title', $mode === 'create' ? trans('accounting::accounting.employees.create') : trans('accounting::accounting.employees.edit'))
@section('content')
<div class="container-fluid">
    <h4 class="mb-3">{{ $mode === 'create' ? trans('accounting::accounting.employees.create') : trans('accounting::accounting.employees.edit') }}</h4>
    <div class="card mb-3">
        <div class="card-body">
            <form method="post" action="{{ $mode === 'create' ? route('admin.accounting.employees.store') : route('admin.accounting.employees.update', $employee->id) }}">
                @csrf
                @if($mode === 'edit')
                    @method('PUT')
                @endif
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">{{ trans('accounting::accounting.employees.name') }}</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $employee->name) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ trans('accounting::accounting.employees.national_id') }}</label>
                        <input type="text" name="national_id" class="form-control" value="{{ old('national_id', $employee->national_id) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ trans('accounting::accounting.employees.email') }}</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email', $employee->email) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ trans('accounting::accounting.employees.phone') }}</label>
                        <input type="text" name="phone" class="form-control" value="{{ old('phone', $employee->phone) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">{{ trans('accounting::accounting.employees.notes') }}</label>
                        <textarea name="notes" class="form-control" rows="2">{{ old('notes', $employee->notes) }}</textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="active" value="1" id="active" {{ old('active', $employee->active ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="active">{{ trans('accounting::accounting.common.active') }}</label>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">{{ trans('accounting::accounting.employees.save') }}</button>
                    <a href="{{ route('admin.accounting.employees.index') }}" class="btn btn-light">{{ trans('accounting::accounting.employees.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
