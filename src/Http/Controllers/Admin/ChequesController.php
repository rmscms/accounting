<?php

namespace RMS\Accounting\Http\Controllers\Admin;
use RMS\Accounting\Http\Controllers\Admin\Concerns\RendersAccountingStructuredResourceForm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RMS\Accounting\Models\Account;
use RMS\Accounting\Models\Cheque;
use RMS\Accounting\Models\Chequebook;
use RMS\Accounting\Models\Customer;
use RMS\Accounting\Models\Currency;
use RMS\Accounting\Models\Party;
use RMS\Accounting\Services\AccountingDateInputNormalizer;
use RMS\Accounting\Services\ChequeLedgerService;
use RMS\Core\Data\Field;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;

class ChequesController extends AccountingAdminController implements
    HasList,
    HasForm,
    ShouldFilter
{
    use RendersAccountingStructuredResourceForm;

    public function table(): string
    {
        return 'cheques';
    }

    public function modelName(): string
    {
        return Cheque::class;
    }

    public function baseRoute(): string
    {
        return 'accounting.cheques';
    }

    public function routeParameter(): string
    {
        return 'cheque';
    }

    public function setTplList(): void
    {
        $this->useCoreTemplates();
        $this->view->usePackageNamespace('accounting')
            ->setTpl('cheques.list');
    }

    public function getFieldsForm(): array
    {
        return [
            Field::string('cheque_number', trans('accounting::accounting.fields.cheque_number'))
                ->required(),

            Field::select('cheque_type', trans('accounting::accounting.fields.cheque_type'))
                ->setOptions($this->getChequeTypeOptions())
                ->required(),

            Field::select('bank_id', trans('accounting::accounting.fields.bank'))
                ->setOptions($this->getBankOptions())
                ->required(),

            Field::number('party_id', trans('accounting::accounting.cheques.counterparty'))
                ->required()
                ->withAttributes([
                    'structured_widget' => 'ajax_customer_select',
                    'dynamic_label_source' => 'cheque_type',
                ]),

            Field::select('chequebook_id', trans('accounting::accounting.cheques.chequebook'))
                ->setOptions($this->getChequebookOptions())
                ->optional(),

            Field::price('amount', trans('accounting::accounting.fields.amount'))
                ->required(),

            Field::select('currency_code', trans('accounting::accounting.fields.currency_code'))
                ->setOptions($this->getCurrencyOptions())
                ->withDefaultValue($this->resolveDefaultCurrencyCode())
                ->required(),

            Field::date('issue_date', trans('accounting::accounting.fields.issue_date'))
                ->withDefaultValue(now()->toDateString())
                ->required(),

            Field::date('due_date', trans('accounting::accounting.fields.due_date'))
                ->required(),

            Field::string('payer_name', trans('accounting::accounting.fields.payer_name'))
                ->optional(),

            Field::string('payer_account', trans('accounting::accounting.fields.payer_account'))
                ->optional(),

            Field::string('payee_name', trans('accounting::accounting.fields.payee_name'))
                ->optional(),

            Field::string('payee_account', trans('accounting::accounting.fields.payee_account'))
                ->optional(),

            Field::select('status', trans('accounting::accounting.fields.status'))
                ->setOptions($this->getChequeStatusOptions())
                ->required(),

            Field::textarea('notes', trans('accounting::accounting.fields.notes'))
                ->optional(),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle('ID')->sortable()->width('80px'),
            Field::make('cheque_number')->withTitle(trans('accounting::accounting.fields.cheque_number'))->searchable()->sortable()->width('140px'),
            Field::make('cheque_type')->withTitle(trans('accounting::accounting.fields.cheque_type'))->width('100px'),
            Field::make('party_id')->withTitle(trans('accounting::accounting.cheques.counterparty'))->customMethod('renderCounterparty')->width('220px'),
            Field::make('amount')->withTitle(trans('accounting::accounting.fields.amount'))->sortable()->width('140px'),
            Field::date('issue_date')->withTitle(trans('accounting::accounting.fields.issue_date'))->sortable()->width('130px'),
            Field::date('due_date')->withTitle(trans('accounting::accounting.fields.due_date'))->sortable()->width('130px'),
            Field::make('status')->withTitle(trans('accounting::accounting.fields.status'))->customMethod('renderStatusBadge')->width('120px'),
            Field::make('accounting_document_id')->withTitle(trans('accounting::accounting.cheques.initial_document'))->customMethod('renderLinkedDocument')->width('140px'),
        ];
    }

    public function rules(): array
    {
        $id = request()->route($this->routeParameter());

        return [
            'cheque_number' => ['required', 'string', 'max:50', Rule::unique('cheques', 'cheque_number')->ignore($id)],
            'cheque_type' => ['required', 'in:received,issued'],
            'bank_id' => ['required', 'integer', 'exists:banks,id'],
            'party_id' => [
                'required',
                'integer',
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    $id = (int) $value;
                    if ($id <= 0) {
                        $fail((string) trans('accounting::accounting.cheques.validation.counterparty_required'));
                        return;
                    }

                    $isCustomerId = Customer::query()->whereKey($id)->exists();
                    $isPartyId = Party::query()->whereKey($id)->exists();
                    if (! $isCustomerId && ! $isPartyId) {
                        $fail((string) trans('accounting::accounting.cheques.validation.counterparty_required'));
                    }
                },
            ],
            'chequebook_id' => [
                'nullable',
                'integer',
                Rule::when(Schema::hasTable('chequebooks'), ['exists:chequebooks,id']),
                Rule::requiredIf((string) request()->input('cheque_type') === Cheque::TYPE_ISSUED),
            ],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'issue_date' => [
                'required',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $this->resolveIsoDateForValidation($value)) {
                        $fail($this->buildInvalidDateDebugMessage('issue_date', $value));
                    }
                },
            ],
            'due_date' => [
                'required',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $this->resolveIsoDateForValidation($value)) {
                        $fail($this->buildInvalidDateDebugMessage('due_date', $value));
                    }
                },
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $dueIso = $this->resolveIsoDateForValidation($value);
                    $issueIso = $this->resolveIsoDateForValidation(request()->input('issue_date'));
                    if ($dueIso === null || $issueIso === null) {
                        return;
                    }
                    if (strcmp($dueIso, $issueIso) < 0) {
                        $fail((string) trans('accounting::accounting.cheques.validation.due_date_after_or_equal_issue_date'));
                    }
                },
            ],
            'payer_name' => ['nullable', 'string', 'max:255'],
            'payer_account' => ['nullable', 'string', 'max:50'],
            'payee_name' => ['nullable', 'string', 'max:255'],
            'payee_account' => ['nullable', 'string', 'max:50'],
            'status' => ['required', 'in:issued,pending,cashed,bounced,cancelled'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'issue_date.required' => (string) trans('accounting::accounting.cheques.validation.issue_date_required'),
            'due_date.required' => (string) trans('accounting::accounting.cheques.validation.due_date_required'),
        ];
    }

    public function cash(Request $request, $id, ChequeLedgerService $chequeLedgerService)
    {
        $cheque = Cheque::findOrFail($id);
        if (! $chequeLedgerService->canCashCheque($cheque)) {
            return redirect()
                ->back()
                ->with('error', trans('accounting::accounting.errors.cheque_not_cashable'));
        }

        try {
            $chequeLedgerService->recordChequeCashed($cheque);
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->back()
            ->with('success', trans('accounting::accounting.messages.cheque_cashed'));
    }

    public function bounce(Request $request, $id)
    {
        $cheque = Cheque::findOrFail($id);
        $rawPenaltyAmount = $request->input('penalty_amount');
        if (is_string($rawPenaltyAmount)) {
            $normalizedPenaltyAmount = trim($rawPenaltyAmount);
            if (function_exists('\RMS\Helper\changeNumberToEn')) {
                $normalizedPenaltyAmount = (string) \RMS\Helper\changeNumberToEn($normalizedPenaltyAmount);
            }
            // Accept formatted amounts like 500,000 or ۵۰۰٬۰۰۰ before numeric validation.
            $normalizedPenaltyAmount = str_replace(['٬', ',', ' '], '', $normalizedPenaltyAmount);
            $normalizedPenaltyAmount = str_replace('٫', '.', $normalizedPenaltyAmount);
            $request->merge([
                'penalty_amount' => $normalizedPenaltyAmount !== '' ? $normalizedPenaltyAmount : null,
            ]);
        }

        $validated = $request->validate([
            'bounce_reason' => ['nullable', 'string', 'max:2000'],
            'penalty_amount' => ['nullable', 'numeric', 'min:0'],
            'penalty_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'penalty_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $service = app(ChequeLedgerService::class);
        if (! $service->canBounceCheque($cheque)) {
            return redirect()
                ->back()
                ->with('error', trans('accounting::accounting.errors.cheque_not_bouncable'));
        }

        $penaltyAmount = (float) ($validated['penalty_amount'] ?? 0);
        $penaltyAccountId = isset($validated['penalty_account_id']) ? (int) $validated['penalty_account_id'] : null;
        if ($penaltyAmount > 0 && (! $penaltyAccountId || ! Account::query()->whereKey($penaltyAccountId)->exists())) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors([
                    'penalty_account_id' => (string) trans('accounting::accounting.cheques.validation.penalty_account_required'),
                ]);
        }

        try {
            $service->recordChequeBounced(
                $cheque,
                (string) ($validated['bounce_reason'] ?? ''),
                $penaltyAmount > 0 ? $penaltyAmount : null,
                $penaltyAccountId,
                isset($validated['penalty_notes']) ? (string) $validated['penalty_notes'] : null
            );
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->back()
            ->with('success', trans('accounting::accounting.messages.cheque_bounced'));
    }

    protected function getBankOptions(): array
    {
        return \RMS\Accounting\Models\Bank::pluck('name', 'id')->toArray();
    }

    protected function getChequebookOptions(): array
    {
        if (! Schema::hasTable('chequebooks')) {
            return [];
        }

        return Chequebook::query()
            ->with('bank')
            ->orderByDesc('active')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(static function (Chequebook $book): array {
                $bank = (string) ($book->bank?->name ?? '');
                $title = trim((string) $book->title);
                $label = trim($title.($bank !== '' ? (' - '.$bank) : ''));

                return [(int) $book->getKey() => $label !== '' ? $label : ('#'.$book->getKey())];
            })
            ->all();
    }

    protected function getCurrencyOptions(): array
    {
        $opts = Currency::query()->where('active', true)->orderBy('code')->pluck('name', 'code')->toArray();

        $base = Currency::resolveBaseCurrencyCode('IRR');

        return $opts !== [] ? $opts : [$base => $base];
    }

    protected function getChequeTypeOptions(): array
    {
        return [
            'received' => trans('accounting::accounting.cheque_types.received'),
            'issued' => trans('accounting::accounting.cheque_types.issued'),
        ];
    }

    protected function getChequeStatusOptions(): array
    {
        return [
            'issued' => trans('accounting::accounting.cheque_statuses.issued'),
            'pending' => trans('accounting::accounting.cheque_statuses.pending'),
            'cashed' => trans('accounting::accounting.cheque_statuses.cashed'),
            'bounced' => trans('accounting::accounting.cheque_statuses.bounced'),
            'cancelled' => trans('accounting::accounting.cheque_statuses.cancelled'),
        ];
    }

    protected function beforeAdd(Request &$request): void
    {
        $this->normalizeChequeRequest($request, null);
    }

    protected function beforeUpdate(Request &$request, int|string $id): void
    {
        $this->normalizeChequeRequest($request, (int) $id);
    }

    protected function afterStructuredAccountingFormPrepareForValidation(Request $request): void
    {
        if ($this->structuredAccountingFormSlug() !== 'cheques') {
            return;
        }

        /** @var AccountingDateInputNormalizer $normalizer */
        $normalizer = app(AccountingDateInputNormalizer::class);
        foreach (['issue_date', 'due_date'] as $field) {
            $raw = $request->input($field);
            if (! is_string($raw)) {
                continue;
            }
            $normalized = $this->normalizeChequeDateInput($raw, $normalizer);
            if ($normalized !== null) {
                $request->merge([$field => $normalized]);
            }
        }

        if (! $request->filled('due_date') && $request->filled('issue_date')) {
            $request->merge(['due_date' => (string) $request->input('issue_date')]);
        }
    }

    protected function afterAdd(Request $request, int|string $id, Model $model): void
    {
        if (! $model instanceof Cheque) {
            return;
        }
        app(ChequeLedgerService::class)->recordChequeCreated($model->fresh());
    }

    protected function afterConfigureStructuredAccountingFormView(Request $request, bool $isEdit, ?Model $model): void
    {
        if ($this->structuredAccountingFormSlug() !== 'cheques') {
            return;
        }
        $this->view->withPlugins(['advanced-select']);
        $this->view->withJs('vendor/accounting/admin/js/sales-customer-picker.js', true);
        $this->view->withJs('vendor/accounting/admin/js/cheques-party-picker.js', true);
    }

    /**
     * @return array<string, mixed>
     */
    protected function structuredAccountingFormExtraViewVariables(Request $request, bool $isEdit, ?Model $model): array
    {
        $partyId = null;
        $partyText = null;
        $customerId = null;
        if ($isEdit && $model instanceof Cheque && (int) ($model->party_id ?? 0) > 0) {
            $party = Party::query()->with('customer')->find((int) $model->party_id);
            if ($party) {
                $partyId = (string) $party->id;
                $partyText = (string) $party->name;
                if ($party->customer) {
                    $customerId = (string) $party->customer->id;
                }
            }
        }

        $ledgerService = app(ChequeLedgerService::class);
        $hasClearing = $ledgerService->hasRequiredClearingAccounts();
        $hasCounterpartyFallback = $ledgerService->hasRequiredCounterpartyFallbackAccounts();
        $missingSetup = ! $hasClearing || ! $hasCounterpartyFallback;
        $setupIssues = [];
        if (! $hasClearing) {
            $setupIssues[] = (string) trans('accounting::accounting.cheques.setup_issue_clearing_accounts');
        }
        if (! $hasCounterpartyFallback) {
            $setupIssues[] = (string) trans('accounting::accounting.cheques.setup_issue_counterparty_defaults');
        }
        $activeBooksCount = Schema::hasTable('chequebooks')
            ? (int) Chequebook::query()->active()->count()
            : 0;
        $isChequeEdit = $isEdit && $model instanceof Cheque;
        $canCash = $isChequeEdit ? $ledgerService->canCashCheque($model) : false;
        $canBounce = $isChequeEdit ? $ledgerService->canBounceCheque($model) : false;
        $penaltyAccounts = Account::query()
            ->where('active', true)
            ->where('account_type', Account::TYPE_EXPENSE)
            ->orderBy('code')
            ->limit(300)
            ->get(['id', 'code', 'name'])
            ->mapWithKeys(static function (Account $account): array {
                return [(int) $account->id => trim((string) $account->code).' - '.trim((string) $account->name)];
            })
            ->all();

        return [
            'partySearchUrl' => route('admin.accounting.suppliers.search-parties'),
            'partySelectInitialText' => $partyText,
            'partySelectInitialId' => $partyId,
            'customerInvoiceSearchUrl' => route('admin.accounting.suppliers.search-parties'),
            'customerQuickCreateUrl' => route('admin.accounting.customer-invoices.quick-create-customer'),
            'customerQuickCreateTypeOptions' => [
                'regular' => trans('accounting::accounting.customer.type_regular'),
                'vip' => trans('accounting::accounting.customer.type_vip'),
                'occasional' => trans('accounting::accounting.customer.type_occasional'),
            ],
            'customerQuickCreateCurrencyOptions' => $this->quickCreateCurrencyOptions(),
            'defaultCurrency' => $this->resolveDefaultCurrencyCode(),
            'chequeCounterpartyCustomerId' => $partyId,
            'chequeCounterpartyCustomerRecordId' => $customerId,
            'chequeCounterpartySelectInitialText' => $partyText,
            'chequeDynamicLabels' => [
                'received' => [
                    'counterparty' => trans('accounting::accounting.cheques.dynamic_labels.received_counterparty'),
                    'hint' => trans('accounting::accounting.cheques.dynamic_hints.received_counterparty'),
                ],
                'issued' => [
                    'counterparty' => trans('accounting::accounting.cheques.dynamic_labels.issued_counterparty'),
                    'hint' => trans('accounting::accounting.cheques.dynamic_hints.issued_counterparty'),
                ],
            ],
            'chequeClearingSetupMissing' => $missingSetup,
            'chequeSetupIssues' => $setupIssues,
            'chequeActiveChequebooksCount' => $activeBooksCount,
            'chequeSettingsUrl' => route('admin.accounting.settings.index'),
            'chequeAccountsUrl' => route('admin.accounting.accounts.index'),
            'chequeActionsEnabled' => $isChequeEdit,
            'chequeCanCash' => $canCash,
            'chequeCanBounce' => $canBounce,
            'chequeCashActionUrl' => $isChequeEdit ? route('admin.accounting.cheques.cash', ['id' => (int) $model->getKey()]) : null,
            'chequeBounceActionUrl' => $isChequeEdit ? route('admin.accounting.cheques.bounce', ['id' => (int) $model->getKey()]) : null,
            'chequePenaltyAccountOptions' => $penaltyAccounts,
        ];
    }

    protected function normalizeChequeRequest(Request $request, ?int $updateId): void
    {
        foreach (['party_id', 'chequebook_id', 'bank_id'] as $fk) {
            $raw = $request->input($fk);
            if ($raw === null || $raw === '' || (string) $raw === '0') {
                $request->merge([$fk => null]);
                continue;
            }
            if (is_numeric($raw)) {
                $request->merge([$fk => (int) $raw]);
            }
        }
        $selectedCustomerId = (int) ($request->input('party_id') ?? 0);
        if ($selectedCustomerId > 0) {
            $customer = Customer::query()->find($selectedCustomerId);
            if ($customer && (int) ($customer->party_id ?? 0) > 0) {
                $request->merge(['party_id' => (int) $customer->party_id]);
            } elseif (Party::query()->whereKey($selectedCustomerId)->exists()) {
                // Keep direct party selection (used in edit fallback when customer row is missing).
                $request->merge(['party_id' => $selectedCustomerId]);
            } else {
                throw ValidationException::withMessages([
                    'party_id' => (string) trans('accounting::accounting.cheques.errors.counterparty_has_no_party'),
                ]);
            }
        }

        $type = (string) $request->input('cheque_type', Cheque::TYPE_RECEIVED);
        if (! in_array($type, [Cheque::TYPE_RECEIVED, Cheque::TYPE_ISSUED], true)) {
            $type = Cheque::TYPE_RECEIVED;
            $request->merge(['cheque_type' => $type]);
        }

        if (! $request->filled('status')) {
            $request->merge(['status' => $type === Cheque::TYPE_ISSUED ? Cheque::STATUS_ISSUED : Cheque::STATUS_PENDING]);
        }

        if (! $request->filled('currency_code')) {
            $request->merge(['currency_code' => $this->resolveDefaultCurrencyCode()]);
        }

        if (! $request->filled('issue_date')) {
            $request->merge(['issue_date' => now()->toDateString()]);
        }
        if (! $request->filled('due_date')) {
            $request->merge(['due_date' => (string) $request->input('issue_date')]);
        }

        if ($type === Cheque::TYPE_ISSUED && ! $request->filled('chequebook_id')) {
            throw ValidationException::withMessages([
                'chequebook_id' => (string) trans('accounting::accounting.cheques.chequebook_required_issued'),
            ]);
        }

        $resolvedParty = null;
        if ($request->filled('party_id')) {
            $resolvedParty = Party::query()->with(['supplier', 'customer'])->find((int) $request->input('party_id'));
        }

        if ($type === Cheque::TYPE_ISSUED) {
            $supplierAccountId = (int) ($resolvedParty?->supplier?->account_id ?? 0);
            if ($supplierAccountId <= 0) {
                throw ValidationException::withMessages([
                    'party_id' => (string) trans('accounting::accounting.cheques.validation.issued_counterparty_requires_supplier'),
                ]);
            }
        }

        if ($request->filled('chequebook_id')) {
            $book = Chequebook::query()->find((int) $request->input('chequebook_id'));
            if ($book && ! $request->filled('bank_id')) {
                $request->merge(['bank_id' => (int) $book->bank_id]);
            }
            if (! $request->filled('cheque_number') && $book) {
                $next = $book->consumeNextSerial();
                if ($next !== null) {
                    $request->merge(['cheque_number' => $next]);
                }
            }
        }

        if ($request->filled('party_id')) {
            $party = Party::query()->find((int) $request->input('party_id'));
            if ($party) {
                if ($type === Cheque::TYPE_RECEIVED) {
                    $request->merge([
                        'payer_name' => $party->name,
                        'payee_name' => (string) config('app.name', 'Company'),
                    ]);
                } else {
                    $request->merge([
                        'payee_name' => $party->name,
                        'payer_name' => (string) config('app.name', 'Company'),
                    ]);
                }
            }
        }

        if ($updateId !== null) {
            $existing = Cheque::query()->find($updateId);
            if ($existing && (int) ($existing->accounting_document_id ?? 0) > 0) {
                $request->merge(['accounting_document_id' => (int) $existing->accounting_document_id]);
            }
        }
    }

    public function renderCounterparty($row): string
    {
        $party = Party::query()->find((int) ($row->party_id ?? 0));
        if (! $party) {
            return '<span class="text-muted">-</span>';
        }

        return e((string) $party->name);
    }

    public function renderStatusBadge($row): string
    {
        $status = (string) ($row->status ?? '');
        $map = [
            Cheque::STATUS_ISSUED => 'bg-info',
            Cheque::STATUS_PENDING => 'bg-warning text-dark',
            Cheque::STATUS_CASHED => 'bg-success',
            Cheque::STATUS_BOUNCED => 'bg-danger',
            Cheque::STATUS_CANCELLED => 'bg-secondary',
        ];

        $label = e((string) trans('accounting::accounting.cheque_statuses.'.$status));
        $class = $map[$status] ?? 'bg-secondary';

        return '<span class="badge '.$class.'">'.$label.'</span>';
    }

    public function renderLinkedDocument($row): string
    {
        $docId = (int) ($row->accounting_document_id ?? 0);
        if ($docId <= 0) {
            return '<span class="text-muted">-</span>';
        }

        try {
            $url = route('admin.accounting.documents.show', ['document' => $docId]);
        } catch (\Throwable) {
            return '<span class="badge bg-secondary">#'.$docId.'</span>';
        }

        return '<a class="badge bg-success text-white text-decoration-none" href="'.e($url).'">#'.$docId.'</a>';
    }

    protected function resolveDefaultCurrencyCode(): string
    {
        return Currency::resolveBaseCurrencyCode('IRR');
    }

    /**
     * @return array<string, string>
     */
    protected function quickCreateCurrencyOptions(): array
    {
        $rows = Currency::query()
            ->active()
            ->orderByDesc('is_base')
            ->orderBy('code')
            ->get(['code', 'name']);

        return $rows->mapWithKeys(static function (Currency $currency): array {
            $code = strtoupper((string) $currency->code);
            $name = trim((string) $currency->name);

            return [$code => ($name !== '' ? ($code.' - '.$name) : $code)];
        })->all();
    }

    protected function normalizeChequeDateInput(string $value, AccountingDateInputNormalizer $normalizer): ?string
    {
        $trimmed = trim(\RMS\Helper\changeNumberToEn($value));
        if ($trimmed === '') {
            return null;
        }

        $gregorian = $normalizer->normalizeFilterDateToGregorian($trimmed);
        if ($gregorian === null && str_contains($trimmed, ' ')) {
            $datePart = trim((string) explode(' ', $trimmed, 2)[0]);
            $gregorian = $normalizer->normalizeFilterDateToGregorian($datePart);
        }
        if ($gregorian === null) {
            return $trimmed;
        }

        $iso = str_replace('/', '-', trim((string) $gregorian));
        if (str_contains($iso, ' ')) {
            $iso = trim((string) explode(' ', $iso, 2)[0]);
        }

        return $iso;
    }

    protected function resolveIsoDateForValidation(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalizer = app(AccountingDateInputNormalizer::class);
        $iso = $this->normalizeChequeDateInput($value, $normalizer);
        if (! is_string($iso)) {
            return null;
        }

        $iso = trim($iso);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $iso)) {
            return null;
        }

        return $iso;
    }

    protected function buildInvalidDateDebugMessage(string $field, mixed $value): string
    {
        $base = (string) trans('accounting::accounting.cheques.validation.'.$field.'_invalid');
        $label = $field === 'due_date'
            ? (string) trans('accounting::accounting.fields.due_date')
            : (string) trans('accounting::accounting.fields.issue_date');

        if (! is_string($value)) {
            return $base.' '.'[field='.$label.'] [received_type='.gettype($value).']';
        }

        $raw = trim($value);
        $normalized = trim(\RMS\Helper\changeNumberToEn($raw));
        $parts = [];
        $parts[] = '[field='.$label.']';
        $parts[] = '[received="'.$raw.'"]';
        $parts[] = '[normalized="'.$normalized.'"]';

        $invalidChars = $this->detectInvalidDateCharacters($normalized);
        if ($invalidChars !== []) {
            $parts[] = '[invalid_chars='.implode(', ', $invalidChars).']';
        } else {
            $parts[] = '[invalid_chars=none]';
        }

        $parts[] = '[shape_ok='.(preg_match('/^\d{4}[\/-]\d{2}[\/-]\d{2}$/', $normalized) ? 'yes' : 'no').']';

        $separator = str_contains($normalized, '-') ? '-' : '/';
        try {
            $converted = \RMS\Helper\gregorian_date($normalized, $separator);
            $parts[] = '[helper_result="'.$converted.'"]';
        } catch (\Throwable $e) {
            $parts[] = '[helper_error="'.$e->getMessage().'"]';
        }

        return $base.' '.implode(' ', $parts);
    }

    /**
     * @return array<int, string>
     */
    protected function detectInvalidDateCharacters(string $value): array
    {
        $matches = [];
        preg_match_all('/[^0-9\/\-\s]/u', $value, $matches, PREG_OFFSET_CAPTURE);
        if (! isset($matches[0]) || ! is_array($matches[0])) {
            return [];
        }

        $result = [];
        foreach ($matches[0] as $match) {
            $char = (string) ($match[0] ?? '');
            $byteOffset = (int) ($match[1] ?? 0);
            if ($char === '') {
                continue;
            }
            $pos = mb_strlen(substr($value, 0, $byteOffset), 'UTF-8') + 1;
            $code = function_exists('mb_ord') ? strtoupper(dechex((int) mb_ord($char, 'UTF-8'))) : 'UNKNOWN';
            $result[] = '"'.$char.'"@'.$pos.'(U+'.$code.')';
        }

        return $result;
    }
}
