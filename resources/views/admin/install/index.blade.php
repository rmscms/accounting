@extends('cms::admin.layout.index')
@section('title', trans('accounting::accounting.install.title'))
@section('content')
<div class="container-fluid">
    <div class="card border-start border-primary border-3 mb-3">
        <div class="card-body">
            <h4 class="mb-2">{{ trans('accounting::accounting.install.title') }}</h4>
            <p class="text-muted mb-0">{{ trans('accounting::accounting.install.lead') }}</p>
        </div>
    </div>

    @if (session('accounting_sample_wiped_notice'))
        <div class="alert alert-success">{{ session('accounting_sample_wiped_notice') }}</div>
    @endif

    <div class="card border-0 shadow-sm mb-3 bg-body-secondary">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h6 class="mb-1 fw-semibold"><i class="ph-path me-1 text-primary"></i>{{ trans('accounting::accounting.install.onboarding_card_title') }}</h6>
                <p class="text-muted small mb-0">{{ trans('accounting::accounting.install.onboarding_card_lead') }}</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('admin.accounting.onboarding') }}" class="btn btn-primary btn-sm">
                    <i class="ph-play-circle me-1"></i>{{ trans('accounting::accounting.onboarding.page_title') }}
                </a>
                <a href="{{ route('admin.accounting.guides.opening-balance') }}" class="btn btn-outline-primary btn-sm">
                    <i class="ph-book-open me-1"></i>{{ trans('accounting::accounting.onboarding.link_guide') }}
                </a>
            </div>
        </div>
    </div>

    @if(session('accounting_install_success') === true)
        <div class="alert alert-success">{{ trans('accounting::accounting.install.flash_done') }}</div>
    @elseif(session('accounting_install_success') === false)
        <div class="alert alert-warning">{{ trans('accounting::accounting.install.flash_partial') }}</div>
    @endif

    @if($isComplete)
        <div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2">
            <span>{{ trans('accounting::accounting.install.already_complete') }}</span>
            <a href="{{ route('admin.accounting.dashboard') }}" class="btn btn-primary">{{ trans('accounting::accounting.install.go_dashboard') }}</a>
        </div>
    @elseif(!$wizardRequired)
        <div class="alert alert-secondary">{{ trans('accounting::accounting.install.wizard_disabled') }}</div>
    @else
        <div class="card mb-3">
            <div class="card-body">
                <p class="mb-3">{{ trans('accounting::accounting.install.run_hint') }}</p>
                <form method="post" action="{{ route('admin.accounting.install.run') }}" onsubmit="return confirm(@json(trans('accounting::accounting.install.run_confirm')));">
                    @csrf
                    <button type="submit" class="btn btn-lg btn-success">
                        <i class="ph-play me-1"></i>
                        {{ trans('accounting::accounting.install.run_button') }}
                    </button>
                </form>
            </div>
        </div>
    @endif

    @php
        $stepsList = $steps ?? [];
        $total = max(count($stepsList), 1);
        $done = collect($stepsList)->whereIn('status', ['done', 'skipped'])->count();
        $pct = (int) round(($done / $total) * 100);
    @endphp

    @if(!empty($stepsList))
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">{{ trans('accounting::accounting.install.progress_title') }}</h5>
            </div>
            <div class="card-body">
                <div class="progress mb-3" style="height: 1.25rem;" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar progress-bar-striped bg-primary" style="width: {{ $pct }}%;">{{ $pct }}%</div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ trans('accounting::accounting.install.col_step') }}</th>
                                <th>{{ trans('accounting::accounting.install.col_type') }}</th>
                                <th>{{ trans('accounting::accounting.install.col_status') }}</th>
                                <th>{{ trans('accounting::accounting.install.col_detail') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($stepsList as $row)
                                <tr>
                                    <td>{{ $row['label'] ?? ($row['key'] ?? '') }}</td>
                                    <td><span class="badge bg-secondary">{{ $row['type'] ?? '' }}</span></td>
                                    <td>
                                        @if(($row['status'] ?? '') === 'done')
                                            <span class="badge bg-success">{{ trans('accounting::accounting.install.status_done') }}</span>
                                        @elseif(($row['status'] ?? '') === 'skipped')
                                            <span class="badge bg-light text-dark">{{ trans('accounting::accounting.install.status_skipped') }}</span>
                                        @elseif(($row['status'] ?? '') === 'error')
                                            <span class="badge bg-danger">{{ trans('accounting::accounting.install.status_error') }}</span>
                                        @else
                                            <span class="badge bg-light text-dark">{{ $row['status'] ?? '' }}</span>
                                        @endif
                                    </td>
                                    <td class="small text-break">{{ $row['detail'] ?? '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if(session('accounting_install_success') === true)
                    <div class="mt-3">
                        <a href="{{ route('admin.accounting.dashboard') }}" class="btn btn-primary">{{ trans('accounting::accounting.install.go_dashboard') }}</a>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
