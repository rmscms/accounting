<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;
use RMS\Accounting\Http\Controllers\Admin\Concerns\RendersAccountingStructuredResourceForm;
use RMS\Accounting\Models\DebitNote;
use RMS\Accounting\Models\PurchaseOrder;
use RMS\Accounting\Models\Supplier;
use RMS\Accounting\Models\SupplierInvoice;
use RMS\Accounting\Services\SupplierInvoiceCorrectionService;
use RMS\Accounting\Services\DebitNoteService;
use RMS\Core\Contracts\Data\UseDatabase;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Data\Field;

class DebitNotesController extends AccountingAdminController implements HasList, HasForm, ShouldFilter
{
    use RendersAccountingStructuredResourceForm;

    protected DebitNoteService $debitNoteService;

    public function __construct(Filesystem $filesystem, DebitNoteService $debitNoteService)
    {
        parent::__construct($filesystem);
        $this->debitNoteService = $debitNoteService;
    }

    public function table(): string
    {
        return 'debit_notes';
    }

    public function modelName(): string
    {
        return DebitNote::class;
    }

    public function baseRoute(): string
    {
        return 'accounting.debit-notes';
    }

    public function routeParameter(): string
    {
        return 'debit_note';
    }

    /**
     * پس از ایجاد یا به‌روزرسانی، به ویرایش همان یادداشت برگردد تا مبلغ و گردش صدور/اعمال بدون رفتن به لیست انجام شود.
     */
    protected function getRedirectResponse(Request $request, int|string $id): RedirectResponse
    {
        return redirect()->route(
            $this->accountingNamedRoute('edit'),
            [$this->routeParameter() => $id]
        )->with('success', trans('admin.success_action'));
    }

    /**
     * join به suppliers برای نام تأمین‌کننده در لیست.
     */
    public function query(Builder $sql): void
    {
        $sql->leftJoin('suppliers', 'suppliers.id', '=', 'a.supplier_id')
            ->leftJoin('supplier_invoices as reference_invoice', 'reference_invoice.id', '=', 'a.supplier_invoice_id')
            ->leftJoin('supplier_invoices as applied_invoice', 'applied_invoice.id', '=', 'a.applied_to_invoice_id')
            ->addSelect(
                'a.*',
                'suppliers.name as supplier_name',
                'reference_invoice.invoice_number as reference_invoice_number',
                'applied_invoice.invoice_number as applied_invoice_number'
            );
    }

    /**
     * @return array<string, mixed>
     */
    protected function structuredAccountingFormExtraViewVariables(Request $request, bool $isEdit, ?Model $model): array
    {
        $prefillSupplierId = null;
        $prefillInvoiceId = null;
        $supplierSelectInitialText = null;
        $supplierInvoiceSelectInitialText = null;

        if (! $isEdit) {
            $raw = $request->query('supplier_id');
            if ($raw !== null && $raw !== '' && ctype_digit((string) $raw)) {
                $prefillSupplierId = (string) $raw;
                $supplier = Supplier::query()->find((int) $raw);
                if ($supplier) {
                    $supplierSelectInitialText = (string) $supplier->name;
                }
            }
            $rawInv = $request->query('supplier_invoice_id');
            if ($rawInv !== null && $rawInv !== '' && ctype_digit((string) $rawInv)) {
                $prefillInvoiceId = (string) $rawInv;
                $inv = SupplierInvoice::query()->find((int) $rawInv);
                if ($inv) {
                    $supplierInvoiceSelectInitialText = (string) $inv->invoice_number;
                    if (($prefillSupplierId === null || $prefillSupplierId === '') && (int) ($inv->supplier_id ?? 0) > 0) {
                        $prefillSupplierId = (string) $inv->supplier_id;
                        $supplier = Supplier::query()->find((int) $inv->supplier_id);
                        if ($supplier) {
                            $supplierSelectInitialText = (string) $supplier->name;
                        }
                    }
                }
            }

            $rawPo = $request->query('purchase_order_id');
            if ($rawPo !== null && $rawPo !== '' && ctype_digit((string) $rawPo)) {
                $po = PurchaseOrder::query()->find((int) $rawPo);
                if ($po) {
                    if (($prefillSupplierId === null || $prefillSupplierId === '') && (int) ($po->supplier_id ?? 0) > 0) {
                        $prefillSupplierId = (string) $po->supplier_id;
                        $supplier = Supplier::query()->find((int) $po->supplier_id);
                        if ($supplier) {
                            $supplierSelectInitialText = (string) $supplier->name;
                        }
                    }
                    if ($prefillInvoiceId === null || $prefillInvoiceId === '') {
                        $latestInv = SupplierInvoice::query()
                            ->where('purchase_order_id', (int) $rawPo)
                            ->whereNull('deleted_at')
                            ->orderByDesc('id')
                            ->first();
                        if ($latestInv) {
                            $prefillInvoiceId = (string) $latestInv->id;
                            $supplierInvoiceSelectInitialText = (string) $latestInv->invoice_number;
                        }
                    }
                }
            }
        } elseif ($model instanceof DebitNote) {
            $supplier = Supplier::query()->find((int) $model->supplier_id);
            if ($supplier) {
                $supplierSelectInitialText = (string) $supplier->name;
            }
            if ($model->supplier_invoice_id) {
                $inv = SupplierInvoice::query()->find((int) $model->supplier_invoice_id);
                if ($inv) {
                    $supplierInvoiceSelectInitialText = (string) $inv->invoice_number;
                }
            }
        }

        $magicInvoiceId = 2147483646;
        $debitNoteReferenceInvoicePreviewUrlTemplate = str_replace(
            (string) $magicInvoiceId,
            '__INVOICE_ID__',
            route('admin.accounting.supplier-invoices.debit-note-reference-preview', ['supplier_invoice' => $magicInvoiceId])
        );

        return [
            'debitNotePrefillSupplierId' => $prefillSupplierId,
            'debitNotePrefillInvoiceId' => $prefillInvoiceId,
            'supplierSelectInitialText' => $supplierSelectInitialText,
            'supplierInvoiceSelectInitialText' => $supplierInvoiceSelectInitialText,
            'debitNoteReferenceInvoicePreviewUrlTemplate' => $debitNoteReferenceInvoicePreviewUrlTemplate,
        ];
    }

    protected function afterConfigureStructuredAccountingFormView(Request $request, bool $isEdit, ?Model $model): void
    {
        if ($this->structuredAccountingFormSlug() !== 'debit_notes') {
            return;
        }
        $this->view->withJs('vendor/accounting/admin/js/accounting-ajax-supplier-widgets.js', true);
        $this->view->withJs('vendor/accounting/admin/js/debit-note-structured-form.js', true);
    }

    protected function afterStructuredAccountingFormPrepareForValidation(Request $request): void
    {
        if ($this->structuredAccountingFormSlug() !== 'debit_notes') {
            return;
        }
        $rawInv = $request->input('supplier_invoice_id');
        if ($rawInv === null || $rawInv === '' || (string) $rawInv === '0') {
            $request->merge(['supplier_invoice_id' => null]);
        } elseif (is_numeric($rawInv)) {
            $request->merge(['supplier_invoice_id' => (int) $rawInv]);
        }
    }

    /**
     * ثبت از طریق سرویس تا شمارهٔ یادداشت (`debit_note_number`) و فیلدهای پیش‌فرض درست پر شوند.
     */
    protected function performAdd(Request $request): RedirectResponse
    {
        $this->beforeAdd($request);
        if (! $this instanceof UseDatabase) {
            throw new \InvalidArgumentException('Controller must implement ' . UseDatabase::class);
        }
        $manualTotalRaw = trim((string) $request->input('total_amount', ''));
        $manualTotalAmount = $this->normalizeManualTotalAmount($manualTotalRaw);
        $manualTotalWasProvided = $manualTotalRaw !== '';

        $debitNote = $this->debitNoteService->createDebitNote([
            'supplier_id' => (int) $request->input('supplier_id'),
            'supplier_invoice_id' => $request->filled('supplier_invoice_id') ? (int) $request->input('supplier_invoice_id') : null,
            'debit_date' => $request->input('debit_date'),
            'debit_type' => (string) $request->input('debit_type'),
            'reason' => $request->input('reason'),
            'notes' => $request->input('notes'),
            'created_by_user_id' => \RMS\Accounting\Support\AuditActor::userId(),
        ]);

        if (! $manualTotalWasProvided && $manualTotalAmount <= 0) {
            $this->prefillDebitNoteItemsFromReferenceInvoiceIfApplicable($request, $debitNote);
        }

        $debitNote->refresh();
        if (! $debitNote->items()->exists() && $manualTotalAmount > 0) {
            $t = $manualTotalAmount;
            $debitNote->total_amount = $t;
            $debitNote->subtotal = $t;
            $debitNote->tax_amount = 0;
            $debitNote->discount_amount = 0;
            $debitNote->amount_base = $t * (float) ($debitNote->fx_rate ?? 1);
            $debitNote->saveQuietly();
        }

        $this->afterAdd($request, $debitNote->getKey(), $debitNote);
        if ((string) ($debitNote->debit_type ?? '') === DebitNote::TYPE_CORRECTION && (int) ($debitNote->supplier_invoice_id ?? 0) > 0) {
            app(SupplierInvoiceCorrectionService::class)->recordAdjustmentFromDebitNote($debitNote);
        }

        return $this->getRedirectResponse($request, $debitNote->getKey());
    }

    protected function afterUpdate(Request $request, int|string $id, Model $model): void
    {
        if (! $model instanceof DebitNote) {
            return;
        }

        $model->refresh();

        if (! $model->canBeEdited()) {
            return;
        }

        $manualTotalRaw = trim((string) $request->input('total_amount', ''));
        $manualTotalAmount = $this->normalizeManualTotalAmount($manualTotalRaw);
        if ($manualTotalRaw !== '' && $manualTotalAmount > 0) {
            $model->total_amount = $manualTotalAmount;
            $model->subtotal = $manualTotalAmount;
            $model->tax_amount = 0;
            $model->discount_amount = 0;
            $model->amount_base = $manualTotalAmount * (float) ($model->fx_rate ?? 1);
            $model->saveQuietly();

            return;
        }

        if ($model->items()->exists()) {
            $this->debitNoteService->recalculateTotals($model);

            return;
        }

        $total = (float) $model->total_amount;
        if ($total > 0) {
            $model->subtotal = $total;
            $model->tax_amount = 0;
            $model->discount_amount = 0;
            $model->amount_base = $total * (float) ($model->fx_rate ?? 1);
            $model->saveQuietly();
        }
    }

    public function getFieldsForm(): array
    {
        $invoiceSearchUrl = route('admin.accounting.supplier-invoices.search-invoices');

        $fields = [
            Field::number('supplier_id')
                ->withTitle(trans('accounting::accounting.supplier.name'))
                ->required()
                ->withAttributes(['structured_widget' => 'ajax_supplier_select']),
            Field::number('supplier_invoice_id')
                ->withTitle(trans('accounting::accounting.payment.supplier_invoice_id'))
                ->optional()
                ->withAttributes([
                    'structured_widget' => 'ajax_supplier_invoice_select',
                    'depends_on_field' => 'supplier_id',
                    'supplier_invoice_search_url' => $invoiceSearchUrl,
                ]),
            Field::select('debit_type', trans('accounting::accounting.common.type'))->setOptions([
                'return' => trans('accounting::accounting.debit_note_form.debit_type_return'),
                'discount' => trans('accounting::accounting.debit_note_form.debit_type_discount'),
                'correction' => trans('accounting::accounting.debit_note_form.debit_type_correction'),
            ])->withDefaultValue((string) request()->query('debit_type', DebitNote::TYPE_RETURN))->required(),
            Field::date('debit_date', trans('accounting::accounting.debit_note_form.debit_date'))->withDefaultValue(now()),
        ];

        $routeNote = $this->resolveCurrentDebitNoteFromRoute();
        $isDebitNoteEdit = $routeNote instanceof DebitNote && $routeNote->exists;

        if ($isDebitNoteEdit && $routeNote->canBeEdited()) {
            $totalField = Field::price('total_amount', trans('accounting::accounting.debit_note_form.total_amount'));
            if (! $routeNote->items()->exists()) {
                $totalField->required();
            } else {
                $totalField->optional();
            }
            $fields[] = $totalField;
        } elseif (! $isDebitNoteEdit) {
            $fields[] = Field::price('total_amount', trans('accounting::accounting.debit_note_form.total_amount'))
                ->optional();
        }

        $fields[] = Field::textarea('reason', trans('accounting::accounting.debit_note_form.reason'))->optional();
        $fields[] = Field::textarea('notes', trans('accounting::accounting.debit_note_form.notes'))->optional();

        return $fields;
    }

    public function getListFields(): array
    {
        return [
            Field::make('id', 'a.id')->withTitle(trans('accounting::accounting.common.id'))->sortable()->width('80px'),
            Field::make('debit_note_number')->withTitle(trans('accounting::accounting.common.number'))->searchable()->sortable(),
            Field::make('supplier_name', 'suppliers.name')
                ->withTitle(trans('accounting::accounting.supplier.name'))
                ->searchable()
                ->sortable(),
            Field::make('debit_type')->withTitle(trans('accounting::accounting.common.type'))->customMethod('renderType'),
            Field::make('supplier_invoice_id')
                ->withTitle(trans('accounting::accounting.debit_note_form.related_invoice'))
                ->customMethod('renderRelatedInvoice'),
            Field::make('total_amount')->withTitle(trans('accounting::accounting.payment.amount'))->customMethod('renderAmount')->sortable(),
            Field::make('status')->withTitle(trans('accounting::accounting.common.status'))->customMethod('renderStatus'),
            Field::date('debit_date')->withTitle(trans('accounting::accounting.debit_note_form.debit_date'))->sortable(),
        ];
    }

    public function rules(): array
    {
        $rules = [
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'supplier_invoice_id' => [
                'nullable',
                'integer',
                Rule::exists('supplier_invoices', 'id')->where(function ($q) {
                    $supplierId = request()->input('supplier_id');
                    if ($supplierId !== null && $supplierId !== '' && ctype_digit((string) $supplierId)) {
                        $q->where('supplier_id', (int) $supplierId);
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                }),
            ],
            'debit_type' => ['required', 'in:return,discount,correction'],
            'debit_date' => ['nullable', 'date'],
        ];

        $routeNote = request()->route('debit_note');
        if ($routeNote instanceof DebitNote && $routeNote->exists && $routeNote->canBeEdited() && ! $routeNote->items()->exists()) {
            $rules['total_amount'] = ['required', 'numeric', 'min:0.01'];
        } else {
            $rules['total_amount'] = ['nullable', 'numeric', 'min:0.01'];
        }

        return $rules;
    }

    protected function beforeAdd(Request &$request): void
    {
        $this->normalizeDebitNoteDateAndAmount($request);
    }

    protected function beforeUpdate(Request &$request, int|string $id): void
    {
        $this->normalizeDebitNoteDateAndAmount($request);
    }

    /**
     * برای یادداشت «برگشت» با فاکتور مرجع، اقلام فاکتور را به debit_note_items کپی می‌کند (قابل ویرایش قبل از صدور).
     */
    protected function prefillDebitNoteItemsFromReferenceInvoiceIfApplicable(Request $request, DebitNote $debitNote): void
    {
        // فقط با opt-in صریح prefill انجام شود تا مبلغ دستی کاربر override نشود.
        if ((string) $request->query('prefill_invoice_items', '0') !== '1') {
            return;
        }
        if (! config('accounting.purchases.debit_note_prefill_from_invoice', true)) {
            return;
        }
        if ((string) $debitNote->debit_type !== DebitNote::TYPE_RETURN) {
            return;
        }
        $invoiceId = (int) ($debitNote->supplier_invoice_id ?? 0);
        if ($invoiceId <= 0) {
            return;
        }
        $invoice = SupplierInvoice::query()->with(['items' => static fn ($q) => $q->orderBy('id')])->find($invoiceId);
        if (! $invoice || (int) $invoice->supplier_id !== (int) $debitNote->supplier_id) {
            return;
        }
        if ($invoice->items->isEmpty()) {
            return;
        }
        foreach ($invoice->items as $line) {
            $this->debitNoteService->addItem($debitNote, [
                'product_id' => $line->product_id,
                'product_sku' => $line->product_sku,
                'product_name' => $line->product_name ?: '—',
                'quantity' => $line->quantity,
                'price' => $line->unit_price,
                'tax_rate' => $line->tax_rate ?? 0,
                'discount_amount' => $line->discount_amount ?? 0,
            ]);
        }
        $this->debitNoteService->recalculateTotals($debitNote->fresh());
    }

    public function issue(Request $request, $id)
    {
        $debitNote = DebitNote::query()->findOrFail($id);

        if (! $debitNote->isDraft()) {
            return redirect()->back()->withErrors([
                'issue' => trans('accounting::accounting.debit_note_form.issue_not_draft'),
            ]);
        }

        if ((float) $debitNote->total_amount <= 0) {
            return redirect()->back()->withErrors([
                'issue' => trans('accounting::accounting.debit_note_form.issue_requires_amount'),
            ]);
        }

        try {
            $this->debitNoteService->issueDebitNote($debitNote);
        } catch (Throwable $e) {
            return redirect()->back()->withErrors([
                'issue' => trans('accounting::accounting.debit_note_form.issue_failed').' — '.$e->getMessage(),
            ]);
        }

        return redirect()->back()->with('success', trans('accounting::accounting.debit_note_form.issue_success'));
    }

    public function apply(Request $request, $id)
    {
        $debitNote = DebitNote::query()->findOrFail($id);

        $fromInput = $request->input('invoice_id');
        $resolvedInvoiceId = null;
        if ($fromInput !== null && $fromInput !== '' && (string) $fromInput !== '0' && ctype_digit((string) $fromInput)) {
            $resolvedInvoiceId = (int) $fromInput;
        } elseif ($debitNote->supplier_invoice_id) {
            $resolvedInvoiceId = (int) $debitNote->supplier_invoice_id;
        }

        if ($resolvedInvoiceId === null || $resolvedInvoiceId <= 0) {
            return redirect()->back()->withErrors([
                'invoice_id' => trans('accounting::accounting.debit_note_form.apply_requires_invoice'),
            ]);
        }

        $request->merge(['invoice_id' => $resolvedInvoiceId]);

        $validated = $request->validate([
            'invoice_id' => [
                'required',
                'integer',
                Rule::exists('supplier_invoices', 'id')->where(function ($q) use ($debitNote) {
                    $q->where('supplier_id', $debitNote->supplier_id);
                }),
            ],
        ]);

        try {
            $this->debitNoteService->applyToInvoice($debitNote, (int) $validated['invoice_id']);
        } catch (Throwable $e) {
            return redirect()->back()->withErrors([
                'apply' => trans('accounting::accounting.debit_note_form.apply_failed').' — '.$e->getMessage(),
            ]);
        }

        return redirect()->back()->with('success', trans('accounting::accounting.debit_note_form.apply_success'));
    }

    public function renderAmount($row): string
    {
        return number_format($row->total_amount) . ' تومان';
    }

    public function renderType($row): string
    {
        $types = [
            DebitNote::TYPE_RETURN => '<span class="badge bg-warning text-dark">'.e(trans('accounting::accounting.debit_note_form.debit_type_return')).'</span>',
            DebitNote::TYPE_DISCOUNT => '<span class="badge bg-success">'.e(trans('accounting::accounting.debit_note_form.debit_type_discount')).'</span>',
            DebitNote::TYPE_CORRECTION => '<span class="badge bg-info text-dark">'.e(trans('accounting::accounting.debit_note_form.debit_type_correction')).'</span>',
        ];

        return $types[$row->debit_type] ?? '<span class="badge bg-light text-body">'.e((string) $row->debit_type).'</span>';
    }

    public function renderStatus($row): string
    {
        $badges = [
            DebitNote::STATUS_DRAFT => '<span class="badge bg-secondary">'.e(trans('accounting::accounting.debit_note_form.list_status.draft')).'</span>',
            DebitNote::STATUS_ISSUED => '<span class="badge bg-primary">'.e(trans('accounting::accounting.debit_note_form.list_status.issued')).'</span>',
            DebitNote::STATUS_APPLIED => '<span class="badge bg-success">'.e(trans('accounting::accounting.debit_note_form.list_status.applied')).'</span>',
            DebitNote::STATUS_VOID => '<span class="badge bg-danger">'.e(trans('accounting::accounting.debit_note_form.list_status.void')).'</span>',
        ];

        return $badges[$row->status] ?? '<span class="badge bg-light text-body">'.e((string) $row->status).'</span>';
    }

    public function renderRelatedInvoice($row): string
    {
        $parts = [];
        if (! empty($row->reference_invoice_number)) {
            $parts[] = '<span class="badge bg-light text-body me-1">'.e(trans('accounting::accounting.debit_note_form.reference_invoice_short')).': '.e((string) $row->reference_invoice_number).'</span>';
        }
        if (! empty($row->applied_invoice_number)) {
            $parts[] = '<span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">'.e(trans('accounting::accounting.debit_note_form.applied_invoice_short')).': '.e((string) $row->applied_invoice_number).'</span>';
        }

        if ($parts === []) {
            return '<span class="text-muted">'.e(trans('accounting::accounting.debit_note_form.related_invoice_none')).'</span>';
        }

        return implode('', $parts);
    }

    protected function normalizeManualTotalAmount(mixed $rawAmount): float
    {
        if ($rawAmount === null) {
            return 0.0;
        }

        $value = trim((string) $rawAmount);
        if ($value === '') {
            return 0.0;
        }

        if (function_exists('\RMS\Helper\changeNumberToEn')) {
            $value = (string) \RMS\Helper\changeNumberToEn($value);
        }

        $value = str_replace(',', '', $value);

        return is_numeric($value) ? (float) $value : 0.0;
    }

    protected function normalizeDebitNoteDateAndAmount(Request $request): void
    {
        if (! $request->filled('debit_date')) {
            $request->merge(['debit_date' => now()->toDateString()]);
        }

        if ($request->exists('total_amount')) {
            $request->merge([
                'total_amount' => $this->normalizeManualTotalAmount($request->input('total_amount')),
            ]);
        }
    }

    protected function resolveCurrentDebitNoteFromRoute(): ?DebitNote
    {
        $routeValue = request()->route($this->routeParameter());
        if ($routeValue instanceof DebitNote) {
            return $routeValue;
        }

        if (is_numeric($routeValue) && (int) $routeValue > 0) {
            return DebitNote::query()->find((int) $routeValue);
        }

        $fallbackId = request()->route('id');
        if (is_numeric($fallbackId) && (int) $fallbackId > 0) {
            return DebitNote::query()->find((int) $fallbackId);
        }

        return null;
    }
}
