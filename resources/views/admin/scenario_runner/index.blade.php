@extends('cms::admin.layout.index')

@section('title', trans('accounting::accounting.scenario_runner.page_title'))

@section('content')
    @php
        $f = is_array($formValues ?? null) ? $formValues : [];
        $previewData = is_array($preview ?? null) ? $preview : null;
        $resultData = is_array($result ?? null) ? $result : null;
        $selectedScenario = (string) old('scenario_key', data_get($f, 'scenario_key', ''));
        $scenarioDetails = $scenarios[$selectedScenario] ?? null;
        $selectedProfile = (string) data_get($scenarioDetails, 'input_profile', 'basic');
        $selectedCategory = (string) data_get($scenarioDetails, 'category_key', 'other');
        $selectedCategoryLabel = (string) trans('accounting::accounting.scenario_runner.categories.'.$selectedCategory);
        $entityOptionsData = is_array($entityOptions ?? null) ? $entityOptions : [];
        $customerSelectedId = (string) old('customer_id', data_get($f, 'customer_id', ''));
        $supplierSelectedId = (string) old('supplier_id', data_get($f, 'supplier_id', ''));
        $customerSelectedText = '';
        foreach ((array) data_get($entityOptionsData, 'customers', []) as $row) {
            if ((string) data_get($row, 'id') === $customerSelectedId) {
                $customerSelectedText = (string) (data_get($row, 'name') ?: data_get($row, 'title') ?: data_get($row, 'label') ?: '');
                break;
            }
        }
        $supplierSelectedText = '';
        foreach ((array) data_get($entityOptionsData, 'suppliers', []) as $row) {
            if ((string) data_get($row, 'id') === $supplierSelectedId) {
                $supplierSelectedText = (string) (data_get($row, 'name') ?: data_get($row, 'title') ?: data_get($row, 'label') ?: '');
                break;
            }
        }
        $transferFromType = (string) old('from_treasury_type', data_get($f, 'from_treasury_type', ''));
        $transferFromId = (int) old('from_treasury_id', data_get($f, 'from_treasury_id', 0));
        $transferToType = (string) old('to_treasury_type', data_get($f, 'to_treasury_type', ''));
        $transferToId = (int) old('to_treasury_id', data_get($f, 'to_treasury_id', 0));
        $transferFromInitialBankId = $transferFromType === 'bank' && $transferFromId > 0 ? $transferFromId : '';
        $transferFromInitialCashBoxId = $transferFromType === 'cashbox' && $transferFromId > 0 ? $transferFromId : '';
        $transferFromInitialWalletId = $transferFromType === 'wallet' && $transferFromId > 0 ? $transferFromId : '';
        $transferToInitialBankId = $transferToType === 'bank' && $transferToId > 0 ? $transferToId : '';
        $transferToInitialCashBoxId = $transferToType === 'cashbox' && $transferToId > 0 ? $transferToId : '';
        $transferToInitialWalletId = $transferToType === 'wallet' && $transferToId > 0 ? $transferToId : '';
        $optionLabel = static function ($row, string $fallback = ''): string {
            $label = (string) (data_get($row, 'name')
                ?: data_get($row, 'title')
                ?: data_get($row, 'label')
                ?: data_get($row, 'code')
                ?: data_get($row, 'number')
                ?: $fallback);

            return trim($label) !== '' ? $label : $fallback;
        };
        $errorClass = static function (string $field) use ($errors): string {
            return $errors->has($field) ? ' is-invalid' : '';
        };
        $canRun = (bool) data_get($previewData, 'can_execute', false);
        $diffOk = (bool) data_get($resultData, 'ok', data_get($resultData, 'diff.ok', false));
        $amountDecimalPlaces = max(0, min(6, (int) ($amountDecimalPlaces ?? 0)));
        $scenarioStateRows = is_array($scenarioStateRows ?? null) ? $scenarioStateRows : [];
        $scenarioStateSummary = is_array($scenarioStateSummary ?? null) ? $scenarioStateSummary : [];
        $scenarioStateFilePath = (string) ($scenarioStateFilePath ?? '');
        $scenarioErrorLogsRouteTemplate = (string) ($scenarioErrorLogsRouteTemplate ?? '');
        $focusTarget = trim((string) ($focusTarget ?? ''));
        $selectedScenarioState = (array) data_get($scenarioStateRows, $selectedScenario, []);
        $scenarioStatusLabels = [
            'not_run' => trans('accounting::accounting.scenario_runner.statuses.not_run'),
            'success' => trans('accounting::accounting.scenario_runner.statuses.success'),
            'failed' => trans('accounting::accounting.scenario_runner.statuses.failed'),
            'mixed' => trans('accounting::accounting.scenario_runner.statuses.mixed'),
            'total_runs_prefix' => trans('accounting::accounting.scenario_runner.selected_status.total_runs_prefix'),
            'success_runs_prefix' => trans('accounting::accounting.scenario_runner.selected_status.success_runs_prefix'),
            'failed_runs_prefix' => trans('accounting::accounting.scenario_runner.selected_status.failed_runs_prefix'),
            'last_run_prefix' => trans('accounting::accounting.scenario_runner.selected_status.last_run_prefix'),
        ];
        $scenarioErrorLogConfig = [
            'route_template' => $scenarioErrorLogsRouteTemplate,
            'show_with_count' => trans('accounting::accounting.scenario_runner.progress.show_errors_with_count'),
            'hide' => trans('accounting::accounting.scenario_runner.progress.hide_errors'),
            'loading' => trans('accounting::accounting.scenario_runner.progress.loading_errors'),
            'empty' => trans('accounting::accounting.scenario_runner.progress.no_errors'),
            'failed' => trans('accounting::accounting.scenario_runner.progress.load_errors_failed'),
            'at_prefix' => trans('accounting::accounting.scenario_runner.progress.error_time_prefix'),
        ];
        $errorFlash = trim((string) session('error', ''));
        $errorFlashUrl = '';
        $errorFlashText = $errorFlash;
        if ($errorFlash !== '' && preg_match('/https?:\/\/[^\s]+/u', $errorFlash, $matches) === 1) {
            $errorFlashUrl = trim((string) ($matches[0] ?? ''));
            if ($errorFlashUrl !== '') {
                $errorFlashText = trim((string) preg_replace('/\s*https?:\/\/[^\s]+\s*/u', ' ', $errorFlash));
            }
        }
    @endphp

    <div class="card border-primary border-opacity-25 shadow-sm mb-3">
        <div class="card-header">
            <h5 class="mb-1">{{ trans('accounting::accounting.scenario_runner.page_title') }}</h5>
            <div class="text-muted small">{{ trans('accounting::accounting.scenario_runner.page_hint') }}</div>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if($errorFlash !== '')
                <div class="alert alert-danger d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>{{ $errorFlashText !== '' ? $errorFlashText : $errorFlash }}</div>
                    @if($errorFlashUrl !== '')
                        <a href="{{ $errorFlashUrl }}"
                           target="_blank"
                           rel="noopener"
                           class="btn btn-sm btn-danger">
                            {{ trans('accounting::accounting.scenario_runner.actions.open_expense_categories') }}
                        </a>
                    @endif
                </div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger">
                    <div class="fw-semibold mb-1">خطاهای اعتبارسنجی:</div>
                    <ul class="mb-0 ps-3">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <script type="application/json" id="scenario-definitions-data">@json($scenarios)</script>
            <script type="application/json" id="scenario-entity-options-data">@json($entityOptionsData)</script>
            <script type="application/json" id="scenario-status-data">@json($scenarioStateRows)</script>
            <script type="application/json" id="scenario-status-labels">@json($scenarioStatusLabels)</script>
            <script type="application/json" id="scenario-error-log-config">@json($scenarioErrorLogConfig)</script>
            @if(is_array($previewData))
                <div class="card border-warning border-opacity-25 shadow-sm mb-3" id="scenario-preview-result">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h6 class="mb-0">{{ trans('accounting::accounting.scenario_runner.preview.title') }}</h6>
                        <button type="button"
                                class="btn btn-sm btn-outline-secondary"
                                data-bs-toggle="collapse"
                                data-bs-target="#scenario-preview-result-body"
                                aria-expanded="true"
                                aria-controls="scenario-preview-result-body">
                            {{ trans('accounting::accounting.scenario_runner.actions.collapse_expand') }}
                        </button>
                    </div>
                    <div class="collapse show" id="scenario-preview-result-body">
                    <div class="card-body">
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-bordered align-middle">
                                <thead>
                                <tr>
                                    <th>{{ trans('accounting::accounting.scenario_runner.preview.columns.check') }}</th>
                                    <th>{{ trans('accounting::accounting.scenario_runner.preview.columns.status') }}</th>
                                    <th>{{ trans('accounting::accounting.scenario_runner.preview.columns.message') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach((array) data_get($previewData, 'precheck', []) as $check)
                                    <tr>
                                        <td>{{ (string) data_get($check, 'key', '—') }}</td>
                                        <td>
                                            @if(data_get($check, 'ok'))
                                                <span class="badge bg-success">{{ trans('accounting::accounting.common.yes') }}</span>
                                            @else
                                                <span class="badge bg-danger">{{ trans('accounting::accounting.common.no') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div>{{ (string) data_get($check, 'message', '') }}</div>
                                            @if((string) data_get($check, 'action_url', '') !== '')
                                                <a href="{{ (string) data_get($check, 'action_url', '') }}"
                                                   target="_blank"
                                                   rel="noopener"
                                                   class="btn btn-sm btn-outline-danger mt-2">
                                                    {{ (string) data_get($check, 'action_label', trans('accounting::accounting.scenario_runner.actions.open_expense_categories')) }}
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        <h6 class="mb-2">{{ trans('accounting::accounting.scenario_runner.preview.expected_movements') }}</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>{{ trans('accounting::accounting.scenario_runner.common.account') }}</th>
                                    <th class="text-end">{{ trans('accounting::accounting.scenario_runner.common.debit') }}</th>
                                    <th class="text-end">{{ trans('accounting::accounting.scenario_runner.common.credit') }}</th>
                                    <th class="text-end">{{ trans('accounting::accounting.scenario_runner.common.net_delta') }}</th>
                                    <th>{{ trans('accounting::accounting.scenario_runner.common.note') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach((array) data_get($previewData, 'expected_entries', []) as $entry)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ (string) data_get($entry, 'account_code', '-') }}</div>
                                            <div class="small text-muted">{{ (string) data_get($entry, 'account_name', '') }}</div>
                                            @php
                                                $counterpartyName = trim((string) data_get($entry, 'counterparty_name', ''));
                                            @endphp
                                            @if($counterpartyName !== '')
                                                <div class="small text-primary-emphasis">طرف حساب: {{ $counterpartyName }}</div>
                                            @endif
                                        </td>
                                        <td class="text-end">{{ number_format((float) data_get($entry, 'debit', 0), $amountDecimalPlaces, '.', ',') }}</td>
                                        <td class="text-end">{{ number_format((float) data_get($entry, 'credit', 0), $amountDecimalPlaces, '.', ',') }}</td>
                                        <td class="text-end">{{ number_format((float) data_get($entry, 'expected_delta', 0), $amountDecimalPlaces, '.', ',') }}</td>
                                        <td>{{ (string) data_get($entry, 'note', '') }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    </div>
                </div>
            @endif

            @if(is_array($resultData))
                <div class="card {{ $diffOk ? 'border-success' : 'border-danger' }} border-opacity-25 shadow-sm mb-3" id="scenario-apply-result">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">{{ trans('accounting::accounting.scenario_runner.result.title') }}</h6>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge {{ $diffOk ? 'bg-success' : 'bg-danger' }}">
                                {{ $diffOk ? trans('accounting::accounting.scenario_runner.result.pass') : trans('accounting::accounting.scenario_runner.result.fail') }}
                            </span>
                            <button type="button"
                                    class="btn btn-sm btn-outline-secondary"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#scenario-apply-result-body"
                                    aria-expanded="true"
                                    aria-controls="scenario-apply-result-body">
                                {{ trans('accounting::accounting.scenario_runner.actions.collapse_expand') }}
                            </button>
                        </div>
                    </div>
                    <div class="collapse show" id="scenario-apply-result-body">
                    <div class="card-body">
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-bordered align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>{{ trans('accounting::accounting.scenario_runner.common.account') }}</th>
                                    <th class="text-end">{{ trans('accounting::accounting.scenario_runner.common.expected_delta') }}</th>
                                    <th class="text-end">{{ trans('accounting::accounting.scenario_runner.common.actual_delta') }}</th>
                                    <th class="text-end">{{ trans('accounting::accounting.scenario_runner.common.difference') }}</th>
                                    <th>{{ trans('accounting::accounting.scenario_runner.common.status') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach((array) data_get($resultData, 'diff.rows', []) as $row)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ (string) data_get($row, 'account_code', '-') }}</div>
                                            <div class="small text-muted">{{ (string) data_get($row, 'account_name', '') }}</div>
                                        </td>
                                        <td class="text-end">{{ number_format((float) data_get($row, 'expected_delta', 0), $amountDecimalPlaces, '.', ',') }}</td>
                                        <td class="text-end">{{ number_format((float) data_get($row, 'actual_delta', 0), $amountDecimalPlaces, '.', ',') }}</td>
                                        <td class="text-end">{{ number_format((float) data_get($row, 'difference', 0), $amountDecimalPlaces, '.', ',') }}</td>
                                        <td>
                                            <span class="badge {{ data_get($row, 'pass') ? 'bg-success' : 'bg-danger' }}">
                                                {{ data_get($row, 'pass') ? trans('accounting::accounting.common.yes') : trans('accounting::accounting.common.no') }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if(!empty((array) data_get($resultData, 'post_checks.rows', [])))
                            <h6 class="mb-2">{{ trans('accounting::accounting.scenario_runner.result.post_checks') }}</h6>
                            <div class="table-responsive mb-3">
                                <table class="table table-sm table-bordered align-middle mb-0">
                                    <thead>
                                    <tr>
                                        <th>{{ trans('accounting::accounting.scenario_runner.preview.columns.check') }}</th>
                                        <th>{{ trans('accounting::accounting.scenario_runner.preview.columns.status') }}</th>
                                        <th>{{ trans('accounting::accounting.scenario_runner.preview.columns.message') }}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach((array) data_get($resultData, 'post_checks.rows', []) as $check)
                                        <tr>
                                            <td>{{ (string) data_get($check, 'key', '—') }}</td>
                                            <td>
                                                <span class="badge {{ data_get($check, 'ok') ? 'bg-success' : 'bg-danger' }}">
                                                    {{ data_get($check, 'ok') ? trans('accounting::accounting.common.yes') : trans('accounting::accounting.common.no') }}
                                                </span>
                                            </td>
                                            <td>
                                                <div>{{ (string) data_get($check, 'message', '') }}</div>
                                                @if((string) data_get($check, 'details', '') !== '')
                                                    <div class="small text-muted">{{ (string) data_get($check, 'details', '') }}</div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        <h6 class="mb-2">{{ trans('accounting::accounting.scenario_runner.result.documents') }}</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>{{ trans('accounting::accounting.scenario_runner.common.document_type') }}</th>
                                    <th>{{ trans('accounting::accounting.scenario_runner.common.status') }}</th>
                                    <th class="text-end">{{ trans('accounting::accounting.scenario_runner.common.total_debit') }}</th>
                                    <th class="text-end">{{ trans('accounting::accounting.scenario_runner.common.total_credit') }}</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach((array) data_get($resultData, 'documents', []) as $doc)
                                    <tr>
                                        <td>{{ (int) data_get($doc, 'id', 0) }}</td>
                                        <td>{{ (string) data_get($doc, 'document_type', '') }}</td>
                                        <td>{{ (string) data_get($doc, 'status', '') }}</td>
                                        <td class="text-end">{{ number_format((float) data_get($doc, 'total_debit', 0), $amountDecimalPlaces, '.', ',') }}</td>
                                        <td class="text-end">{{ number_format((float) data_get($doc, 'total_credit', 0), $amountDecimalPlaces, '.', ',') }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('admin.accounting.documents.show', ['document' => (int) data_get($doc, 'id', 0)]) }}" class="btn btn-sm btn-outline-primary">
                                                {{ trans('accounting::accounting.scenario_runner.actions.open_document') }}
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    </div>
                </div>
            @endif

            <div class="d-flex flex-wrap gap-2 mb-2 scenario-runner-actions" id="scenario-form-actions">
                <button type="button" id="scenario-sample-fill-btn" class="btn btn-light">
                    {{ trans('accounting::accounting.scenario_runner.actions.fill_sample') }}
                </button>
                <button type="submit" form="scenario-runner-form" class="btn btn-outline-primary">
                    {{ trans('accounting::accounting.scenario_runner.actions.preview') }}
                </button>
                <button type="submit" form="scenario-runner-run-form" class="btn btn-primary" @disabled(!$canRun)>
                    {{ trans('accounting::accounting.scenario_runner.actions.run') }}
                </button>
                <button type="submit"
                        form="scenario-runner-reset-form"
                        class="btn btn-outline-danger"
                        data-reset-confirm="{{ trans('accounting::accounting.scenario_runner.actions.reset_confirm') }}">
                    {{ trans('accounting::accounting.scenario_runner.actions.reset_all') }}
                </button>
            </div>

            <form id="scenario-runner-form" method="post" action="{{ $previewRoute }}" data-focus-target="{{ $focusTarget }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">{{ trans('accounting::accounting.scenario_runner.form.scenario_search') }}</label>
                        <input type="text"
                               id="scenario-filter-input"
                               class="form-control"
                               autocomplete="off"
                               placeholder="{{ trans('accounting::accounting.scenario_runner.form.scenario_search_placeholder') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ trans('accounting::accounting.scenario_runner.form.scenario_status_filter') }}</label>
                        <select class="form-select" id="scenario-status-filter">
                            <option value="all">{{ trans('accounting::accounting.scenario_runner.status_filter.all') }}</option>
                            <option value="not_run">{{ trans('accounting::accounting.scenario_runner.statuses.not_run') }}</option>
                            <option value="success">{{ trans('accounting::accounting.scenario_runner.statuses.success') }}</option>
                            <option value="failed">{{ trans('accounting::accounting.scenario_runner.statuses.failed') }}</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ trans('accounting::accounting.scenario_runner.form.scenario') }}</label>
                        <select class="form-select{{ $errorClass('scenario_key') }}" name="scenario_key" id="scenario-key-select" required>
                            @foreach($scenarios as $scenarioKey => $meta)
                                <option value="{{ $scenarioKey }}" @selected($selectedScenario === $scenarioKey)>
                                    {{ data_get($meta, 'title', $scenarioKey) }}
                                </option>
                            @endforeach
                        </select>
                        @error('scenario_key')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-12">
                        <div class="scenario-runner-selected-status"
                             id="scenario-selected-status"
                             data-scenario-selection-summary
                             data-default-status="{{ (string) data_get($selectedScenarioState, 'status', 'not_run') }}"
                             data-default-total-runs="{{ (int) data_get($selectedScenarioState, 'total_runs', 0) }}"
                             data-default-success-runs="{{ (int) data_get($selectedScenarioState, 'success_runs', 0) }}"
                             data-default-failed-runs="{{ (int) data_get($selectedScenarioState, 'failed_runs', 0) }}"
                             data-default-last-run-at="{{ (string) data_get($selectedScenarioState, 'last_run_at', '') }}"
                             data-default-last-message="{{ (string) data_get($selectedScenarioState, 'last_message', '') }}">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span class="text-muted">{{ trans('accounting::accounting.scenario_runner.selected_status.label') }}</span>
                                <span class="badge bg-secondary" data-role="status-label">
                                    {{ trans('accounting::accounting.scenario_runner.statuses.'.(string) data_get($selectedScenarioState, 'status', 'not_run')) }}
                                </span>
                                <span class="badge bg-light text-dark" data-role="runs-count">
                                    {{ trans('accounting::accounting.scenario_runner.selected_status.total_runs', ['count' => (int) data_get($selectedScenarioState, 'total_runs', 0)]) }}
                                </span>
                                <span class="badge bg-light text-dark" data-role="success-count">
                                    {{ trans('accounting::accounting.scenario_runner.selected_status.success_runs', ['count' => (int) data_get($selectedScenarioState, 'success_runs', 0)]) }}
                                </span>
                                <span class="badge bg-light text-dark" data-role="failed-count">
                                    {{ trans('accounting::accounting.scenario_runner.selected_status.failed_runs', ['count' => (int) data_get($selectedScenarioState, 'failed_runs', 0)]) }}
                                </span>
                                <span class="badge bg-light text-dark" data-role="last-run-at">
                                    {{ trans('accounting::accounting.scenario_runner.selected_status.last_run_at', ['value' => (string) data_get($selectedScenarioState, 'last_run_at', '—')]) }}
                                </span>
                            </div>
                            <div class="small text-muted mt-2 d-none" data-role="last-message"></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ trans('accounting::accounting.scenario_runner.form.amount') }}</label>
                        <input type="text" class="form-control js-accounting-amount-input{{ $errorClass('amount') }}" inputmode="decimal" name="amount" value="{{ old('amount', data_get($f, 'amount', 1000000)) }}" required>
                        @error('amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ trans('accounting::accounting.scenario_runner.form.scenario_date') }}</label>
                        <input type="text"
                               class="form-control accounting-date-field persian-datepicker{{ $errorClass('scenario_date') }}"
                               data-calendar="jalali"
                               data-persian-date
                               data-format="YYYY-MM-DD"
                               name="scenario_date"
                               value="{{ old('scenario_date', data_get($f, 'scenario_date', now()->toDateString())) }}"
                               required>
                        @error('scenario_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">{{ trans('accounting::accounting.scenario_runner.form.notes') }}</label>
                        <input type="text" class="form-control{{ $errorClass('notes') }}" name="notes" maxlength="1000" value="{{ old('notes', data_get($f, 'notes', '')) }}">
                        @error('notes')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="border rounded p-3 mt-3 scenario-profile-block" data-profile-target="common_customer_supplier">
                    <div class="fw-semibold mb-2">{{ trans('accounting::accounting.scenario_runner.form.entity_selection') }}</div>
                    <div class="row g-3">
                        <div class="col-md-6 scenario-field-error{{ $errors->has('customer_id') ? ' has-error' : '' }}" data-scenario-field-key="customer_id">
                            <x-accounting::sales-customer-picker
                                name="customer_id"
                                id="scenario-customer-id"
                                :required="false"
                                :search-url="$customerSearchUrl"
                                :selected-id="$customerSelectedId"
                                :selected-customer-id="$customerSelectedId"
                                :selected-text="$customerSelectedText"
                                :currency-options="$currencyOptions ?? []"
                                :label="trans('accounting::accounting.scenario_runner.form.customer')"
                            />
                            @error('customer_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6 scenario-field-error{{ $errors->has('supplier_id') ? ' has-error' : '' }}" data-scenario-field-key="supplier_id">
                            <label class="form-label">{{ trans('accounting::accounting.scenario_runner.form.supplier') }}</label>
                            <div class="card border-primary border-opacity-50 js-accounting-card-picker"
                                 data-search-url="{{ $supplierSearchUrl }}"
                                 data-placeholder="{{ trans('accounting::accounting.scenario_runner.form.select_placeholder') }}"
                                 data-initial-id="{{ $supplierSelectedId }}"
                                 data-initial-text="{{ $supplierSelectedText !== '' ? $supplierSelectedText : ($supplierSelectedId !== '' ? '#'.$supplierSelectedId : '') }}">
                                <div class="card-body p-3">
                                    <div class="d-flex align-items-center justify-content-between d-none mb-2" data-selected-box>
                                        <div>
                                            <div class="fw-semibold text-success" data-selected-text></div>
                                            <small class="text-muted" data-selected-id></small>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-light" data-clear-selection><i class="ph-x"></i></button>
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="ph-magnifying-glass"></i></span>
                                        <input type="text"
                                               class="form-control"
                                               data-search-input
                                               autocomplete="off"
                                               placeholder="{{ trans('accounting::accounting.scenario_runner.form.select_placeholder') }}">
                                        <input type="hidden" name="supplier_id" value="{{ $supplierSelectedId }}">
                                    </div>
                                    <div class="list-group mt-2 d-none" data-search-results></div>
                                </div>
                            </div>
                            @error('supplier_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="border rounded p-3 mt-3 scenario-profile-block" data-profile-target="treasury_entities">
                    <div class="fw-semibold mb-2">{{ trans('accounting::accounting.scenario_runner.form.treasury_selection') }}</div>
                    <div class="row g-3">
                        <div class="col-md-12 scenario-field-error{{ $errors->has('payment_method_id') || $errors->has('bank_id') || $errors->has('cash_box_id') || $errors->has('wallet_id') ? ' has-error' : '' }}" data-scenario-field-key="payment_method_id bank_id cash_box_id wallet_id">
                            <x-accounting::payment-destination-picker
                                context="customer_payment"
                                :catalog-url="route('admin.accounting.ajax.payment-destinations')"
                                name-prefix=""
                                :initial-payment-method-id="old('payment_method_id', data_get($f, 'payment_method_id', ''))"
                                :initial-bank-id="old('bank_id', data_get($f, 'bank_id', ''))"
                                :initial-cash-box-id="old('cash_box_id', data_get($f, 'cash_box_id', ''))"
                                :initial-wallet-id="old('wallet_id', data_get($f, 'wallet_id', ''))"
                                :initial-cheque-id="0"
                                :initial-pos-terminal-id="0"
                                :setup-routes="[
                                    'banks' => route('admin.accounting.banks.create'),
                                    'cashboxes' => route('admin.accounting.cashboxes.create'),
                                    'cheques' => route('admin.accounting.cheques.create'),
                                    'pos-terminals' => route('admin.accounting.pos-terminals.create'),
                                    'wallets' => route('admin.accounting.wallets.create'),
                                    'payment-methods' => route('admin.accounting.payment-methods.create'),
                                ]"
                            />
                            @error('payment_method_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            @error('bank_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            @error('cash_box_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            @error('wallet_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6" data-scenario-field-key="chequebook_id">
                            <label class="form-label">{{ trans('accounting::accounting.scenario_runner.form.chequebook') }}</label>
                            <select class="form-select{{ $errorClass('chequebook_id') }}" name="chequebook_id">
                                <option value="">{{ trans('accounting::accounting.scenario_runner.form.select_placeholder') }}</option>
                                @foreach((array) data_get($entityOptionsData, 'chequebooks', []) as $chequebook)
                                    <option value="{{ (int) data_get($chequebook, 'id') }}" @selected((int) old('chequebook_id', data_get($f, 'chequebook_id', 0)) === (int) data_get($chequebook, 'id'))>
                                        {{ $optionLabel($chequebook, '#'.(int) data_get($chequebook, 'id')) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('chequebook_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="border rounded p-3 mt-3 scenario-profile-block" data-profile-target="other_entities">
                    <div class="fw-semibold mb-2">{{ trans('accounting::accounting.scenario_runner.form.accounting_entities') }}</div>
                    <div class="row g-3">
                        <div class="col-md-4" data-scenario-field-key="expense_category_id">
                            <label class="form-label">{{ trans('accounting::accounting.scenario_runner.form.expense_category') }}</label>
                            <select class="form-select{{ $errorClass('expense_category_id') }}" name="expense_category_id">
                                <option value="">{{ trans('accounting::accounting.scenario_runner.form.select_placeholder') }}</option>
                                @foreach((array) data_get($entityOptionsData, 'expense_categories', []) as $category)
                                    <option value="{{ (int) data_get($category, 'id') }}" @selected((int) old('expense_category_id', data_get($f, 'expense_category_id', 0)) === (int) data_get($category, 'id'))>
                                        {{ $optionLabel($category, '#'.(int) data_get($category, 'id')) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('expense_category_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4" data-scenario-field-key="fixed_asset_category_id">
                            <label class="form-label">{{ trans('accounting::accounting.scenario_runner.form.fixed_asset_category') }}</label>
                            <select class="form-select{{ $errorClass('fixed_asset_category_id') }}" name="fixed_asset_category_id">
                                <option value="">{{ trans('accounting::accounting.scenario_runner.form.select_placeholder') }}</option>
                                @foreach((array) data_get($entityOptionsData, 'fixed_asset_categories', []) as $category)
                                    <option value="{{ (int) data_get($category, 'id') }}" @selected((int) old('fixed_asset_category_id', data_get($f, 'fixed_asset_category_id', 0)) === (int) data_get($category, 'id'))>
                                        {{ $optionLabel($category, '#'.(int) data_get($category, 'id')) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('fixed_asset_category_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4" data-scenario-field-key="shareholder_id">
                            <label class="form-label">{{ trans('accounting::accounting.scenario_runner.form.shareholder') }}</label>
                            <select class="form-select{{ $errorClass('shareholder_id') }}" name="shareholder_id">
                                <option value="">{{ trans('accounting::accounting.scenario_runner.form.select_placeholder') }}</option>
                                @foreach((array) data_get($entityOptionsData, 'shareholders', []) as $shareholder)
                                    <option value="{{ (int) data_get($shareholder, 'id') }}" @selected((int) old('shareholder_id', data_get($f, 'shareholder_id', 0)) === (int) data_get($shareholder, 'id'))>
                                        {{ $optionLabel($shareholder, '#'.(int) data_get($shareholder, 'id')) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('shareholder_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="border rounded p-3 mt-3 scenario-profile-block" data-profile-target="transfer_details">
                    <div class="fw-semibold mb-2">{{ trans('accounting::accounting.scenario_runner.form.transfer_details') }}</div>
                    <div class="row g-3">
                        <div class="col-md-6 scenario-field-error{{ $errors->has('from_treasury_type') || $errors->has('from_treasury_id') ? ' has-error' : '' }}" data-scenario-field-key="from_treasury_type from_treasury_id">
                            <label class="form-label">{{ trans('accounting::accounting.scenario_runner.form.from_treasury') }}</label>
                            <div data-transfer-endpoint-picker="from">
                                <x-accounting::payment-destination-picker
                                    context="customer_payment"
                                    :catalog-url="route('admin.accounting.ajax.payment-destinations')"
                                    name-prefix="transfer_from_"
                                    :initial-payment-method-id="null"
                                    :initial-bank-id="$transferFromInitialBankId"
                                    :initial-cash-box-id="$transferFromInitialCashBoxId"
                                    :initial-wallet-id="$transferFromInitialWalletId"
                                    :initial-cheque-id="null"
                                    :initial-pos-terminal-id="null"
                                    :setup-routes="[
                                        'banks' => route('admin.accounting.banks.create'),
                                        'cashboxes' => route('admin.accounting.cashboxes.create'),
                                        'wallets' => route('admin.accounting.wallets.create'),
                                        'payment-methods' => route('admin.accounting.payment-methods.create'),
                                    ]"
                                />
                            </div>
                            <input type="hidden" name="from_treasury_type" value="{{ $transferFromType }}">
                            <input type="hidden" name="from_treasury_id" value="{{ $transferFromId > 0 ? $transferFromId : '' }}">
                            @error('from_treasury_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            @error('from_treasury_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6 scenario-field-error{{ $errors->has('to_treasury_type') || $errors->has('to_treasury_id') ? ' has-error' : '' }}" data-scenario-field-key="to_treasury_type to_treasury_id">
                            <label class="form-label">{{ trans('accounting::accounting.scenario_runner.form.to_treasury') }}</label>
                            <div data-transfer-endpoint-picker="to">
                                <x-accounting::payment-destination-picker
                                    context="customer_payment"
                                    :catalog-url="route('admin.accounting.ajax.payment-destinations')"
                                    name-prefix="transfer_to_"
                                    :initial-payment-method-id="null"
                                    :initial-bank-id="$transferToInitialBankId"
                                    :initial-cash-box-id="$transferToInitialCashBoxId"
                                    :initial-wallet-id="$transferToInitialWalletId"
                                    :initial-cheque-id="null"
                                    :initial-pos-terminal-id="null"
                                    :setup-routes="[
                                        'banks' => route('admin.accounting.banks.create'),
                                        'cashboxes' => route('admin.accounting.cashboxes.create'),
                                        'wallets' => route('admin.accounting.wallets.create'),
                                        'payment-methods' => route('admin.accounting.payment-methods.create'),
                                    ]"
                                />
                            </div>
                            <input type="hidden" name="to_treasury_type" value="{{ $transferToType }}">
                            <input type="hidden" name="to_treasury_id" value="{{ $transferToId > 0 ? $transferToId : '' }}">
                            @error('to_treasury_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            @error('to_treasury_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3" data-scenario-field-key="transfer_fee">
                            <label class="form-label">{{ trans('accounting::accounting.scenario_runner.form.transfer_fee') }}</label>
                            <input type="text" class="form-control js-accounting-amount-input{{ $errorClass('transfer_fee') }}" inputmode="decimal" name="transfer_fee" value="{{ old('transfer_fee', data_get($f, 'transfer_fee', '0')) }}">
                            @error('transfer_fee')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3" data-scenario-field-key="value_date">
                            <label class="form-label">{{ trans('accounting::accounting.scenario_runner.form.value_date') }}</label>
                            <input type="text"
                                   class="form-control accounting-date-field persian-datepicker{{ $errorClass('value_date') }}"
                                   data-calendar="jalali"
                                   data-persian-date
                                   data-format="YYYY-MM-DD"
                                   name="value_date"
                                   value="{{ old('value_date', data_get($f, 'value_date', data_get($f, 'scenario_date', now()->toDateString()))) }}">
                            @error('value_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3" data-scenario-field-key="from_treasury_type">
                            <label class="form-label">{{ trans('accounting::accounting.scenario_runner.form.reference_number') }}</label>
                            <input type="text" class="form-control{{ $errorClass('reference_number') }}" maxlength="100" name="reference_number" value="{{ old('reference_number', data_get($f, 'reference_number', '')) }}">
                            @error('reference_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3" data-scenario-field-key="to_treasury_type">
                            <label class="form-label">{{ trans('accounting::accounting.scenario_runner.form.description') }}</label>
                            <input type="text" class="form-control{{ $errorClass('description') }}" maxlength="500" name="description" value="{{ old('description', data_get($f, 'description', '')) }}">
                            @error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </form>
            <form method="post" action="{{ $runRoute }}" class="d-none" id="scenario-runner-run-form">
                @csrf
                <input type="hidden" name="scenario_key" value="{{ old('scenario_key', data_get($f, 'scenario_key', '')) }}">
                <input type="hidden" name="amount" value="{{ old('amount', data_get($f, 'amount', 0)) }}">
                <input type="hidden" name="scenario_date" value="{{ old('scenario_date', data_get($f, 'scenario_date', now()->toDateString())) }}">
                <input type="hidden" name="notes" value="{{ old('notes', data_get($f, 'notes', '')) }}">
                <input type="hidden" name="customer_id" value="{{ old('customer_id', data_get($f, 'customer_id', '')) }}">
                <input type="hidden" name="supplier_id" value="{{ old('supplier_id', data_get($f, 'supplier_id', '')) }}">
                <input type="hidden" name="bank_id" value="{{ old('bank_id', data_get($f, 'bank_id', '')) }}">
                <input type="hidden" name="cash_box_id" value="{{ old('cash_box_id', data_get($f, 'cash_box_id', '')) }}">
                <input type="hidden" name="wallet_id" value="{{ old('wallet_id', data_get($f, 'wallet_id', '')) }}">
                <input type="hidden" name="chequebook_id" value="{{ old('chequebook_id', data_get($f, 'chequebook_id', '')) }}">
                <input type="hidden" name="payment_method_id" value="{{ old('payment_method_id', data_get($f, 'payment_method_id', '')) }}">
                <input type="hidden" name="expense_category_id" value="{{ old('expense_category_id', data_get($f, 'expense_category_id', '')) }}">
                <input type="hidden" name="fixed_asset_category_id" value="{{ old('fixed_asset_category_id', data_get($f, 'fixed_asset_category_id', '')) }}">
                <input type="hidden" name="shareholder_id" value="{{ old('shareholder_id', data_get($f, 'shareholder_id', '')) }}">
                <input type="hidden" name="from_treasury_type" value="{{ old('from_treasury_type', data_get($f, 'from_treasury_type', '')) }}">
                <input type="hidden" name="from_treasury_id" value="{{ old('from_treasury_id', data_get($f, 'from_treasury_id', '')) }}">
                <input type="hidden" name="to_treasury_type" value="{{ old('to_treasury_type', data_get($f, 'to_treasury_type', '')) }}">
                <input type="hidden" name="to_treasury_id" value="{{ old('to_treasury_id', data_get($f, 'to_treasury_id', '')) }}">
                <input type="hidden" name="value_date" value="{{ old('value_date', data_get($f, 'value_date', '')) }}">
                <input type="hidden" name="transfer_fee" value="{{ old('transfer_fee', data_get($f, 'transfer_fee', '0')) }}">
                <input type="hidden" name="reference_number" value="{{ old('reference_number', data_get($f, 'reference_number', '')) }}">
                <input type="hidden" name="description" value="{{ old('description', data_get($f, 'description', '')) }}">
            </form>
            <form method="post" action="{{ $resetRoute }}" class="d-none" id="scenario-runner-reset-form">
                @csrf
            </form>

            @if(is_array($scenarioDetails))
                <div class="alert alert-info mt-3 mb-0">
                    <div class="fw-semibold">{{ data_get($scenarioDetails, 'title') }}</div>
                    <div class="small mb-1">{{ data_get($scenarioDetails, 'description') }}</div>
                    <span class="badge bg-primary">{{ data_get($scenarioDetails, 'module') }}</span>
                    <span class="badge bg-secondary ms-1">{{ $selectedCategoryLabel !== 'accounting::accounting.scenario_runner.categories.'.$selectedCategory ? $selectedCategoryLabel : $selectedCategory }}</span>
                    <span class="badge bg-dark ms-1">{{ $selectedProfile }}</span>
                </div>
            @endif
        </div>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-md-2 col-sm-4">
            <div class="scenario-runner-kpi">
                <div class="small text-muted">{{ trans('accounting::accounting.scenario_runner.stats.total') }}</div>
                <div class="fs-5 fw-semibold">{{ (int) data_get($scenarioStateSummary, 'total', 0) }}</div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4">
            <div class="scenario-runner-kpi">
                <div class="small text-muted">{{ trans('accounting::accounting.scenario_runner.stats.executed') }}</div>
                <div class="fs-5 fw-semibold">{{ (int) data_get($scenarioStateSummary, 'executed', 0) }}</div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4">
            <div class="scenario-runner-kpi">
                <div class="small text-muted">{{ trans('accounting::accounting.scenario_runner.stats.success') }}</div>
                <div class="fs-5 fw-semibold text-success">{{ (int) data_get($scenarioStateSummary, 'success', 0) }}</div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4">
            <div class="scenario-runner-kpi">
                <div class="small text-muted">{{ trans('accounting::accounting.scenario_runner.stats.failed') }}</div>
                <div class="fs-5 fw-semibold text-danger">{{ (int) data_get($scenarioStateSummary, 'failed', 0) }}</div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4">
            <div class="scenario-runner-kpi">
                <div class="small text-muted">{{ trans('accounting::accounting.scenario_runner.stats.not_run') }}</div>
                <div class="fs-5 fw-semibold text-secondary">{{ (int) data_get($scenarioStateSummary, 'not_run', 0) }}</div>
            </div>
        </div>
    </div>
    @include('accounting::admin.partials.accounting-date-ui-script')

    <div class="card border-info border-opacity-25 shadow-sm mb-3" id="scenario-progress-report">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h6 class="mb-0">{{ trans('accounting::accounting.scenario_runner.progress.title') }}</h6>
            <span class="badge bg-light text-dark">{{ trans('accounting::accounting.scenario_runner.progress.file_path', ['path' => $scenarioStateFilePath !== '' ? $scenarioStateFilePath : '—']) }}</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0 scenario-runner-table">
                    <thead>
                    <tr>
                        <th>{{ trans('accounting::accounting.scenario_runner.progress.columns.scenario') }}</th>
                        <th>{{ trans('accounting::accounting.scenario_runner.progress.columns.module') }}</th>
                        <th>{{ trans('accounting::accounting.scenario_runner.progress.columns.status') }}</th>
                        <th class="text-end">{{ trans('accounting::accounting.scenario_runner.progress.columns.total_runs') }}</th>
                        <th class="text-end">{{ trans('accounting::accounting.scenario_runner.progress.columns.success_runs') }}</th>
                        <th class="text-end">{{ trans('accounting::accounting.scenario_runner.progress.columns.failed_runs') }}</th>
                        <th class="text-center">{{ trans('accounting::accounting.scenario_runner.progress.columns.error_logs') }}</th>
                        <th>{{ trans('accounting::accounting.scenario_runner.progress.columns.last_run_at') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($scenarios as $scenarioKey => $meta)
                        @php
                            $row = (array) data_get($scenarioStateRows, $scenarioKey, []);
                            $statusKey = (string) data_get($row, 'status', 'not_run');
                            $statusBadgeClass = [
                                'success' => 'bg-success',
                                'failed' => 'bg-danger',
                                'mixed' => 'bg-warning text-dark',
                                'not_run' => 'bg-secondary',
                            ][$statusKey] ?? 'bg-secondary';
                            $errorCount = (int) data_get($row, 'error_count', 0);
                        @endphp
                        <tr class="js-scenario-select-row" data-scenario-key="{{ $scenarioKey }}" title="{{ trans('accounting::accounting.scenario_runner.progress.row_click_hint') }}">
                            <td>
                                <div class="fw-semibold">{{ (string) data_get($meta, 'title', $scenarioKey) }}</div>
                                @if((string) data_get($row, 'last_message', '') !== '')
                                    <div class="small text-muted">{{ (string) data_get($row, 'last_message', '') }}</div>
                                @endif
                            </td>
                            <td>{{ (string) data_get($meta, 'module', '') }}</td>
                            <td><span class="badge {{ $statusBadgeClass }}">{{ trans('accounting::accounting.scenario_runner.statuses.'.$statusKey) }}</span></td>
                            <td class="text-end">{{ (int) data_get($row, 'total_runs', 0) }}</td>
                            <td class="text-end">{{ (int) data_get($row, 'success_runs', 0) }}</td>
                            <td class="text-end">{{ (int) data_get($row, 'failed_runs', 0) }}</td>
                            <td class="text-center">
                                <button type="button"
                                        class="btn btn-sm btn-outline-danger js-scenario-errors-toggle"
                                        data-scenario-key="{{ $scenarioKey }}"
                                        data-error-count="{{ $errorCount }}"
                                        @disabled($errorCount <= 0)>
                                    {{ trans('accounting::accounting.scenario_runner.progress.show_errors_with_count', ['count' => $errorCount]) }}
                                </button>
                            </td>
                            <td>{{ (string) data_get($row, 'last_run_at', '—') !== '' ? (string) data_get($row, 'last_run_at', '—') : '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

