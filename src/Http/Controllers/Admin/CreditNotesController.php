<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use RMS\Accounting\Http\Controllers\Admin\Concerns\RendersAccountingStructuredResourceForm;
use RMS\Accounting\Models\Currency;
use RMS\Accounting\Models\CreditNote;
use RMS\Accounting\Models\Customer;
use RMS\Accounting\Models\CustomerInvoice;
use RMS\Accounting\Services\CreditNoteService;
use RMS\Accounting\Services\CurrencyService;
use Illuminate\Http\Request;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RMS\Core\Data\Field;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;

class CreditNotesController extends AccountingAdminController implements HasList, HasForm, ShouldFilter
{
    use RendersAccountingStructuredResourceForm;

    protected CreditNoteService $creditNoteService;

    public function __construct(Filesystem $filesystem, CreditNoteService $creditNoteService)
    {
        parent::__construct($filesystem);
        $this->creditNoteService = $creditNoteService;
    }

    public function table(): string
    {
        return 'credit_notes';
    }

    public function modelName(): string
    {
        return CreditNote::class;
    }

    public function baseRoute(): string
    {
        return 'accounting.credit-notes';
    }

    public function routeParameter(): string
    {
        return 'credit_note';
    }

    public function query(Builder $sql): void
    {
        $sql->leftJoin('customers', 'customers.id', '=', 'a.customer_id')
            ->leftJoin('customer_invoices as reference_invoice', 'reference_invoice.id', '=', 'a.customer_invoice_id')
            ->leftJoin('customer_invoices as applied_invoice', 'applied_invoice.id', '=', 'a.applied_to_invoice_id')
            ->addSelect(
                'a.*',
                'customers.name as customer_name',
                'reference_invoice.invoice_number as reference_invoice_number',
                'applied_invoice.invoice_number as applied_invoice_number'
            );
    }

    public function getFieldsForm(): array
    {
        $queryCustomerId = request()->query('customer_id');
        $queryInvoiceId = request()->query('customer_invoice_id');
        $queryType = request()->query('credit_type');

        $prefillCustomerId = ($queryCustomerId !== null && $queryCustomerId !== '' && ctype_digit((string) $queryCustomerId))
            ? (string) (int) $queryCustomerId
            : '';
        $prefillInvoiceId = ($queryInvoiceId !== null && $queryInvoiceId !== '' && ctype_digit((string) $queryInvoiceId))
            ? (string) (int) $queryInvoiceId
            : '';
        $prefillType = in_array((string) $queryType, [CreditNote::TYPE_RETURN, CreditNote::TYPE_DISCOUNT, CreditNote::TYPE_CORRECTION], true)
            ? (string) $queryType
            : CreditNote::TYPE_RETURN;

        return [
            Field::number('customer_id', trans('accounting::accounting.invoice.customer_id'))
                ->withAttributes(['structured_widget' => 'customer_payment_customer_picker'])
                ->withDefaultValue($prefillCustomerId)
                ->required(),
            Field::select('customer_invoice_id', trans('accounting::accounting.credit_note_form.related_invoice'))
                ->setOptions($this->getInvoiceOptions())
                ->withDefaultValue($prefillInvoiceId)
                ->optional(),
            Field::select('credit_type', trans('accounting::accounting.common.type'))
                ->setOptions([
                    CreditNote::TYPE_RETURN => trans('accounting::accounting.credit_note_form.type_return'),
                    CreditNote::TYPE_DISCOUNT => trans('accounting::accounting.credit_note_form.type_discount'),
                    CreditNote::TYPE_CORRECTION => trans('accounting::accounting.credit_note_form.type_correction'),
                ])
                ->withDefaultValue($prefillType)
                ->required(),
            Field::date('credit_date', trans('accounting::accounting.credit_note_form.credit_date'))
                ->withDefaultValue(now()->toDateString())
                ->optional(),
            Field::number('total_amount', trans('accounting::accounting.payment.amount'))->optional(),
            Field::textarea('reason', trans('accounting::accounting.credit_note_form.reason'))->optional(),
            Field::textarea('notes', trans('accounting::accounting.credit_note_form.notes'))->optional(),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle('شناسه')->sortable()->width('80px'),
            Field::make('credit_note_number')->withTitle('شماره')->searchable()->sortable(),
            Field::make('customer_name', 'customers.name')->withTitle('مشتری')->customMethod('renderCustomerName')->searchable(),
            Field::make('credit_type')->withTitle('نوع')->customMethod('renderType'),
            Field::make('customer_invoice_id')->withTitle('فاکتور مرتبط')->customMethod('renderRelatedInvoice'),
            Field::make('total_amount')->withTitle('مبلغ')->customMethod('renderAmount')->sortable(),
            Field::make('status')->withTitle('وضعیت')->customMethod('renderStatus'),
            Field::date('credit_date')->withTitle('تاریخ')->sortable(),
        ];
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'customer_invoice_id' => [
                'nullable',
                'integer',
                Rule::exists('customer_invoices', 'id')->where(function ($q) {
                    $cid = request()->input('customer_id');
                    if ($cid !== null && $cid !== '' && ctype_digit((string) $cid)) {
                        $q->where('customer_id', (int) $cid);
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                }),
            ],
            'credit_type' => ['required', 'in:return,discount,correction'],
            'credit_date' => ['nullable', 'date'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    protected function beforeAdd(Request &$request): void
    {
        $this->normalizeCreditNoteInput($request);
    }

    protected function beforeUpdate(Request &$request, int|string $id): void
    {
        $this->normalizeCreditNoteInput($request);
    }

    /**
     * @return array<string,mixed>
     */
    protected function structuredAccountingFormExtraViewVariables(Request $request, bool $isEdit, ?Model $model): array
    {
        $prefillCustomerId = null;
        $prefillInvoiceId = null;
        $customerSelectInitialText = null;
        $invoicePreview = null;
        $quickCreateCurrencyOptions = [];

        if (! $isEdit) {
            $rawCustomer = $request->query('customer_id');
            if ($rawCustomer !== null && $rawCustomer !== '' && ctype_digit((string) $rawCustomer)) {
                $prefillCustomerId = (string) (int) $rawCustomer;
                $customer = Customer::query()->find((int) $rawCustomer);
                if ($customer) {
                    $customerSelectInitialText = (string) $customer->name;
                }
            }
            $rawInvoice = $request->query('customer_invoice_id');
            if ($rawInvoice !== null && $rawInvoice !== '' && ctype_digit((string) $rawInvoice)) {
                $prefillInvoiceId = (string) (int) $rawInvoice;
                $invoice = CustomerInvoice::query()->find((int) $rawInvoice);
                if ($invoice && $prefillCustomerId === null) {
                    $prefillCustomerId = (string) $invoice->customer_id;
                    $customer = Customer::query()->find((int) $invoice->customer_id);
                    if ($customer) {
                        $customerSelectInitialText = (string) $customer->name;
                    }
                }
                if ($invoice) {
                    $invoicePreview = CustomerInvoice::query()
                        ->with(['items' => static fn ($q) => $q->orderBy('id'), 'customer'])
                        ->find((int) $invoice->getKey());
                }
            }
        } elseif ($model instanceof CreditNote) {
            $prefillCustomerId = (string) ($model->customer_id ?? '');
            $prefillInvoiceId = (string) ($model->customer_invoice_id ?? '');
            if ((int) ($model->customer_id ?? 0) > 0) {
                $customer = Customer::query()->find((int) $model->customer_id);
                if ($customer) {
                    $customerSelectInitialText = (string) $customer->name;
                }
            }
            if ((int) ($model->customer_invoice_id ?? 0) > 0) {
                $invoicePreview = CustomerInvoice::query()
                    ->with(['items' => static fn ($q) => $q->orderBy('id'), 'customer'])
                    ->find((int) $model->customer_invoice_id);
            }
        }

        try {
            $quickCreateCurrencyOptions = app(CurrencyService::class)
                ->getActiveCurrencies()
                ->mapWithKeys(static fn ($currency) => [
                    (string) $currency->code => (string) $currency->code.' — '.(string) $currency->name,
                ])
                ->all();
        } catch (\Throwable) {
            $quickCreateCurrencyOptions = [];
        }

        $magicInvoiceId = 2147483646;
        $creditNoteReferenceInvoicePreviewUrlTemplate = str_replace(
            (string) $magicInvoiceId,
            '__INVOICE_ID__',
            route('admin.accounting.credit-notes.reference-invoice-preview', ['customer_invoice' => $magicInvoiceId])
        );

        return [
            'creditNotePrefillCustomerId' => $prefillCustomerId,
            'creditNotePrefillInvoiceId' => $prefillInvoiceId,
            'customerPrefillId' => $prefillCustomerId,
            'customerSelectInitialText' => $customerSelectInitialText,
            'customerPaymentPrefillId' => $prefillCustomerId,
            'customerPaymentSelectInitialText' => $customerSelectInitialText,
            'customerPaymentSearchUrl' => route('admin.accounting.customer-invoices.search-customers'),
            'customerQuickCreateCurrencyOptions' => $quickCreateCurrencyOptions,
            'creditNoteReferenceInvoicePreviewUrlTemplate' => $creditNoteReferenceInvoicePreviewUrlTemplate,
            'creditNoteContextInvoice' => $invoicePreview,
        ];
    }

    protected function afterConfigureStructuredAccountingFormView(Request $request, bool $isEdit, ?Model $model): void
    {
        if ($this->structuredAccountingFormSlug() !== 'credit_notes') {
            return;
        }

        $this->view->withCss('vendor/accounting/admin/css/customer-payment-customer-picker.css', true);
        $this->view->withJs('vendor/accounting/admin/js/customer-payment-structured-form.js', true);
        $this->view->withJs('vendor/accounting/admin/js/credit-note-structured-form.js', true);
    }

    public function referenceInvoicePreview(Request $request, int|string $customer_invoice): View
    {
        $customerId = (int) $request->query('customer_id', 0);
        if ($customerId <= 0) {
            abort(404);
        }

        $invoice = CustomerInvoice::query()
            ->with(['items' => static fn ($q) => $q->orderBy('id'), 'customer'])
            ->findOrFail((int) $customer_invoice);
        if ((int) $invoice->customer_id !== $customerId) {
            abort(404);
        }

        return view('accounting::admin.customer_invoices._credit_note_reference_preview', [
            'invoice' => $invoice,
        ]);
    }

    public function issue(Request $request, $id)
    {
        $creditNote = CreditNote::findOrFail($id);
        try {
            $this->creditNoteService->issueCreditNote($creditNote);
            return redirect()->back()->with('success', 'اعتبار برگشتی صادر شد');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function apply(Request $request, $id)
    {
        $validated = $request->validate(['invoice_id' => 'required|exists:customer_invoices,id']);
        $creditNote = CreditNote::findOrFail($id);
        try {
            if ($creditNote->isDraft()) {
                $creditNote = $this->creditNoteService->issueCreditNote($creditNote);
            }
            $this->creditNoteService->applyToInvoice($creditNote, $validated['invoice_id']);
            return redirect()->back()->with('success', 'اعتبار برگشتی به فاکتور اعمال شد');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    protected function getCustomerOptions(): array
    {
        return [];
    }

    protected function getInvoiceOptions(): array
    {
        $customerId = request()->input('customer_id');
        $query = CustomerInvoice::query()
            ->whereIn('payment_status', [CustomerInvoice::STATUS_UNPAID, CustomerInvoice::STATUS_PARTIALLY_PAID])
            ->orderByDesc('id');

        if ($customerId !== null && $customerId !== '' && ctype_digit((string) $customerId)) {
            $query->where('customer_id', (int) $customerId);
        }

        return $query->limit(100)->pluck('invoice_number', 'id')->toArray();
    }

    protected function normalizeCreditNoteInput(Request $request): void
    {
        if (! $request->filled('credit_note_number')) {
            $request->merge(['credit_note_number' => $this->suggestNextCreditNoteNumber()]);
        }
        if (! $request->filled('credit_date')) {
            $request->merge(['credit_date' => now()->toDateString()]);
        }

        $request->merge([
            'currency_code' => strtoupper((string) $request->input('currency_code', Currency::resolveBaseCurrencyCode('IRR'))),
            'fx_rate' => (float) ($this->parseDecimalInput($request->input('fx_rate')) ?? 1),
            'subtotal' => (float) ($this->parseDecimalInput($request->input('subtotal')) ?? 0),
            'tax_amount' => (float) ($this->parseDecimalInput($request->input('tax_amount')) ?? 0),
            'discount_amount' => (float) ($this->parseDecimalInput($request->input('discount_amount')) ?? 0),
            'total_amount' => (float) ($this->parseDecimalInput($request->input('total_amount')) ?? 0),
        ]);

        $subtotal = (float) $request->input('subtotal', 0);
        $taxAmount = (float) $request->input('tax_amount', 0);
        $discountAmount = (float) $request->input('discount_amount', 0);
        $totalAmount = (float) $request->input('total_amount', 0);
        if ($totalAmount > 0 && abs($subtotal) < 0.00001 && abs($taxAmount) < 0.00001 && abs($discountAmount) < 0.00001) {
            $request->merge([
                'subtotal' => $totalAmount,
            ]);
        }

        if (! $request->filled('amount_base')) {
            $request->merge([
                'amount_base' => (float) $request->input('total_amount', 0) * (float) $request->input('fx_rate', 1),
            ]);
        }
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

    protected function suggestNextCreditNoteNumber(): string
    {
        $prefix = 'CN-'.now()->format('Ym').'-';
        $last = CreditNote::query()
            ->where('credit_note_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('credit_note_number');

        $next = 1;
        if ($last && preg_match('/-(\d+)$/', (string) $last, $matches)) {
            $next = (int) $matches[1] + 1;
        }

        return $prefix.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    public function renderAmount($row): string
    {
        $amount = number_format((float) ($row->total_amount ?? 0), 0);
        $currency = strtoupper(trim((string) ($row->currency_code ?? '')));
        if ($currency === '') {
            $currency = 'IRT';
        }

        return $amount . ' ' . e($currency);
    }

    public function renderCustomerName($row): string
    {
        $name = trim((string) ($row->customer_name ?? ''));
        if ($name === '') {
            return '<span class="text-muted">—</span>';
        }

        return e($name);
    }

    public function renderType($row): string
    {
        $types = [
            'return' => '<span class="badge bg-warning text-dark">برگشت کالا</span>',
            'discount' => '<span class="badge bg-success">تخفیف</span>',
            'correction' => '<span class="badge bg-info text-dark">اصلاح</span>',
        ];
        return $types[$row->credit_type] ?? $row->credit_type;
    }

    public function renderStatus($row): string
    {
        $badges = [
            'draft' => '<span class="badge bg-secondary">پیش‌نویس</span>',
            'issued' => '<span class="badge bg-primary">صادر شده</span>',
            'applied' => '<span class="badge bg-success">اعمال شده</span>',
            'refunded' => '<span class="badge bg-info text-dark">بازگشت وجه</span>',
            'void' => '<span class="badge bg-danger">باطل</span>',
        ];
        return $badges[$row->status] ?? $row->status;
    }

    public function renderRelatedInvoice($row): string
    {
        $ref = trim((string) ($row->reference_invoice_number ?? ''));
        $applied = trim((string) ($row->applied_invoice_number ?? ''));
        if ($ref === '' && $applied === '') {
            return '<span class="text-muted">—</span>';
        }
        $parts = [];
        if ($ref !== '') {
            $parts[] = '<span class="badge bg-light text-dark border me-1">مرجع: '.e($ref).'</span>';
        }
        if ($applied !== '') {
            $parts[] = '<span class="badge bg-light text-dark border">اعمال: '.e($applied).'</span>';
        }
        return implode('', $parts);
    }
}
