{{-- فرم اختصاصی ثبت/ویرایش هزینه — پکیج accounting --}}
@extends('cms::admin.layout.index')

@section('content')
@php
    $isEdit = !empty($isEdit);
    /** @var \RMS\Accounting\Models\Expense|null $expense */
    $expense = $expense ?? null;
    $expenseDateOld = old('expense_date');
    if ($expenseDateOld !== null && trim((string) $expenseDateOld) !== '') {
        $pdExpense = trim(\RMS\Helper\changeNumberToEn((string) $expenseDateOld));
    } elseif ($isEdit && $expense && $expense->expense_date) {
        $pdExpense = \RMS\Accounting\Support\AccountingDateUi::gregorianYmdToInputDisplay(
            $expense->expense_date instanceof \DateTimeInterface
                ? $expense->expense_date->format('Y-m-d')
                : (string) $expense->expense_date
        );
    } else {
        $pdExpense = \RMS\Accounting\Support\AccountingDateUi::gregorianYmdToInputDisplay(now()->format('Y-m-d'));
    }
    $banks = $banks ?? collect();
    $cashBoxes = $cashBoxes ?? collect();
    $posTerminals = $posTerminals ?? collect();
    $currentStatus = old('status', $expense?->status ?? \RMS\Accounting\Models\Expense::STATUS_DRAFT);
    $paymentSourceKind = old('payment_source_kind');
    if ($paymentSourceKind === null && $expense) {
        if ($expense->cash_box_id) {
            $paymentSourceKind = 'cash_box';
        } elseif ($expense->bank_id) {
            $paymentSourceKind = 'bank';
        } elseif ($expense->pos_terminal_id) {
            $paymentSourceKind = 'pos_terminal';
        } else {
            $paymentSourceKind = 'cash_box';
        }
    } elseif ($paymentSourceKind === null) {
        $paymentSourceKind = 'cash_box';
    }
    $amountDecimalPlaces = (int) ($amountDecimalPlaces ?? 0);
    $amountFieldDefault = '';
    if ($isEdit && $expense && $expense->amount !== null) {
        $amountFieldDefault = number_format((float) $expense->amount, $amountDecimalPlaces, '.', ',');
    }
    $destinationInitialBankId = (int) old('expense_paid_at_source_bank_id', old('bank_id', $expense?->bank_id));
    $destinationInitialCashBoxId = (int) old('expense_paid_at_source_cash_box_id', old('cash_box_id', $expense?->cash_box_id));
    $destinationInitialPosTerminalId = (int) old('expense_paid_at_source_pos_terminal_id', old('pos_terminal_id', $expense?->pos_terminal_id));
@endphp
<div class="container-fluid" data-role="expense-form-page">
    <div class="card border-primary border-opacity-25">
        <div class="card-header d-flex flex-wrap align-items-start justify-content-between gap-2">
            <div class="flex-grow-1">
                <h5 class="mb-1">
                    {{ $isEdit ? trans('accounting::accounting.expense_create.title_edit') : trans('accounting::accounting.expense_create.title') }}
                </h5>
                <small class="text-muted d-block">{{ trans('accounting::accounting.expense_create.page_subtitle') }}</small>
                @if($isEdit && $expense)
                    <small class="text-muted d-block mt-1">
                        {{ trans('accounting::accounting.expense_create.expense_number_label') }}:
                        <span class="font-monospace">{{ $expense->expense_number }}</span>
                    </small>
                @endif
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <a href="{{ route('admin.accounting.expenses.index') }}" class="btn btn-light btn-sm">{{ trans('accounting::accounting.expense_create.back_to_list') }}</a>
            </div>
        </div>
        <div class="card-body">
            @if ($errors->has('_form'))
                <div class="alert alert-danger">{{ $errors->first('_form') }}</div>
            @endif

            @if($suggestedCategories->isEmpty() && $otherCategories->isEmpty())
                <div class="alert alert-warning">
                    {{ trans('accounting::accounting.expense_create.no_categories_prefix') }}
                    <a href="{{ route('admin.accounting.expense-categories.index') }}">{{ trans('accounting::accounting.expense_create.no_categories_link') }}</a>
                    {{ trans('accounting::accounting.expense_create.no_categories_suffix') }}
                </div>
            @endif

            @if($isEdit && $expense)
                @include('accounting::admin.expenses.partials.status_timeline', ['expense' => $expense, 'statusOptions' => $statusOptions])
                <form method="post" action="{{ route('admin.accounting.expenses.update', $expense) }}" class="row g-3" enctype="multipart/form-data" data-expense-form="1">
                    @csrf
                    @method('PUT')
            @else
                <form method="post" action="{{ route('admin.accounting.expenses.store') }}" class="row g-3" enctype="multipart/form-data" data-expense-form="1">
                    @csrf
            @endif

                <x-accounting::date-field
                    name="expense_date"
                    :label="trans('accounting::accounting.expense_create.expense_date') . ' <span class=\'text-danger\'>*</span>'"
                    :value="$pdExpense"
                    :required="true"
                    col-class="col-md-3"
                    error-key="expense_date"
                />

                <div class="col-md-5">
                    <label class="form-label">{{ trans('accounting::accounting.expense_create.category') }} <span class="text-danger">*</span></label>
                    <select name="expense_category_id" class="form-select enhanced-select @error('expense_category_id') is-invalid @enderror" required data-placeholder="{{ trans('accounting::accounting.expense_create.category_placeholder') }}">
                        <option value=""></option>
                        @if($suggestedCategories->isNotEmpty())
                            <optgroup label="{{ trans('accounting::accounting.expense_create.optgroup_suggested') }}">
                                @foreach($suggestedCategories as $cat)
                                    <option value="{{ $cat->id }}" @selected(old('expense_category_id', $expense?->expense_category_id) == $cat->id)>{{ $cat->name }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                        @if($otherCategories->isNotEmpty())
                            <optgroup label="{{ trans('accounting::accounting.expense_create.optgroup_other') }}">
                                @foreach($otherCategories as $cat)
                                    <option value="{{ $cat->id }}" @selected(old('expense_category_id', $expense?->expense_category_id) == $cat->id)>{{ $cat->name }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                    </select>
                    @error('expense_category_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    <div class="form-text">{{ trans('accounting::accounting.expense_create.featured_config_hint') }}</div>
                </div>

                <div class="col-md-4">
                    <label class="form-label">{{ trans('accounting::accounting.expense_create.amount', ['currency' => $defaultCurrency]) }} <span class="text-danger">*</span></label>
                    <input type="text" name="amount" class="form-control amount-decimal @error('amount') is-invalid @enderror"
                           data-type="amount-decimal" data-decimals="{{ $amountDecimalPlaces }}" value="{{ old('amount', $amountFieldDefault) }}" inputmode="decimal" autocomplete="off" required>
                    @error('amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label">{{ trans('accounting::accounting.expense_create.expense_type') }} <span class="text-danger">*</span></label>
                    <select name="expense_type" class="form-select @error('expense_type') is-invalid @enderror" required>
                        @foreach($expenseTypes as $value => $label)
                            <option value="{{ $value }}" @selected(old('expense_type', $expense?->expense_type ?? \RMS\Accounting\Models\Expense::TYPE_OPERATIONAL) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('expense_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label">{{ trans('accounting::accounting.expense_create.payee_type') }} <span class="text-danger">*</span></label>
                    <select name="payee_type" class="form-select @error('payee_type') is-invalid @enderror" required>
                        @foreach($payeeTypes as $value => $label)
                            <option value="{{ $value }}" @selected(old('payee_type', $expense?->payee_type ?? 'other') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('payee_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label">{{ trans('accounting::accounting.expense_create.payee_name') }}</label>
                    <input type="text" name="payee_name" class="form-control @error('payee_name') is-invalid @enderror" value="{{ old('payee_name', $expense?->payee_name) }}" placeholder="{{ trans('accounting::accounting.expense_create.payee_name_placeholder') }}">
                    @error('payee_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label class="form-label">{{ trans('accounting::accounting.expense_create.description') }} <span class="text-danger">*</span></label>
                    <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="3" required placeholder="{{ trans('accounting::accounting.expense_create.description_placeholder') }}">{{ old('description', $expense?->description) }}</textarea>
                    @error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label">{{ trans('accounting::accounting.expense_create.status') }}</label>
                    <select name="status" id="expense-status-select" class="form-select @error('status') is-invalid @enderror" required>
                        @foreach($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $expense?->status ?? \RMS\Accounting\Models\Expense::STATUS_DRAFT) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 {{ $currentStatus === \RMS\Accounting\Models\Expense::STATUS_PAID ? '' : 'd-none' }}" id="expense-payment-source-wrap">
                    <div class="border rounded p-3 bg-light bg-opacity-50">
                        <h6 class="mb-2">{{ trans('accounting::accounting.expense_create.payment_source_section') }}</h6>
                        <p class="small text-muted mb-3">{{ trans('accounting::accounting.expense_create.payment_source_hint') }}</p>
                        @error('bank_id')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror
                        <x-accounting::payment-destination-picker
                            context="customer_payment"
                            :catalog-url="route('admin.accounting.ajax.payment-destinations')"
                            name-prefix="expense_paid_at_source_"
                            :initial-payment-method-id="(int) old('expense_paid_at_source_payment_method_id', 0)"
                            :initial-bank-id="$destinationInitialBankId"
                            :initial-cash-box-id="$destinationInitialCashBoxId"
                            :initial-cheque-id="0"
                            :initial-pos-terminal-id="$destinationInitialPosTerminalId"
                            :initial-wallet-id="0"
                            :required="false"
                            :compact="false"
                            :show-currency-note="false"
                            :setup-routes="[
                                'banks' => route('admin.accounting.banks.create'),
                                'cashboxes' => route('admin.accounting.cashboxes.create'),
                                'cheques' => route('admin.accounting.cheques.create'),
                                'pos-terminals' => route('admin.accounting.pos-terminals.create'),
                                'wallets' => route('admin.accounting.wallets.create'),
                                'payment-methods' => route('admin.accounting.payment-methods.create'),
                            ]"
                        />
                        <input type="hidden" name="bank_id" value="{{ old('bank_id', $expense?->bank_id) }}">
                        <input type="hidden" name="cash_box_id" value="{{ old('cash_box_id', $expense?->cash_box_id) }}">
                        <input type="hidden" name="pos_terminal_id" value="{{ old('pos_terminal_id', $expense?->pos_terminal_id) }}">
                        <input type="hidden" name="payment_source_kind" value="{{ old('payment_source_kind', $paymentSourceKind) }}">
                        @error('cash_box_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @error('pos_terminal_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="col-12">
                    <hr class="my-2">
                    <h6 class="mb-2">{{ trans('accounting::accounting.attachments.section_title') }}</h6>
                    <p class="text-muted small mb-2">{{ trans('accounting::accounting.attachments.section_hint') }}</p>

                    @if($isEdit && $expense && $expense->attachments->isNotEmpty())
                        <div class="mb-3" id="expense-existing-attachments">
                            @foreach($expense->attachments as $att)
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                    <label class="mb-0 d-flex align-items-center gap-2">
                                        <input type="checkbox" name="keep_attachment_uuids[]" value="{{ $att->uuid }}" checked>
                                        <span>{{ $att->original_name }}</span>
                                        <span class="badge bg-light text-muted">{{ strtoupper(pathinfo($att->original_name, PATHINFO_EXTENSION) ?: '—') }}</span>
                                    </label>
                                    <a href="{{ route('admin.accounting.attachments.download', $att->uuid) }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">{{ trans('accounting::accounting.attachments.download') }}</a>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($isEdit && $expense && $expense->receipt_image && $expense->attachments->count() === 0)
                        <div class="alert alert-light border small mb-2">
                            {{ trans('accounting::accounting.attachments.legacy_receipt') }}:
                            <span class="text-break">{{ $expense->receipt_image }}</span>
                        </div>
                    @endif

                    @php
                        $attachmentMimeJson = json_encode(['image/jpeg', 'image/png', 'image/webp', 'application/pdf']);
                        $attachmentMaxSizeAttr = max(0.1, round(($attachmentMaxKb ?? 10240) / 1024, 1)) . 'MB';
                    @endphp
                    <div class="image-uploader border rounded p-3 mb-2"
                         data-max-size="{{ $attachmentMaxSizeAttr }}"
                         data-allowed-types="{{ e($attachmentMimeJson) }}"
                         data-formats-hint="{{ trans('accounting::accounting.attachments.formats_hint') }}">
                        <label class="form-label">{{ trans('accounting::accounting.attachments.files_label') }}</label>
                        <input type="file" name="attachments[]" class="form-control" multiple
                               data-max-size="{{ $attachmentMaxSizeAttr }}"
                               data-allowed-types="{{ e($attachmentMimeJson) }}"
                               data-formats-hint="{{ trans('accounting::accounting.attachments.formats_hint') }}"
                               accept="image/jpeg,image/png,image/webp,application/pdf,.pdf">
                        <div class="form-text">{{ trans('accounting::accounting.attachments.files_help', ['max' => $attachmentMaxKb ?? 10240, 'maxn' => $attachmentMaxPerExpense ?? 5]) }}</div>
                        @error('attachments')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="col-12 d-flex gap-2 pt-2">
                    <button type="submit" class="btn btn-primary">{{ trans('accounting::accounting.expense_create.save') }}</button>
                    @if($isEdit && $expense && $expense->status !== \RMS\Accounting\Models\Expense::STATUS_PAID)
                        <button type="submit" class="btn btn-success" name="_action" value="apply">{{ trans('accounting::accounting.expense_create.apply_btn') }}</button>
                    @endif
                    <a href="{{ route('admin.accounting.expenses.index') }}" class="btn btn-light">{{ trans('accounting::accounting.expense_create.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>

    @include('accounting::components.collapse_help_card', [
        'collapseId' => 'accounting-expense-form-help',
        'toggleLabel' => trans('accounting::accounting.expense_help.toggle_label'),
        'title' => trans('accounting::accounting.expense_help.title'),
        'paragraphs' => trans('accounting::accounting.expense_help.paragraphs'),
        'append_html' => view('accounting::components.expense_status_help_colored', ['statusOptions' => $statusOptions])->render(),
    ])
</div>
<script>
(function () {
    var root = document.querySelector('[data-role="expense-form-page"]');
    var form = root ? root.querySelector('form[data-expense-form="1"]') : null;
    if (!form) return;
    var statusEl = form.querySelector('#expense-status-select');
    var wrap = document.getElementById('expense-payment-source-wrap');
    var paid = '{{ \RMS\Accounting\Models\Expense::STATUS_PAID }}';

    function syncPaymentVisibility() {
        if (!statusEl || !wrap) return;
        wrap.classList.toggle('d-none', statusEl.value !== paid);
    }

    function syncLegacyPaymentFields() {
        var bank = form.querySelector('[name="expense_paid_at_source_bank_id"]');
        var cash = form.querySelector('[name="expense_paid_at_source_cash_box_id"]');
        var pos = form.querySelector('[name="expense_paid_at_source_pos_terminal_id"]');
        var outBank = form.querySelector('[name="bank_id"]');
        var outCash = form.querySelector('[name="cash_box_id"]');
        var outPos = form.querySelector('[name="pos_terminal_id"]');
        var outKind = form.querySelector('[name="payment_source_kind"]');
        if (!outBank || !outCash || !outPos || !outKind) return;

        var bankVal = bank && bank.value ? bank.value : '';
        var cashVal = cash && cash.value ? cash.value : '';
        var posVal = pos && pos.value ? pos.value : '';
        outBank.value = bankVal;
        outCash.value = cashVal;
        outPos.value = posVal;
        outKind.value = cashVal ? 'cash_box' : (bankVal ? 'bank' : (posVal ? 'pos_terminal' : ''));
    }

    if (statusEl) statusEl.addEventListener('change', syncPaymentVisibility);
    syncPaymentVisibility();
    syncLegacyPaymentFields();

    form.addEventListener('change', function () {
        syncLegacyPaymentFields();
    });

    form.addEventListener('submit', function () {
        if (!statusEl || statusEl.value !== paid) {
            ['bank_id', 'cash_box_id', 'pos_terminal_id'].forEach(function (name) {
                var el = form.querySelector('[name="' + name + '"]');
                if (el) el.value = '';
            });
            var kind = form.querySelector('[name="payment_source_kind"]');
            if (kind) kind.value = '';
            return;
        }
        syncLegacyPaymentFields();
    });
})();
</script>
@include('accounting::admin.partials.accounting-date-ui-script')
@endsection
