@extends('cms::admin.form.index')

@section('content')
    @parent

    @if(!empty($accounting_collapse_help) && is_array($accounting_collapse_help))
        @include('accounting::components.collapse_help_card', $accounting_collapse_help)
    @endif
@endsection
