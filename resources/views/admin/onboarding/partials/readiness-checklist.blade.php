@php
    /** @var array $readiness */
    $items = $readiness['items'] ?? [];
    $compact = $compact ?? false;
    $pct = (int) ($readiness['percent'] ?? 0);
    $chartInstallRoute = \Illuminate\Support\Facades\Route::has('admin.accounting.onboarding.run-chart-install')
        ? route('admin.accounting.onboarding.run-chart-install')
        : null;
@endphp
<div class="card border-0 shadow-sm mb-3"
     data-onboarding-readiness
     data-chart-install-url="{{ $chartInstallRoute }}"
     data-chart-install-running="{{ trans('accounting::accounting.onboarding.chart_install_running') }}"
     data-chart-install-button-text="{{ trans('accounting::accounting.onboarding.chart_install_action') }}">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2 border-0 pb-0">
        <h6 class="mb-0 fw-semibold">
            <i class="ph-checks me-1 text-primary"></i>
            {{ trans('accounting::accounting.readiness.progress_label') }}
            <span class="text-muted fw-normal small">({{ (int) ($readiness['required_ok'] ?? 0) }}/{{ (int) ($readiness['required_total'] ?? 0) }})</span>
        </h6>
        @if(!empty($readiness['all_required_ok']))
            <span class="badge bg-success rounded-pill"><i class="ph-check me-1"></i>{{ trans('accounting::accounting.onboarding.required_complete_badge') }}</span>
        @else
            <span class="badge bg-warning text-dark rounded-pill">{{ trans('accounting::accounting.onboarding.required_pending_badge') }}</span>
        @endif
    </div>
    <div class="card-body pt-2">
        <div class="progress mb-3" style="height: 0.65rem;" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar {{ $pct >= 100 ? 'bg-success' : 'bg-primary' }}" style="width: {{ $pct }}%;"></div>
        </div>
        @if(!$compact)
            <div class="row g-2">
                @foreach($items as $row)
                    @php
                        $ok = !empty($row['ok']);
                        $tier = $row['tier'] ?? 'required';
                        $tierLabelKey = match ($tier) {
                            'required' => 'readiness.tier_required',
                            'recommended' => 'readiness.tier_recommended',
                            default => 'readiness.tier_optional',
                        };
                        $tierLabel = trans('accounting::accounting.'.$tierLabelKey);
                        $border = $ok ? 'border-success' : ($tier === 'required' ? 'border-danger' : 'border-warning');
                        $icon = $ok ? 'ph-check-circle text-success' : ($tier === 'required' ? 'ph-x-circle text-danger' : 'ph-warning-circle text-warning');
                    @endphp
                    <div class="col-12 col-lg-6">
                        <div class="card h-100 border-start border-3 {{ $border }}">
                            <div class="card-body py-2 px-3">
                                <div class="d-flex align-items-start gap-2">
                                    <i class="{{ $icon }} fs-4 flex-shrink-0 mt-1"></i>
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                            <span class="fw-semibold">{{ $row['label'] ?? '' }}</span>
                                            <span class="badge bg-light text-muted border">{{ $tierLabel }}</span>
                                        </div>
                                        <p class="text-muted small mb-2 mb-md-0">{{ $row['message'] ?? '' }}</p>
                                        @if(($row['key'] ?? null) === 'chart_of_accounts' && !$ok && !empty($chartInstallRoute))
                                            <button type="button" class="btn btn-sm btn-primary mt-1 js-chart-install-run">
                                                <i class="ph-play me-1"></i>{{ trans('accounting::accounting.onboarding.chart_install_action') }}
                                            </button>
                                        @endif
                                        @if(!empty($row['action_route']) && \Illuminate\Support\Facades\Route::has($row['action_route']))
                                            <a href="{{ route($row['action_route'], (array) ($row['action_params'] ?? [])) }}" class="btn btn-sm btn-outline-primary mt-1">
                                                <i class="ph-arrow-square-out me-1"></i>{{ $row['action_label'] ?? trans('accounting::accounting.readiness.open_action') }}
                                            </a>
                                        @endif
                                        @if(($row['key'] ?? null) === 'treasury' && \Illuminate\Support\Facades\Route::has('admin.accounting.cashboxes.index'))
                                            <a href="{{ route('admin.accounting.cashboxes.index') }}" class="btn btn-sm btn-outline-primary mt-1">
                                                <i class="ph-currency-circle-dollar me-1"></i>{{ trans('accounting::accounting.onboarding.step4_link_cashboxes') }}
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
