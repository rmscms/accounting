@extends('cms::admin.layout.index')
@section('title', trans('accounting::accounting.bank_reconciliation.page_title'))
@section('content')
@php
    $statementDateDisplay = old('statement_date');
    $statementDateDisplay = ($statementDateDisplay !== null && trim((string) $statementDateDisplay) !== '')
        ? trim(\RMS\Helper\changeNumberToEn((string) $statementDateDisplay))
        : ($defaultStatementDateDisplay ?? \RMS\Accounting\Support\AccountingDateUi::gregorianYmdToInputDisplay(now()->format('Y-m-d')));
@endphp
<script type="application/json" id="br-format-config">@json(['decimal_places' => (int) ($decimalPlaces ?? 0)])</script>
<div class="container-fluid" id="bank-recon-workspace"
     data-url-open-session="{{ route('admin.accounting.bank-reconciliation.session') }}"
     data-url-load-session-template="{{ route('admin.accounting.bank-reconciliation.session.load', ['session' => '__SESSION__']) }}"
     data-url-delete-session-template="{{ route('admin.accounting.bank-reconciliation.session.delete', ['session' => '__SESSION__']) }}"
     data-url-add-item-template="{{ route('admin.accounting.bank-reconciliation.items.add', ['session' => '__SESSION__']) }}"
     data-url-delete-item-template="{{ route('admin.accounting.bank-reconciliation.items.delete', ['session' => '__SESSION__', 'itemId' => '__ITEM__']) }}"
     data-url-validate-template="{{ route('admin.accounting.bank-reconciliation.validate', ['session' => '__SESSION__']) }}"
     data-url-finalize-template="{{ route('admin.accounting.bank-reconciliation.finalize', ['session' => '__SESSION__']) }}"
     data-url-outstanding-template="{{ route('admin.accounting.bank-reconciliation.candidates.outstanding-cheques', ['session' => '__SESSION__']) }}"
     data-url-dit-template="{{ route('admin.accounting.bank-reconciliation.candidates.deposits-in-transit', ['session' => '__SESSION__']) }}"
     data-url-attach-template="{{ route('admin.accounting.bank-reconciliation.attachments.upload', ['session' => '__SESSION__']) }}"
     data-text-journal-link="{{ trans('accounting::accounting.bank_reconciliation.journal_link_label') }}"
     data-text-journal-draft="{{ trans('accounting::accounting.bank_reconciliation.journal_draft_ready') }}"
     data-text-open-session-first="{{ trans('accounting::accounting.bank_reconciliation.open_session_first_error') }}"
     data-text-session-finalized-locked="{{ trans('accounting::accounting.bank_reconciliation.session_finalized_locked_error') }}"
     data-text-sync-success="{{ trans('accounting::accounting.bank_reconciliation.sync_session_success') }}"
     data-text-sync-session-btn="{{ trans('accounting::accounting.bank_reconciliation.sync_session_btn') }}"
     data-text-sync-session-dirty-btn="{{ trans('accounting::accounting.bank_reconciliation.sync_session_dirty_btn') }}"
     data-text-candidates-outstanding-title="{{ trans('accounting::accounting.bank_reconciliation.candidates_outstanding_title') }}"
     data-text-candidates-dit-title="{{ trans('accounting::accounting.bank_reconciliation.candidates_dit_title') }}"
     data-text-candidates-loading="{{ trans('accounting::accounting.bank_reconciliation.candidates_loading') }}"
     data-text-candidates-empty="{{ trans('accounting::accounting.bank_reconciliation.candidates_empty') }}"
     data-text-delete-title="{{ trans('accounting::accounting.bank_reconciliation.delete_confirm_title') }}"
     data-text-delete-message="{{ trans('accounting::accounting.bank_reconciliation.delete_confirm_message') }}"
     data-text-delete-button="{{ trans('accounting::accounting.bank_reconciliation.delete_confirm_button') }}"
     data-text-live-syncing="{{ trans('accounting::accounting.bank_reconciliation.live_syncing') }}"
     data-text-live-synced="{{ trans('accounting::accounting.bank_reconciliation.live_synced') }}"
     data-text-live-action-add="{{ trans('accounting::accounting.bank_reconciliation.live_action_add') }}"
     data-text-live-action-delete="{{ trans('accounting::accounting.bank_reconciliation.live_action_delete') }}"
     data-text-live-action-candidates="{{ trans('accounting::accounting.bank_reconciliation.live_action_candidates') }}"
     data-text-live-action-upload="{{ trans('accounting::accounting.bank_reconciliation.live_action_upload') }}"
     data-text-load-more-items="{{ trans('accounting::accounting.bank_reconciliation.load_more_items_btn') }}"
     data-text-load-more-sessions="{{ trans('accounting::accounting.bank_reconciliation.load_more_sessions_btn') }}"
     data-text-delete-session-title="{{ trans('accounting::accounting.bank_reconciliation.delete_session_confirm_title') }}"
     data-text-delete-session-message="{{ trans('accounting::accounting.bank_reconciliation.delete_session_confirm_message') }}"
     data-text-delete-session-button="{{ trans('accounting::accounting.bank_reconciliation.delete_session_confirm_button') }}"
>
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div>
            <h4 class="mb-1">{{ trans('accounting::accounting.bank_reconciliation.page_title') }}</h4>
            <small class="text-muted">{{ trans('accounting::accounting.bank_reconciliation.page_subtitle') }}</small>
        </div>
        <span class="badge bg-light text-dark px-3 py-2" id="bank-recon-status-badge">{{ trans('accounting::accounting.bank_reconciliation.status_idle') }}</span>
    </div>

    <div class="card shadow-sm border-primary border-opacity-25 mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-lg-3">
                    <label class="form-label">{{ trans('accounting::accounting.bank_reconciliation.bank_label') }}</label>
                    <select id="br-bank-id" class="form-select">
                        <option value="">{{ trans('accounting::accounting.bank_reconciliation.bank_placeholder') }}</option>
                        @foreach($banks as $bank)
                            <option value="{{ $bank->id }}">{{ $bank->label_for_select }}</option>
                        @endforeach
                    </select>
                </div>
                <x-accounting::date-field
                    name="statement_date"
                    :label="trans('accounting::accounting.bank_reconciliation.statement_date_label')"
                    :value="$statementDateDisplay"
                    :required="true"
                    col-class="col-lg-3"
                />
                <div class="col-lg-3">
                    <label class="form-label">{{ trans('accounting::accounting.bank_reconciliation.bank_statement_balance_label') }}</label>
                    <input type="text" id="br-bank-balance" class="form-control js-accounting-amount-input" inputmode="decimal" autocomplete="off" placeholder="0">
                </div>
                <div class="col-lg-3 d-grid gap-2">
                    <button type="button" class="btn btn-primary" id="br-open-session">{{ trans('accounting::accounting.bank_reconciliation.open_session_btn') }}</button>
                    <button type="button" class="btn btn-outline-primary" id="br-sync-session" disabled>{{ trans('accounting::accounting.bank_reconciliation.sync_session_btn') }}</button>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-3 col-md-6">
            <div class="card h-100 border-start border-info border-3">
                <div class="card-body">
                    <small class="text-muted d-block">{{ trans('accounting::accounting.bank_reconciliation.kpi_book_balance') }}</small>
                    <div class="fs-5 fw-semibold" id="kpi-book-balance">0</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card h-100 border-start border-secondary border-3">
                <div class="card-body">
                    <small class="text-muted d-block">{{ trans('accounting::accounting.bank_reconciliation.kpi_bank_balance') }}</small>
                    <div class="fs-5 fw-semibold" id="kpi-bank-balance">0</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card h-100 border-start border-success border-3">
                <div class="card-body">
                    <small class="text-muted d-block">{{ trans('accounting::accounting.bank_reconciliation.kpi_adjusted_book') }}</small>
                    <div class="fs-5 fw-semibold" id="kpi-adjusted-book">0</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card h-100 border-start border-warning border-3">
                <div class="card-body">
                    <small class="text-muted d-block">{{ trans('accounting::accounting.bank_reconciliation.kpi_difference') }}</small>
                    <div class="fs-5 fw-semibold" id="kpi-difference">0</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-6">
            <div class="card h-100 shadow-sm" id="br-quick-add-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>{{ trans('accounting::accounting.bank_reconciliation.quick_add_title') }}</span>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <button type="button" class="btn btn-outline-danger" id="btn-add-bank-charge">{{ trans('accounting::accounting.bank_reconciliation.add_bank_charge_btn') }}</button>
                        <button type="button" class="btn btn-outline-success" id="btn-add-interest">{{ trans('accounting::accounting.bank_reconciliation.add_interest_btn') }}</button>
                        <button type="button" class="btn btn-outline-primary" id="btn-pick-outstanding">{{ trans('accounting::accounting.bank_reconciliation.pick_outstanding_btn') }}</button>
                        <button type="button" class="btn btn-outline-secondary" id="btn-pick-dit">{{ trans('accounting::accounting.bank_reconciliation.pick_dit_btn') }}</button>
                    </div>
                    <div>
                        <label class="form-label">{{ trans('accounting::accounting.bank_reconciliation.attach_statement_label') }}</label>
                        <div class="input-group">
                            <input type="file" id="br-attachment-file" class="form-control" accept="image/jpeg,image/png,image/webp,application/pdf,.pdf">
                            <button type="button" class="btn btn-outline-primary" id="btn-upload-attachment">{{ trans('accounting::accounting.bank_reconciliation.upload_attachment_btn') }}</button>
                        </div>
                        <small class="text-muted">{{ trans('accounting::accounting.bank_reconciliation.attach_statement_hint', ['max' => $attachmentMaxKb ?? 10240]) }}</small>
                    </div>
                    <div class="mt-3" id="attachment-list"></div>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card h-100 shadow-sm" id="br-finalize-card">
                <div class="card-header">{{ trans('accounting::accounting.bank_reconciliation.actions_title') }}</div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-outline-info" id="btn-validate">{{ trans('accounting::accounting.bank_reconciliation.validate_btn') }}</button>
                        <button type="button" class="btn btn-success" id="btn-finalize">{{ trans('accounting::accounting.bank_reconciliation.finalize_btn') }}</button>
                    </div>
                    <div class="alert alert-light border mt-3 mb-0 small" id="validation-hint">
                        {{ trans('accounting::accounting.bank_reconciliation.validation_hint') }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mt-3">
        <div class="card-header">{{ trans('accounting::accounting.bank_reconciliation.items_table_title') }}</div>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>{{ trans('accounting::accounting.bank_reconciliation.col_type') }}</th>
                    <th>{{ trans('accounting::accounting.bank_reconciliation.col_reference') }}</th>
                    <th class="text-end">{{ trans('accounting::accounting.bank_reconciliation.col_amount') }}</th>
                    <th>{{ trans('accounting::accounting.bank_reconciliation.col_effect') }}</th>
                    <th>{{ trans('accounting::accounting.bank_reconciliation.col_desc') }}</th>
                    <th>{{ trans('accounting::accounting.bank_reconciliation.col_actions') }}</th>
                </tr>
                </thead>
                <tbody id="recon-items-body">
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">{{ trans('accounting::accounting.bank_reconciliation.empty_items') }}</td>
                </tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer text-center d-none" id="items-load-more-wrap">
            <button type="button" class="btn btn-outline-primary btn-sm" id="br-items-load-more">
                {{ trans('accounting::accounting.bank_reconciliation.load_more_items_btn') }}
            </button>
        </div>
    </div>

    <div class="card shadow-sm mt-3">
        <div class="card-header">{{ trans('accounting::accounting.bank_reconciliation.recent_sessions_title') }}</div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th>{{ trans('accounting::accounting.bank_reconciliation.col_bank') }}</th>
                    <th>{{ trans('accounting::accounting.bank_reconciliation.col_date') }}</th>
                    <th>{{ trans('accounting::accounting.bank_reconciliation.col_status') }}</th>
                    <th class="text-end">{{ trans('accounting::accounting.bank_reconciliation.col_difference') }}</th>
                    <th class="text-end">{{ trans('accounting::accounting.bank_reconciliation.col_actions') }}</th>
                </tr>
                </thead>
                <tbody id="recent-sessions-body">
                @forelse($sessions as $row)
                    <tr>
                        <td>{{ $row->bank?->label_for_select ?? $row->bank?->name ?? '-' }}</td>
                        <td>{{ \RMS\Helper\persian_date(\Carbon\Carbon::parse((string) $row->statement_date), 'Y/m/d') }}</td>
                        <td>
                            @if((string) $row->status === \RMS\Accounting\Models\BankReconciliation::STATUS_FINALIZED)
                                <span class="badge bg-success">{{ trans('accounting::accounting.bank_reconciliation.status_finalized') }}</span>
                            @else
                                <span class="badge bg-warning text-dark">{{ trans('accounting::accounting.bank_reconciliation.status_draft') }}</span>
                            @endif
                        </td>
                        <td class="text-end">{{ number_format((float) $row->difference_amount, (int) ($decimalPlaces ?? 0)) }}</td>
                        <td class="text-end">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary btn-load-session"
                                data-session-id="{{ (int) $row->id }}"
                                data-bank-id="{{ (int) $row->bank_id }}"
                                data-statement-date="{{ (string) $row->statement_date?->format('Y-m-d') }}"
                                data-statement-date-display="{{ \RMS\Accounting\Support\AccountingDateUi::gregorianYmdToInputDisplay((string) $row->statement_date?->format('Y-m-d')) }}"
                                data-bank-balance="{{ (float) $row->bank_statement_balance }}"
                                data-session-status="{{ (string) $row->status }}"
                            >
                                {{ (string) $row->status === \RMS\Accounting\Models\BankReconciliation::STATUS_DRAFT
                                    ? trans('accounting::accounting.bank_reconciliation.continue_draft_btn')
                                    : trans('accounting::accounting.bank_reconciliation.view_session_btn') }}
                            </button>
                            @if((string) $row->status === \RMS\Accounting\Models\BankReconciliation::STATUS_FINALIZED)
                                <a href="{{ route('admin.accounting.manual-journals.index') }}" class="btn btn-sm btn-outline-secondary">
                                    {{ trans('accounting::accounting.bank_reconciliation.docs_action_btn') }}
                                </a>
                            @else
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger btn-delete-session"
                                    data-session-id="{{ (int) $row->id }}"
                                >
                                    {{ trans('accounting::accounting.bank_reconciliation.delete_session_btn') }}
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-3">{{ trans('accounting::accounting.bank_reconciliation.empty_sessions') }}</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer text-center d-none" id="sessions-load-more-wrap">
            <button type="button" class="btn btn-outline-primary btn-sm" id="br-sessions-load-more">
                {{ trans('accounting::accounting.bank_reconciliation.load_more_sessions_btn') }}
            </button>
        </div>
    </div>

    <div class="modal fade" id="modal-bank-charge" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">{{ trans('accounting::accounting.bank_reconciliation.modal_bank_charge_title') }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">{{ trans('accounting::accounting.bank_reconciliation.col_amount') }}</label>
                    <input type="text" class="form-control js-accounting-amount-input" id="bank-charge-amount" inputmode="decimal">
                    <label class="form-label mt-2">{{ trans('accounting::accounting.bank_reconciliation.col_desc') }}</label>
                    <input type="text" class="form-control" id="bank-charge-desc">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ trans('accounting::accounting.common.cancel') }}</button>
                    <button type="button" class="btn btn-primary" id="save-bank-charge">{{ trans('accounting::accounting.common.save') }}</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modal-interest-income" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">{{ trans('accounting::accounting.bank_reconciliation.modal_interest_title') }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">{{ trans('accounting::accounting.bank_reconciliation.col_amount') }}</label>
                    <input type="text" class="form-control js-accounting-amount-input" id="interest-amount" inputmode="decimal">
                    <label class="form-label mt-2">{{ trans('accounting::accounting.bank_reconciliation.col_desc') }}</label>
                    <input type="text" class="form-control" id="interest-desc">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ trans('accounting::accounting.common.cancel') }}</button>
                    <button type="button" class="btn btn-primary" id="save-interest">{{ trans('accounting::accounting.common.save') }}</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modal-candidates" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title" id="candidate-modal-title">{{ trans('accounting::accounting.bank_reconciliation.candidates_title') }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                            <tr>
                                <th>{{ trans('accounting::accounting.common.select') }}</th>
                                <th>{{ trans('accounting::accounting.bank_reconciliation.col_reference') }}</th>
                                <th>{{ trans('accounting::accounting.bank_reconciliation.col_date') }}</th>
                                <th class="text-end">{{ trans('accounting::accounting.bank_reconciliation.col_amount') }}</th>
                            </tr>
                            </thead>
                            <tbody id="candidate-rows"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ trans('accounting::accounting.common.cancel') }}</button>
                    <button type="button" class="btn btn-primary" id="apply-candidates">{{ trans('accounting::accounting.bank_reconciliation.apply_selected_btn') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
@include('accounting::admin.partials.accounting-date-ui-script')
@endsection

