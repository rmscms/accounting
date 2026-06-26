<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use RMS\Accounting\Http\Controllers\Admin\Concerns\RendersAccountingStructuredResourceForm;
use RMS\Accounting\Models\CreditNote;
use RMS\Accounting\Models\Customer;
use RMS\Accounting\Models\CustomerInvoice;
use RMS\Accounting\Models\CustomerRefund;
use RMS\Accounting\Services\RefundService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RMS\Core\Data\Field;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;

class CustomerRefundsController extends AccountingAdminController implements HasList, HasForm, ShouldFilter
{
    use RendersAccountingStructuredResourceForm;

    protected RefundService $refundService;

    public function __construct(Filesystem $filesystem, RefundService $refundService)
    {
        parent::__construct($filesystem);
        $this->refundService = $refundService;
    }

    public function table(): string
    {
        return 'customer_refunds';
    }

    public function modelName(): string
    {
        return CustomerRefund::class;
    }

    public function baseRoute(): string
    {
        return 'accounting.customer-refunds';
    }

    public function routeParameter(): string
    {
        return 'customer_refund';
    }

    public function query(Builder $sql): void
    {
        $sql->leftJoin('customers', 'customers.id', '=', 'a.customer_id')
            ->addSelect(
                'a.*',
                'customers.name as customer_name'
            );
    }

    public function getFieldsForm(): array
    {
        $queryInvoiceId = request()->query('customer_invoice_id');
        $queryCustomerId = request()->query('customer_id');
        $queryAmount = request()->query('amount');

        $prefillInvoiceId = ($queryInvoiceId !== null && $queryInvoiceId !== '' && ctype_digit((string) $queryInvoiceId))
            ? (string) (int) $queryInvoiceId
            : '';
        $prefillCustomerId = ($queryCustomerId !== null && $queryCustomerId !== '' && ctype_digit((string) $queryCustomerId))
            ? (string) (int) $queryCustomerId
            : '';
        $prefillAmount = (is_scalar($queryAmount) && trim((string) $queryAmount) !== '')
            ? (string) $queryAmount
            : '';

        return [
            Field::select('customer_id', 'مشتری')->setOptions($this->getCustomerOptions())->required()->withDefaultValue($prefillCustomerId),
            Field::hidden('customer_invoice_id', $prefillInvoiceId)->skipDatabase(),
            Field::select('credit_note_id', 'اعتبار برگشتی')->setOptions($this->getCreditNoteOptions())->optional(),
            Field::number('amount', 'مبلغ')->required(),
            Field::select('refund_method', 'روش بازگشت')->setOptions([
                'cash' => 'نقدی',
                'bank_transfer' => 'انتقال بانکی',
                'cheque' => 'چک',
                'online' => 'آنلاین',
                'deduct_from_next_invoice' => 'کسر از فاکتور بعدی',
            ])->required(),
            Field::date('refund_date', 'تاریخ بازگشت')->withDefaultValue(now()->toDateString())->optional(),
            Field::textarea('reason', 'دلیل')->optional(),
            Field::textarea('notes', 'یادداشت')->optional(),
            Field::hidden('refund_number', ''),
            Field::hidden('status', CustomerRefund::STATUS_PENDING),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle('شناسه')->sortable()->width('80px'),
            Field::make('refund_number')->withTitle('شماره')->searchable()->sortable(),
            Field::make('customer_name', 'customers.name')->withTitle('مشتری')->searchable(),
            Field::make('amount')->withTitle('مبلغ')->customMethod('renderAmount')->sortable(),
            Field::make('refund_method')->withTitle('روش')->customMethod('renderMethod'),
            Field::make('status')->withTitle('وضعیت')->customMethod('renderStatus'),
            Field::date('refund_date')->withTitle('تاریخ')->sortable(),
        ];
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'customer_invoice_id' => ['nullable', 'integer', 'exists:customer_invoices,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'refund_method' => ['required', 'in:cash,bank_transfer,cheque,online,deduct_from_next_invoice'],
            'refund_date' => ['nullable', 'date'],
            'credit_note_id' => [
                'nullable',
                'integer',
                Rule::exists('credit_notes', 'id')->where(function ($q) {
                    $cid = request()->input('customer_id');
                    if ($cid !== null && $cid !== '' && ctype_digit((string) $cid)) {
                        $q->where('customer_id', (int) $cid);
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                }),
            ],
        ];
    }

    protected function beforeAdd(Request &$request): void
    {
        $this->normalizeCustomerRefundRequest($request);
    }

    protected function beforeUpdate(Request &$request, int|string $id): void
    {
        $this->normalizeCustomerRefundRequest($request);
    }

    protected function afterAdd(Request $request, int|string $id, Model $model): void
    {
        if (! $model instanceof CustomerRefund) {
            return;
        }

        DB::transaction(function () use ($model): void {
            $this->refundService->ensureCustomerLedgerPosted($model);
        });
    }

    protected function afterUpdate(Request $request, int|string $id, Model $model): void
    {
        if (! $model instanceof CustomerRefund) {
            return;
        }

        DB::transaction(function () use ($model): void {
            $this->refundService->ensureCustomerLedgerPosted($model);
        });
    }

    protected function getCustomerOptions(): array
    {
        return Customer::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->toArray();
    }

    protected function getCreditNoteOptions(): array
    {
        $customerId = request()->input('customer_id');
        $query = CreditNote::query()
            ->whereIn('status', [CreditNote::STATUS_ISSUED, CreditNote::STATUS_APPLIED])
            ->orderByDesc('id');
        if ($customerId !== null && $customerId !== '' && ctype_digit((string) $customerId)) {
            $query->where('customer_id', (int) $customerId);
        }

        return $query->limit(100)->pluck('credit_note_number', 'id')->toArray();
    }

    public function renderAmount($row): string
    {
        return number_format($row->amount) . ' تومان';
    }

    public function renderMethod($row): string
    {
        $methods = [
            'cash' => '<span class="badge bg-success">نقدی</span>',
            'bank_transfer' => '<span class="badge bg-primary">انتقال بانکی</span>',
            'cheque' => '<span class="badge bg-info text-dark">چک</span>',
            'online' => '<span class="badge bg-secondary">آنلاین</span>',
            'deduct_from_next_invoice' => '<span class="badge bg-warning text-dark">کسر از فاکتور بعدی</span>',
        ];
        return $methods[$row->refund_method] ?? $row->refund_method;
    }

    public function renderStatus($row): string
    {
        $badges = [
            'pending' => '<span class="badge bg-warning text-dark">در انتظار</span>',
            'processed' => '<span class="badge bg-success">پردازش شده</span>',
            'cancelled' => '<span class="badge bg-danger">لغو شده</span>',
        ];
        return $badges[$row->status] ?? $row->status;
    }

    protected function normalizeCustomerRefundRequest(Request $request): void
    {
        if (! $request->filled('refund_date')) {
            $request->merge(['refund_date' => now()->toDateString()]);
        }
        if (! $request->filled('refund_number')) {
            $request->merge([
                'refund_number' => sprintf('CRF-%s-%06d', now()->format('Ym'), random_int(1, 999999)),
            ]);
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
        if (! $request->filled('status')) {
            $request->merge(['status' => CustomerRefund::STATUS_PENDING]);
        }

        $amount = (float) ($this->parseDecimalInput($request->input('amount')) ?? 0);
        $fxRate = (float) ($this->parseDecimalInput($request->input('fx_rate')) ?? 1);
        $request->merge([
            'amount' => $amount,
            'fx_rate' => $fxRate > 0 ? $fxRate : 1,
            'amount_base' => $amount * ($fxRate > 0 ? $fxRate : 1),
        ]);

        $invoiceId = (int) $request->input('customer_invoice_id', 0);
        if ($invoiceId > 0 && ! $request->filled('credit_note_id')) {
            $creditNote = CreditNote::query()
                ->where('customer_id', (int) $request->input('customer_id'))
                ->where('applied_to_invoice_id', $invoiceId)
                ->whereIn('status', [CreditNote::STATUS_ISSUED, CreditNote::STATUS_APPLIED])
                ->orderByDesc('id')
                ->first();
            if ($creditNote) {
                $request->merge(['credit_note_id' => (int) $creditNote->id]);
            }
        }

        if ($request->filled('credit_note_id') && $invoiceId > 0) {
            $creditNote = CreditNote::query()->find((int) $request->input('credit_note_id'));
            if ($creditNote && (int) ($creditNote->applied_to_invoice_id ?? 0) > 0 && (int) $creditNote->applied_to_invoice_id !== $invoiceId) {
                throw ValidationException::withMessages([
                    'credit_note_id' => (string) trans('accounting::accounting.customer_refund_form.invoice_credit_mismatch'),
                ]);
            }
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
}
