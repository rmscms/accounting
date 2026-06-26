<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RMS\Accounting\Http\Controllers\Admin\Concerns\RendersAccountingStructuredResourceForm;
use RMS\Accounting\Models\PaymentMethod;
use RMS\Accounting\Models\PurchaseOrder;
use RMS\Accounting\Models\Supplier;
use RMS\Accounting\Models\SupplierInvoice;
use RMS\Accounting\Models\SupplierRefund;
use RMS\Accounting\Services\ChequeAutoCreationService;
use RMS\Accounting\Services\PaymentDestinationCatalog;
use RMS\Accounting\Services\RefundService;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Data\Field;

class SupplierRefundsController extends AccountingAdminController implements HasList, HasForm, ShouldFilter
{
    use RendersAccountingStructuredResourceForm;

    public const SETTLEMENT_CASH = 'cash';

    public const SETTLEMENT_OFFSET_PAYABLE = 'offset_payable';

    public const SETTLEMENT_SUPPLIER_CREDIT = 'supplier_credit';

    protected RefundService $refundService;

    public function __construct(Filesystem $filesystem, RefundService $refundService)
    {
        parent::__construct($filesystem);
        $this->refundService = $refundService;
    }

    public function table(): string
    {
        return 'supplier_refunds';
    }

    public function modelName(): string
    {
        return SupplierRefund::class;
    }

    public function baseRoute(): string
    {
        return 'accounting.supplier-refunds';
    }

    public function routeParameter(): string
    {
        return 'supplier_refund';
    }

    /**
     * Join suppliers to provide supplier name in list queries.
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
     * اولویت: فاکتور خرید (منبع اصلی supplier و invoice)، سپس سفارش خرید، سپس supplier_id در query.
     *
     * @return array{supplier_id: ?string, supplier_label: ?string, supplier_invoice_id: ?string, purchase_order_id: ?string}
     */
    protected function resolveSupplierRefundQueryPrefill(Request $request): array
    {
        $empty = ['supplier_id' => null, 'supplier_label' => null, 'supplier_invoice_id' => null, 'purchase_order_id' => null];

        $rawInv = $request->query('supplier_invoice_id');
        if ($rawInv !== null && $rawInv !== '' && ctype_digit((string) $rawInv)) {
            $inv = SupplierInvoice::query()->find((int) $rawInv);
            if ($inv && (int) ($inv->supplier_id ?? 0) > 0) {
                $supplier = Supplier::query()->find((int) $inv->supplier_id);
                $poFromQuery = $request->query('purchase_order_id');
                $poIdStr = null;
                if ($poFromQuery !== null && $poFromQuery !== '' && ctype_digit((string) $poFromQuery)) {
                    $poIdStr = (string) (int) $poFromQuery;
                } elseif ((int) ($inv->purchase_order_id ?? 0) > 0) {
                    $poIdStr = (string) (int) $inv->purchase_order_id;
                }

                return [
                    'supplier_id' => (string) $inv->supplier_id,
                    'supplier_label' => $supplier ? (string) $supplier->name : null,
                    'supplier_invoice_id' => (string) $inv->getKey(),
                    'purchase_order_id' => $poIdStr,
                ];
            }
        }

        $rawPo = $request->query('purchase_order_id');
        if ($rawPo !== null && $rawPo !== '' && ctype_digit((string) $rawPo)) {
            $po = PurchaseOrder::query()->find((int) $rawPo);
            if ($po && (int) ($po->supplier_id ?? 0) > 0) {
                $supplier = Supplier::query()->find((int) $po->supplier_id);

                return [
                    'supplier_id' => (string) $po->supplier_id,
                    'supplier_label' => $supplier ? (string) $supplier->name : null,
                    'supplier_invoice_id' => null,
                    'purchase_order_id' => (string) $po->getKey(),
                ];
            }
        }

        $rawSid = $request->query('supplier_id');
        if ($rawSid !== null && $rawSid !== '' && ctype_digit((string) $rawSid)) {
            $supplier = Supplier::query()->find((int) $rawSid);

            return [
                'supplier_id' => (string) $rawSid,
                'supplier_label' => $supplier ? (string) $supplier->name : null,
                'supplier_invoice_id' => null,
                'purchase_order_id' => null,
            ];
        }

        return $empty;
    }

    protected function resolveSupplierRefundInvoicePreviewId(Request $request): ?int
    {
        $raw = $request->query('supplier_invoice_id');
        if ($raw !== null && $raw !== '' && ctype_digit((string) $raw)) {
            return (int) $raw;
        }
        $old = $request->old('supplier_invoice_id');
        if ($old !== null && $old !== '' && ctype_digit((string) $old)) {
            return (int) $old;
        }

        return null;
    }

    /**
     * شناسهٔ PO برای کارت workflow و فیلد مخفی (بعد از خطای اعتبارسنجی از old).
     */
    protected function resolveSupplierRefundPurchaseOrderContextId(Request $request, array $prefill): ?int
    {
        $old = $request->old('refund_context_purchase_order_id');
        if ($old !== null && $old !== '' && ctype_digit((string) $old)) {
            return (int) $old;
        }
        $q = $prefill['purchase_order_id'] ?? null;
        if ($q !== null && $q !== '' && ctype_digit((string) $q)) {
            return (int) $q;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function structuredAccountingFormExtraViewVariables(Request $request, bool $isEdit, ?Model $model): array
    {
        $prefill = $this->resolveSupplierRefundQueryPrefill($request);

        $supplierSelectInitialText = $prefill['supplier_label'];
        $supplierRefundPrefillSupplierId = $prefill['supplier_id'];
        $supplierRefundPrefillInvoiceId = $prefill['supplier_invoice_id'];
        $supplierRefundPrefillPurchaseOrderId = $prefill['purchase_order_id'] ?? null;
        $supplierInvoiceSelectInitialText = $prefill['supplier_invoice_id']
            ? (string) (SupplierInvoice::query()->whereKey((int) $prefill['supplier_invoice_id'])->value('invoice_number') ?? '')
            : null;

        if ($isEdit && $model instanceof SupplierRefund) {
            $supplierRefundPrefillSupplierId = (string) $model->supplier_id;
            $supplier = Supplier::query()->find((int) $model->supplier_id);
            $supplierSelectInitialText = $supplier ? (string) $supplier->name : null;
            if ((int) ($model->supplier_invoice_id ?? 0) > 0) {
                $supplierRefundPrefillInvoiceId = (string) $model->supplier_invoice_id;
                $supplierInvoiceSelectInitialText = (string) (SupplierInvoice::query()->whereKey((int) $model->supplier_invoice_id)->value('invoice_number') ?? '');
            }
            $supplierRefundPrefillPurchaseOrderId = null;
        } else {
            $oldPo = $request->old('refund_context_purchase_order_id');
            if ($oldPo !== null && $oldPo !== '' && ctype_digit((string) $oldPo)) {
                $supplierRefundPrefillPurchaseOrderId = (string) (int) $oldPo;
            }
        }

        $invoicePreview = null;
        if ($isEdit && $model instanceof SupplierRefund && (int) ($model->supplier_invoice_id ?? 0) > 0) {
            $invoicePreview = SupplierInvoice::query()
                ->with(['items', 'supplier'])
                ->find((int) $model->supplier_invoice_id);
        } elseif (! $isEdit) {
            $previewId = $this->resolveSupplierRefundInvoicePreviewId($request);
            if ($previewId > 0) {
                $invoicePreview = SupplierInvoice::query()
                    ->with(['items', 'supplier'])
                    ->find($previewId);
            }
        }

        $poPreview = null;
        if (! $isEdit && $invoicePreview === null) {
            $poCtxId = $this->resolveSupplierRefundPurchaseOrderContextId($request, $prefill);
            if ($poCtxId > 0 && $supplierRefundPrefillSupplierId !== null && $supplierRefundPrefillSupplierId !== '') {
                $poRow = PurchaseOrder::query()->with(['items', 'supplier'])->find($poCtxId);
                if ($poRow && (int) $poRow->supplier_id === (int) $supplierRefundPrefillSupplierId) {
                    $poPreview = $poRow;
                }
            }
        }

        return [
            'supplierRefundPrefillSupplierId' => $supplierRefundPrefillSupplierId,
            'supplierRefundPrefillInvoiceId' => $supplierRefundPrefillInvoiceId,
            'supplierRefundPrefillPurchaseOrderId' => $supplierRefundPrefillPurchaseOrderId,
            'supplierSelectInitialText' => $supplierSelectInitialText,
            'supplierInvoiceSelectInitialText' => $supplierInvoiceSelectInitialText,
            'supplierRefundContextInvoice' => $invoicePreview,
            'supplierRefundContextPurchaseOrder' => $poPreview,
        ];
    }

    protected function afterConfigureStructuredAccountingFormView(Request $request, bool $isEdit, ?Model $model): void
    {
        if ($this->structuredAccountingFormSlug() !== 'supplier_refunds') {
            return;
        }
        if (! $isEdit) {
            $this->view->withJs('vendor/accounting/admin/js/payment-destination-picker.js', true);
        }
        $this->view->withJs('vendor/accounting/admin/js/accounting-ajax-supplier-widgets.js', true);
    }

    protected function afterStructuredAccountingFormPrepareForValidation(Request $request): void
    {
        if ($this->structuredAccountingFormSlug() !== 'supplier_refunds') {
            return;
        }
        $rawInv = $request->input('supplier_invoice_id');
        if ($rawInv === null || $rawInv === '' || (string) $rawInv === '0') {
            $request->merge(['supplier_invoice_id' => null]);
        } elseif (is_numeric($rawInv)) {
            $request->merge(['supplier_invoice_id' => (int) $rawInv]);
        }

        $rawPoCtx = $request->input('refund_context_purchase_order_id');
        if ($rawPoCtx === null || $rawPoCtx === '' || (string) $rawPoCtx === '0') {
            $request->merge(['refund_context_purchase_order_id' => null]);
        } elseif (is_numeric($rawPoCtx)) {
            $request->merge(['refund_context_purchase_order_id' => (int) $rawPoCtx]);
        }

        $routeRefund = $request->route($this->routeParameter());
        $isRefundEdit = $routeRefund instanceof SupplierRefund && $routeRefund->exists;
        if (! $isRefundEdit) {
            $settlement = (string) $request->input('supplier_refund_settlement', self::SETTLEMENT_CASH);
            if (! in_array($settlement, [self::SETTLEMENT_CASH, self::SETTLEMENT_OFFSET_PAYABLE, self::SETTLEMENT_SUPPLIER_CREDIT], true)) {
                $request->merge(['supplier_refund_settlement' => self::SETTLEMENT_CASH]);
                $settlement = self::SETTLEMENT_CASH;
            }
            if ($settlement !== self::SETTLEMENT_CASH) {
                $request->merge([
                    'payment_method_id' => null,
                    'bank_id' => null,
                    'cash_box_id' => null,
                    'cheque_id' => null,
                    'pos_terminal_id' => null,
                    'wallet_id' => null,
                ]);
            } else {
                $this->applyRefundPaymentDestinationValidation($request);
            }

            $this->validateSupplierRefundSettlementAgainstInvoice($request, $settlement);
        }
    }

    /**
     * اعتبارسنجی ترکیب «نوع تسویه» و فاکتور خرید (پس از نرمال‌سازی شناسهٔ فاکتور).
     */
    protected function validateSupplierRefundSettlementAgainstInvoice(Request $request, string $settlement): void
    {
        if ($settlement === self::SETTLEMENT_CASH) {
            return;
        }

        $invId = (int) $request->input('supplier_invoice_id', 0);
        if ($invId <= 0) {
            throw ValidationException::withMessages([
                'supplier_invoice_id' => (string) trans('accounting::accounting.supplier_refund_form.invoice_required_for_settlement'),
            ]);
        }

        $supplierId = (int) $request->input('supplier_id', 0);
        $inv = SupplierInvoice::query()->find($invId);
        if (! $inv || (int) $inv->supplier_id !== $supplierId) {
            throw ValidationException::withMessages([
                'supplier_invoice_id' => (string) trans('accounting::accounting.supplier_refund_form.invoice_invalid_for_supplier'),
            ]);
        }

        $total = (float) ($inv->total_amount ?? 0);
        $paid = (float) ($inv->paid_amount ?? 0);
        $amount = (float) $request->input('amount', 0);
        $open = max(0.0, round($total - $paid, 4));

        if ($settlement === self::SETTLEMENT_OFFSET_PAYABLE) {
            if ((string) $inv->payment_status === SupplierInvoice::STATUS_PAID && $paid + 0.0001 >= $total) {
                throw ValidationException::withMessages([
                    'supplier_refund_settlement' => (string) trans('accounting::accounting.supplier_refund_form.offset_requires_unpaid_invoice'),
                ]);
            }
            if ($amount > $open + 0.01) {
                throw ValidationException::withMessages([
                    'amount' => (string) trans('accounting::accounting.supplier_refund_form.amount_exceeds_open_balance', [
                        'open' => number_format($open, 2),
                    ]),
                ]);
            }

            return;
        }

        if ($settlement === self::SETTLEMENT_SUPPLIER_CREDIT) {
            $fullyPaid = ((string) $inv->payment_status === SupplierInvoice::STATUS_PAID)
                || ($paid + 0.0001 >= $total && $total > 0);
            if (! $fullyPaid) {
                throw ValidationException::withMessages([
                    'supplier_refund_settlement' => (string) trans('accounting::accounting.supplier_refund_form.credit_requires_paid_invoice'),
                ]);
            }
        }
    }

    /**
     * نرمال‌سازی FKهای مقصد تسویه و اعتبارسنجی ترکیب روش پرداخت + مقصد (مثل supplier_payments).
     */
    private function applyRefundPaymentDestinationValidation(Request $request): void
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
                'context' => 'supplier_refund',
                'source_short' => 'SR',
                'payment_method_id' => $pm,
                'cheque_type' => \RMS\Accounting\Models\Cheque::TYPE_ISSUED,
                'party_id' => (int) ($supplier?->party_id ?? 0),
                'amount' => (float) ($request->input('amount') ?? 0),
                'currency_code' => (string) ($request->input('currency_code') ?: 'IRR'),
                'issue_date' => (string) ($request->input('refund_date') ?: now()->toDateString()),
                'due_date' => (string) ($request->input('refund_date') ?: now()->toDateString()),
                'notes' => (string) ($request->input('reason') ?: ''),
            ]);
            if ($autoCheque) {
                $request->merge(['cheque_id' => (int) $autoCheque->id]);
            }
        }
        $hasAnyDestination = $request->filled('bank_id')
            || $request->filled('cash_box_id')
            || $request->filled('cheque_id')
            || $request->filled('pos_terminal_id')
            || $request->filled('wallet_id');

        if ($pm <= 0) {
            if ($hasAnyDestination) {
                throw ValidationException::withMessages([
                    'payment_method_id' => (string) trans('accounting::accounting.payment_destination.method_required'),
                ]);
            }

            return;
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
            throw ValidationException::withMessages(['payment_method_id' => $res['message']]);
        }
    }

    protected function beforeAdd(Request &$request): void
    {
        $routeRefund = $request->route($this->routeParameter());
        $isRefundEdit = $routeRefund instanceof SupplierRefund && $routeRefund->exists;

        if (! $isRefundEdit && ! $request->filled('refund_number')) {
            $request->merge(['refund_number' => $this->refundService->allocateNextSupplierRefundNumber()]);
        }

        if (! $isRefundEdit) {
            $settlement = (string) $request->input('supplier_refund_settlement', self::SETTLEMENT_CASH);
            if ($settlement === self::SETTLEMENT_OFFSET_PAYABLE) {
                $request->merge([
                    'refund_method' => SupplierRefund::METHOD_OFFSET_PAYABLE,
                    'payment_method_id' => null,
                    'bank_id' => null,
                    'cash_box_id' => null,
                    'cheque_id' => null,
                    'wallet_id' => null,
                    'pos_terminal_id' => null,
                ]);
            } elseif ($settlement === self::SETTLEMENT_SUPPLIER_CREDIT) {
                $request->merge([
                    'refund_method' => SupplierRefund::METHOD_SUPPLIER_CREDIT_ON_ACCOUNT,
                    'payment_method_id' => null,
                    'bank_id' => null,
                    'cash_box_id' => null,
                    'cheque_id' => null,
                    'wallet_id' => null,
                    'pos_terminal_id' => null,
                ]);
            } else {
                $pmId = (int) $request->input('payment_method_id', 0);
                if ($pmId > 0) {
                    $pm = PaymentMethod::query()->find($pmId);
                    if ($pm) {
                        $request->merge(['refund_method' => $this->mapPaymentMethodTypeToRefundMethod((string) $pm->type)]);
                    }
                } elseif (! $request->filled('refund_method')) {
                    $request->merge(['refund_method' => SupplierRefund::METHOD_BANK_TRANSFER]);
                }
            }
        }

        $amt = (float) $request->input('amount', 0);
        $fx = (float) ($request->input('fx_rate', 1) ?: 1);
        if (! $request->filled('amount_base')) {
            $request->merge(['amount_base' => $amt * $fx]);
        }
        if (! $request->filled('currency_code')) {
            $request->merge(['currency_code' => 'IRR']);
        }
        if (! $request->filled('fx_rate')) {
            $request->merge(['fx_rate' => 1]);
        }
        if (! $request->filled('store_id')) {
            $request->merge(['store_id' => 0]);
        }
    }

    protected function afterAdd(Request $request, int|string $id, Model $model): void
    {
        if (! $model instanceof SupplierRefund) {
            return;
        }

        DB::transaction(function () use ($model) {
            $this->refundService->ensureLedgerPosted($model);
            $model->refresh();
            if ((int) ($model->cheque_id ?? 0) > 0) {
                $cheque = \RMS\Accounting\Models\Cheque::query()->find((int) $model->cheque_id);
                if ($cheque) {
                    app(ChequeAutoCreationService::class)->attachSource($cheque, SupplierRefund::class, (int) $model->id);
                }
            }
            if ((int) ($model->accounting_document_id ?? 0) > 0 && (string) $model->status !== SupplierRefund::STATUS_RECEIVED) {
                $model->update([
                    'status' => SupplierRefund::STATUS_RECEIVED,
                    'received_at' => now(),
                    'approved_by_user_id' => \RMS\Accounting\Support\AuditActor::userId(),
                    'approved_at' => now(),
                ]);
            }
        });
    }

    protected function mapPaymentMethodTypeToRefundMethod(string $type): string
    {
        return match ($type) {
            PaymentMethod::TYPE_CASH => SupplierRefund::METHOD_CASH,
            PaymentMethod::TYPE_CHEQUE => SupplierRefund::METHOD_CHEQUE,
            PaymentMethod::TYPE_ONLINE,
            PaymentMethod::TYPE_POS,
            PaymentMethod::TYPE_WALLET => SupplierRefund::METHOD_ONLINE,
            PaymentMethod::TYPE_CARD_TRANSFER => SupplierRefund::METHOD_BANK_TRANSFER,
            default => SupplierRefund::METHOD_BANK_TRANSFER,
        };
    }

    public function getFieldsForm(): array
    {
        $dnId = request()->query('debit_note_id');
        $prefill = $this->resolveSupplierRefundQueryPrefill(request());
        $invoiceSearchUrl = route('admin.accounting.supplier-invoices.search-invoices');

        $routeRefund = request()->route($this->routeParameter());
        $isRefundEdit = $routeRefund instanceof SupplierRefund && $routeRefund->exists;

        $supplierField = Field::number('supplier_id')
            ->withTitle(trans('accounting::accounting.supplier.name'))
            ->required()
            ->withAttributes(['structured_widget' => 'ajax_supplier_select']);
        if ($isRefundEdit) {
            $supplierField = $supplierField->withDefaultValue((string) $routeRefund->supplier_id);
        } elseif ($prefill['supplier_id'] !== null && $prefill['supplier_id'] !== '') {
            $supplierField = $supplierField->withDefaultValue($prefill['supplier_id']);
        }

        $invoiceField = Field::number('supplier_invoice_id')
            ->withTitle(trans('accounting::accounting.payment.supplier_invoice_id'))
            ->optional()
            ->withAttributes([
                'structured_widget' => 'ajax_supplier_invoice_select',
                'depends_on_field' => 'supplier_id',
                'supplier_invoice_search_url' => $invoiceSearchUrl,
            ]);
        if ($isRefundEdit && (int) ($routeRefund->supplier_invoice_id ?? 0) > 0) {
            $invoiceField = $invoiceField->withDefaultValue((string) $routeRefund->supplier_invoice_id);
        } elseif ($prefill['supplier_invoice_id'] !== null && $prefill['supplier_invoice_id'] !== '') {
            $invoiceField = $invoiceField->withDefaultValue($prefill['supplier_invoice_id']);
        }

        $amountField = Field::number('amount', trans('accounting::accounting.payment.amount'))->required();

        $tail = [
            Field::date('refund_date', trans('accounting::accounting.supplier_refund_form.refund_date'))->withDefaultValue(now()),
            Field::number('debit_note_id', trans('accounting::accounting.supplier_refund_form.debit_note_id'))
                ->optional()
                ->withDefaultValue($dnId !== null && $dnId !== '' && ctype_digit((string) $dnId) ? (string) $dnId : ''),
            Field::textarea('reason', trans('accounting::accounting.supplier_refund_form.reason'))->optional(),
        ];

        if ($isRefundEdit) {
            return [
                $supplierField,
                $invoiceField,
                $amountField,
                Field::select('refund_method', trans('accounting::accounting.supplier_refund_form.refund_method'))->setOptions([
                    'cash' => trans('accounting::accounting.supplier_refund_form.method_cash'),
                    'bank_transfer' => trans('accounting::accounting.supplier_refund_form.method_bank_transfer'),
                    'cheque' => trans('accounting::accounting.supplier_refund_form.method_cheque'),
                    'online' => trans('accounting::accounting.supplier_refund_form.method_online'),
                    'deduct_from_next_purchase' => trans('accounting::accounting.supplier_refund_form.method_deduct_from_next_purchase'),
                    SupplierRefund::METHOD_OFFSET_PAYABLE => trans('accounting::accounting.supplier_refund_form.method_offset_payable'),
                    SupplierRefund::METHOD_SUPPLIER_CREDIT_ON_ACCOUNT => trans('accounting::accounting.supplier_refund_form.method_supplier_credit_on_account'),
                ])->required(),
                ...$tail,
            ];
        }

        $settlementField = Field::select('supplier_refund_settlement', trans('accounting::accounting.supplier_refund_form.settlement_type'))
            ->setOptions([
                self::SETTLEMENT_CASH => trans('accounting::accounting.supplier_refund_form.settlement_cash'),
                self::SETTLEMENT_OFFSET_PAYABLE => trans('accounting::accounting.supplier_refund_form.settlement_offset_payable'),
                self::SETTLEMENT_SUPPLIER_CREDIT => trans('accounting::accounting.supplier_refund_form.settlement_supplier_credit'),
            ])
            ->required()
            ->withDefaultValue(self::SETTLEMENT_CASH)
            ->skipDatabase();

        return [
            $supplierField,
            $invoiceField,
            $settlementField,
            $amountField,
            Field::number('payment_method_id')
                ->withTitle(trans('accounting::accounting.supplier_refund_form.payment_destination_label'))
                ->optional()
                ->withAttributes([
                    'structured_widget' => 'payment_destination_picker',
                    'payment_destination_context' => PaymentDestinationCatalog::CONTEXT_SUPPLIER_PAYMENT,
                ]),
            ...$tail,
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle('شناسه')->sortable()->width('80px'),
            Field::make('refund_number')->withTitle('شماره')->searchable()->sortable(),
            Field::make('supplier_name', 'suppliers.name')
                ->withTitle('تامین‌کننده')
                ->searchable()
                ->sortable(),
            Field::make('amount')->withTitle('مبلغ')->customMethod('renderAmount')->sortable(),
            Field::make('status')->withTitle('وضعیت')->customMethod('renderStatus'),
            Field::date('refund_date')->withTitle('تاریخ')->sortable(),
        ];
    }

    public function rules(): array
    {
        $routeRefund = request()->route($this->routeParameter());
        $isRefundEdit = $routeRefund instanceof SupplierRefund && $routeRefund->exists;

        $rules = [
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'supplier_invoice_id' => [
                'nullable',
                'integer',
                Rule::exists('supplier_invoices', 'id')->where(function ($q) {
                    $sid = request()->input('supplier_id');
                    if ($sid !== null && $sid !== '' && ctype_digit((string) $sid)) {
                        $q->where('supplier_id', (int) $sid);
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                }),
            ],
            'amount' => ['required', 'numeric', 'min:0'],
            'refund_date' => ['required', 'date'],
            'debit_note_id' => [
                'nullable',
                'integer',
                Rule::exists('debit_notes', 'id')->where(function ($q) {
                    $sid = request()->input('supplier_id');
                    if ($sid !== null && $sid !== '' && ctype_digit((string) $sid)) {
                        $q->where('supplier_id', (int) $sid);
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                }),
            ],
        ];

        if ($isRefundEdit) {
            $rules['refund_method'] = [
                'required',
                'in:cash,bank_transfer,cheque,online,deduct_from_next_purchase,offset_payable,supplier_credit_on_account',
            ];
        } else {
            $rules['supplier_refund_settlement'] = [
                'required',
                'string',
                'in:'.self::SETTLEMENT_CASH.','.self::SETTLEMENT_OFFSET_PAYABLE.','.self::SETTLEMENT_SUPPLIER_CREDIT,
            ];
            $rules['payment_method_id'] = [
                'nullable',
                'integer',
                Rule::exists('payment_methods', 'id'),
                Rule::requiredIf((string) request()->input('supplier_refund_settlement', self::SETTLEMENT_CASH) === self::SETTLEMENT_CASH),
            ];
            $rules['bank_id'] = ['nullable', 'integer', 'exists:banks,id'];
            $rules['cash_box_id'] = ['nullable', 'integer', 'exists:cash_boxes,id'];
            $rules['cheque_id'] = ['nullable', 'integer', 'exists:cheques,id'];
            $rules['wallet_id'] = ['nullable', 'integer', 'exists:wallets,id'];
            $rules['pos_terminal_id'] = ['nullable', 'integer', 'exists:pos_terminals,id'];
            $rules['refund_context_purchase_order_id'] = [
                'nullable',
                'integer',
                Rule::exists('purchase_orders', 'id')->where(function ($q) {
                    $sid = request()->input('supplier_id');
                    if ($sid !== null && $sid !== '' && ctype_digit((string) $sid)) {
                        $q->where('supplier_id', (int) $sid);
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                }),
            ];
        }

        return $rules;
    }

    protected function getSupplierOptions(): array
    {
        return Supplier::where('active', true)->pluck('name', 'id')->toArray();
    }

    public function renderAmount($row): string
    {
        return number_format($row->amount) . ' تومان';
    }

    public function renderStatus($row): string
    {
        $badges = [
            'pending' => '<span class="badge badge-warning">در انتظار</span>',
            'received' => '<span class="badge badge-success">دریافت شده</span>',
            'cancelled' => '<span class="badge badge-danger">لغو شده</span>',
        ];

        return $badges[$row->status] ?? $row->status;
    }
}
