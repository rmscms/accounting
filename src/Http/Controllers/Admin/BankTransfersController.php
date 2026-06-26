<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RMS\Accounting\Http\Controllers\Admin\Concerns\ParsesAccountingMoneyInput;
use RMS\Accounting\Models\Bank;
use RMS\Accounting\Models\BankTransfer;
use RMS\Accounting\Models\AccountingDocument;
use RMS\Accounting\Models\CashBox;
use RMS\Accounting\Models\Currency;
use RMS\Accounting\Models\Wallet;
use RMS\Accounting\Services\BankTransferService;
use RMS\Accounting\Support\AccountingDateUi;
use RMS\Core\Data\Field;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Models\Setting;

class BankTransfersController extends AccountingAdminController implements
    HasList,
    HasForm,
    ShouldFilter
{
    use ParsesAccountingMoneyInput;

    protected BankTransferService $transferService;

    public function __construct(Filesystem $filesystem, BankTransferService $transferService)
    {
        parent::__construct($filesystem);
        $this->transferService = $transferService;
    }

    public function table(): string
    {
        return 'bank_transfers';
    }

    public function modelName(): string
    {
        return BankTransfer::class;
    }

    public function baseRoute(): string
    {
        return 'accounting.bank-transfers';
    }

    public function routeParameter(): string
    {
        return 'bank_transfer';
    }

    public function create(Request $request)
    {
        $htmlPageTitle = $this->resolveBankTransferFormDocumentTitle(false);
        $this->title($htmlPageTitle);
        $this->use_package_namespace = true;
        $this->view->usePackageNamespace('accounting');

        $plugins = array_merge(
            AccountingDateUi::calendarMode() === AccountingDateUi::MODE_JALALI ? ['persian-datepicker'] : [],
            ['advanced-select', 'amount-formatter']
        );

        $this->view
            ->setTpl('bank_transfers.form')
            ->withPlugins($plugins)
            ->withVariables([
                'isEdit' => false,
                'bankTransfer' => null,
                'treasuryOptions' => $this->treasuryOptionsForFormSelect(),
                'htmlPageTitle' => $htmlPageTitle,
                'defaultCurrency' => $this->resolveDefaultCurrencyCode(),
                'amountDecimalPlaces' => $this->resolveAccountingAmountDecimalPlaces(),
            ]);

        return $this->view();
    }

    public function edit(Request $request, $id)
    {
        $bankTransfer = $id instanceof BankTransfer ? $id : BankTransfer::query()->findOrFail((int) $id);

        $htmlPageTitle = $this->resolveBankTransferFormDocumentTitle(true, $bankTransfer);
        $this->title($htmlPageTitle);
        $this->use_package_namespace = true;
        $this->view->usePackageNamespace('accounting');

        $plugins = array_merge(
            AccountingDateUi::calendarMode() === AccountingDateUi::MODE_JALALI ? ['persian-datepicker'] : [],
            ['advanced-select', 'amount-formatter']
        );

        $this->view
            ->setTpl('bank_transfers.form')
            ->withPlugins($plugins)
            ->withVariables([
                'isEdit' => true,
                'bankTransfer' => $bankTransfer,
                'treasuryOptions' => $this->treasuryOptionsForFormSelect(),
                'transferSummary' => $this->buildTransferSummary($bankTransfer),
                'htmlPageTitle' => $htmlPageTitle,
                'defaultCurrency' => $this->resolveDefaultCurrencyCode(),
                'amountDecimalPlaces' => $this->resolveAccountingAmountDecimalPlaces(),
            ]);

        return $this->view();
    }

    public function update(Request $request, $id): RedirectResponse
    {
        $resolvedId = $id instanceof BankTransfer ? $id->getKey() : $id;

        return parent::update($request, $resolvedId);
    }

    public function getFieldsForm(): array
    {
        return [
            Field::select('from_treasury', trans('accounting::accounting.bank_transfer_form.fields.from_treasury'))
                ->setOptions($this->getTreasuryOptions())
                ->required(),

            Field::select('to_treasury', trans('accounting::accounting.bank_transfer_form.fields.to_treasury'))
                ->setOptions($this->getTreasuryOptions())
                ->required(),

            Field::number('amount', trans('accounting::accounting.bank_transfer_form.fields.amount'))
                ->required(),

            Field::date('transfer_date', trans('accounting::accounting.bank_transfer_form.fields.transfer_date'))
                ->withDefaultValue(now())
                ->required(),

            Field::number('transfer_fee', trans('accounting::accounting.bank_transfer_form.fields.transfer_fee'))
                ->withDefaultValue(0)
                ->optional(),

            Field::string('reference_number', trans('accounting::accounting.bank_transfer_form.fields.reference_number'))
                ->optional(),

            Field::textarea('description', trans('accounting::accounting.bank_transfer_form.fields.description'), 2)
                ->optional(),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle('شناسه')->sortable()->width('80px'),
            Field::make('transfer_number')->withTitle('شماره')->searchable()->sortable()->width('150px'),
            Field::make('from_treasury_type')->withTitle(trans('accounting::accounting.bank_transfer_form.fields.from_treasury'))->customMethod('renderFromTreasury')->width('180px'),
            Field::make('to_treasury_type')->withTitle(trans('accounting::accounting.bank_transfer_form.fields.to_treasury'))->customMethod('renderToTreasury')->width('180px'),
            Field::number('amount')->withTitle('مبلغ')->sortable()->width('120px'),
            Field::make('status')->withTitle('وضعیت')->customMethod('renderStatus')->sortable()->width('120px'),
            Field::date('transfer_date')->withTitle('تاریخ')->sortable()->width('120px'),
        ];
    }

    public function rules(): array
    {
        return [
            'from_treasury' => ['required', 'string'],
            'to_treasury' => ['required', 'string', 'different:from_treasury'],
            'amount' => ['required', 'numeric', 'min:0'],
            'transfer_date' => ['required', 'date'],
            'transfer_fee' => ['nullable', 'numeric', 'min:0'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function prepareForValidation(Request &$request): void
    {
        $this->mergeParsedDecimalFields($request, ['amount', 'transfer_fee'], 'transfer_fee');

        if ($request->has('transfer_date')) {
            $val = $request->input('transfer_date');
            if (is_string($val) && trim($val) !== '') {
                $g = app(\RMS\Accounting\Services\AccountingDateInputNormalizer::class)
                    ->normalizeFilterDateToGregorian(trim($val));
                if ($g !== null) {
                    $request->merge(['transfer_date' => $g]);
                }
            }
        }
    }

    public function attributes(): array
    {
        return [
            'from_treasury' => trans('accounting::accounting.bank_transfer_form.fields.from_treasury'),
            'to_treasury' => trans('accounting::accounting.bank_transfer_form.fields.to_treasury'),
            'amount' => trans('accounting::accounting.bank_transfer_form.fields.amount'),
            'transfer_date' => trans('accounting::accounting.bank_transfer_form.fields.transfer_date'),
            'transfer_fee' => trans('accounting::accounting.bank_transfer_form.fields.transfer_fee'),
            'reference_number' => trans('accounting::accounting.bank_transfer_form.fields.reference_number'),
            'description' => trans('accounting::accounting.bank_transfer_form.fields.description'),
        ];
    }

    protected function beforeAdd(Request &$request): void
    {
        parent::beforeAdd($request);
        [$fromType, $fromId] = $this->parseTreasurySelection((string) $request->input('from_treasury'));
        [$toType, $toId] = $this->parseTreasurySelection((string) $request->input('to_treasury'));

        $request->merge([
            'transfer_number' => BankTransfer::generateTransferNumber(),
            'currency_code' => $this->resolveDefaultCurrencyCode(),
            'fx_rate' => '1',
            'status' => 'pending',
            'from_treasury_type' => $fromType,
            'from_treasury_id' => $fromId,
            'to_treasury_type' => $toType,
            'to_treasury_id' => $toId,
            'from_bank_id' => $fromType === BankTransfer::TREASURY_TYPE_BANK ? $fromId : null,
            'to_bank_id' => $toType === BankTransfer::TREASURY_TYPE_BANK ? $toId : null,
        ]);

        $request->request->remove('from_treasury');
        $request->request->remove('to_treasury');
    }

    protected function beforeUpdate(Request &$request, int|string $id): void
    {
        $model = $this->modelOrFail((int) $id);
        if ($model->status !== 'pending') {
            throw ValidationException::withMessages([
                '_form' => [trans('accounting::accounting.bank_transfer_form.messages.edit_only_pending')],
            ]);
        }

        parent::beforeUpdate($request, $id);
        [$fromType, $fromId] = $this->parseTreasurySelection((string) $request->input('from_treasury'));
        [$toType, $toId] = $this->parseTreasurySelection((string) $request->input('to_treasury'));
        $request->merge([
            'from_treasury_type' => $fromType,
            'from_treasury_id' => $fromId,
            'to_treasury_type' => $toType,
            'to_treasury_id' => $toId,
            'from_bank_id' => $fromType === BankTransfer::TREASURY_TYPE_BANK ? $fromId : null,
            'to_bank_id' => $toType === BankTransfer::TREASURY_TYPE_BANK ? $toId : null,
        ]);
        $request->request->remove('from_treasury');
        $request->request->remove('to_treasury');
    }

    public function process(Request $request, $id)
    {
        try {
            $this->transferService->processTransfer((int) $id);

            return redirect()->back()->with('success', trans('accounting::accounting.bank_transfer_form.messages.processed'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $this->formatActionExceptionMessage('accounting::accounting.bank_transfer_form.messages.action_failed', $e));
        }
    }

    public function complete(Request $request, $id)
    {
        try {
            $this->transferService->completeTransfer((int) $id);

            return redirect()->back()->with('success', trans('accounting::accounting.bank_transfer_form.messages.completed'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $this->formatActionExceptionMessage('accounting::accounting.bank_transfer_form.messages.action_failed', $e));
        }
    }

    public function cancel(Request $request, $id)
    {
        $request->validate([
            'reason' => ['nullable', 'string'],
        ]);

        try {
            $this->transferService->cancelTransfer((int) $id, $request->reason);

            return redirect()->back()->with('success', trans('accounting::accounting.bank_transfer_form.messages.cancelled'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $this->formatActionExceptionMessage('accounting::accounting.bank_transfer_form.messages.action_failed', $e));
        }
    }

    public function renderStatus($row): string
    {
        $badges = [
            'pending' => '<span class="badge bg-warning text-dark">' . e(trans('accounting::accounting.bank_transfer_form.statuses.pending')) . '</span>',
            'completed' => '<span class="badge bg-success">' . e(trans('accounting::accounting.bank_transfer_form.statuses.completed')) . '</span>',
            'failed' => '<span class="badge bg-danger">' . e(trans('accounting::accounting.bank_transfer_form.statuses.failed')) . '</span>',
            'cancelled' => '<span class="badge bg-secondary">' . e(trans('accounting::accounting.bank_transfer_form.statuses.cancelled')) . '</span>',
        ];

        return $badges[$row->status] ?? e((string) $row->status);
    }

    public function renderFromTreasury($row): string
    {
        return e($this->resolveTreasuryLabelForRow($row, true));
    }

    public function renderToTreasury($row): string
    {
        return e($this->resolveTreasuryLabelForRow($row, false));
    }

    /**
     * @return array<string, string>
     */
    protected function getTreasuryOptions(): array
    {
        $options = [];
        foreach ($this->treasuryOptionsForFormSelect() as $item) {
            $options[$item['key']] = $item['label'];
        }

        return $options;
    }

    /**
     * @return array<int, array{key:string,label:string}>
     */
    protected function treasuryOptionsForFormSelect(): array
    {
        $rows = [];

        $banks = Bank::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
        foreach ($banks as $bank) {
            $rows[] = [
                'key' => BankTransfer::TREASURY_TYPE_BANK . ':' . $bank->id,
                'label' => '🏦 ' . $bank->name,
            ];
        }

        $cashBoxes = CashBox::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
        foreach ($cashBoxes as $cashBox) {
            $rows[] = [
                'key' => BankTransfer::TREASURY_TYPE_CASHBOX . ':' . $cashBox->id,
                'label' => '💵 ' . $cashBox->name,
            ];
        }

        $wallets = Wallet::query()
            ->where('active', true)
            ->orderBy('id')
            ->get(['id', 'wallet_type']);
        foreach ($wallets as $wallet) {
            $rows[] = [
                'key' => BankTransfer::TREASURY_TYPE_WALLET . ':' . $wallet->id,
                'label' => '👛 Wallet #' . $wallet->id . ' (' . $wallet->wallet_type . ')',
            ];
        }

        return $rows;
    }

    protected function resolveBankTransferFormDocumentTitle(bool $isEdit, ?BankTransfer $transfer = null): string
    {
        $appName = (string) config('app.name', 'RMS');

        if ($isEdit && $transfer !== null) {
            return trans('accounting::accounting.bank_transfer_form.document_title_edit', [
                'number' => $transfer->transfer_number,
                'app' => $appName,
            ]);
        }

        return trans('accounting::accounting.bank_transfer_form.document_title_create', [
            'app' => $appName,
        ]);
    }

    protected function getRedirectResponse(Request $request, int|string $id): RedirectResponse
    {
        if ($request->routeIs('admin.accounting.bank-transfers.store')) {
            return redirect()->route('admin.accounting.bank-transfers.edit', ['bank_transfer' => $id]);
        }

        return parent::getRedirectResponse($request, $id);
    }

    /**
     * @return array{0:string,1:int}
     */
    protected function parseTreasurySelection(string $raw): array
    {
        if (! str_contains($raw, ':')) {
            throw ValidationException::withMessages([
                '_form' => [trans('accounting::accounting.bank_transfer_form.messages.invalid_treasury_endpoint')],
            ]);
        }

        [$type, $idRaw] = explode(':', $raw, 2);
        $type = trim($type);
        $id = (int) trim($idRaw);

        if (! in_array($type, [
            BankTransfer::TREASURY_TYPE_BANK,
            BankTransfer::TREASURY_TYPE_CASHBOX,
            BankTransfer::TREASURY_TYPE_WALLET,
        ], true) || $id <= 0) {
            throw ValidationException::withMessages([
                '_form' => [trans('accounting::accounting.bank_transfer_form.messages.invalid_treasury_endpoint')],
            ]);
        }

        return [$type, $id];
    }

    protected function resolveTreasuryLabelForRow($row, bool $source): string
    {
        $typeKey = $source ? 'from_treasury_type' : 'to_treasury_type';
        $idKey = $source ? 'from_treasury_id' : 'to_treasury_id';
        $bankIdKey = $source ? 'from_bank_id' : 'to_bank_id';

        $type = '';
        $id = 0;

        if (is_object($row)) {
            $type = property_exists($row, $typeKey) ? (string) ($row->{$typeKey} ?? '') : '';
            $id = property_exists($row, $idKey) ? (int) ($row->{$idKey} ?? 0) : 0;
            $legacyBankId = property_exists($row, $bankIdKey) ? (int) ($row->{$bankIdKey} ?? 0) : 0;
            if ($type === '' && $legacyBankId > 0) {
                $type = BankTransfer::TREASURY_TYPE_BANK;
                $id = $legacyBankId;
            }
        }

        // If list query doesn't include treasury id fields, load from the transfer row by id.
        if (($type === '' || $id <= 0) && is_object($row) && property_exists($row, 'id')) {
            $transfer = BankTransfer::query()->find((int) $row->id);
            if ($transfer) {
                $type = $source
                    ? (string) ($transfer->from_treasury_type ?: ($transfer->from_bank_id ? BankTransfer::TREASURY_TYPE_BANK : ''))
                    : (string) ($transfer->to_treasury_type ?: ($transfer->to_bank_id ? BankTransfer::TREASURY_TYPE_BANK : ''));
                $id = $source
                    ? (int) ($transfer->from_treasury_id ?: $transfer->from_bank_id)
                    : (int) ($transfer->to_treasury_id ?: $transfer->to_bank_id);
            }
        }

        if ($id <= 0 || $type === '') {
            return '-';
        }

        if ($type === BankTransfer::TREASURY_TYPE_BANK) {
            $name = (string) optional(Bank::query()->find($id))->name;
            return $name !== '' ? '🏦 ' . $name : ('🏦 #' . $id);
        }

        if ($type === BankTransfer::TREASURY_TYPE_CASHBOX) {
            $name = (string) optional(CashBox::query()->find($id))->name;
            return $name !== '' ? '💵 ' . $name : ('💵 #' . $id);
        }

        if ($type === BankTransfer::TREASURY_TYPE_WALLET) {
            $wallet = Wallet::query()->find($id);
            if ($wallet) {
                return '👛 Wallet #' . $wallet->id . ' (' . $wallet->wallet_type . ')';
            }
            return '👛 Wallet #' . $id;
        }

        return '#'.$id;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildTransferSummary(BankTransfer $transfer): array
    {
        $fromType = (string) ($transfer->from_treasury_type ?: ($transfer->from_bank_id ? BankTransfer::TREASURY_TYPE_BANK : ''));
        $fromId = (int) ($transfer->from_treasury_id ?: $transfer->from_bank_id);
        $toType = (string) ($transfer->to_treasury_type ?: ($transfer->to_bank_id ? BankTransfer::TREASURY_TYPE_BANK : ''));
        $toId = (int) ($transfer->to_treasury_id ?: $transfer->to_bank_id);

        $document = null;
        if ($transfer->accounting_document_id) {
            $document = AccountingDocument::query()->find((int) $transfer->accounting_document_id);
        }

        return [
            'from_label' => $this->resolveTreasuryLabelByTypeAndId($fromType, $fromId),
            'to_label' => $this->resolveTreasuryLabelByTypeAndId($toType, $toId),
            'status_label' => trans('accounting::accounting.bank_transfer_form.statuses.' . $transfer->status),
            'transfer_date' => $transfer->transfer_date,
            'value_date' => $transfer->value_date,
            'amount' => (float) $transfer->amount,
            'fee' => (float) ($transfer->transfer_fee ?? 0),
            'reference_number' => (string) ($transfer->reference_number ?? ''),
            'description' => (string) ($transfer->description ?? ''),
            'processed_at' => $transfer->processed_at,
            'document_id' => (int) ($transfer->accounting_document_id ?? 0),
            'document_number' => (string) ($document?->document_number ?? ''),
        ];
    }

    protected function resolveTreasuryLabelByTypeAndId(string $type, int $id): string
    {
        if ($id <= 0 || $type === '') {
            return '-';
        }

        if ($type === BankTransfer::TREASURY_TYPE_BANK) {
            $name = (string) optional(Bank::query()->find($id))->name;
            return $name !== '' ? '🏦 ' . $name : ('🏦 #' . $id);
        }

        if ($type === BankTransfer::TREASURY_TYPE_CASHBOX) {
            $name = (string) optional(CashBox::query()->find($id))->name;
            return $name !== '' ? '💵 ' . $name : ('💵 #' . $id);
        }

        if ($type === BankTransfer::TREASURY_TYPE_WALLET) {
            $wallet = Wallet::query()->find($id);
            if ($wallet) {
                return '👛 Wallet #' . $wallet->id . ' (' . $wallet->wallet_type . ')';
            }
            return '👛 Wallet #' . $id;
        }

        return '#'.$id;
    }
}
