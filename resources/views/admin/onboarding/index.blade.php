@extends('cms::admin.layout.index')
@section('title', trans('accounting::accounting.onboarding.page_title'))
@section('content')
@php
    $step = (int) ($step ?? 1);
@endphp
<div class="container-fluid">
    <div class="card border-start border-primary border-3 mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <h4 class="mb-1">{{ trans('accounting::accounting.onboarding.page_title') }}</h4>
                    <p class="text-muted mb-0">{{ trans('accounting::accounting.onboarding.lead') }}</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('admin.accounting.install') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="ph-wrench me-1"></i>{{ trans('accounting::accounting.onboarding.link_install') }}
                    </a>
                    <a href="{{ route('admin.accounting.guides.opening-balance') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="ph-book-open me-1"></i>{{ trans('accounting::accounting.onboarding.link_guide') }}
                    </a>
                    <a href="{{ route('admin.accounting.dashboard') }}" class="btn btn-sm btn-primary">
                        <i class="ph-house me-1"></i>{{ trans('accounting::accounting.onboarding.link_dashboard') }}
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-3">
            <p class="text-muted small mb-0 mb-lg-2">{{ trans('accounting::accounting.onboarding.step_nav_hint') }}</p>
            {{-- همان الگوی form_wizard (Limitless): .wizard > .steps — تم روشن/تاریک از متغیرهای CSS پنل --}}
            <div class="wizard mb-0">
                <div class="steps clearfix">
                    <ul role="list">
                        @for($i = 1; $i <= 6; $i++)
                            @php
                                $stepClass = $step === $i ? 'current' : ($i < $step ? 'done' : '');
                                $numberInner = ($i < $step || $step === $i) ? '' : (string) $i;
                            @endphp
                            <li class="{{ $stepClass }}">
                                <a href="{{ route('admin.accounting.onboarding', ['step' => $i]) }}"
                                   class="text-decoration-none"
                                   @if($step === $i) aria-current="step" @endif>
                                    <span class="number">{{ $numberInner }}</span>
                                    <span class="d-block fs-sm fw-semibold text-body text-truncate px-1 mt-1">
                                        {{ trans('accounting::accounting.onboarding.steps.'.$i.'.short') }}
                                    </span>
                                </a>
                            </li>
                        @endfor
                    </ul>
                </div>
            </div>
        </div>
    </div>

    @include('accounting::admin.onboarding.partials.readiness-checklist', ['readiness' => $readiness, 'compact' => $step !== 1])

    <div class="card border-0 shadow-sm">
        <div class="card-header border-0">
            <h5 class="mb-0">{{ trans('accounting::accounting.onboarding.steps.'.$step.'.title') }}</h5>
        </div>
        <div class="card-body">
            @if($step === 1)
                <p class="mb-0">{{ trans('accounting::accounting.onboarding.step1_body') }}</p>
            @elseif($step === 2)
                <h6 class="fw-semibold">{{ trans('accounting::accounting.onboarding.step2_title') }}</h6>
                <p>{{ trans('accounting::accounting.onboarding.step2_body') }}</p>
                @if($install->isComplete() || ! $install->isWizardRequired())
                    <div class="alert alert-success mb-0">{{ trans('accounting::accounting.onboarding.step2_done') }}</div>
                @else
                    <a href="{{ route('admin.accounting.install') }}" class="btn btn-success">
                        <i class="ph-play me-1"></i>{{ trans('accounting::accounting.onboarding.link_install') }}
                    </a>
                @endif
            @elseif($step === 3)
                <h6 class="fw-semibold">{{ trans('accounting::accounting.onboarding.step3_title') }}</h6>
                <p>{{ trans('accounting::accounting.onboarding.step3_body') }}</p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('admin.accounting.fiscal_years.index') }}" class="btn btn-primary btn-sm">
                        <i class="ph-calendar-blank me-1"></i>{{ trans('accounting::accounting.readiness.items.fiscal_year.action') }}
                    </a>
                    @if(\Illuminate\Support\Facades\Route::has('admin.accounting.currencies.reference-rates'))
                        <a href="{{ route('admin.accounting.currencies.reference-rates') }}" class="btn btn-outline-primary btn-sm">
                            <i class="ph-arrows-clockwise me-1"></i>{{ trans('accounting::accounting.readiness.items.reference_currency_and_rate.action') }}
                        </a>
                    @endif
                </div>
            @elseif($step === 4)
                <h6 class="fw-semibold">{{ trans('accounting::accounting.onboarding.step4_title') }}</h6>
                <p>{{ trans('accounting::accounting.onboarding.step4_body') }}</p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('admin.accounting.banks.index') }}" class="btn btn-primary btn-sm">
                        <i class="ph-bank me-1"></i>{{ trans('accounting::accounting.readiness.items.treasury.action') }}
                    </a>
                    <a href="{{ route('admin.accounting.cashboxes.index') }}" class="btn btn-outline-primary btn-sm">
                        <i class="ph-currency-circle-dollar me-1"></i>{{ trans('accounting::accounting.onboarding.step4_link_cashboxes') }}
                    </a>
                </div>
            @elseif($step === 5)
                <h6 class="fw-semibold">{{ trans('accounting::accounting.onboarding.step5_title') }}</h6>
                <p>{{ trans('accounting::accounting.onboarding.step5_body') }}</p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('admin.accounting.manual-journals.create') }}" class="btn btn-primary btn-sm">
                        <i class="ph-note-pencil me-1"></i>{{ trans('accounting::accounting.readiness.items.opening_journal.action') }}
                    </a>
                    <a href="{{ route('admin.accounting.reports.trial-balance') }}" class="btn btn-outline-primary btn-sm">
                        <i class="ph-scales me-1"></i>{{ trans('accounting::accounting.onboarding.step5_link_trial') }}
                    </a>
                </div>
            @else
                <h6 class="fw-semibold">{{ trans('accounting::accounting.onboarding.step6_title') }}</h6>
                <p>{{ trans('accounting::accounting.onboarding.step6_body') }}</p>
                <p class="text-muted small mb-3">{{ trans('accounting::accounting.onboarding.step6_catalog_hint') }}</p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('admin.accounting.guides.opening-balance') }}" class="btn btn-primary btn-sm">
                        <i class="ph-book-open me-1"></i>{{ trans('accounting::accounting.onboarding.link_guide') }}
                    </a>
                    <a href="{{ route('admin.accounting.onboarding', ['step' => 1]) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="ph-arrow-counter-clockwise me-1"></i>{{ trans('accounting::accounting.onboarding.steps.1.short') }}
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
