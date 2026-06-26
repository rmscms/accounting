<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RMS\Accounting\Http\Controllers\Admin\Concerns\RendersAccountingStructuredResourceForm;
use RMS\Accounting\Models\Currency;
use RMS\Accounting\Models\PaymentMethod;
use RMS\Accounting\Models\PurchaseOrder;
use RMS\Accounting\Models\Supplier;
use RMS\Accounting\Models\SupplierInvoice;
use RMS\Accounting\Models\Wallet;
use RMS\Accounting\Services\ChequeAutoCreationService;
use RMS\Accounting\Services\PaymentDestinationCatalog;
use RMS\Accounting\Services\SupplierInvoiceCorrectionService;
use RMS\Accounting\Services\SupplierInvoiceService;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Data\Field;
use RMS\Core\Models\Setting;

class SupplierInvoicesController extends AccountingAdminController implements HasList, HasForm, ShouldFilter
{
    use RendersAccountingStructuredResourceForm;

    public function table(): string
    {
        return 'supplier_invoices';
    }

    public function modelName(): string
    {
        return SupplierInvoice::class;
    }

    public function baseRoute(): string
    {
        return 'accounting.supplier-invoices';
    }

    public function routeParameter(): string
    {
        return 'supplier_invoice';
    }

    /**
     * پس از ایجاد یا به‌روزرسانی، به ویرایش همان فاکتور برگردد تا اقلام و ادامهٔ گردش بدون رفتن به لیست انجام شود.
     */
    protected function getRedirectResponse(Request $request, int|string $id): RedirectResponse
    {
        return redirect()->route(
            $this->accountingNamedRoute('edit'),
            [$this->routeParameter() => $id]
        )->with('success', trans('admin.success_action'));
    }

    /**
     * یکتایی شماره فاکتور (برای blur / پیش‌نمایش قبل از submit).
     */
    public function checkInvoiceNumber(Request $request): JsonResponse
    {
        $number = trim((string) $request->query('number', ''));
        if ($number === '') {
            return response()->json([
                'available' => false,
                'message' => (string) trans('accounting::accounting.supplier_invoice.invoice_number_required'),
            ]);
        }

        $excludeId = (int) $request->query('exclude_id', 0);
        $q = SupplierInvoice::query()->where('invoice_number', $number);
        if ($excludeId > 0) {
            $q->where('id', '!=', $excludeId);
        }

        $available = ! $q->exists();

        return response()->json([
            'available' => $available,
            'message' => $available ? '' : (string) trans('accounting::accounting.supplier_invoice.invoice_number_taken'),
        ]);
    }

    /**
     * جستجوی تأمین‌کننده برای Select2 (مقدار = suppliers.id).
     */
    public function searchSuppliers(Request $request): JsonResponse
    {
        $term = trim((string) $request->get('q', ''));
        $limit = max(5, min((int) $request->get('limit', 20), 50));

        if (mb_strlen($term) < 1) {
            return response()->json(['results' => []]);
        }

        $query = Supplier::query();

        if (Schema::hasColumn('suppliers', 'active')) {
            $query->where('suppliers.active', true);
        }

        $query->where(function ($q) use ($term) {
            $q->where('suppliers.name', 'like', '%'.$term.'%')
                ->orWhere('suppliers.code', 'like', '%'.$term.'%')
                ->orWhere('suppliers.phone', 'like', '%'.$term.'%');
        });

        $suppliers = $query->orderBy('suppliers.name')->limit($limit)->get();

        $results = $suppliers->map(static function (Supplier $s) {
            $label = (string) $s->name;
            if ($s->code) {
                $label .= ' ('.$s->code.')';
            }

            return [
                'id' => (string) $s->id,
                'text' => $label,
                'entity_type' => 'supplier',
                'entity_type_label' => (string) trans('accounting::accounting.supplier.party_badge_supplier'),
            ];
        })->values()->all();

        return response()->json(['results' => $results]);
    }

    /**
     * فاکتورهای خرید یک تأمین‌کننده برای Select2.
     *
     * پیش‌فرض: همهٔ فاکتورهای آن تأمین‌کننده (برای مرجع، یادداشت بدهکار، پیش‌نمایش و غیره).
     * با ?payable_only=1 فقط فاکتورهای با ماندهٔ پرداخت (unpaid / partially_paid) — مناسب انتخاب فاکتور برای پرداخت.
     */
    public function searchInvoicesForSupplier(Request $request): JsonResponse
    {
        $supplierIdRaw = $request->input('supplier_id');
        if ($supplierIdRaw === null || $supplierIdRaw === '' || (int) $supplierIdRaw <= 0) {
            return response()->json(['results' => []]);
        }

        $validated = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'q' => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'payable_only' => ['nullable', 'boolean'],
        ]);

        $supplierId = (int) $validated['supplier_id'];
        $term = trim((string) ($validated['q'] ?? ''));
        $limit = max(5, min((int) ($validated['limit'] ?? 30), 50));

        $query = SupplierInvoice::query()
            ->where('supplier_id', $supplierId);

        if ($request->boolean('payable_only')) {
            $query->whereIn('payment_status', ['unpaid', 'partially_paid']);
        }

        if ($term !== '') {
            $query->where('invoice_number', 'like', '%'.$term.'%');
        }

        $invoices = $query->orderByDesc('id')->limit($limit)->get();

        $results = $invoices->map(static function (SupplierInvoice $inv) {
            $text = (string) $inv->invoice_number;
            if ($inv->invoice_date) {
                $text .= ' — '.$inv->invoice_date->format('Y-m-d');
            }

            return [
                'id' => (string) $inv->id,
                'text' => $text,
            ];
        })->values()->all();

        return response()->json(['results' => $results]);
    }

    /**
     * HTML fragment: جدول اقلام فاکتور خرید (بارگذاری با AJAX).
     */
    public function itemsFragment(Request $request, int|string $supplier_invoice): View
    {
        $invoice = SupplierInvoice::query()
            ->with(['items' => static fn ($q) => $q->orderBy('id')])
            ->findOrFail($supplier_invoice);

        return view('accounting::admin.supplier_invoices._items_table', [
            'invoice' => $invoice,
        ]);
    }

    /**
     * پیش‌نمایش فقط‌خواندنی فاکتور خرید برای فرم یادداشت بدهکار (supplier_id باید با فاکتور یکی باشد).
     */
    public function debitNoteReferencePreview(Request $request, int|string $supplier_invoice): View
    {
        $supplierId = (int) $request->query('supplier_id', 0);
        if ($supplierId <= 0) {
            abort(404);
        }

        $invoice = SupplierInvoice::query()
            ->with([
                'items' => static fn ($q) => $q->orderBy('id'),
                'supplier.party',
            ])
            ->findOrFail($supplier_invoice);

        if ((int) $invoice->supplier_id !== $supplierId) {
            abort(404);
        }

        return view('accounting::admin.supplier_invoices._debit_note_reference_preview', [
            'invoice' => $invoice,
        ]);
    }

    /**
     * ثبت قطعی سند خرید در دفتر کل (مشابه «ثبت نهایی» در گردش سفارش خرید).
     */
    public function postAccountingDocument(Request $request, int|string $supplier_invoice): RedirectResponse
    {
        $invoice = SupplierInvoice::query()->with(['supplier.party'])->findOrFail((int) $supplier_invoice);

        if ($invoice->document_id) {
            return redirect()->back()->with('warning', (string) trans('accounting::accounting.supplier_invoice.post_document_already'));
        }

        try {
            DB::transaction(function () use ($invoice) {
                app(SupplierInvoiceService::class)->postPurchaseAccountingDocument($invoice->fresh());
            });
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', (string) trans('accounting::accounting.supplier_invoice.post_document_success'));
    }

    public function reverseAndCreateReplacement(Request $request, int|string $supplier_invoice): RedirectResponse
    {
        $invoice = SupplierInvoice::query()->findOrFail((int) $supplier_invoice);
        $reason = trim((string) $request->input('reason', ''));

        try {
            $result = app(SupplierInvoiceService::class)
                ->reverseAndCreateReplacement($invoice, $reason !== '' ? $reason : null);
            /** @var SupplierInvoice $replacement */
            $replacement = $result['replacement_invoice'];
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            report($e);
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.accounting.supplier-invoices.edit', ['supplier_invoice' => $replacement->getKey()])
            ->with('success', (string) trans('accounting::accounting.supplier_invoice.correction_reversal_replacement_success'));
    }

    public function createAdjustment(Request $request, int|string $supplier_invoice): RedirectResponse
    {
        $invoice = SupplierInvoice::query()->findOrFail((int) $supplier_invoice);

        return redirect()->route('admin.accounting.debit-notes.create', [
            'supplier_id' => (string) $invoice->supplier_id,
            'supplier_invoice_id' => (string) $invoice->getKey(),
            'debit_type' => 'correction',
        ]);
    }

    protected function afterConfigureStructuredAccountingFormView(Request $request, bool $isEdit, ?Model $model): void
    {
        if ($this->structuredAccountingFormSlug() !== 'supplier_invoices') {
            return;
        }

        $this->view->withCss('vendor/accounting/admin/css/accounting-purchase-summary-cards.css', true);
        $this->view->withJs('vendor/accounting/admin/js/payment-destination-picker.js', true);
        $this->view->withJs('vendor/accounting/admin/js/accounting-line-items-editor.js', true);
        $this->view->withJs('vendor/accounting/admin/js/accounting-ajax-supplier-widgets.js', true);
        $this->view->withJs('vendor/accounting/admin/js/supplier-invoice-structured-form.js', true);
    }

    /**
     * @return array<string, mixed>
     */
    protected function structuredAccountingFormExtraViewVariables(Request $request, bool $isEdit, ?Model $model): array
    {
        $supplierInvoiceItemsFragmentUrl = null;
        $supplierSelectInitialText = null;
        $defaultSupplierInvoiceNumber = null;
        $invoiceNumberUniquenessUrl = route('admin.accounting.supplier-invoices.check-invoice-number');

        $supplierInvoiceItemsStoreUrl = null;

        if ($isEdit && $model instanceof SupplierInvoice) {
            $supplierInvoiceItemsFragmentUrl = route('admin.accounting.supplier-invoices.items-fragment', [
                'supplier_invoice' => $model->getKey(),
            ]);
            $supplierInvoiceItemsStoreUrl = route('admin.accounting.supplier-invoices.items.store', [
                'supplier_invoice' => $model->getKey(),
            ]);

            if ($model->supplier_id) {
                $supplier = Supplier::query()->find((int) $model->supplier_id);
                if ($supplier) {
                    $supplierSelectInitialText = (string) $supplier->name;
                }
            }
        } else {
            $defaultSupplierInvoiceNumber = $this->suggestNextSupplierInvoiceNumber();
        }

        $supplierInvoicePrefillSupplierId = null;
        $supplierInvoicePrefillPurchaseOrderId = null;
        $supplierInvoicePrefillTotalFromPo = null;
        $supplierInvoicePrefillCurrencyCode = null;
        $supplierInvoicePrefillFxRate = null;
        if (! $isEdit) {
            $rawPo = $request->query('purchase_order_id');
            if ($rawPo !== null && $rawPo !== '' && ctype_digit((string) $rawPo)) {
                $poRow = PurchaseOrder::query()->find((int) $rawPo);
                if ($poRow) {
                    $supplierInvoicePrefillPurchaseOrderId = (string) $poRow->getKey();
                    $supplierInvoicePrefillTotalFromPo = (string) $poRow->total_amount;
                    $supplierInvoicePrefillSupplierId = (string) $poRow->supplier_id;
                    $supplierInvoicePrefillCurrencyCode = (string) ($poRow->currency_code ?? '');
                    $supplierInvoicePrefillFxRate = (string) ($poRow->fx_rate_at_order ?? '');
                    if ($supplierSelectInitialText === null && (int) $poRow->supplier_id > 0) {
                        $supplier = Supplier::query()->find((int) $poRow->supplier_id);
                        if ($supplier) {
                            $supplierSelectInitialText = (string) $supplier->name;
                        }
                    }
                }
            }
            $rawSid = $request->query('supplier_id');
            if ($rawSid !== null && $rawSid !== '' && ctype_digit((string) $rawSid)) {
                $supplierInvoicePrefillSupplierId = (string) (int) $rawSid;
                if ($supplierSelectInitialText === null) {
                    $supplier = Supplier::query()->find((int) $rawSid);
                    if ($supplier) {
                        $supplierSelectInitialText = (string) $supplier->name;
                    }
                }
            }
        }

        $baseCurrency = $this->resolveBaseCurrencyCode();
        $fxCardCurrencyMeta = $this->currencyMetaForFxCard();
        $baseCurrencyDecimals = max(0, min(6, (int) ($fxCardCurrencyMeta[$baseCurrency]['decimals'] ?? 4)));
        $fxCardInitialCurrency = strtoupper((string) old(
            'currency_code',
            ($isEdit && $model instanceof SupplierInvoice)
                ? (string) ($model->currency_code ?: $baseCurrency)
                : (string) (($supplierInvoicePrefillCurrencyCode ?: $baseCurrency))
        ));
        if ($fxCardInitialCurrency === '') {
            $fxCardInitialCurrency = $baseCurrency;
        }
        $fxCardInitialRate = (string) old(
            'fx_rate_at_invoice',
            ($isEdit && $model instanceof SupplierInvoice)
                ? (string) ($model->fx_rate_at_invoice ?: '1')
                : (string) (($supplierInvoicePrefillFxRate !== null && $supplierInvoicePrefillFxRate !== '') ? $supplierInvoicePrefillFxRate : '1')
        );
        $fxCardInitialBaseAmount = (string) old(
            'amount_base_at_invoice',
            ($isEdit && $model instanceof SupplierInvoice)
                ? (string) ($model->amount_base_at_invoice ?? '')
                : (
                    ($supplierInvoicePrefillTotalFromPo !== null && $supplierInvoicePrefillTotalFromPo !== '' && $fxCardInitialRate !== '')
                        ? (string) round(((float) $supplierInvoicePrefillTotalFromPo) * ((float) $fxCardInitialRate), $baseCurrencyDecimals)
                        : ''
                )
        );

        return [
            'supplierInvoiceItemsFragmentUrl' => $supplierInvoiceItemsFragmentUrl,
            'supplierInvoiceItemsStoreUrl' => $supplierInvoiceItemsStoreUrl,
            'supplierSelectInitialText' => $supplierSelectInitialText,
            'defaultSupplierInvoiceNumber' => $defaultSupplierInvoiceNumber,
            'invoiceNumberUniquenessUrl' => $invoiceNumberUniquenessUrl,
            'supplierInvoicePrefillSupplierId' => $supplierInvoicePrefillSupplierId,
            'supplierInvoicePrefillPurchaseOrderId' => $supplierInvoicePrefillPurchaseOrderId,
            'supplierInvoicePrefillTotalFromPo' => $supplierInvoicePrefillTotalFromPo,
            'fxCardEnabled' => true,
            'fxCardCurrencyField' => 'currency_code',
            'fxCardRateField' => 'fx_rate_at_invoice',
            'fxCardBaseAmountField' => 'amount_base_at_invoice',
            'fxCardBaseCurrency' => $baseCurrency,
            'fxCardCurrencyOptions' => $this->currencyOptionsForFxCard(),
            'fxCardCurrencyMeta' => $fxCardCurrencyMeta,
            'fxCardInitialCurrency' => $fxCardInitialCurrency,
            'fxCardInitialRate' => $fxCardInitialRate,
            'fxCardInitialBaseAmount' => $fxCardInitialBaseAmount,
            'supplierInvoiceCorrectionsTimeline' => ($isEdit && $model instanceof SupplierInvoice)
                ? app(SupplierInvoiceCorrectionService::class)->timelineForInvoice($model)
                : collect(),
        ];
    }

    public function getFieldsForm(): array
    {
        $hidden = $this->hiddenFormFields();

        $paymentField = in_array('supplier_invoice_status', $hidden, true)
            ? Field::hidden('payment_status', SupplierInvoice::STATUS_UNPAID)
            : Field::select('payment_status', trans('accounting::accounting.supplier_invoice.payment_status'))
                ->setOptions([
                    SupplierInvoice::STATUS_UNPAID => trans('accounting::accounting.supplier_invoice.payment_status_unpaid'),
                    SupplierInvoice::STATUS_PARTIALLY_PAID => trans('accounting::accounting.supplier_invoice.payment_status_partial'),
                    SupplierInvoice::STATUS_PAID => trans('accounting::accounting.supplier_invoice.payment_status_paid'),
                ])
                ->withDefaultValue(SupplierInvoice::STATUS_UNPAID)
                ->required();

        $base = [
            Field::select('settlement_mode', trans('accounting::accounting.supplier_invoice.settlement_mode'))
                ->setOptions([
                    SupplierInvoice::SETTLEMENT_ON_ACCOUNT => trans('accounting::accounting.supplier_invoice.settlement_on_account'),
                    SupplierInvoice::SETTLEMENT_PAID_AT_SOURCE => trans('accounting::accounting.supplier_invoice.settlement_paid_at_source'),
                ])
                ->withDefaultValue(SupplierInvoice::SETTLEMENT_ON_ACCOUNT)
                ->required(),
            Field::number('paid_at_source_destination', trans('accounting::accounting.supplier_invoice.settlement_destination'))
                ->optional()
                ->withAttributes([
                    'structured_widget' => 'payment_destination_picker',
                    'payment_destination_context' => PaymentDestinationCatalog::CONTEXT_SUPPLIER_PAYMENT,
                    'pdp_name_prefix' => 'paid_at_source_',
                    'wrap_settlement_destination' => true,
                ]),
            Field::string('invoice_number', trans('accounting::accounting.supplier_invoice.invoice_number'))->required(),
            Field::date('invoice_date', trans('accounting::accounting.supplier_invoice.invoice_date'))->required(),
            Field::number('supplier_id', trans('accounting::accounting.supplier_invoice.supplier_id'))
                ->required()
                ->withAttributes(['structured_widget' => 'ajax_supplier_select']),
            Field::hidden('purchase_order_id', ''),
            Field::number('total_amount', trans('accounting::accounting.supplier_invoice.total_amount'))
                ->withDefaultValue(0)
                ->optional(),
        ];

        $vatTail = [
            Field::hidden('tax_method', function_exists('tax_calculation_method') ? tax_calculation_method() : 'exclusive'),
            Field::hidden('subtotal', 0),
            Field::hidden('tax_amount', 0),
            Field::hidden('discount_amount', 0),
        ];

        return array_merge($base, $vatTail, [$paymentField]);
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle(trans('accounting::accounting.common.id'))->sortable()->width('80px'),
            Field::make('invoice_number')->withTitle(trans('accounting::accounting.supplier_invoice.invoice_number'))->searchable()->sortable()->width('150px'),
            Field::make('invoice_date')->withTitle(trans('accounting::accounting.supplier_invoice.invoice_date'))->sortable()->width('120px'),
            Field::make('supplier_id')->withTitle(trans('accounting::accounting.supplier_invoice.supplier_id'))->sortable()->width('100px'),
            Field::make('total_amount')
                ->withTitle(trans('accounting::accounting.supplier_invoice.total_amount'))
                ->customMethod('renderTotalAmountWithCurrency')
                ->sortable()
                ->width('180px'),
            Field::make('payment_status')->withTitle(trans('accounting::accounting.supplier_invoice.payment_status'))->customMethod('renderSupplierInvoicePaymentStatus')->width('120px'),
            Field::make('list_payment_action')
                ->withTitle(trans('accounting::accounting.supplier_invoice.list_payment_column'))
                ->customMethod('renderSupplierInvoiceListPaymentLink')
                ->skipDatabase()
                ->width('140px'),
        ];
    }

    public function filters(): array
    {
        return [
            Field::select('payment_status', trans('accounting::accounting.supplier_invoice.payment_status'))
                ->setOptions([
                    '' => trans('accounting::accounting.common.all'),
                    SupplierInvoice::STATUS_UNPAID => trans('accounting::accounting.supplier_invoice.payment_status_unpaid'),
                    SupplierInvoice::STATUS_PARTIALLY_PAID => trans('accounting::accounting.supplier_invoice.payment_status_partial'),
                    SupplierInvoice::STATUS_PAID => trans('accounting::accounting.supplier_invoice.payment_status_paid'),
                ]),
        ];
    }

    public function renderSupplierInvoicePaymentStatus($row): string
    {
        $s = (string) ($row->payment_status ?? SupplierInvoice::STATUS_UNPAID);
        $map = [
            SupplierInvoice::STATUS_UNPAID => 'bg-warning',
            SupplierInvoice::STATUS_PARTIALLY_PAID => 'bg-info',
            SupplierInvoice::STATUS_PAID => 'bg-success',
        ];
        $cls = $map[$s] ?? 'bg-secondary';
        $label = match ($s) {
            SupplierInvoice::STATUS_UNPAID => trans('accounting::accounting.supplier_invoice.payment_status_unpaid'),
            SupplierInvoice::STATUS_PARTIALLY_PAID => trans('accounting::accounting.supplier_invoice.payment_status_partial'),
            SupplierInvoice::STATUS_PAID => trans('accounting::accounting.supplier_invoice.payment_status_paid'),
            default => $s,
        };

        return '<span class="badge '.$cls.'">'.e((string) $label).'</span>';
    }

    public function renderTotalAmountWithCurrency($row): string
    {
        $amount = (float) ($row->total_amount ?? 0);
        $formattedAmount = rtrim(rtrim(number_format($amount, 4, '.', ','), '0'), '.');
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
     * لینک مستقیم به ثبت پرداخت برای همین فاکتور (فقط اگر مانده پرداخت دارد).
     */
    public function renderSupplierInvoiceListPaymentLink($row): string
    {
        $status = (string) ($row->payment_status ?? SupplierInvoice::STATUS_UNPAID);
        if ($status === SupplierInvoice::STATUS_PAID) {
            return '<span class="text-muted small">'.e(trans('accounting::accounting.supplier_invoice.list_paid_short')).'</span>';
        }

        $invoiceId = (int) ($row->id ?? 0);
        if ($invoiceId <= 0) {
            return '<span class="text-muted">—</span>';
        }

        $supplierId = (int) ($row->supplier_id ?? 0);
        $query = ['supplier_invoice_id' => (string) $invoiceId];
        if ($supplierId > 0) {
            $query['supplier_id'] = (string) $supplierId;
        }
        $balanceDue = (float) ($row->balance_due ?? 0);
        if ($balanceDue > 0) {
            $query['amount'] = rtrim(rtrim(number_format($balanceDue, 4, '.', ''), '0'), '.');
        }
        $url = route('admin.accounting.supplier-payments.create', $query);
        $label = e(trans('accounting::accounting.supplier_invoice.list_pay_cta'));

        return '<a href="'.e($url).'" class="btn btn-sm btn-outline-success">'
            .'<i class="ph-currency-circle-dollar me-1"></i>'.$label.'</a>';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = request()->route($this->routeParameter());

        $rules = [
            'settlement_mode' => ['required', 'string', 'in:'.SupplierInvoice::SETTLEMENT_ON_ACCOUNT.','.SupplierInvoice::SETTLEMENT_PAID_AT_SOURCE],
            'paid_at_source_bank_id' => ['nullable', 'integer', 'exists:banks,id'],
            'paid_at_source_cash_box_id' => ['nullable', 'integer', 'exists:cash_boxes,id'],
            'paid_at_source_cheque_id' => ['nullable', 'integer', 'exists:cheques,id'],
            'paid_at_source_wallet_id' => [
                'nullable',
                'integer',
                Rule::exists('wallets', 'id')->where(static function ($query) {
                    $query->where('wallet_type', Wallet::TYPE_TREASURY)->where('active', true);
                }),
            ],
            'payment_status' => ['required', 'in:'.SupplierInvoice::STATUS_UNPAID.','.SupplierInvoice::STATUS_PARTIALLY_PAID.','.SupplierInvoice::STATUS_PAID],
            'invoice_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('supplier_invoices', 'invoice_number')->ignore($id),
            ],
            'invoice_date' => ['required'],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'purchase_order_id' => [
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
            ],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'fx_rate_at_invoice' => ['required', 'numeric', 'gt:0'],
            'amount_base_at_invoice' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'subtotal' => ['nullable', 'numeric', 'min:0'],
            'tax_method' => ['nullable', 'string', 'in:inclusive,exclusive'],
        ];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'invoice_number.unique' => (string) trans('accounting::accounting.supplier_invoice.invoice_number_taken'),
        ];
    }

    protected function afterStructuredAccountingFormPrepareForValidation(Request $request): void
    {
        $this->normalizeDecimalRequestValues($request, [
            'total_amount',
            'fx_rate_at_invoice',
            'amount_base_at_invoice',
            'subtotal',
            'tax_amount',
            'discount_amount',
            'shipping_amount',
        ]);

        $rawPo = $request->input('purchase_order_id');
        if ($rawPo === null || $rawPo === '' || (string) $rawPo === '0') {
            $request->merge(['purchase_order_id' => null]);
        } elseif (is_numeric($rawPo)) {
            $request->merge(['purchase_order_id' => (int) $rawPo]);
        }

        if (! $request->filled('payment_status')) {
            $request->merge(['payment_status' => SupplierInvoice::STATUS_UNPAID]);
        }
        if (! $request->filled('settlement_mode')) {
            $request->merge(['settlement_mode' => SupplierInvoice::SETTLEMENT_ON_ACCOUNT]);
        }
        $mode = (string) $request->input('settlement_mode', SupplierInvoice::SETTLEMENT_ON_ACCOUNT);
        if (! in_array($mode, [SupplierInvoice::SETTLEMENT_ON_ACCOUNT, SupplierInvoice::SETTLEMENT_PAID_AT_SOURCE], true)) {
            $request->merge(['settlement_mode' => SupplierInvoice::SETTLEMENT_ON_ACCOUNT]);
            $mode = SupplierInvoice::SETTLEMENT_ON_ACCOUNT;
        }
        foreach ([
            'paid_at_source_payment_method_id',
            'paid_at_source_pos_terminal_id',
        ] as $strip) {
            $request->request->remove($strip);
        }
        $pm = (int) $request->input('paid_at_source_payment_method_id', 0);
        $methodType = (string) PaymentMethod::query()->whereKey($pm)->value('type');
        if ($pm > 0 && $methodType === PaymentMethod::TYPE_CHEQUE && ! $request->filled('paid_at_source_cheque_id')) {
            $supplier = Supplier::query()->find((int) $request->input('supplier_id', 0));
            $autoCheque = app(ChequeAutoCreationService::class)->ensureCheque([
                'context' => 'supplier_invoice_paid_at_source',
                'source_short' => 'SI',
                'payment_method_id' => $pm,
                'cheque_type' => \RMS\Accounting\Models\Cheque::TYPE_ISSUED,
                'party_id' => (int) ($supplier?->party_id ?? 0),
                'amount' => (float) ($request->input('total_amount') ?? 0),
                'currency_code' => (string) ($request->input('currency_code') ?: 'IRT'),
                'issue_date' => (string) ($request->input('invoice_date') ?: now()->toDateString()),
                'due_date' => (string) ($request->input('due_date') ?: $request->input('invoice_date') ?: now()->toDateString()),
                'notes' => (string) ($request->input('notes') ?: ''),
            ]);
            if ($autoCheque) {
                $request->merge(['paid_at_source_cheque_id' => (int) $autoCheque->id]);
            }
        }
        if ($mode === SupplierInvoice::SETTLEMENT_PAID_AT_SOURCE) {
            $bid = (int) $request->input('paid_at_source_bank_id', 0);
            $cid = (int) $request->input('paid_at_source_cash_box_id', 0);
            $wid = (int) $request->input('paid_at_source_wallet_id', 0);
            if ($bid > 0 && $cid > 0) {
                $cid = 0;
            }
            if ($bid > 0 && $wid > 0) {
                $wid = 0;
            }
            if ($cid > 0 && $wid > 0) {
                $wid = 0;
            }
            if ($bid <= 0 && $cid <= 0 && $wid <= 0) {
                throw ValidationException::withMessages([
                    'paid_at_source_bank_id' => (string) trans('accounting::accounting.supplier_invoice.settlement_paid_at_source_required'),
                ]);
            }
            if ($wid > 0) {
                $wallet = Wallet::query()
                    ->whereKey($wid)
                    ->where('wallet_type', Wallet::TYPE_TREASURY)
                    ->where('active', true)
                    ->first();
                if (! $wallet || ! $wallet->account_id) {
                    throw ValidationException::withMessages([
                        'paid_at_source_bank_id' => (string) trans('accounting::accounting.supplier_invoice.settlement_paid_at_source_required'),
                    ]);
                }
            }
            $request->merge([
                'paid_at_source_bank_id' => $bid > 0 ? $bid : null,
                'paid_at_source_cash_box_id' => $cid > 0 ? $cid : null,
                'paid_at_source_cheque_id' => $request->filled('paid_at_source_cheque_id') ? (int) $request->input('paid_at_source_cheque_id') : null,
                'paid_at_source_wallet_id' => $wid > 0 ? $wid : null,
            ]);
        } else {
            $request->merge([
                'paid_at_source_bank_id' => null,
                'paid_at_source_cash_box_id' => null,
                'paid_at_source_cheque_id' => null,
                'paid_at_source_wallet_id' => null,
            ]);
        }
    }

    protected function beforeAdd(Request &$request): void
    {
        $this->mergeSupplierInvoiceFinancialDefaults($request, null);
    }

    protected function beforeUpdate(Request &$request, int|string $id): void
    {
        $this->mergeSupplierInvoiceFinancialDefaults($request, (int) $id);
    }

    protected function afterAdd(Request $request, int|string $id, Model $model): void
    {
        if (! $model instanceof SupplierInvoice) {
            return;
        }
        if ((int) ($model->paid_at_source_cheque_id ?? 0) > 0) {
            $cheque = \RMS\Accounting\Models\Cheque::query()->find((int) $model->paid_at_source_cheque_id);
            if ($cheque) {
                app(ChequeAutoCreationService::class)->attachSource($cheque, SupplierInvoice::class, (int) $model->id);
            }
        }
    }

    protected function afterUpdate(Request $request, int|string $id, Model $model): void
    {
        if (! $model instanceof SupplierInvoice) {
            return;
        }
        if ((int) ($model->paid_at_source_cheque_id ?? 0) > 0) {
            $cheque = \RMS\Accounting\Models\Cheque::query()->find((int) $model->paid_at_source_cheque_id);
            if ($cheque) {
                app(ChequeAutoCreationService::class)->attachSource($cheque, SupplierInvoice::class, (int) $model->id);
            }
        }
    }

    /**
     * جدول supplier_invoices ستون status ندارد؛ payment_status و فیلدهای مبلغی پر می‌شوند.
     */
    private function mergeSupplierInvoiceFinancialDefaults(Request $request, ?int $updateId): void
    {
        $rawPo = $request->input('purchase_order_id');
        if ($rawPo === null || $rawPo === '' || (string) $rawPo === '0') {
            $request->merge(['purchase_order_id' => null]);
        } elseif (is_numeric($rawPo)) {
            $request->merge(['purchase_order_id' => (int) $rawPo]);
        }

        $request->request->remove('status');
        foreach ([
            'paid_at_source_payment_method_id',
            'paid_at_source_pos_terminal_id',
        ] as $strip) {
            $request->request->remove($strip);
        }

        if (! $request->filled('payment_status')) {
            $request->merge(['payment_status' => SupplierInvoice::STATUS_UNPAID]);
        }

        $totalFloat = (float) ($this->parseDecimalInput($request->input('total_amount')) ?? 0.0);
        $invoiceDate = $request->input('invoice_date');

        if (! $request->filled('due_date') && is_string($invoiceDate) && trim($invoiceDate) !== '') {
            $request->merge(['due_date' => $invoiceDate]);
        }

        if (! $request->filled('subtotal')) {
            $request->merge(['subtotal' => $totalFloat]);
        }
        if (! $request->filled('tax_amount')) {
            $request->merge(['tax_amount' => 0]);
        }
        $taxMethod = strtolower(trim((string) $request->input('tax_method', '')));
        if (! in_array($taxMethod, ['inclusive', 'exclusive'], true)) {
            $existingTaxMethod = null;
            if ($updateId !== null) {
                $existingTaxMethod = (string) (SupplierInvoice::query()->whereKey($updateId)->value('tax_method') ?? '');
            }
            $taxMethod = in_array($existingTaxMethod, ['inclusive', 'exclusive'], true)
                ? $existingTaxMethod
                : (function_exists('tax_calculation_method') ? tax_calculation_method() : 'exclusive');
        }
        $request->merge(['tax_method' => $taxMethod]);
        if (! $request->filled('discount_amount')) {
            $request->merge(['discount_amount' => 0]);
        }
        $baseCurrency = $this->resolveBaseCurrencyCode();
        $currencyCode = strtoupper(trim((string) $request->input('currency_code', $baseCurrency)));
        if ($currencyCode === '') {
            $currencyCode = $baseCurrency;
        }
        $fx = $request->filled('fx_rate_at_invoice')
            ? $this->parseDecimalInput($request->input('fx_rate_at_invoice'))
            : 1.0;
        if ($currencyCode === $baseCurrency && (!$request->filled('fx_rate_at_invoice') || (float) $fx <= 0.0)) {
            $fx = 1.0;
        }
        $fxValue = (float) ($fx ?? 0.0);
        $currencyMeta = $this->currencyMetaForFxCard();
        $baseDecimals = max(0, min(6, (int) ($currencyMeta[$baseCurrency]['decimals'] ?? 4)));
        $amountBase = round($totalFloat * $fxValue, $baseDecimals);
        $request->merge([
            'currency_code' => $currencyCode,
            'fx_rate_at_invoice' => $fxValue,
            'amount_base_at_invoice' => $amountBase,
        ]);

        $mode = (string) $request->input('settlement_mode', SupplierInvoice::SETTLEMENT_ON_ACCOUNT);

        if ($mode === SupplierInvoice::SETTLEMENT_PAID_AT_SOURCE) {
            $request->merge([
                'payment_status' => SupplierInvoice::STATUS_PAID,
                'paid_amount' => $totalFloat,
                'balance_due' => 0,
            ]);

            return;
        }

        if ($updateId === null) {
            $request->merge([
                'paid_amount' => 0,
                'balance_due' => $totalFloat,
            ]);

            return;
        }

        $existing = SupplierInvoice::query()->whereKey($updateId)->first(['id', 'document_id']);
        if ($existing && (int) ($existing->document_id ?? 0) > 0) {
            throw ValidationException::withMessages([
                '_invoice' => (string) trans('accounting::accounting.supplier_invoice.header_locked_document'),
            ]);
        }

        app(SupplierInvoiceService::class)->updatePaymentStatus($updateId);
        $row = SupplierInvoice::query()->whereKey($updateId)->first(['paid_amount', 'payment_status', 'balance_due']);
        if ($row) {
            $request->merge([
                'paid_amount' => (string) $row->paid_amount,
                'payment_status' => $row->payment_status,
                'balance_due' => (string) $row->balance_due,
            ]);
        } elseif (! $request->filled('balance_due')) {
            $paid = $request->has('paid_amount')
                ? (float) $request->input('paid_amount')
                : (float) (SupplierInvoice::query()->whereKey($updateId)->value('paid_amount') ?? 0);
            $request->merge(['balance_due' => max(0, $totalFloat - $paid)]);
        }
    }

    protected function suggestNextSupplierInvoiceNumber(): string
    {
        return SupplierInvoice::suggestNextInvoiceNumber();
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
        return Currency::resolveBaseCurrencyCode('IRT');
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
     * @return array<int, string>
     */
    protected function hiddenFormFields(): array
    {
        $raw = Setting::get('accounting.package_forms.purchase.hidden_fields', '[]');
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, static fn ($value) => is_string($value) && $value !== ''));
    }
}
