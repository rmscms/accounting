<?php

namespace RMS\Accounting\Http\Controllers\Admin;
use RMS\Accounting\Http\Controllers\Admin\Concerns\RendersAccountingStructuredResourceForm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RMS\Accounting\Models\Currency;
use RMS\Accounting\Models\PaymentMethod;
use RMS\Accounting\Models\PurchaseOrder;
use RMS\Accounting\Models\Supplier;
use RMS\Accounting\Models\SupplierInvoice;
use RMS\Accounting\Models\SupplierPayment;
use RMS\Accounting\Services\ChequeAutoCreationService;
use RMS\Accounting\Services\PaymentDestinationCatalog;
use RMS\Accounting\Services\SupplierPaymentService;
use Throwable;
use RMS\Core\Data\Field;
use RMS\Core\Models\Setting;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;

class SupplierPaymentsController extends AccountingAdminController implements HasList, HasForm, ShouldFilter
{
    use RendersAccountingStructuredResourceForm;

    /**
     * @return array<string, mixed>
     */
    protected function structuredAccountingFormExtraViewVariables(Request $request, bool $isEdit, ?Model $model): array
    {
        $supplierIdRaw = $request->query('supplier_id');
        $invoiceIdRaw = $request->query('supplier_invoice_id');
        $purchaseOrderIdRaw = $request->query('purchase_order_id');
        $amountQuery = $request->query('amount');

        $prefillSupplierId = $supplierIdRaw !== null && $supplierIdRaw !== '' ? (string) $supplierIdRaw : null;
        $prefillInvoiceId = $invoiceIdRaw !== null && $invoiceIdRaw !== '' ? (string) $invoiceIdRaw : null;
        $prefillPurchaseOrderIdFromQuery = $purchaseOrderIdRaw !== null && $purchaseOrderIdRaw !== '' && ctype_digit((string) $purchaseOrderIdRaw)
            ? (string) $purchaseOrderIdRaw : null;

        $oldPoRaw = $request->old('purchase_order_id');
        $prefillPurchaseOrderIdFromOld = ($oldPoRaw !== null && $oldPoRaw !== '' && ctype_digit((string) $oldPoRaw))
            ? (string) $oldPoRaw : null;

        $poLockFromQuery = ! $isEdit && $prefillPurchaseOrderIdFromQuery !== null;
        $poLockSticky = ! $isEdit
            && (string) $request->old('_supplier_payment_po_from_query', '') === '1'
            && $prefillPurchaseOrderIdFromOld !== null;
        $supplierPaymentPoLockedFromQuery = $poLockFromQuery || $poLockSticky;

        $prefillPurchaseOrderIdForBinding = $prefillPurchaseOrderIdFromQuery
            ?? ($supplierPaymentPoLockedFromQuery ? $prefillPurchaseOrderIdFromOld : null);

        $supplierSelectInitialText = null;
        $supplierPaymentPrefillAmount = null;

        $invoice = null;
        if ($prefillInvoiceId !== null && ctype_digit($prefillInvoiceId)) {
            $invoice = SupplierInvoice::query()->find((int) $prefillInvoiceId);
            if ($invoice && ($prefillSupplierId === null || $prefillSupplierId === '')) {
                $prefillSupplierId = (string) $invoice->supplier_id;
            }
        }

        $purchaseOrder = null;
        if ($prefillPurchaseOrderIdFromQuery !== null) {
            $purchaseOrder = PurchaseOrder::query()->find((int) $prefillPurchaseOrderIdFromQuery);
        } elseif ($supplierPaymentPoLockedFromQuery && $prefillPurchaseOrderIdFromOld !== null) {
            $purchaseOrder = PurchaseOrder::query()->find((int) $prefillPurchaseOrderIdFromOld);
        }

        if ($purchaseOrder && ($prefillSupplierId === null || $prefillSupplierId === '')) {
            $prefillSupplierId = (string) $purchaseOrder->supplier_id;
        }

        if (! $isEdit && $prefillSupplierId !== null && ctype_digit($prefillSupplierId)) {
            $supplier = Supplier::query()->find((int) $prefillSupplierId);
            if ($supplier) {
                $supplierSelectInitialText = (string) $supplier->name;
            }
        }

        if (! $isEdit) {
            if ($amountQuery !== null && $amountQuery !== '' && is_numeric($amountQuery)) {
                $supplierPaymentPrefillAmount = (string) $amountQuery;
            } elseif ($invoice !== null) {
                $due = (float) ($invoice->balance_due ?? 0);
                if ($due > 0) {
                    $supplierPaymentPrefillAmount = rtrim(rtrim(number_format($due, 4, '.', ''), '0'), '.');
                }
            } elseif ($purchaseOrder !== null && $prefillInvoiceId === null) {
                $supplierPaymentPrefillAmount = $this->supplierPaymentAmountPrefillFromPurchaseOrder($purchaseOrder);
            }
        }

        $supplierPaymentLinkedPurchaseOrderLabel = null;
        $supplierPaymentLinkedPurchaseOrderEditUrl = null;
        if ($supplierPaymentPoLockedFromQuery && $prefillPurchaseOrderIdForBinding !== null) {
            if ($purchaseOrder !== null) {
                $poNum = trim((string) ($purchaseOrder->po_number ?? ''));
                $supplierPaymentLinkedPurchaseOrderLabel = $poNum !== ''
                    ? $poNum
                    : ('#'.$purchaseOrder->getKey());
                try {
                    $supplierPaymentLinkedPurchaseOrderEditUrl = route('admin.accounting.purchase-orders.edit', ['purchase_order' => $purchaseOrder->getKey()]);
                } catch (\Throwable) {
                    $supplierPaymentLinkedPurchaseOrderEditUrl = null;
                }
            } else {
                $supplierPaymentLinkedPurchaseOrderLabel = '#'.(int) $prefillPurchaseOrderIdForBinding;
            }
        }

        $baseCurrency = $this->resolveBaseCurrencyCode();
        $fxCardCurrencyMeta = $this->currencyMetaForFxCard();
        $baseCurrencyDecimals = max(0, min(6, (int) ($fxCardCurrencyMeta[$baseCurrency]['decimals'] ?? 4)));
        $fxInvoice = $invoice instanceof SupplierInvoice ? $invoice : null;
        if (! $fxInvoice && ! $isEdit) {
            $oldInvoiceId = trim((string) $request->old('supplier_invoice_id', ''));
            if ($oldInvoiceId !== '' && ctype_digit($oldInvoiceId)) {
                $fxInvoice = SupplierInvoice::query()->find((int) $oldInvoiceId);
            }
        }
        if (! $fxInvoice && $isEdit && $model instanceof SupplierPayment && (int) ($model->supplier_invoice_id ?? 0) > 0) {
            $fxInvoice = SupplierInvoice::query()->find((int) $model->supplier_invoice_id);
        }

        $fxCardInitialCurrency = strtoupper((string) old(
            'currency_code',
            ($isEdit && $model instanceof SupplierPayment)
                ? (string) ($model->currency_code ?: ($fxInvoice?->currency_code ?: $baseCurrency))
                : (string) (($fxInvoice?->currency_code ?: $baseCurrency))
        ));
        if ($fxCardInitialCurrency === '') {
            $fxCardInitialCurrency = $baseCurrency;
        }
        $fxCardInitialRate = (string) old(
            'fx_rate_at_payment',
            ($isEdit && $model instanceof SupplierPayment)
                ? (string) ($model->fx_rate_at_payment ?: ($fxInvoice?->fx_rate_at_invoice ?: '1'))
                : (string) (($fxInvoice?->fx_rate_at_invoice ?: '1'))
        );
        $prefillAmountFloat = $this->parseDecimalInput($supplierPaymentPrefillAmount);
        $fxCardInitialBaseAmount = (string) old(
            'amount_base_at_payment',
            ($isEdit && $model instanceof SupplierPayment)
                ? (string) ($model->amount_base_at_payment ?? '')
                : (
                    ($prefillAmountFloat !== null && $fxCardInitialRate !== '')
                        ? (string) round($prefillAmountFloat * ((float) $fxCardInitialRate), $baseCurrencyDecimals)
                        : ''
                )
        );

        $defaultSupplierPaymentNumber = $isEdit ? null : $this->suggestNextSupplierPaymentNumber();

        return [
            'supplierPaymentPrefillSupplierId' => $prefillSupplierId,
            'supplierPaymentPrefillInvoiceId' => $prefillInvoiceId,
            'supplierPaymentPrefillPurchaseOrderId' => $prefillPurchaseOrderIdForBinding,
            'supplierPaymentPoLockedFromQuery' => $supplierPaymentPoLockedFromQuery,
            'supplierPaymentLinkedPurchaseOrderLabel' => $supplierPaymentLinkedPurchaseOrderLabel,
            'supplierPaymentLinkedPurchaseOrderEditUrl' => $supplierPaymentLinkedPurchaseOrderEditUrl,
            'supplierSelectInitialText' => $supplierSelectInitialText,
            'supplierPaymentPrefillAmount' => $supplierPaymentPrefillAmount,
            'defaultSupplierPaymentNumber' => $defaultSupplierPaymentNumber,
            'supplierPaymentNumberUniquenessUrl' => route('admin.accounting.supplier-payments.check-payment-number'),
            'fxCardEnabled' => true,
            'fxCardCurrencyField' => 'currency_code',
            'fxCardRateField' => 'fx_rate_at_payment',
            'fxCardBaseAmountField' => 'amount_base_at_payment',
            'fxCardTotalField' => 'amount',
            'fxCardBaseCurrency' => $baseCurrency,
            'fxCardCurrencyOptions' => $this->currencyOptionsForFxCard(),
            'fxCardCurrencyMeta' => $fxCardCurrencyMeta,
            'fxCardInitialCurrency' => $fxCardInitialCurrency,
            'fxCardInitialRate' => $fxCardInitialRate,
            'fxCardInitialBaseAmount' => $fxCardInitialBaseAmount,
        ];
    }

    private function supplierPaymentAmountPrefillFromPurchaseOrder(PurchaseOrder $purchaseOrder): ?string
    {
        $poTotal = (float) ($purchaseOrder->total_amount ?? 0);
        if ($poTotal <= 0) {
            $poTotal = (float) ($purchaseOrder->subtotal ?? 0) + (float) ($purchaseOrder->tax_amount ?? 0) - (float) ($purchaseOrder->discount_amount ?? 0);
        }
        if ($poTotal <= 0) {
            return null;
        }

        return rtrim(rtrim(number_format($poTotal, 4, '.', ''), '0'), '.');
    }

    /**
     * یکتایی شماره پرداخت (blur قبل از submit).
     */
    /**
     * برگشت/باطل‌سازی پرداخت تکمیل‌شده (سند برگشتی + بازگرداندن ماندهٔ فاکتور در صورت اتصال).
     */
    public function voidPayment(Request $request, SupplierPayment $supplierPayment): RedirectResponse
    {
        $validated = $request->validate([
            'void_reason' => ['required', 'string', 'max:5000'],
        ]);

        if ((string) ($supplierPayment->status ?? '') === SupplierPayment::STATUS_VOIDED) {
            return redirect()->back()->withErrors([
                'void_reason' => (string) trans('accounting::accounting.payment.void_already_voided'),
            ]);
        }

        if ((string) ($supplierPayment->status ?? '') !== SupplierPayment::STATUS_COMPLETED) {
            return redirect()->back()->withErrors([
                'void_reason' => (string) trans('accounting::accounting.payment.void_only_completed'),
            ]);
        }

        try {
            app(SupplierPaymentService::class)->voidPayment(
                $supplierPayment->fresh(),
                (string) $validated['void_reason']
            );
        } catch (Throwable $e) {
            report($e);

            return redirect()->back()->withErrors([
                'void_reason' => (string) trans('accounting::accounting.payment.void_failed').' — '.$e->getMessage(),
            ]);
        }

        return redirect()->back()->with('success', (string) trans('accounting::accounting.payment.void_success'));
    }

    public function checkPaymentNumber(Request $request): JsonResponse
    {
        $number = trim((string) $request->query('number', ''));
        if ($number === '') {
            return response()->json([
                'available' => false,
                'message' => (string) trans('accounting::accounting.payment.payment_number_required'),
            ]);
        }

        $excludeId = (int) $request->query('exclude_id', 0);
        $q = SupplierPayment::query()->where('payment_number', $number);
        if ($excludeId > 0) {
            $q->where('id', '!=', $excludeId);
        }

        $available = ! $q->exists();

        return response()->json([
            'available' => $available,
            'message' => $available ? '' : (string) trans('accounting::accounting.payment.payment_number_taken'),
        ]);
    }

    protected function suggestNextSupplierPaymentNumber(): string
    {
        // PAY-SUP = supplier outbound payment; date; daily sequence (editable by user).
        $prefix = 'PAY-SUP-'.now()->format('Ymd').'-';
        $last = SupplierPayment::query()
            ->where('payment_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('payment_number');

        $next = 1;
        if ($last && preg_match('/-(\d+)$/', (string) $last, $m)) {
            $next = (int) $m[1] + 1;
        }

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    protected function beforeAdd(Request &$request): void
    {
        $request->request->remove('_supplier_payment_po_from_query');
        $this->normalizeSupplierPaymentInvoiceId($request);
        $this->normalizeSupplierPaymentPurchaseOrderId($request);
        $this->mergeSupplierPaymentFinancialDefaults($request);
    }

    protected function beforeUpdate(Request &$request, int|string $id): void
    {
        $request->request->remove('_supplier_payment_po_from_query');
        $this->normalizeSupplierPaymentInvoiceId($request);
        $this->normalizeSupplierPaymentPurchaseOrderId($request);
        $this->mergeSupplierPaymentFinancialDefaults($request);
    }

    private function normalizeSupplierPaymentInvoiceId(Request $request): void
    {
        $raw = $request->input('supplier_invoice_id');
        if ($raw === null || $raw === '') {
            $request->merge(['supplier_invoice_id' => null]);
        }
    }

    private function normalizeSupplierPaymentPurchaseOrderId(Request $request): void
    {
        $raw = $request->input('purchase_order_id');
        if ($raw === null || $raw === '' || (string) $raw === '0') {
            $request->merge(['purchase_order_id' => null]);

            return;
        }
        if (is_numeric($raw)) {
            $request->merge(['purchase_order_id' => (int) $raw]);
        }
    }

    public function table(): string
    {
        return 'supplier_payments';
    }

    public function modelName(): string
    {
        return \RMS\Accounting\Models\SupplierPayment::class;
    }

    public function baseRoute(): string
    {
        return 'accounting.supplier-payments';
    }

    public function routeParameter(): string
    {
        return 'supplier_payment';
    }

    /**
     * اضافه کردن join به suppliers برای نمایش نام تامین‌کننده
     */
    public function query(Builder $sql): void
    {
        $sql->leftJoin('suppliers', 'suppliers.id', '=', 'a.supplier_id')
            ->addSelect(
                'a.*',
                'suppliers.name as supplier_name',
                'suppliers.phone as supplier_phone'
            );
    }

    public function getFieldsForm(): array
    {
        $hidden = $this->hiddenFormFields();
        $fields = [
            Field::string('payment_number')->withTitle(trans('accounting::accounting.payment.payment_number'))->required(),
            Field::date('payment_date')->withTitle(trans('accounting::accounting.payment.payment_date'))->required(),
            Field::number('supplier_id')->withTitle(trans('accounting::accounting.supplier.name'))
                ->required()
                ->withAttributes(['structured_widget' => 'ajax_supplier_select']),
            Field::number('supplier_invoice_id')->withTitle(trans('accounting::accounting.payment.supplier_invoice_id'))->optional(),
            Field::number('purchase_order_id')->withTitle(trans('accounting::accounting.payment.purchase_order_id'))->optional()
                ->withAttributes(['structured_widget' => 'supplier_payment_purchase_order']),
            Field::number('amount')->withTitle(trans('accounting::accounting.payment.amount'))->required(),
            Field::number('payment_method_id')->withTitle(trans('accounting::accounting.payment.payment_method'))
                ->required()
                ->withAttributes([
                    'structured_widget' => 'payment_destination_picker',
                    'payment_destination_context' => PaymentDestinationCatalog::CONTEXT_SUPPLIER_PAYMENT,
                ]),
            in_array('supplier_payment_status', $hidden, true)
                ? Field::hidden('status', 'pending')
                : Field::select('status')->withTitle(trans('accounting::accounting.common.status'))
                ->setOptions([
                    'pending' => trans('accounting::accounting.statuses.pending'),
                    'completed' => trans('accounting::accounting.statuses.completed'),
                    'failed' => trans('accounting::accounting.statuses.failed'),
                ])
                ->withDefaultValue('pending')
                ->required(),
            Field::textarea('notes')->withTitle(trans('accounting::accounting.payment.notes')),
        ];

        if (in_array('supplier_payment_notes', $hidden, true)) {
            $fields = array_values(array_filter($fields, static fn ($field) => (string) ($field->key ?? '') !== 'notes'));
        }

        return $fields;
    }

    public function getListFields(): array
    {
        return [
            Field::make('id', 'a.id')->withTitle(trans('accounting::accounting.common.id'))->sortable()->width('80px'),
            Field::make('payment_number')->withTitle(trans('accounting::accounting.payment.payment_number'))->searchable()->sortable()->width('150px'),
            Field::make('payment_date')->withTitle(trans('accounting::accounting.payment.payment_date'))->sortable()->width('120px'),
            
            // نمایش نام تامین‌کننده با customMethod
            Field::make('supplier_name', 'suppliers.name')
                ->withTitle(trans('accounting::accounting.supplier.name'))
                ->customMethod('renderSupplierName')
                ->searchable()
                ->width('200px'),
            
            // نمایش مبلغ با فرمت
            Field::make('amount')
                ->withTitle(trans('accounting::accounting.payment.amount'))
                ->customMethod('renderAmount')
                ->sortable()
                ->width('150px'),
            
            // نمایش وضعیت با badge
            Field::make('status')
                ->withTitle(trans('accounting::accounting.common.status'))
                ->customMethod('renderStatusBadge')
                ->sortable()
                ->width('120px'),
            
            Field::make('created_at')->withTitle(trans('accounting::accounting.common.created_at'))->sortable()->width('150px'),
        ];
    }

    public function filters(): array
    {
        return [
            Field::select('status', trans('accounting::accounting.common.status'))
                ->setOptions([
                    '' => trans('accounting::accounting.common.all'),
                    'pending' => trans('accounting::accounting.statuses.pending'),
                    'completed' => trans('accounting::accounting.statuses.completed'),
                    'failed' => trans('accounting::accounting.statuses.failed'),
                ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = request()->route($this->routeParameter());

        return [
            'payment_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('supplier_payments', 'payment_number')->ignore($id),
            ],
            'payment_date' => ['required'],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'supplier_invoice_id' => ['nullable', 'integer', 'exists:supplier_invoices,id'],
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'fx_rate_at_payment' => ['required', 'numeric', 'gt:0'],
            'amount_base_at_payment' => ['nullable', 'numeric', 'min:0'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'status' => ['required', 'in:pending,completed,failed'],
            'bank_id' => ['nullable', 'integer', 'exists:banks,id'],
            'cash_box_id' => ['nullable', 'integer', 'exists:cash_boxes,id'],
            'cheque_id' => ['nullable', 'integer', 'exists:cheques,id'],
            'wallet_id' => ['nullable', 'integer', 'exists:wallets,id'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * نمایش نام تامین‌کننده با اطلاعات تماس
     */
    public function renderSupplierName($row): string
    {
        if (!$row->supplier_id) {
            return '<span class="text-muted">-</span>';
        }
        
        $name = e($row->supplier_name ?: '#'.$row->supplier_id);
        $phone = $row->supplier_phone ? '<br><small class="text-muted">'.e($row->supplier_phone).'</small>' : '';
        
        return '<div>'.$name.$phone.'</div>';
    }

    /**
     * نمایش مبلغ با فرمت
     */
    public function renderAmount($row): string
    {
        $rawAmount = (float) ($row->amount ?? 0);
        $formattedAmount = rtrim(rtrim(number_format($rawAmount, 4, '.', ','), '0'), '.');
        if ($formattedAmount === '') {
            $formattedAmount = '0';
        }

        $currencyCode = strtoupper(trim((string) ($row->currency_code ?? '')));
        if ($currencyCode === '') {
            $currencyCode = $this->resolveBaseCurrencyCode();
        }

        return '<span class="fw-semibold">'.$formattedAmount
            .' <small class="text-muted">'.e($currencyCode).'</small></span>';
    }

    /**
     * نمایش وضعیت با badge رنگی
     */
    public function renderStatusBadge($row): string
    {
        $statusMap = [
            'pending' => [
                'label' => trans('accounting::accounting.statuses.pending'),
                'class' => 'bg-warning'
            ],
            'completed' => [
                'label' => trans('accounting::accounting.statuses.completed'),
                'class' => 'bg-success'
            ],
            'failed' => [
                'label' => trans('accounting::accounting.statuses.failed'),
                'class' => 'bg-danger'
            ],
            'reversed' => [
                'label' => trans('accounting::accounting.statuses.reversed'),
                'class' => 'bg-secondary'
            ],
            'cancelled' => [
                'label' => trans('accounting::accounting.statuses.cancelled'),
                'class' => 'bg-secondary'
            ],
            'voided' => [
                'label' => trans('accounting::accounting.statuses.voided'),
                'class' => 'bg-dark'
            ],
        ];

        $status = (string)($row->status ?? 'pending');
        $info = $statusMap[$status] ?? ['label' => $status, 'class' => 'bg-secondary'];
        $label = e($info['label']);
        $class = $info['class'];

        return '<span class="badge '.$class.'">'.$label.'</span>';
    }

    /**
     * گرفتن لیست روش‌های پرداخت
     */
    protected function getPaymentMethodOptions(): array
    {
        return PaymentMethod::query()->where('active', true)->orderBy('sort_order')->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected function afterConfigureStructuredAccountingFormView(Request $request, bool $isEdit, ?Model $model): void
    {
        if ($this->structuredAccountingFormSlug() === 'supplier_payments') {
            $this->view->withJs('vendor/accounting/admin/js/payment-destination-picker.js', true);
            $this->view->withJs('vendor/accounting/admin/js/accounting-ajax-supplier-widgets.js', true);
            $this->view->withJs('vendor/accounting/admin/js/supplier-payment-structured-form.js', true);
        }
    }

    protected function afterStructuredAccountingFormPrepareForValidation(Request $request): void
    {
        if ($this->structuredAccountingFormSlug() === 'supplier_payments') {
            $this->normalizeDecimalRequestValues($request, [
                'amount',
                'fx_rate_at_payment',
                'amount_base_at_payment',
            ]);
            $this->applyPaymentDestinationValidation($request, PaymentDestinationCatalog::CONTEXT_SUPPLIER_PAYMENT);
        }
    }

    private function applyPaymentDestinationValidation(Request $request, string $context): void
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
                'context' => 'supplier_payment',
                'source_short' => 'SP',
                'payment_method_id' => $pm,
                'cheque_type' => \RMS\Accounting\Models\Cheque::TYPE_ISSUED,
                'party_id' => (int) ($supplier?->party_id ?? 0),
                'amount' => (float) ($request->input('amount') ?? 0),
                'currency_code' => (string) ($request->input('currency_code') ?: 'IRR'),
                'issue_date' => (string) ($request->input('payment_date') ?: now()->toDateString()),
                'due_date' => (string) ($request->input('payment_date') ?: now()->toDateString()),
                'notes' => (string) ($request->input('notes') ?: ''),
            ]);
            if ($autoCheque) {
                $request->merge(['cheque_id' => (int) $autoCheque->id]);
            }
        }
        $status = (string) $request->input('status', '');
        $hasAnyDestination = $request->filled('bank_id')
            || $request->filled('cash_box_id')
            || $request->filled('cheque_id')
            || $request->filled('pos_terminal_id')
            || $request->filled('wallet_id');

        if ($pm <= 0) {
            if ($hasAnyDestination || $status === SupplierPayment::STATUS_COMPLETED) {
                throw ValidationException::withMessages([
                    'payment_method_id' => (string) trans('accounting::accounting.payment_destination.method_required'),
                ]);
            }

            return;
        }

        $res = $catalog->validateSelection(
            $context,
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

    /**
     * @return array<int, string>
     */
    protected function hiddenFormFields(): array
    {
        $raw = Setting::get('accounting.package_forms.purchase.hidden_fields', '[]');
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, static fn ($value) => is_string($value) && $value !== ''));
    }

    private function mergeSupplierPaymentFinancialDefaults(Request $request): void
    {
        $baseCurrency = $this->resolveBaseCurrencyCode();
        $currencyCode = strtoupper(trim((string) $request->input('currency_code', $baseCurrency)));
        if ($currencyCode === '') {
            $currencyCode = $baseCurrency;
        }

        $invoice = null;
        $invoiceIdRaw = (string) $request->input('supplier_invoice_id', '');
        if ($invoiceIdRaw !== '' && ctype_digit($invoiceIdRaw)) {
            $invoice = SupplierInvoice::query()->find((int) $invoiceIdRaw);
        }

        if ($currencyCode === $baseCurrency && $invoice instanceof SupplierInvoice && ! empty($invoice->currency_code)) {
            $currencyCode = strtoupper((string) $invoice->currency_code);
        }

        $fxRate = $this->parseDecimalInput($request->input('fx_rate_at_payment'));
        if (($fxRate === null || $fxRate <= 0.0) && $invoice instanceof SupplierInvoice) {
            $fxRate = $this->parseDecimalInput($invoice->fx_rate_at_invoice);
        }
        if (($fxRate === null || $fxRate <= 0.0) && $currencyCode === $baseCurrency) {
            $fxRate = 1.0;
        }
        if ($fxRate === null || $fxRate <= 0.0) {
            $fxRate = 1.0;
        }

        $amountFloat = (float) ($this->parseDecimalInput($request->input('amount')) ?? 0.0);
        $baseDecimals = max(0, min(6, (int) (($this->currencyMetaForFxCard()[$baseCurrency]['decimals'] ?? 4))));
        $amountBase = round($amountFloat * (float) $fxRate, $baseDecimals);

        $request->merge([
            'currency_code' => $currencyCode,
            'fx_rate_at_payment' => (float) $fxRate,
            'amount_base_at_payment' => $amountBase,
        ]);
    }

    protected function resolveBaseCurrencyCode(): string
    {
        return Currency::resolveBaseCurrencyCode('IRR');
    }

    /**
     * @return array<string, string>
     */
    protected function currencyOptionsForFxCard(): array
    {
        $rows = Currency::query()
            ->active()
            ->orderByDesc('is_base')
            ->orderBy('code')
            ->get(['code', 'name']);

        if ($rows->isEmpty()) {
            $base = $this->resolveBaseCurrencyCode();
            return [$base => $base];
        }

        return $rows->mapWithKeys(static function (Currency $currency): array {
            $code = strtoupper((string) $currency->code);
            $name = trim((string) $currency->name);
            return [$code => ($name !== '' ? ($code . ' - ' . $name) : $code)];
        })->all();
    }

    /**
     * @return array<string, array{decimals:int}>
     */
    protected function currencyMetaForFxCard(): array
    {
        $rows = Currency::query()
            ->active()
            ->get(['code', 'decimals']);

        if ($rows->isEmpty()) {
            $base = $this->resolveBaseCurrencyCode();
            return [$base => ['decimals' => 0]];
        }

        return $rows->mapWithKeys(static function (Currency $currency): array {
            $code = strtoupper((string) $currency->code);
            return [$code => ['decimals' => max(0, min(6, (int) ($currency->decimals ?? 0)))]];
        })->all();
    }

    protected function parseDecimalInput(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        if (function_exists('\RMS\Helper\changeNumberToEn')) {
            $raw = (string) \RMS\Helper\changeNumberToEn($raw);
        }
        $raw = str_replace([',', ' '], '', $raw);
        if (! is_numeric($raw)) {
            return null;
        }

        return (float) $raw;
    }

    protected function normalizeDecimalRequestValues(Request $request, array $fields): void
    {
        $merged = [];
        foreach ($fields as $field) {
            if (! is_string($field) || $field === '' || ! $request->exists($field)) {
                continue;
            }
            $raw = $request->input($field);
            if ($raw === null || (is_string($raw) && trim($raw) === '')) {
                continue;
            }
            $parsed = $this->parseDecimalInput($raw);
            if ($parsed !== null) {
                $merged[$field] = $parsed;
            }
        }
        if ($merged !== []) {
            $request->merge($merged);
        }
    }

    /**
     * پس از ثبت پرداخت از فرم ادمین، اگر به فاکتور وصل و تکمیل‌شده باشد ماندهٔ فاکتور را به‌روز کن (recordPayment در API سند می‌زند؛ اینجا فقط مانده).
     */
    protected function afterAdd(Request $request, int|string $id, Model $model): void
    {
        if (! $model instanceof SupplierPayment) {
            return;
        }
        if ((int) ($model->cheque_id ?? 0) > 0) {
            $cheque = \RMS\Accounting\Models\Cheque::query()->find((int) $model->cheque_id);
            if ($cheque) {
                app(ChequeAutoCreationService::class)->attachSource($cheque, SupplierPayment::class, (int) $model->id);
            }
        }
        app(SupplierPaymentService::class)->processCompletedPayment($model->fresh());
    }
}
