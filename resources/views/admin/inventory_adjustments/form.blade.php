@extends('cms::admin.form.index')

@section('content')
    @parent

    @php
        $iaParagraphs = trans('accounting::accounting.inventory_adjustments_help.paragraphs');
        if (! is_array($iaParagraphs)) {
            $iaParagraphs = [];
        }
    @endphp
    @include('accounting::components.collapse_help_card', [
        'toggleLabel' => __('accounting::accounting.inventory_adjustments_help.toggle'),
        'title' => __('accounting::accounting.inventory_adjustments_help.title'),
        'paragraphs' => $iaParagraphs,
        'cardClass' => 'mt-3 mb-4',
    ])
@endsection
