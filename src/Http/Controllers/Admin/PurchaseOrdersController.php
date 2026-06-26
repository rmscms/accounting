<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use RMS\Accounting\Services\PurchaseOrderService;
use RMS\Accounting\Http\Controllers\Admin\Concerns\RendersAccountingStructuredResourceForm;
use RMS\Accounting\Models\Currency;
use RMS\Accounting\Models\PurchaseOrder;
use RMS\Accounting\Models\Supplier;
use RMS\Accounting\Models\SupplierInvoice;
use RMS\Accounting\Models\SupplierPayment;
use RMS\Core\Data\Field;
use RMS\Core\Models\Setting;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;

class PurchaseOrdersController extends AccountingAdminController implements HasList, HasForm, ShouldFilter
{
    use RendersAccountingStructuredResourceForm;

    /**
     * HTML fragment: جدول اقلام سفارش خرید.
     */
    public function itemsFragment(Request $request, int|string $purchase_order): View
    {
        $order = PurchaseOrder::query()
            ->with(['items' => static fn ($q) => $q->orderBy('id')])
            ->findOrFail($purchase_order);

        $warehouseReceiptPdfUrl = $order->items->isNotEmpty()
            ? route('admin.accounting.purchase-orders.warehouse-receipt-pdf', ['purchase_order' => $order->getKey()])
            : null;

        return view('accounting::admin.purchase_orders._items_table', [
            'order' => $order,
            'warehouseReceiptPdfUrl' => $warehouseReceiptPdfUrl,
        ]);
    }

    /**
     * یکتایی شمارهٔ سفارش خرید (blur / قبل از submit).
     */
    public function checkPoNumber(Request $request): JsonResponse
    {
        $number = trim((string) $request->query('number', ''));
        if ($number === '') {
            return response()->json([
                'available' => false,
                'message' => (string) trans('accounting::accounting.purchase_order.po_number_required'),
            ]);
        }

        $excludeId = (int) $request->query('exclude_id', 0);
        $q = PurchaseOrder::query()->where('po_number', $number);
        if ($excludeId > 0) {
            $q->where('id', '!=', $excludeId);
        }

        $available = ! $q->exists();

        return response()->json([
            'available' => $available,
            'message' => $available ? '' : (string) trans('accounting::accounting.purchase_order.po_number_taken'),
        ]);
    }

    /**
     * PDF رسید انبار برای سفارش خرید (اقلام باید ثبت شده باشند).
     */
    public function warehouseReceiptPdf(Request $request, int|string $purchase_order): Response|View
    {
        $order = PurchaseOrder::query()
            ->with([
                'supplier.party',
                'items' => static fn ($q) => $q->orderBy('id'),
            ])
            ->findOrFail($purchase_order);

        if ($order->items->isEmpty()) {
            abort(404, (string) trans('accounting::accounting.purchase_order.warehouse_receipt_no_items'));
        }

        $html = view('accounting::admin.purchase_orders.pdf.warehouse_receipt', [
            'order' => $order,
        ])->render();

        $safePo = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $order->po_number) ?: 'po';
        $filename = 'warehouse-receipt-'.$safePo.'-'.now()->format('Ymd-His').'.pdf';

        if (class_exists(\App\Services\Pdf\SitePdfService::class)) {
            return app(\App\Services\Pdf\SitePdfService::class)->downloadHtml($html, $filename, ['rtl' => true]);
        }

        return response()->view('accounting::admin.purchase_orders.pdf.warehouse_receipt_html_fallback', [
            'order' => $order,
            'title' => $filename,
        ]);
    }

    protected function afterConfigureStructuredAccountingFormView(Request $request, bool $isEdit, ?Model $model): void
    {
        if ($this->structuredAccountingFormSlug() !== 'purchase_orders') {
            return;
        }

        $this->view->withCss('vendor/accounting/admin/css/accounting-purchase-summary-cards.css', true);
        $this->view->withJs('vendor/accounting/admin/js/accounting-line-items-editor.js', true);
        $this->view->withJs('vendor/accounting/admin/js/accounting-ajax-supplier-widgets.js', true);
        $this->view->withJs('vendor/accounting/admin/js/purchase-order-structured-form.js', true);
    }

    /**
     * @return array<string, mixed>
     */
    protected function structuredAccountingFormExtraViewVariables(Request $request, bool $isEdit, ?Model $model): array
    {
        $purchaseOrderItemsFragmentUrl = null;
        $purchaseOrderItemsStoreUrl = null;
        $supplierSelectInitialText = null;
        $defaultPurchaseOrderNumber = null;
        $poNumberUniquenessUrl = route('admin.accounting.purchase-orders.check-po-number');
        $purchaseOrderWarehouseReceiptPdfUrl = null;
        $baseCurrency = $this->resolveBaseCurrencyCode();
        $fxCardCurrencyMeta = $this->currencyMetaForFxCard();
        $fxCardInitialCurrency = $baseCurrency;
        $fxCardInitialRate = '1';
        $fxCardInitialBaseAmount = '';

        if ($isEdit && $model instanceof PurchaseOrder) {
            $purchaseOrderItemsFragmentUrl = route('admin.accounting.purchase-orders.items-fragment', [
                'purchase_order' => $model->getKey(),
            ]);
            $purchaseOrderItemsStoreUrl = route('admin.accounting.purchase-orders.items.store', [
                'purchase_order' => $model->getKey(),
            ]);
            $purchaseOrderWarehouseReceiptPdfUrl = route('admin.accounting.purchase-orders.warehouse-receipt-pdf', [
                'purchase_order' => $model->getKey(),
            ]);

            if ($model->supplier_id) {
                $supplier = Supplier::query()->with('party')->find((int) $model->supplier_id);
                if ($supplier) {
                    $supplierSelectInitialText = (string) ($supplier->party?->name ?: $supplier->name);
                }
            }
            $fxCardInitialCurrency = strtoupper((string) ($model->currency_code ?: $baseCurrency));
            $fxCardInitialRate = (string) ($model->fx_rate_at_order ?: '1');
            $fxCardInitialBaseAmount = (string) ($model->amount_base_at_order ?? '');
        } else {
            $defaultPurchaseOrderNumber = app(PurchaseOrderService::class)->suggestNextPoNumber();
        }

        $fxCardInitialCurrency = strtoupper((string) old('currency_code', $fxCardInitialCurrency));
        if ($fxCardInitialCurrency === '') {
            $fxCardInitialCurrency = $baseCurrency;
        }
        $fxCardInitialRate = (string) old('fx_rate_at_order', $fxCardInitialRate);
        $fxCardInitialBaseAmount = (string) old('amount_base_at_order', $fxCardInitialBaseAmount);

        $purchaseOrderFromPoInvoiceGate = ['can' => false, 'reason' => null, 'existing_invoice_id' => null];
        if ($isEdit && $model instanceof PurchaseOrder) {
            $purchaseOrderFromPoInvoiceGate = app(PurchaseOrderService::class)->gateCreateSupplierInvoiceFromPurchaseOrder($model);
        }

        return [
            'purchaseOrderItemsFragmentUrl' => $purchaseOrderItemsFragmentUrl,
            'purchaseOrderItemsStoreUrl' => $purchaseOrderItemsStoreUrl,
            'supplierSelectInitialText' => $supplierSelectInitialText,
            'defaultPurchaseOrderNumber' => $defaultPurchaseOrderNumber,
            'poNumberUniquenessUrl' => $poNumberUniquenessUrl,
            'purchaseOrderWarehouseReceiptPdfUrl' => $purchaseOrderWarehouseReceiptPdfUrl,
            'purchaseOrderFromPoInvoiceGate' => $purchaseOrderFromPoInvoiceGate,
            'fxCardEnabled' => true,
            'fxCardCurrencyField' => 'currency_code',
            'fxCardRateField' => 'fx_rate_at_order',
            'fxCardBaseAmountField' => 'amount_base_at_order',
            'fxCardBaseCurrency' => $baseCurrency,
            'fxCardCurrencyOptions' => $this->currencyOptionsForFxCard(),
            'fxCardCurrencyMeta' => $fxCardCurrencyMeta,
            'fxCardInitialCurrency' => $fxCardInitialCurrency,
            'fxCardInitialRate' => $fxCardInitialRate,
            'fxCardInitialBaseAmount' => $fxCardInitialBaseAmount,
        ];
    }

    public function table(): string
    {
        return 'purchase_orders';
    }

    public function modelName(): string
    {
        return PurchaseOrder::class;
    }

    public function baseRoute(): string
    {
        return 'accounting.purchase-orders';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = request()->route($this->routeParameter());

        return [
            'po_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('purchase_orders', 'po_number')->ignore($id),
            ],
            'order_date' => ['required'],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'fx_rate_at_order' => ['required', 'numeric', 'gt:0'],
            'amount_base_at_order' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'string', 'in:draft,sent,confirmed,partially_received,received,invoiced,cancelled'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'po_number.unique' => (string) trans('accounting::accounting.purchase_order.po_number_taken'),
        ];
    }

    public function routeParameter(): string
    {
        return 'purchase_order';
    }

    /**
     * پس از اولین ذخیره (create) یا به‌روزرسانی، به ویرایش همان سفارش برگردد تا اقلام را بدون رفتن به لیست اضافه کنید.
     */
    protected function getRedirectResponse(Request $request, int|string $id): RedirectResponse
    {
        return redirect()->route(
            $this->accountingNamedRoute('edit'),
            [$this->routeParameter() => $id]
        )->with('success', trans('admin.success_action'));
    }

    public function getFieldsForm(): array
    {
        $hidden = $this->hiddenFormFields();
        $fields = [
            Field::string('po_number', trans('accounting::accounting.purchase_order.po_number'))->required(),
            Field::date('order_date', trans('accounting::accounting.purchase_order.order_date'))->required(),
            Field::number('supplier_id', trans('accounting::accounting.purchase_order.supplier_id'))
                ->required()
                ->withAttributes(['structured_widget' => 'ajax_supplier_select']),
            Field::number('total_amount', trans('accounting::accounting.purchase_order.total_amount'))->required(),
            Field::hidden('subtotal', 0),
            Field::hidden('tax_amount', 0),
            Field::hidden('discount_amount', 0),
            in_array('purchase_order_status', $hidden, true)
                ? Field::hidden('status', 'draft')
                : Field::select('status', trans('accounting::accounting.purchase_order.status'))
                ->setOptions([
                    'draft' => trans('accounting::accounting.statuses.draft'),
                    'confirmed' => trans('accounting::accounting.statuses.confirmed'),
                    'partially_received' => trans('accounting::accounting.purchase_order.status_partially_received'),
                    'received' => trans('accounting::accounting.statuses.received'),
                    'invoiced' => trans('accounting::accounting.purchase_order.status_invoiced'),
                    'cancelled' => trans('accounting::accounting.statuses.cancelled'),
                ])
                ->withDefaultValue('draft')
                ->required(),
            Field::textarea('notes', trans('accounting::accounting.purchase_order.notes'))->optional(),
        ];

        if (in_array('purchase_order_notes', $hidden, true)) {
            $fields = array_values(array_filter($fields, static fn ($field) => (string) ($field->key ?? '') !== 'notes'));
        }

        return $fields;
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle(trans('accounting::accounting.common.id'))->sortable()->width('80px'),
            Field::make('po_number')->withTitle(trans('accounting::accounting.purchase_order.po_number'))->searchable()->sortable()->width('150px'),
            Field::make('order_date')->withTitle(trans('accounting::accounting.purchase_order.order_date'))->sortable()->width('120px'),
            Field::make('supplier_id')->withTitle(trans('accounting::accounting.purchase_order.supplier_id'))->sortable()->width('100px'),
            Field::price('total_amount', trans('accounting::accounting.purchase_order.total_amount'))->sortable()->width('140px'),
            Field::make('status')
                ->withTitle(trans('accounting::accounting.purchase_order.status'))
                ->customMethod('renderPurchaseOrderStatusBadge')
                ->sortable()
                ->width('130px'),
            Field::make(
                'po_payable_invoice_id',
                self::purchaseOrderListPayableInvoiceIdSql(),
                true
            )
                ->withTitle(trans('accounting::accounting.purchase_order.list_payment_column'))
                ->customMethod('renderPurchaseOrderPaymentLink')
                ->width('140px'),
        ];
    }

    /**
     * ستون‌های تشخیصی برای نمایش شمارهٔ فاکتور / آخرین پرداخت لینک‌شده به سفارش.
     */
    public function query(Builder $sql): void
    {
        $siTable = (new SupplierInvoice())->getTable();
        $extras = [
            DB::raw("(SELECT si.invoice_number FROM {$siTable} AS si WHERE si.purchase_order_id = a.id AND si.deleted_at IS NULL ORDER BY si.id DESC LIMIT 1) as po_latest_invoice_number"),
        ];
        if (Schema::hasColumn('supplier_payments', 'purchase_order_id')) {
            $spTable = (new SupplierPayment())->getTable();
            $extras[] = DB::raw("(SELECT sp.payment_number FROM {$spTable} AS sp WHERE sp.purchase_order_id = a.id AND sp.deleted_at IS NULL ORDER BY sp.id DESC LIMIT 1) as po_latest_payment_number");
        } else {
            $extras[] = DB::raw('NULL as po_latest_payment_number');
        }
        $sql->addSelect($extras);
    }

    /**
     * آخرین فاکتور خرید وصل به سفارش که هنوز تسویه کامل نشده (برای ستون لینک پرداخت در لیست).
     */
    private static function purchaseOrderListPayableInvoiceIdSql(): string
    {
        $t = (new SupplierInvoice())->getTable();
        $u = SupplierInvoice::STATUS_UNPAID;
        $p = SupplierInvoice::STATUS_PARTIALLY_PAID;

        return '(SELECT MAX(si.id) FROM '.$t.' AS si WHERE si.purchase_order_id = a.id AND si.deleted_at IS NULL'
            ." AND si.payment_status IN ('{$u}','{$p}'))";
    }

    /**
     * لینک به فرم «پرداخت به تأمین‌کننده» با پیش‌پر کردن فاکتور (و در صورت وجود، تأمین‌کنندهٔ سفارش).
     */
    public function renderPurchaseOrderPaymentLink($row): string
    {
        $supplierId = (int) ($row->supplier_id ?? 0);
        $purchaseOrderId = (int) ($row->id ?? 0);
        $raw = $row->po_payable_invoice_id ?? null;
        $invoiceId = is_numeric($raw) ? (int) $raw : 0;

        $invNo = trim((string) ($row->po_latest_invoice_number ?? ''));
        $payNo = trim((string) ($row->po_latest_payment_number ?? ''));
        $summary = '';
        if ($invNo !== '') {
            $summary .= '<div class="small text-muted mb-1">'.e(trans('accounting::accounting.purchase_order.list_invoice_line'))
                .': <span class="text-body fw-semibold">'.e($invNo).'</span></div>';
        }
        if ($payNo !== '') {
            $summary .= '<div class="small text-muted mb-1">'.e(trans('accounting::accounting.purchase_order.list_payment_ref_line'))
                .': <span class="text-body fw-semibold">'.e($payNo).'</span></div>';
        }

        if ($invoiceId > 0) {
            $query = ['supplier_invoice_id' => (string) $invoiceId];
            if ($supplierId > 0) {
                $query['supplier_id'] = (string) $supplierId;
            }
            if ($purchaseOrderId > 0) {
                $query['purchase_order_id'] = (string) $purchaseOrderId;
            }
            $inv = SupplierInvoice::query()->find($invoiceId);
            if ($inv) {
                $due = (float) ($inv->balance_due ?? 0);
                if ($due > 0) {
                    $query['amount'] = rtrim(rtrim(number_format($due, 4, '.', ''), '0'), '.');
                }
            }
            $url = route('admin.accounting.supplier-payments.create', $query);
            $label = e(trans('accounting::accounting.purchase_order.list_pay_cta'));

            return '<div class="d-flex flex-column gap-1">'.$summary
                .'<a href="'.e($url).'" class="btn btn-sm btn-outline-success align-self-start">'
                .'<i class="ph-currency-circle-dollar me-1"></i>'.$label.'</a></div>';
        }

        if ($supplierId > 0 && $purchaseOrderId > 0) {
            $query = [
                'supplier_id' => (string) $supplierId,
                'purchase_order_id' => (string) $purchaseOrderId,
            ];
            $po = PurchaseOrder::query()->find($purchaseOrderId);
            if ($po) {
                $t = (float) ($po->total_amount ?? 0);
                if ($t > 0) {
                    $query['amount'] = rtrim(rtrim(number_format($t, 4, '.', ''), '0'), '.');
                }
            }
            $url = route('admin.accounting.supplier-payments.create', $query);
            $label = e(trans('accounting::accounting.purchase_order.list_pay_cta'));

            return '<div class="d-flex flex-column gap-1">'.$summary
                .'<a href="'.e($url).'" class="btn btn-sm btn-outline-success align-self-start" title="'.e(trans('accounting::accounting.purchase_order.list_no_payable_invoice_title')).'">'
                .'<i class="ph-currency-circle-dollar me-1"></i>'.$label.'</a></div>';
        }

        return '<div class="d-flex flex-column gap-1">'.$summary
            .'<span class="text-muted small">'.e(trans('accounting::accounting.purchase_order.list_no_payable_invoice')).'</span></div>';
    }

    /**
     * نمایش وضعیت سفارش خرید با بج رنگی و برچسب ترجمه‌شده.
     */
    public function renderPurchaseOrderStatusBadge($row): string
    {
        $statusMap = [
            PurchaseOrder::STATUS_DRAFT => [
                'label' => trans('accounting::accounting.statuses.draft'),
                'class' => 'bg-secondary',
            ],
            PurchaseOrder::STATUS_SENT => [
                'label' => trans('accounting::accounting.statuses.sent'),
                'class' => 'bg-info text-dark',
            ],
            PurchaseOrder::STATUS_CONFIRMED => [
                'label' => trans('accounting::accounting.statuses.confirmed'),
                'class' => 'bg-primary',
            ],
            PurchaseOrder::STATUS_PARTIALLY_RECEIVED => [
                'label' => trans('accounting::accounting.purchase_order.status_partially_received'),
                'class' => 'bg-warning text-dark',
            ],
            PurchaseOrder::STATUS_RECEIVED => [
                'label' => trans('accounting::accounting.statuses.received'),
                'class' => 'bg-success',
            ],
            PurchaseOrder::STATUS_INVOICED => [
                'label' => trans('accounting::accounting.statuses.invoiced'),
                'class' => 'bg-dark',
            ],
            PurchaseOrder::STATUS_CANCELLED => [
                'label' => trans('accounting::accounting.statuses.cancelled'),
                'class' => 'bg-danger',
            ],
        ];

        $status = (string) ($row->status ?? PurchaseOrder::STATUS_DRAFT);
        $fallbackKey = 'accounting::accounting.statuses.'.$status;
        $fallbackLabel = trans($fallbackKey);
        if ($fallbackLabel === $fallbackKey) {
            $fallbackLabel = $status;
        }
        $info = $statusMap[$status] ?? [
            'label' => $fallbackLabel,
            'class' => 'bg-secondary',
        ];
        $label = e((string) $info['label']);
        $class = e((string) $info['class']);

        return '<span class="badge '.$class.'">'.$label.'</span>';
    }

    public function filters(): array
    {
        return [
            Field::select('status', trans('accounting::accounting.purchase_order.status'))
                ->setOptions([
                    '' => trans('accounting::accounting.common.all'),
                    'draft' => trans('accounting::accounting.statuses.draft'),
                    'sent' => trans('accounting::accounting.statuses.sent'),
                    'confirmed' => trans('accounting::accounting.statuses.confirmed'),
                    'received' => trans('accounting::accounting.statuses.received'),
                    'invoiced' => trans('accounting::accounting.statuses.invoiced'),
                    'cancelled' => trans('accounting::accounting.statuses.cancelled'),
                ]),
        ];
    }

    /**
     * تایید سفارش خرید
     */
    protected function beforeAdd(Request &$request): void
    {
        $po = trim((string) $request->input('po_number', ''));
        $request->merge(['po_number' => $po]);
        if ($po === '') {
            $request->merge(['po_number' => app(PurchaseOrderService::class)->suggestNextPoNumber()]);
        }
        $this->mergePurchaseOrderFinancialDefaults($request);
    }

    protected function beforeUpdate(Request &$request, int|string $id): void
    {
        $request->merge(['po_number' => trim((string) $request->input('po_number', ''))]);
        $this->mergePurchaseOrderFinancialDefaults($request);
        $this->syncPurchaseOrderApprovalWhenConfirmedFromDraft($request, $id);
    }

    /**
     * اگر وضعیت از پیش‌نویس به «تأیید شده» از طریق همان فرم تغییر کند، همان داده‌های مسیر دکمهٔ تأیید پر شود.
     */
    private function syncPurchaseOrderApprovalWhenConfirmedFromDraft(Request $request, int|string $id): void
    {
        $newStatus = (string) $request->input('status', '');
        if ($newStatus !== PurchaseOrder::STATUS_CONFIRMED) {
            return;
        }
        $previous = PurchaseOrder::query()->whereKey($id)->value('status');
        if ((string) $previous !== PurchaseOrder::STATUS_DRAFT) {
            return;
        }
        $request->merge([
            'approved_by_user_id' => \RMS\Accounting\Support\AuditActor::userId(),
            'approved_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * ستون‌های NOT NULL جدول purchase_orders که در فرم ساختاریافته نیستند را از total_amount و تنظیمات پر می‌کند.
     */
    private function mergePurchaseOrderFinancialDefaults(Request $request): void
    {
        $totalFloat = is_numeric($request->input('total_amount')) ? (float) $request->input('total_amount') : 0.0;

        if (! $request->filled('subtotal')) {
            $request->merge(['subtotal' => $totalFloat]);
        }
        if (! $request->filled('tax_amount')) {
            $request->merge(['tax_amount' => 0]);
        }
        if (! $request->filled('discount_amount')) {
            $request->merge(['discount_amount' => 0]);
        }
        $baseCurrency = $this->resolveBaseCurrencyCode();
        $currencyCode = strtoupper(trim((string) $request->input('currency_code', $baseCurrency)));
        if ($currencyCode === '') {
            $currencyCode = $baseCurrency;
        }
        $fx = $request->filled('fx_rate_at_order')
            ? $this->parseDecimalInput($request->input('fx_rate_at_order'))
            : 1.0;
        if ($currencyCode === $baseCurrency && (!$request->filled('fx_rate_at_order') || (float) $fx <= 0.0)) {
            $fx = 1.0;
        }
        $fxValue = (float) ($fx ?? 0.0);
        $currencyMeta = $this->currencyMetaForFxCard();
        $baseDecimals = max(0, min(6, (int) ($currencyMeta[$baseCurrency]['decimals'] ?? 4)));
        $amountBase = round($totalFloat * $fxValue, $baseDecimals);
        $request->merge([
            'currency_code' => $currencyCode,
            'fx_rate_at_order' => $fxValue,
            'amount_base_at_order' => $amountBase,
        ]);
    }

    /**
     * پس از ذخیرهٔ هدر، اگر اقلام وجود داشته باشد جمع‌ها را با سطرها هم‌تراز می‌کند.
     */
    protected function afterAdd(Request $request, int|string $id, Model $model): void
    {
        $this->recalculatePurchaseOrderTotalsIfHasItems($model);
    }

    protected function afterUpdate(Request $request, int|string $id, Model $model): void
    {
        $this->recalculatePurchaseOrderTotalsIfHasItems($model);
    }

    private function recalculatePurchaseOrderTotalsIfHasItems(Model $model): void
    {
        if (! $model instanceof PurchaseOrder) {
            return;
        }
        if (! $model->items()->exists()) {
            return;
        }
        app(PurchaseOrderService::class)->calculateTotals($model->fresh(['items']));
    }

    /**
     * ایجاد فاکتور خرید از سفارش (کپی اقلام) — بدون ثبت خودکار در دفتر کل.
     */
    public function createSupplierInvoiceFromPurchaseOrder(Request $request, int|string $purchase_order): RedirectResponse
    {
        $po = PurchaseOrder::query()
            ->with(['items' => static fn ($q) => $q->orderBy('id')])
            ->findOrFail($purchase_order);

        try {
            $invoice = app(PurchaseOrderService::class)->convertToSupplierInvoice($po);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->with('error', (string) trans('accounting::accounting.purchase_order.invoice_from_po_failed'));
        }

        return redirect()
            ->route('admin.accounting.supplier-invoices.edit', ['supplier_invoice' => $invoice->getKey()])
            ->with('success', (string) trans('accounting::accounting.purchase_order.invoice_from_po_success'));
    }

    public function confirm(Request $request, int $id)
    {
        $purchaseOrder = PurchaseOrder::findOrFail($id);
        
        if ($purchaseOrder->status !== 'draft') {
            return redirect()->back()->with('error', trans('accounting::accounting.errors.po_not_draft'));
        }

        $purchaseOrder->status = 'confirmed';
        $purchaseOrder->approved_by_user_id = \RMS\Accounting\Support\AuditActor::userId();
        $purchaseOrder->approved_at = now();
        $purchaseOrder->save();

        return redirect()->back()->with('success', trans('accounting::accounting.messages.po_confirmed'));
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
            return [$code => ['decimals' => max(0, min(6, (int) ($currency->decimals ?? 0)))]]; // decimal precision per currency
        })->all();
    }

    protected function resolveBaseCurrencyCode(): string
    {
        return Currency::resolveBaseCurrencyCode('IRR');
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
}
