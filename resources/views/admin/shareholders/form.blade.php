@extends('cms::admin.layout.index')
@section('title', $mode === 'create' ? trans('accounting::accounting.shareholders.create') : trans('accounting::accounting.shareholders.edit'))
@section('content')
<div class="container-fluid">
    <h4 class="mb-3">{{ $mode === 'create' ? trans('accounting::accounting.shareholders.create') : trans('accounting::accounting.shareholders.edit') }}</h4>
    <div class="card mb-3">
        <div class="card-body">
            <form method="post" action="{{ $mode === 'create' ? route('admin.accounting.shareholders.store') : route('admin.accounting.shareholders.update', $shareholder->id) }}">
                @csrf
                @if($mode === 'edit')
                    @method('PUT')
                @endif
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">{{ trans('accounting::accounting.shareholders.name') }}</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $shareholder->name) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ trans('accounting::accounting.shareholders.national_id') }}</label>
                        <input type="text" name="national_id" class="form-control" value="{{ old('national_id', $shareholder->national_id) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ trans('accounting::accounting.shareholders.email') }}</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email', $shareholder->email) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ trans('accounting::accounting.shareholders.phone') }}</label>
                        <input type="text" name="phone" class="form-control" value="{{ old('phone', $shareholder->phone) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">{{ trans('accounting::accounting.shareholders.notes') }}</label>
                        <textarea name="notes" class="form-control" rows="2">{{ old('notes', $shareholder->notes) }}</textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="active" value="1" id="active" {{ old('active', $shareholder->active ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="active">{{ trans('accounting::accounting.common.active') }}</label>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">{{ trans('accounting::accounting.shareholders.save') }}</button>
                    <a href="{{ route('admin.accounting.shareholders.index') }}" class="btn btn-light">{{ trans('accounting::accounting.shareholders.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
