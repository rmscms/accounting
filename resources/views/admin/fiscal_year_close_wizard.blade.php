@extends('cms::admin.layout.index')

@section('title', trans('accounting::accounting.page_titles.fiscal_year_close_wizard'))

@section('content')
    <div class="card" id="fiscal-close-wizard"
         data-step-order="precheck,preview,execute,postcheck,openNext">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="mb-0">{{ trans('accounting::accounting.fiscal_year_close.wizard.title') }}</h5>
            <a href="{{ $backRoute }}" class="btn btn-light btn-sm">{{ trans('accounting::accounting.common.back') }}</a>
        </div>
        <div class="card-body">
            @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="mb-4">
                <p class="mb-1">
                    <strong>{{ trans('accounting::accounting.fiscal_year_close.wizard.labels.year') }}:</strong>
                    {{ $fiscalYear->year_code }}
                    ({{ $fiscalYear->start_date?->format('Y-m-d') }} — {{ $fiscalYear->end_date?->format('Y-m-d') }})
                </p>
                <p class="mb-1">
                    <strong>{{ trans('accounting::accounting.fiscal_year_close.wizard.labels.status') }}:</strong>
                    {{ $fiscalYear->status }}
                </p>
                <p class="mb-1">
                    <strong>{{ trans('accounting::accounting.fiscal_year_close.wizard.labels.is_current') }}:</strong>
                    {{ $isCurrent ? trans('accounting::accounting.common.yes') : trans('accounting::accounting.common.no') }}
                </p>
                <p class="mb-0">
                    <strong>{{ trans('accounting::accounting.fiscal_year_close.wizard.labels.draft_documents') }}:</strong>
                    {{ number_format($draftDocuments) }}
                </p>
            </div>

            <div class="alert alert-info small mb-4">
                {!! nl2br(e(trans('accounting::accounting.fiscal_year_close.data_quality.summary'))) !!}
            </div>
            <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 small mb-4">
                <div>
                    در صورت خطای حساب سود انباشته/خلاصه سود و زیان، ابتدا این دو حساب را در تنظیمات مشخص کنید.
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-sm btn-outline-warning"
                       href="{{ route('admin.accounting.settings.index', ['settings_tab' => 'general-tab', 'account_setting_tag' => 'equity.retained_earnings']) }}">
                        تنظیم سود انباشته
                    </a>
                    <a class="btn btn-sm btn-outline-warning"
                       href="{{ route('admin.accounting.settings.index', ['settings_tab' => 'general-tab', 'account_setting_tag' => 'equity.income_summary']) }}">
                        تنظیم خلاصه سود و زیان
                    </a>
                </div>
            </div>

            @if ($fiscalYear->status === 'closed')
                <div class="alert alert-warning mb-0">
                    {{ trans('accounting::accounting.errors.fiscal_year_already_closed') }}
                </div>
            @else
                <div class="wizard mb-3">
                    <ul class="steps mb-0">
                        <li class="current"><a href="javascript:void(0);"><span class="number">1</span>{{ trans('accounting::accounting.fiscal_year_close.wizard.step1') }}</a></li>
                        <li><a href="javascript:void(0);"><span class="number">2</span>{{ trans('accounting::accounting.fiscal_year_close.wizard.step2') }}</a></li>
                        <li><a href="javascript:void(0);"><span class="number">3</span>{{ trans('accounting::accounting.fiscal_year_close.wizard.step3') }}</a></li>
                        <li><a href="javascript:void(0);"><span class="number">4</span>{{ trans('accounting::accounting.fiscal_year_close.wizard.step4') }}</a></li>
                        <li><a href="javascript:void(0);"><span class="number">5</span>{{ trans('accounting::accounting.fiscal_year_close.wizard.step5') }}</a></li>
                    </ul>
                </div>

                <form method="post" action="{{ $executeRoute }}" class="needs-validation mb-4">
                    @csrf
                    <input type="hidden" name="close_mode" value="full_entries">
                    <h6 class="text-muted mb-2">{{ trans('accounting::accounting.fiscal_year_close.wizard.mode_full_entries') }}</h6>
                    @if (!$canFullClose && $fullCloseBlockReason)
                        <div class="alert alert-warning py-2">{{ $fullCloseBlockReason }}</div>
                    @endif
                    <p class="small text-muted mb-0">{{ trans('accounting::accounting.fiscal_year_close.wizard.confirm_hint') }}</p>
                </form>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="mb-2">{{ trans('accounting::accounting.fiscal_year_close.wizard.step1') }}</h6>
                                <p class="small text-muted">{{ trans('accounting::accounting.fiscal_year_close.wizard.step1_hint') }}</p>
                                <button type="button" class="btn btn-outline-primary js-fy-step" data-step="precheck" {{ !$canFullClose ? 'disabled' : '' }}>
                                    {{ trans('accounting::accounting.fiscal_year_close.wizard.run_step1') }}
                                </button>
                                <div class="mt-2 small js-step-status" data-step-status="precheck"></div>
                                <div class="table-responsive mt-2 d-none js-temp-table-wrap">
                                    <table class="table table-sm">
                                        <thead>
                                        <tr>
                                            <th>{{ trans('accounting::accounting.fiscal_year_close.wizard.col_code') }}</th>
                                            <th>{{ trans('accounting::accounting.fiscal_year_close.wizard.col_name') }}</th>
                                            <th class="text-end">{{ trans('accounting::accounting.fiscal_year_close.wizard.col_net') }}</th>
                                        </tr>
                                        </thead>
                                        <tbody class="js-temp-table-body"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="mb-2">{{ trans('accounting::accounting.fiscal_year_close.wizard.step2') }}</h6>
                                <p class="small text-muted">{{ trans('accounting::accounting.fiscal_year_close.wizard.step2_hint') }}</p>
                                <button type="button" class="btn btn-outline-primary js-fy-step" data-step="preview" disabled>
                                    {{ trans('accounting::accounting.fiscal_year_close.wizard.run_step2') }}
                                </button>
                                <div class="mt-2 small js-step-status" data-step-status="preview"></div>
                                <div class="mt-2 small d-none js-preview-wrap">
                                    <div>{{ trans('accounting::accounting.fiscal_year_close.wizard.preview_estimated_net') }}: <strong class="js-preview-net"></strong></div>
                                    <div>{{ trans('accounting::accounting.fiscal_year_close.wizard.preview_income_tax') }}: <strong class="js-preview-tax"></strong></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="mb-2">{{ trans('accounting::accounting.fiscal_year_close.wizard.step3') }}</h6>
                                <p class="small text-muted">{{ trans('accounting::accounting.fiscal_year_close.wizard.step3_hint') }}</p>
                                <button type="button" class="btn btn-danger js-fy-step" data-step="execute" disabled>
                                    {{ trans('accounting::accounting.fiscal_year_close.wizard.run_step3') }}
                                </button>
                                <div class="mt-2 small js-step-status" data-step-status="execute"></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="mb-2">{{ trans('accounting::accounting.fiscal_year_close.wizard.step4') }}</h6>
                                <p class="small text-muted">{{ trans('accounting::accounting.fiscal_year_close.wizard.step4_hint') }}</p>
                                <button type="button" class="btn btn-outline-primary js-fy-step" data-step="postcheck" disabled>
                                    {{ trans('accounting::accounting.fiscal_year_close.wizard.run_step4') }}
                                </button>
                                <div class="mt-2 small js-step-status" data-step-status="postcheck"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-success">
                    <div class="card-body">
                        <h6 class="mb-2">{{ trans('accounting::accounting.fiscal_year_close.wizard.step5') }}</h6>
                        <p class="small text-muted">{{ trans('accounting::accounting.fiscal_year_close.wizard.step5_hint') }}</p>
                        <div class="row g-3 align-items-end mb-3">
                            <div class="col-md-6">
                                <label class="form-label" for="next_fiscal_year_id">{{ trans('accounting::accounting.fiscal_year_close.wizard.labels.activate_existing') }}</label>
                                <select id="next_fiscal_year_id" class="form-select">
                                    <option value="">{{ trans('accounting::accounting.common.none') }}</option>
                                    @foreach ($nextYearCandidates as $candidate)
                                        <option value="{{ $candidate->id }}">
                                            {{ $candidate->year_code }}
                                            @if ($candidate->is_current)
                                                ({{ trans('accounting::accounting.fiscal_year_close.wizard.labels.already_current_badge') }})
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" id="create_next">
                                    <label class="form-check-label" for="create_next">
                                        {{ trans('accounting::accounting.fiscal_year_close.wizard.labels.create_next_year') }}
                                    </label>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-success js-fy-step" data-step="openNext" disabled>
                            {{ trans('accounting::accounting.fiscal_year_close.wizard.run_step5') }}
                        </button>
                        <div class="mt-2 small js-step-status" data-step-status="openNext"></div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if ($fiscalYear->status !== 'closed')
        @php
            $wizardConfig = [
                'csrf' => csrf_token(),
                'routes' => [
                    'precheck' => $precheckRoute,
                    'preview' => $previewRoute,
                    'execute' => $executeStepRoute,
                    'postcheck' => $postcheckRoute,
                    'openNext' => $openNextRoute,
                ],
                'messages' => [
                    'running' => trans('accounting::accounting.fiscal_year_close.wizard.running'),
                    'done' => trans('accounting::accounting.fiscal_year_close.wizard.done'),
                    'failed' => trans('accounting::accounting.fiscal_year_close.wizard.failed'),
                ],
            ];
        @endphp
        <script id="fiscal-close-wizard-config" type="application/json">
            {!! json_encode($wizardConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
        </script>
    @endif
@endsection
