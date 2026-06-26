{{-- سطرهای بدهکار/بستانکار — فقط ویرایش سند دستی در حالت پیش‌نویس؛ CRUD سطرها با Ajax (بدون رفرش صفحه) --}}
@php
    /** @var \RMS\Accounting\Models\ManualJournal $model */
    $mjAccounts = $manualJournalAccountsSelect ?? [];
    $lineStore = $manualJournalLineStoreRoute ?? null;
    $reverseRoute = $manualJournalReverseRoute ?? null;
    $reverseReasonDefault = $manualJournalReverseDefaultReason ?? '';
    $reversedByJournalId = (int) ($manualJournalReversedByJournalId ?? 0);
    $reversalOfJournalId = (int) ($manualJournalReversalOfJournalId ?? 0);
    $mjAjax = $manualJournalAjax ?? null;
    $isDraft = ($model->status ?? '') === 'draft';
@endphp
@if($isDraft && $lineStore && $mjAjax)
    <div class="card border-0 shadow-sm mt-3" id="manual-journal-lines-card" data-mj-ajax="1">
        <div class="card-header py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h6 class="mb-0 fw-semibold">{{ trans('accounting::accounting.manual_journal_lines.section_title') }}</h6>
            <span class="badge bg-secondary rounded-pill">{{ trans('accounting::accounting.manual_journal_lines.status_draft_hint') }}</span>
        </div>
        <div class="card-body">
            <div id="mj-alert" class="alert d-none mb-3" role="alert"></div>
            <p class="text-muted small mb-3">{{ trans('accounting::accounting.manual_journal_lines.section_intro') }}</p>

            <div class="table-responsive mb-4">
                <table class="table table-sm table-bordered align-middle mb-0" id="mj-lines-table">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center mj-th-num">#</th>
                            <th>{{ trans('accounting::accounting.manual_journal_lines.th_account') }}</th>
                            <th class="text-end mj-th-amount">{{ trans('accounting::accounting.manual_journal_lines.th_debit') }}</th>
                            <th class="text-end mj-th-amount">{{ trans('accounting::accounting.manual_journal_lines.th_credit') }}</th>
                            <th>{{ trans('accounting::accounting.manual_journal_lines.th_desc') }}</th>
                            <th class="text-center mj-th-actions"></th>
                        </tr>
                    </thead>
                    <tbody id="mj-lines-tbody">
                        @forelse($model->lines ?? [] as $line)
                            @php
                                $accCode = ($line->relationLoaded('account') && $line->account) ? (string) $line->account->code : '';
                                $accName = ($line->relationLoaded('account') && $line->account) ? (string) $line->account->name : '';
                            @endphp
                            <tr class="mj-line-row"
                                data-line-id="{{ (int) $line->getKey() }}"
                                data-line-number="{{ (int) $line->line_number }}"
                                data-account-id="{{ (int) $line->account_id }}"
                                data-account-code="{{ e($accCode) }}"
                                data-account-name="{{ e($accName) }}"
                                data-debit-amount="{{ e((string) $line->debit_amount) }}"
                                data-credit-amount="{{ e((string) $line->credit_amount) }}"
                                data-description="{{ e((string) ($line->description ?? '')) }}">
                                <td class="text-center mj-col-num mj-td-num">{{ $line->line_number }}</td>
                                <td class="mj-col-account">
                                    @if($accCode !== '')
                                        <span class="text-body">{{ $accCode }}</span>
                                        <span class="text-muted">— {{ $accName }}</span>
                                    @else
                                        <span class="text-muted">#{{ $line->account_id }}</span>
                                    @endif
                                </td>
                                <td class="text-end font-monospace mj-col-debit mj-td-amount">{{ number_format((float) $line->debit_amount, (int) $amountDecimalPlaces, '.', ',') }}</td>
                                <td class="text-end font-monospace mj-col-credit mj-td-amount">{{ number_format((float) $line->credit_amount, (int) $amountDecimalPlaces, '.', ',') }}</td>
                                <td class="small text-muted mj-col-desc">{{ $line->description }}</td>
                                <td class="text-center p-1 mj-col-actions mj-td-actions">
                                    <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-1 mj-btn-edit" title="{{ trans('accounting::accounting.manual_journal_lines.btn_edit') }}">
                                        <i class="ph-pencil-simple"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1 mj-btn-delete" title="{{ trans('accounting::accounting.manual_journal_lines.btn_delete') }}">
                                        <i class="ph-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr id="mj-empty-row">
                                <td colspan="6" class="text-center text-muted py-3">{{ trans('accounting::accounting.manual_journal_lines.empty_lines') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="table-light {{ ($model->lines ?? collect())->isEmpty() ? 'd-none' : '' }}" id="mj-lines-tfoot">
                        <tr>
                            <th colspan="2" class="text-end">{{ trans('accounting::accounting.manual_journal_lines.totals') }}</th>
                            <th class="text-end font-monospace" id="mj-total-debit">{{ number_format((float) $model->total_debit, (int) $amountDecimalPlaces, '.', ',') }}</th>
                            <th class="text-end font-monospace" id="mj-total-credit">{{ number_format((float) $model->total_credit, (int) $amountDecimalPlaces, '.', ',') }}</th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <h6 class="fw-semibold mb-2">{{ trans('accounting::accounting.manual_journal_lines.add_row_title') }}</h6>
            <form id="mj-add-line-form" class="row g-2 align-items-end border rounded p-3 bg-body-secondary bg-opacity-25">
                @csrf
                <div class="col-12 col-lg-5">
                    <label class="form-label small mb-1">{{ trans('accounting::accounting.manual_journal_lines.field_account') }}</label>
                    <select name="account_id" id="mj-add-account" class="form-select form-select-sm enhanced-select" required>
                        <option value="">{{ trans('accounting::accounting.structured_resource_forms.select_placeholder') }}</option>
                        @foreach($mjAccounts as $opt)
                            <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label small mb-1">{{ trans('accounting::accounting.manual_journal_lines.field_debit') }}</label>
                    <input type="text" name="debit_amount" id="mj-add-debit" class="form-control form-control-sm amount-decimal" inputmode="decimal"
                           data-type="amount-decimal" data-decimals="{{ (int) $amountDecimalPlaces }}" value="" placeholder="0" autocomplete="off">
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label small mb-1">{{ trans('accounting::accounting.manual_journal_lines.field_credit') }}</label>
                    <input type="text" name="credit_amount" id="mj-add-credit" class="form-control form-control-sm amount-decimal" inputmode="decimal"
                           data-type="amount-decimal" data-decimals="{{ (int) $amountDecimalPlaces }}" value="" placeholder="0" autocomplete="off">
                </div>
                <div class="col-12 col-lg-2">
                    <label class="form-label small mb-1">{{ trans('accounting::accounting.manual_journal_lines.field_line_desc') }}</label>
                    <input type="text" name="description" id="mj-add-desc" class="form-control form-control-sm" maxlength="500" value="" autocomplete="off">
                </div>
                <div class="col-12 col-lg-1 d-grid">
                    <button type="submit" class="btn btn-primary btn-sm" id="mj-add-submit">
                        <i class="ph-plus me-1"></i>{{ trans('accounting::accounting.manual_journal_lines.btn_add') }}
                    </button>
                </div>
                <div class="col-12">
                    <div class="form-text">{{ trans('accounting::accounting.manual_journal_lines.amount_hint', ['currency' => $defaultCurrency]) }}</div>
                </div>
            </form>

            <hr class="my-4">

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <h6 class="fw-semibold mb-1">{{ trans('accounting::accounting.manual_journal_lines.post_title') }}</h6>
                    <p class="text-muted small mb-0">{{ trans('accounting::accounting.manual_journal_lines.post_help') }}</p>
                </div>
                <form id="mj-post-form">
                    @csrf
                    <button type="submit" class="btn btn-success" id="mj-post-submit">
                        <i class="ph-check-circle me-1"></i>{{ trans('accounting::accounting.manual_journal_lines.btn_post') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
    {{-- دادهٔ بوت‌استرپ برای manual-journal-lines.js (بدون اسکریپت اجرایی اینلاین) --}}
    <script type="application/json" id="manual-journal-lines-config">@json($mjAjax)</script>
@elseif(! $isDraft)
    <div class="card border-0 shadow-sm mt-3">
        <div class="card-body">
            <h6 class="fw-semibold mb-2">{{ trans('accounting::accounting.manual_journal_lines.section_readonly_title') }}</h6>
            <p class="text-muted small mb-3">{{ trans('accounting::accounting.manual_journal_lines.section_readonly_intro', ['status' => $model->status]) }}</p>
            @if(($model->status ?? '') === 'posted' && ! empty($reverseRoute) && $reversedByJournalId <= 0)
                <div class="border rounded p-3 mb-3 bg-danger bg-opacity-10 border-danger-subtle">
                    <h6 class="fw-semibold mb-1">{{ trans('accounting::accounting.manual_journal_lines.reverse_title') }}</h6>
                    <p class="small text-muted mb-2">{{ trans('accounting::accounting.manual_journal_lines.reverse_help') }}</p>
                    <form method="post"
                          action="{{ $reverseRoute }}"
                          class="js-mj-reverse-form d-inline-flex"
                          data-confirm-title="{{ trans('accounting::accounting.manual_journal_lines.confirm_reverse_title') }}"
                          data-confirm-message="{{ trans('accounting::accounting.manual_journal_lines.confirm_reverse') }}"
                          data-confirm-button="{{ trans('accounting::accounting.manual_journal_lines.confirm_reverse_btn') }}">
                        @csrf
                        <input type="hidden" name="reason" value="{{ $reverseReasonDefault }}">
                        <button type="submit" class="btn btn-danger btn-sm">
                            <i class="ph-arrow-counter-clockwise me-1"></i>{{ trans('accounting::accounting.manual_journal_lines.btn_reverse') }}
                        </button>
                    </form>
                </div>
            @endif
            @if($reversedByJournalId > 0)
                <div class="alert alert-warning border border-warning-subtle py-2 small mb-3">
                    {{ trans('accounting::accounting.manual_journal_lines.reversed_by_hint') }}
                    <a href="{{ route('admin.accounting.manual-journals.edit', $reversedByJournalId) }}" class="fw-semibold">
                        #{{ $reversedByJournalId }}
                    </a>
                </div>
            @endif
            @if($reversalOfJournalId > 0)
                <div class="alert alert-info border border-info-subtle py-2 small mb-3">
                    {{ trans('accounting::accounting.manual_journal_lines.reversal_of_hint') }}
                    <a href="{{ route('admin.accounting.manual-journals.edit', $reversalOfJournalId) }}" class="fw-semibold">
                        #{{ $reversalOfJournalId }}
                    </a>
                </div>
            @endif
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0" id="mj-lines-readonly-table">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center mj-th-num">#</th>
                            <th>{{ trans('accounting::accounting.manual_journal_lines.th_account') }}</th>
                            <th class="text-end mj-th-amount">{{ trans('accounting::accounting.manual_journal_lines.th_debit') }}</th>
                            <th class="text-end mj-th-amount">{{ trans('accounting::accounting.manual_journal_lines.th_credit') }}</th>
                            <th>{{ trans('accounting::accounting.manual_journal_lines.th_desc') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($model->lines ?? [] as $line)
                            <tr>
                                <td class="text-center mj-td-num">{{ $line->line_number }}</td>
                                <td>
                                    @if($line->relationLoaded('account') && $line->account)
                                        {{ $line->account->code }} — {{ $line->account->name }}
                                    @else
                                        #{{ $line->account_id }}
                                    @endif
                                </td>
                                <td class="text-end font-monospace mj-td-amount">{{ number_format((float) $line->debit_amount, (int) $amountDecimalPlaces, '.', ',') }}</td>
                                <td class="text-end font-monospace mj-td-amount">{{ number_format((float) $line->credit_amount, (int) $amountDecimalPlaces, '.', ',') }}</td>
                                <td class="small text-muted">{{ $line->description }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">{{ trans('accounting::accounting.manual_journal_lines.empty_lines') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
