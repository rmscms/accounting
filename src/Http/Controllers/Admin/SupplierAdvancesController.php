<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RMS\Accounting\Http\Controllers\Admin\Concerns\RendersAccountingStructuredResourceForm;
use RMS\Accounting\Models\PaymentMethod;
use RMS\Accounting\Models\Supplier;
use RMS\Accounting\Models\SupplierAdvance;
use RMS\Accounting\Services\AdvancePaymentService;
use RMS\Accounting\Services\ChequeAutoCreationService;
use RMS\Accounting\Services\PaymentDestinationCatalog;
use RMS\Core\Contracts\Data\UseDatabase;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Data\Field;

class SupplierAdvancesController extends AccountingAdminController implements HasList, HasForm, ShouldFilter
{
    use RendersAccountingStructuredResourceForm;

    protected AdvancePaymentService $advanceService;

    public function __construct(Filesystem $filesystem, AdvancePaymentService $service)
    {
        parent::__construct($filesystem);
        $this->advanceService = $service;
    }

    public function table(): string
    {
        return 'supplier_advances';
    }

    public function modelName(): string
    {
        return SupplierAdvance::class;
    }

    public function baseRoute(): string
    {
        return 'accounting.supplier-advances';
    }

    public function routeParameter(): string
    {
        return 'supplier_advance';
    }

    /**
     * join به suppliers برای نام تأمین‌کننده در لیست (بدون این، supplier.name در SQL نامعتبر است).
     */
    public function query(Builder $sql): void
    {
        $sql->leftJoin('suppliers', 'suppliers.id', '=', 'a.supplier_id')
            ->addSelect(
                'a.*',
                'suppliers.name as supplier_name'
            );
    }

    /**
     * @return array<string, mixed>
     */
    protected function structuredAccountingFormExtraViewVariables(Request $request, bool $isEdit, ?Model $model): array
    {
        $prefillSupplierId = null;
        $supplierSelectInitialText = null;
        if (! $isEdit) {
            $raw = $request->query('supplier_id');
            if ($raw !== null && $raw !== '' && ctype_digit((string) $raw)) {
                $prefillSupplierId = (string) $raw;
                $supplier = Supplier::query()->with('party')->find((int) $raw);
                if ($supplier) {
                    $supplierSelectInitialText = (string) ($supplier->party?->name ?: $supplier->name);
                }
            }
        }

        return [
            'supplierPaymentPrefillSupplierId' => $prefillSupplierId,
            'supplierSelectInitialText' => $supplierSelectInitialText,
        ];
    }

    protected function afterConfigureStructuredAccountingFormView(Request $request, bool $isEdit, ?Model $model): void
    {
        if ($this->structuredAccountingFormSlug() === 'supplier_advances') {
            $this->view->withJs('vendor/accounting/admin/js/payment-destination-picker.js', true);
            $this->view->withJs('vendor/accounting/admin/js/accounting-ajax-supplier-widgets.js', true);
            $this->view->withJs('vendor/accounting/admin/js/supplier-payment-structured-form.js', true);
        }
    }

    protected function afterStructuredAccountingFormPrepareForValidation(Request $request): void
    {
        if ($this->structuredAccountingFormSlug() === 'supplier_advances') {
            $this->applySupplierAdvancePaymentDestinationValidation($request);
        }
    }

    public function getFieldsForm(): array
    {
        return [
            Field::number('supplier_id')->withTitle(trans('accounting::accounting.supplier.name'))
                ->required()
                ->withAttributes(['structured_widget' => 'ajax_supplier_select']),
            Field::number('amount')->withTitle(trans('accounting::accounting.payment.amount'))->required(),
            Field::number('payment_method_id')->withTitle(trans('accounting::accounting.payment.payment_method'))
                ->required()
                ->withAttributes([
                    'structured_widget' => 'payment_destination_picker',
                    'payment_destination_context' => PaymentDestinationCatalog::CONTEXT_SUPPLIER_PAYMENT,
                ]),
            Field::date('advance_date', trans('accounting::accounting.payment.payment_date'))->withDefaultValue(now()),
            Field::textarea('notes', trans('accounting::accounting.payment.notes'))->optional(),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id', 'a.id')->withTitle('شناسه')->sortable()->width('80px'),
            Field::make('advance_number')->withTitle('شماره')->searchable()->sortable(),
            Field::make('supplier_name', 'suppliers.name')
                ->withTitle(trans('accounting::accounting.supplier.name'))
                ->searchable()
                ->sortable(),
            Field::make('amount')->withTitle('مبلغ کل')->customMethod('renderAmount'),
            Field::make('remaining_amount')->withTitle('مانده')->customMethod('renderRemaining')->sortable(),
            Field::make('status', 'a.status')
                ->withTitle(trans('accounting::accounting.common.status'))
                ->customMethod('renderSupplierAdvanceStatusBadge')
                ->sortable()
                ->width('140px'),
            Field::date('advance_date')->withTitle('تاریخ')->sortable(),
        ];
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'advance_date' => ['required', 'date'],
            'bank_id' => ['nullable', 'integer', 'exists:banks,id'],
            'cash_box_id' => ['nullable', 'integer', 'exists:cash_boxes,id'],
            'cheque_id' => ['nullable', 'integer', 'exists:cheques,id'],
            'pos_terminal_id' => ['nullable', 'integer', 'exists:pos_terminals,id'],
            'wallet_id' => ['nullable', 'integer', 'exists:wallets,id'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * ثبت پیش‌پرداخت از طریق سرویس (سند دفترکل) به‌جای fill مستقیم مدل.
     */
    protected function performAdd(Request $request): RedirectResponse
    {
        $this->beforeAdd($request);
        if (! $this instanceof UseDatabase) {
            throw new \InvalidArgumentException('Controller must implement ' . UseDatabase::class);
        }
        $advance = $this->advanceService->paySupplierAdvance($this->buildSupplierAdvancePayload($request));
        $this->afterAdd($request, $advance->getKey(), $advance);

        return $this->getRedirectResponse($request, $advance->getKey());
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildSupplierAdvancePayload(Request $request): array
    {
        $amountRaw = $request->input('amount');
        $amount = is_numeric($amountRaw)
            ? (float) $amountRaw
            : (float) str_replace([',', ' ', '٬'], '', (string) $amountRaw);

        return [
            'supplier_id' => (int) $request->input('supplier_id'),
            'advance_date' => $request->input('advance_date'),
            'amount' => $amount,
            'notes' => $request->input('notes'),
            'payment_method' => $this->mapSupplierAdvancePaymentMethodEnum($request),
            'payment_method_id' => $request->filled('payment_method_id') ? (int) $request->input('payment_method_id') : null,
            'bank_id' => $request->filled('bank_id') ? (int) $request->input('bank_id') : null,
            'cash_box_id' => $request->filled('cash_box_id') ? (int) $request->input('cash_box_id') : null,
            'cheque_id' => $request->filled('cheque_id') ? (int) $request->input('cheque_id') : null,
            'pos_terminal_id' => $request->filled('pos_terminal_id') ? (int) $request->input('pos_terminal_id') : null,
            'wallet_id' => $request->filled('wallet_id') ? (int) $request->input('wallet_id') : null,
        ];
    }

    protected function mapSupplierAdvancePaymentMethodEnum(Request $request): string
    {
        $pmId = (int) $request->input('payment_method_id', 0);
        if ($pmId <= 0) {
            return 'bank_transfer';
        }
        $pm = PaymentMethod::query()->find($pmId);
        if ($pm === null) {
            return 'bank_transfer';
        }

        return match ((string) $pm->type) {
            PaymentMethod::TYPE_CASH => 'cash',
            PaymentMethod::TYPE_CHEQUE => 'cheque',
            PaymentMethod::TYPE_ONLINE => 'online',
            PaymentMethod::TYPE_CARD_TRANSFER => PaymentMethod::TYPE_CARD_TRANSFER,
            PaymentMethod::TYPE_BANK_TRANSFER => PaymentMethod::TYPE_BANK_TRANSFER,
            default => 'bank_transfer',
        };
    }

    private function applySupplierAdvancePaymentDestinationValidation(Request $request): void
    {
        $catalog = app(PaymentDestinationCatalog::class);
        foreach (['bank_id', 'cash_box_id', 'cheque_id', 'pos_terminal_id', 'wallet_id'] as $fk) {
            $v = $request->input($fk);
            if ($v === '' || $v === null) {
                $request->merge([$fk => null]);

                continue;
            }
            if (is_numeric($v)) {
                $request->merge([$fk => (int) $v]);
            }
        }

        $pm = (int) $request->input('payment_method_id', 0);
        $methodType = (string) PaymentMethod::query()->whereKey($pm)->value('type');
        if ($pm > 0 && $methodType === PaymentMethod::TYPE_CHEQUE && ! $request->filled('cheque_id')) {
            $supplier = Supplier::query()->find((int) $request->input('supplier_id', 0));
            $autoCheque = app(ChequeAutoCreationService::class)->ensureCheque([
                'context' => 'supplier_advance',
                'source_short' => 'SA',
                'payment_method_id' => $pm,
                'cheque_type' => \RMS\Accounting\Models\Cheque::TYPE_ISSUED,
                'party_id' => (int) ($supplier?->party_id ?? 0),
                'amount' => (float) ($request->input('amount') ?? 0),
                'currency_code' => 'IRR',
                'issue_date' => (string) ($request->input('advance_date') ?: now()->toDateString()),
                'due_date' => (string) ($request->input('advance_date') ?: now()->toDateString()),
                'notes' => (string) ($request->input('notes') ?: ''),
            ]);
            if ($autoCheque) {
                $request->merge(['cheque_id' => (int) $autoCheque->id]);
            }
        }
        if ($pm <= 0) {
            throw ValidationException::withMessages([
                'payment_method_id' => (string) trans('accounting::accounting.payment_destination.method_required'),
            ]);
        }

        $res = $catalog->validateSelection(
            PaymentDestinationCatalog::CONTEXT_SUPPLIER_PAYMENT,
            $pm,
            $request->filled('bank_id') ? (int) $request->input('bank_id') : null,
            $request->filled('cash_box_id') ? (int) $request->input('cash_box_id') : null,
            $request->filled('cheque_id') ? (int) $request->input('cheque_id') : null,
            $request->filled('pos_terminal_id') ? (int) $request->input('pos_terminal_id') : null,
            $request->filled('wallet_id') ? (int) $request->input('wallet_id') : null
        );
        if (! $res['ok']) {
            throw ValidationException::withMessages(['payment_method_id' => (string) ($res['message'] ?? '')]);
        }
    }

    protected function afterAdd(Request $request, int|string $id, Model $model): void
    {
        if (! $model instanceof SupplierAdvance) {
            return;
        }
        if ((int) ($model->cheque_id ?? 0) > 0) {
            $cheque = \RMS\Accounting\Models\Cheque::query()->find((int) $model->cheque_id);
            if ($cheque) {
                app(ChequeAutoCreationService::class)->attachSource($cheque, SupplierAdvance::class, (int) $model->id);
            }
        }
    }

    public function apply(Request $request, $id)
    {
        $validated = $request->validate([
            'invoice_id' => 'required|exists:supplier_invoices,id',
            'amount' => 'required|numeric|min:0',
        ]);

        $this->advanceService->applySupplierAdvanceToInvoice($id, $validated['invoice_id'], $validated['amount']);

        return redirect()->back()->with('success', 'پیش پرداخت به فاکتور اعمال شد');
    }

    public function renderAmount($row): string
    {
        return number_format($row->amount) . ' تومان';
    }

    public function renderRemaining($row): string
    {
        return '<span class="text-primary font-weight-bold">' . number_format($row->remaining_amount) . ' تومان</span>';
    }

    public function renderSupplierAdvanceStatusBadge($row): string
    {
        $status = (string) ($row->status ?? '');

        $map = [
            SupplierAdvance::STATUS_ACTIVE => [
                'label' => (string) trans('accounting::accounting.supplier_advance.list_status.active'),
                'class' => 'bg-success',
            ],
            SupplierAdvance::STATUS_FULLY_APPLIED => [
                'label' => (string) trans('accounting::accounting.supplier_advance.list_status.fully_applied'),
                'class' => 'bg-secondary',
            ],
            SupplierAdvance::STATUS_REFUNDED => [
                'label' => (string) trans('accounting::accounting.supplier_advance.list_status.refunded'),
                'class' => 'bg-info text-dark',
            ],
            SupplierAdvance::STATUS_CANCELLED => [
                'label' => (string) trans('accounting::accounting.supplier_advance.list_status.cancelled'),
                'class' => 'bg-danger',
            ],
        ];

        if (isset($map[$status])) {
            $label = e($map[$status]['label']);
            $class = $map[$status]['class'];
        } elseif ($status !== '') {
            $label = e($status);
            $class = 'bg-dark';
        } else {
            $label = e((string) trans('accounting::accounting.supplier_advance.list_status.unknown'));
            $class = 'bg-dark';
        }

        return '<span class="badge rounded-pill '.$class.' px-2 py-1 fw-medium">'.$label.'</span>';
    }
}
