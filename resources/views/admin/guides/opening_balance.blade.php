@extends('cms::admin.layout.index')
@section('title', trans('accounting::accounting.opening_balance_guide.page_title'))
@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h4 class="mb-1">{{ trans('accounting::accounting.opening_balance_guide.page_title') }}</h4>
            <p class="text-muted mb-0">{{ trans('accounting::accounting.opening_balance_guide.lead') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.accounting.onboarding') }}" class="btn btn-sm btn-primary">
                <i class="ph-path me-1"></i>{{ trans('accounting::accounting.onboarding.page_title') }}
            </a>
            <a href="{{ route('admin.accounting.manual-journals.create') }}" class="btn btn-sm btn-outline-primary">
                <i class="ph-note-pencil me-1"></i>{{ trans('accounting::accounting.readiness.items.opening_journal.action') }}
            </a>
        </div>
    </div>

    @include('accounting::admin.onboarding.partials.readiness-checklist', ['readiness' => $readiness, 'compact' => false])

    @php
        $mjEditPathPattern = str_replace('/1/', '/{id}/', parse_url(route('admin.accounting.manual-journals.edit', ['manual_journal' => 1]), PHP_URL_PATH) ?? '');
    @endphp
    <div class="card border-primary border-opacity-25 shadow-sm mb-3">
        <div class="card-header py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h6 class="mb-0 fw-semibold">{{ trans('accounting::accounting.opening_balance_guide.workflow_card_title') }}</h6>
            <a href="{{ route('admin.accounting.manual-journals.index') }}" class="btn btn-sm btn-outline-primary">
                <i class="ph-list me-1"></i>{{ trans('accounting::accounting.opening_balance_guide.workflow_btn_list') }}
            </a>
        </div>
        <div class="card-body py-3">
            <p class="text-muted small mb-3">{{ trans('accounting::accounting.opening_balance_guide.workflow_lead') }}</p>
            <ol class="mb-3 ps-3 small">
                <li class="mb-2">
                    {{ trans('accounting::accounting.opening_balance_guide.workflow_step_1') }}
                    <a class="fw-semibold" href="{{ route('admin.accounting.manual-journals.create') }}">{{ trans('accounting::accounting.opening_balance_guide.workflow_link_create') }}</a>
                    <code class="d-inline-block ms-1 user-select-all small text-body">{{ parse_url(route('admin.accounting.manual-journals.create'), PHP_URL_PATH) }}</code>
                </li>
                <li class="mb-2">
                    {{ trans('accounting::accounting.opening_balance_guide.workflow_step_2') }}
                    <a class="fw-semibold" href="{{ route('admin.accounting.manual-journals.index') }}">{{ trans('accounting::accounting.opening_balance_guide.workflow_link_index') }}</a>
                    <code class="d-inline-block ms-1 user-select-all small text-body">{{ parse_url(route('admin.accounting.manual-journals.index'), PHP_URL_PATH) }}</code>
                </li>
                <li class="mb-2">
                    {{ trans('accounting::accounting.opening_balance_guide.workflow_step_3') }}
                    <code class="d-inline-block user-select-all small text-body">{{ $mjEditPathPattern }}</code>
                </li>
                <li class="mb-0">
                    {{ trans('accounting::accounting.opening_balance_guide.workflow_step_4') }}
                    <a class="fw-semibold" href="{{ route('admin.accounting.reports.trial-balance') }}">{{ trans('accounting::accounting.opening_balance_guide.workflow_link_trial') }}</a>
                    <code class="d-inline-block ms-1 user-select-all small text-body">{{ parse_url(route('admin.accounting.reports.trial-balance'), PHP_URL_PATH) }}</code>
                </li>
            </ol>
            <div class="alert alert-warning text-dark mb-0 small" role="note">
                <i class="ph-info me-1"></i>{{ trans('accounting::accounting.opening_balance_guide.workflow_lines_note') }}
            </div>
        </div>
    </div>

    <x-accounting::page-description class="mb-3" :title="trans('accounting::accounting.opening_balance_guide.section_prereq_title')">
        <p>{{ trans('accounting::accounting.opening_balance_guide.section_prereq_intro') }}</p>
        <ul class="mb-0">
            @foreach(trans('accounting::accounting.opening_balance_guide.section_prereq_list') as $line)
                <li>{{ $line }}</li>
            @endforeach
        </ul>
    </x-accounting::page-description>

    <x-accounting::page-description class="mb-3" :title="trans('accounting::accounting.opening_balance_guide.section_cash_title')">
        <p class="mb-0">{{ trans('accounting::accounting.opening_balance_guide.section_cash_body') }}</p>
    </x-accounting::page-description>

    <x-accounting::page-description class="mb-3 border-warning" :title="trans('accounting::accounting.opening_balance_guide.section_inventory_title')">
        <p class="mb-0">{{ trans('accounting::accounting.opening_balance_guide.section_inventory_body') }}</p>
    </x-accounting::page-description>

    <x-accounting::page-description class="mb-3" :title="trans('accounting::accounting.opening_balance_guide.section_ap_ar_title')">
        <p class="mb-0">{{ trans('accounting::accounting.opening_balance_guide.section_ap_ar_body') }}</p>
    </x-accounting::page-description>

    <x-accounting::page-description class="mb-3" :title="trans('accounting::accounting.opening_balance_guide.section_equity_title')">
        <p class="mb-0">{{ trans('accounting::accounting.opening_balance_guide.section_equity_body') }}</p>
    </x-accounting::page-description>

    <x-accounting::page-description class="mb-3" :title="trans('accounting::accounting.opening_balance_guide.section_journal_title')">
        <p class="mb-0">{{ trans('accounting::accounting.opening_balance_guide.section_journal_body') }}</p>
    </x-accounting::page-description>

    <x-accounting::page-description class="mb-0" :title="trans('accounting::accounting.opening_balance_guide.section_verify_title')">
        <p class="mb-0">{{ trans('accounting::accounting.opening_balance_guide.section_verify_body') }}</p>
    </x-accounting::page-description>
</div>
@endsection
