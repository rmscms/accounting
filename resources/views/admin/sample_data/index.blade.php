@extends('cms::admin.layout.index')

@section('title', trans('accounting::accounting.sample_data.page_title'))

@section('content')
    <div class="card">
        <div class="card-header">
            <h5 class="mb-1">{{ trans('accounting::accounting.sample_data.page_title') }}</h5>
            <div class="text-muted small">{{ trans('accounting::accounting.sample_data.page_hint') }}</div>
        </div>

        <div class="card-body">
            @php
                $preflightOk = (bool) data_get($result, 'ok', false);
                $f = is_array($formValues ?? null) ? $formValues : [];
            @endphp
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif
            @if (!$preflightOk)
                <div class="alert alert-warning">
                    {{ trans('accounting::accounting.sample_data.alerts.form_locked_preflight') }}
                </div>
            @endif

            <h6 class="mb-2">{{ trans('accounting::accounting.sample_data.preflight.title') }}</h6>
            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered align-middle">
                    <thead>
                    <tr>
                        <th>{{ trans('accounting::accounting.sample_data.columns.check') }}</th>
                        <th>{{ trans('accounting::accounting.sample_data.columns.status') }}</th>
                        <th>{{ trans('accounting::accounting.sample_data.columns.message') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach(($result['checks'] ?? []) as $check)
                        <tr>
                            <td>{{ (string) ($check['key'] ?? '—') }}</td>
                            <td>
                                @if(!empty($check['ok']))
                                    <span class="badge bg-success">{{ trans('accounting::accounting.common.yes') }}</span>
                                @else
                                    <span class="badge bg-danger">{{ trans('accounting::accounting.common.no') }}</span>
                                @endif
                            </td>
                            <td>
                                {{ (string) ($check['message'] ?? '') }}
                                @if(!empty($check['action_route']) && \Illuminate\Support\Facades\Route::has((string) $check['action_route']))
                                    @php
                                        $query = is_array($check['action_query'] ?? null) ? (array) $check['action_query'] : [];
                                    @endphp
                                    <a class="ms-2" href="{{ route((string) $check['action_route'], $query) }}">
                                        {{ trans('accounting::accounting.sample_data.actions.go_fix') }}
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <form id="sample-data-generate-form" method="post" action="{{ $generateRoute }}">
                @csrf
                <fieldset @disabled(!$preflightOk)>
                    <h6 class="mb-3">{{ trans('accounting::accounting.sample_data.form.title') }}</h6>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">{{ trans('accounting::accounting.sample_data.form.customers_count') }}</label>
                            <input type="number" class="form-control" name="customers_count" min="0" max="200" value="{{ old('customers_count', data_get($f, 'customers_count', 20)) }}">
                            <small class="text-muted">{{ trans('accounting::accounting.sample_data.form.customers_count_hint') }}</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ trans('accounting::accounting.sample_data.form.shared_suppliers_count') }}</label>
                            <input type="number" class="form-control" name="shared_suppliers_count" min="0" max="200" value="{{ old('shared_suppliers_count', data_get($f, 'shared_suppliers_count', 7)) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ trans('accounting::accounting.sample_data.form.purchase_orders_count') }}</label>
                            <input type="number" class="form-control" name="purchase_orders_count" min="0" max="500" value="{{ old('purchase_orders_count', data_get($f, 'purchase_orders_count', 6)) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ trans('accounting::accounting.sample_data.form.supplier_direct_invoices_count') }}</label>
                            <input type="number" class="form-control" name="supplier_direct_invoices_count" min="0" max="500" value="{{ old('supplier_direct_invoices_count', data_get($f, 'supplier_direct_invoices_count', 8)) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ trans('accounting::accounting.sample_data.form.sales_invoices_count') }}</label>
                            <input type="number" class="form-control" name="sales_invoices_count" min="0" max="500" value="{{ old('sales_invoices_count', data_get($f, 'sales_invoices_count', 12)) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ trans('accounting::accounting.sample_data.form.customer_payments_count') }}</label>
                            <input type="number" class="form-control" name="customer_payments_count" min="0" max="500" value="{{ old('customer_payments_count', data_get($f, 'customer_payments_count', 8)) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ trans('accounting::accounting.sample_data.form.customer_cheque_payments_count') }}</label>
                            <input type="number" class="form-control" name="customer_cheque_payments_count" min="0" max="500" value="{{ old('customer_cheque_payments_count', data_get($f, 'customer_cheque_payments_count', 4)) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ trans('accounting::accounting.sample_data.form.supplier_payments_count') }}</label>
                            <input type="number" class="form-control" name="supplier_payments_count" min="0" max="500" value="{{ old('supplier_payments_count', data_get($f, 'supplier_payments_count', 7)) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ trans('accounting::accounting.sample_data.form.supplier_cheque_payments_count') }}</label>
                            <input type="number" class="form-control" name="supplier_cheque_payments_count" min="0" max="500" value="{{ old('supplier_cheque_payments_count', data_get($f, 'supplier_cheque_payments_count', 4)) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ trans('accounting::accounting.sample_data.form.expenses_count') }}</label>
                            <input type="number" class="form-control" name="expenses_count" min="0" max="500" value="{{ old('expenses_count', data_get($f, 'expenses_count', 8)) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ trans('accounting::accounting.sample_data.form.fixed_assets_count') }}</label>
                            <input type="number" class="form-control" name="fixed_assets_count" min="0" max="500" value="{{ old('fixed_assets_count', data_get($f, 'fixed_assets_count', 6)) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ trans('accounting::accounting.sample_data.form.shareholders_count') }}</label>
                            <input type="number" class="form-control" name="shareholders_count" min="1" max="50" value="{{ old('shareholders_count', data_get($f, 'shareholders_count', 2)) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ trans('accounting::accounting.sample_data.form.capital_contributions_min') }}</label>
                            <input type="number" class="form-control" name="capital_contributions_min" min="1" max="50" value="{{ old('capital_contributions_min', data_get($f, 'capital_contributions_min', 3)) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ trans('accounting::accounting.sample_data.form.capital_contributions_max') }}</label>
                            <input type="number" class="form-control" name="capital_contributions_max" min="1" max="50" value="{{ old('capital_contributions_max', data_get($f, 'capital_contributions_max', 5)) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ trans('accounting::accounting.sample_data.form.withdrawals_min') }}</label>
                            <input type="number" class="form-control" name="withdrawals_min" min="1" max="50" value="{{ old('withdrawals_min', data_get($f, 'withdrawals_min', 2)) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ trans('accounting::accounting.sample_data.form.withdrawals_max') }}</label>
                            <input type="number" class="form-control" name="withdrawals_max" min="1" max="50" value="{{ old('withdrawals_max', data_get($f, 'withdrawals_max', 4)) }}">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label d-block">{{ trans('accounting::accounting.sample_data.form.mode') }}</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="mode" id="sample-mode-append" value="append" @checked(old('mode', 'rebuild') === 'append')>
                            <label class="form-check-label" for="sample-mode-append">{{ trans('accounting::accounting.sample_data.form.mode_append') }}</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="mode" id="sample-mode-rebuild" value="rebuild" @checked(old('mode', 'rebuild') !== 'append')>
                            <label class="form-check-label" for="sample-mode-rebuild">{{ trans('accounting::accounting.sample_data.form.mode_rebuild') }}</label>
                        </div>
                        <small class="d-block text-muted">{{ trans('accounting::accounting.sample_data.form.mode_hint') }}</small>
                    </div>

                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" value="1" id="wipe-all-before-generate" name="wipe_all_before_generate" @checked(old('wipe_all_before_generate') == 1)>
                        <label class="form-check-label text-danger" for="wipe-all-before-generate">
                            {{ trans('accounting::accounting.sample_data.actions.wipe_all_before_generate') }}
                        </label>
                    </div>
                </fieldset>
            </form>

            @if (is_array($summary))
                <h6 class="mb-2">{{ trans('accounting::accounting.sample_data.summary.title') }}</h6>
                <ul class="mb-0">
                    @foreach($summary as $key => $count)
                        <li>{{ $key }}: {{ (int) $count }}</li>
                    @endforeach
                </ul>
            @endif

            <div class="border-top mt-4 pt-3 d-flex flex-wrap align-items-center gap-2">
                <form method="post" action="{{ $preflightRoute }}" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-light border-primary text-primary">
                        {{ trans('accounting::accounting.sample_data.actions.preflight') }}
                    </button>
                </form>

                <button type="submit" form="sample-data-generate-form" class="btn btn-primary" @disabled(!$preflightOk)>
                    {{ trans('accounting::accounting.sample_data.actions.generate') }}
                </button>

                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#sampleDataFullWipeModal">
                    {{ trans('accounting::accounting.sample_data.actions.reset_db_open_wizard') }}
                </button>
            </div>
        </div>
    </div>

    <form id="sample-data-full-wipe-form" method="post" action="{{ $wipeRoute }}" class="d-none">
        @csrf
        <input type="hidden" name="confirm_full_wipe" value="1">
        <input type="hidden" name="redirect_to_install" value="1">
    </form>

    <div class="modal fade" id="sampleDataFullWipeModal" tabindex="-1" aria-labelledby="sampleDataFullWipeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title text-danger" id="sampleDataFullWipeModalLabel">
                        {{ trans('accounting::accounting.sample_data.modal.full_wipe.title') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ trans('accounting::accounting.sample_data.modal.full_wipe.close_aria') }}"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">{{ trans('accounting::accounting.sample_data.modal.full_wipe.body') }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        {{ trans('accounting::accounting.sample_data.modal.full_wipe.cancel') }}
                    </button>
                    <button type="submit" class="btn btn-danger" form="sample-data-full-wipe-form">
                        {{ trans('accounting::accounting.sample_data.modal.full_wipe.confirm') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

