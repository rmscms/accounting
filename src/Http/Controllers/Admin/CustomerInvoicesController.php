<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use RMS\Accounting\Http\Controllers\Admin\Concerns\RendersAccountingStructuredResourceForm;
use RMS\Accounting\Models\Currency;
use RMS\Accounting\Models\Customer;
use RMS\Accounting\Models\CustomerInvoice;
use RMS\Accounting\Models\PaymentMethod;
use RMS\Accounting\Models\Wallet;
use RMS\Accounting\Services\ChequeAutoCreationService;
use RMS\Accounting\Services\CustomerInvoiceCorrectionService;
use RMS\Accounting\Services\CustomerInvoiceService;
use RMS\Accounting\Services\CurrencyService;
use RMS\Accounting\Services\PartyService;
use RMS\Accounting\Services\PaymentDestinationCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RMS\Core\Data\Field;
use RMS\Core\Models\Setting;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;

class CustomerInvoicesController extends AccountingAdminController implements HasList, HasForm, ShouldFilter
{
    use RendersAccountingStructuredResourceForm;

    public function table(): string
    {
        return 'customer_invoices';
    }

    public function modelName(): string
    {
        return CustomerInvoice::class;
    }

    public function baseRoute(): string
    {
        return 'accounting.customer-invoices';
    }

    public function routeParameter(): string
    {
        return 'customer_invoice';
    }

    protected function getRedirectResponse(Request $request, int|string $id): RedirectResponse
    {
        return redirect()->route(
            $this->accountingNamedRoute('edit'),
            [$this->routeParameter() => $id]
        )->with('success', trans('admin.success_action'));
    }

    /**
     * اضافه کردن join به customers برای نمایش نام مشتری
     */
    public function query(Builder $sql): void
    {
        $sql->leftJoin('customers', 'customers.id', '=', 'a.customer_id')
            ->addSelect(
                'a.*',
                'customers.name as customer_name',
                'customers.phone as customer_phone'
            );
    }

    public function getFieldsForm(): array
    {
        $tail = [
            Field::hidden('discount_amount', 0),
            Field::hidden('status', CustomerInvoice::STATUS_DRAFT),
        ];

        $base = [
            Field::select('settlement_mode', trans('accounting::accounting.customer_invoice.settlement_mode'))
                ->setOptions([
                    CustomerInvoice::SETTLEMENT_CREDIT => trans('accounting::accounting.customer_invoice.settlement_credit'),
                    CustomerInvoice::SETTLEMENT_CASH => trans('accounting::accounting.customer_invoice.settlement_cash'),
                    CustomerInvoice::SETTLEMENT_MIXED => trans('accounting::accounting.customer_invoice.settlement_mixed'),
                ])
                ->withDefaultValue(CustomerInvoice::SETTLEMENT_CREDIT)
                ->required(),
            Field::number('paid_at_source_destination', trans('accounting::accounting.customer_invoice.settlement_destination'))
                ->optional()
                ->withAttributes([
                    'structured_widget' => 'payment_destination_picker',
                    'payment_destination_context' => PaymentDestinationCatalog::CONTEXT_CUSTOMER_PAYMENT,
                    'pdp_name_prefix' => 'paid_at_source_',
                    'wrap_settlement_destination' => true,
                ]),
            Field::string('invoice_number', trans('accounting::accounting.invoice.invoice_number'))->required(),
            Field::date('invoice_date', trans('accounting::accounting.invoice.invoice_date'))
                ->withDefaultValue(now()->toDateString())
                ->optional(),
            Field::number('customer_id', trans('accounting::accounting.invoice.customer_id'))
                ->required()
                ->withAttributes(['structured_widget' => 'customer_payment_customer_picker']),
            Field::number('upfront_payment_amount', trans('accounting::accounting.customer_invoice.upfront_payment_amount'))->withDefaultValue(0),
            Field::number('total_amount', trans('accounting::accounting.invoice.total_amount'))
                ->withDefaultValue(0)
                ->optional(),
        ];

        return array_merge($base, [
            Field::hidden('tax_method', function_exists('tax_calculation_method') ? tax_calculation_method() : 'exclusive'),
            Field::hidden('subtotal', 0),
            Field::hidden('tax_amount', 0),
        ], $tail);
    }

    /**
     * @return array<int, string>
     */
    protected function hiddenSalesFormFields(): array
    {
        $raw = Setting::get('accounting.package_forms.sales.hidden_fields', '[]');
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, static fn ($value) => is_string($value) && $value !== ''));
    }

    public function getListFields(): array
    {
        return [
            Field::make('id', 'a.id')->withTitle(trans('accounting::accounting.common.id'))->sortable()->width('80px'),
            Field::make('invoice_number')->withTitle(trans('accounting::accounting.invoice.invoice_number'))->searchable()->sortable()->width('150px'),
            Field::make('invoice_date')->withTitle(trans('accounting::accounting.invoice.invoice_date'))->sortable()->width('120px'),
            
            // نمایش نام مشتری با customMethod
            Field::make('customer_name', 'customers.name')
                ->withTitle(trans('accounting::accounting.invoice.customer_id'))
                ->customMethod('renderCustomerName')
                ->searchable()
                ->width('200px'),
            
            // نمایش مبلغ با فرمت
            Field::make('total_amount')
                ->withTitle(trans('accounting::accounting.invoice.total_amount'))
                ->customMethod('renderAmount')
                ->sortable()
                ->width('150px'),
            
            // نمایش وضعیت با badge
            Field::make('status')
                ->withTitle(trans('accounting::accounting.invoice.status'))
                ->customMethod('renderStatusBadge')
                ->sortable()
                ->width('120px'),
            Field::make('payment_status')
                ->withTitle(trans('accounting::accounting.invoice.payment_status'))
                ->customMethod('renderPaymentStatusBadge')
                ->sortable()
                ->width('120px'),
            Field::make('list_payment_action')
                ->withTitle(trans('accounting::accounting.supplier_invoice.list_payment_column'))
                ->customMethod('renderInvoiceListPaymentLink')
                ->skipDatabase()
                ->width('140px'),
            
            Field::make('created_at')->withTitle(trans('accounting::accounting.common.created_at'))->sortable()->width('150px'),
        ];
    }

    public function filters(): array
    {
        return [
            Field::select('status', trans('accounting::accounting.invoice.status'))
                ->setOptions([
                    '' => trans('accounting::accounting.common.all'),
                    'draft' => trans('accounting::accounting.statuses.draft'),
                    'issued' => trans('accounting::accounting.statuses.issued'),
                    'cancelled' => trans('accounting::accounting.statuses.cancelled'),
                    'void' => trans('accounting::accounting.statuses.void'),
                ]),
            Field::select('payment_status', trans('accounting::accounting.invoice.payment_status'))
                ->setOptions([
                    '' => trans('accounting::accounting.common.all'),
                    'unpaid' => trans('accounting::accounting.statuses.unpaid'),
                    'partially_paid' => trans('accounting::accounting.statuses.partially_paid'),
                    'paid' => trans('accounting::accounting.statuses.paid'),
                ]),
        ];
    }

    public function rules(): array
    {
        $id = request()->route($this->routeParameter());

        $rules = [
            'settlement_mode' => ['required', 'string', 'in:'.CustomerInvoice::SETTLEMENT_CREDIT.','.CustomerInvoice::SETTLEMENT_CASH.','.CustomerInvoice::SETTLEMENT_MIXED],
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
            'upfront_payment_amount' => ['nullable', 'numeric', 'min:0'],
            'invoice_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('customer_invoices', 'invoice_number')->ignore($id),
            ],
            'invoice_date' => ['nullable'],
            'due_date' => ['nullable'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:draft,issued,cancelled,void'],
            'tax_method' => ['nullable', 'string', 'in:inclusive,exclusive'],
        ];

        $rules['tax_amount'] = ['nullable', 'numeric', 'min:0'];
        $rules['subtotal'] = ['nullable', 'numeric', 'min:0'];

        return $rules;
    }

    /**
     * نمایش نام مشتری با اطلاعات تماس
     */
    public function renderCustomerName($row): string
    {
        if (!$row->customer_id) {
            return '<span class="text-muted">-</span>';
        }
        
        $name = e($row->customer_name ?: '#'.$row->customer_id);
        $phone = $row->customer_phone ? '<br><small class="text-muted">'.e($row->customer_phone).'</small>' : '';
        
        return '<div>'.$name.$phone.'</div>';
    }

    /**
     * نمایش مبلغ با فرمت
     */
    public function renderAmount($row): string
    {
        $amount = number_format((float)$row->total_amount);
        return '<span class="fw-semibold">'.$amount.' <small class="text-muted">تومان</small></span>';
    }

    /**
     * نمایش وضعیت با badge رنگی
     */
    public function renderStatusBadge($row): string
    {
        $statusMap = [
            'draft' => [
                'label' => trans('accounting::accounting.statuses.draft'),
                'class' => 'bg-secondary'
            ],
            'issued' => [
                'label' => trans('accounting::accounting.statuses.issued'),
                'class' => 'bg-primary'
            ],
            'cancelled' => [
                'label' => trans('accounting::accounting.statuses.cancelled'),
                'class' => 'bg-danger'
            ],
            'void' => [
                'label' => trans('accounting::accounting.statuses.void'),
                'class' => 'bg-dark'
            ],
        ];

        $status = (string)($row->status ?? 'draft');
        $info = $statusMap[$status] ?? ['label' => $status, 'class' => 'bg-secondary'];
        $label = e($info['label']);
        $class = $info['class'];

        return '<span class="badge '.$class.'">'.$label.'</span>';
    }

    public function renderPaymentStatusBadge($row): string
    {
        $status = (string) ($row->payment_status ?? CustomerInvoice::STATUS_UNPAID);
        $map = [
            CustomerInvoice::STATUS_UNPAID => ['class' => 'bg-warning text-dark', 'label' => trans('accounting::accounting.statuses.unpaid')],
            CustomerInvoice::STATUS_PARTIALLY_PAID => ['class' => 'bg-info text-dark', 'label' => trans('accounting::accounting.statuses.partially_paid')],
            CustomerInvoice::STATUS_PAID => ['class' => 'bg-success', 'label' => trans('accounting::accounting.statuses.paid')],
        ];
        $info = $map[$status] ?? ['class' => 'bg-secondary', 'label' => $status];

        return '<span class="badge '.$info['class'].'">'.e((string) $info['label']).'</span>';
    }

    public function renderInvoiceListPaymentLink($row): string
    {
        $status = (string) ($row->payment_status ?? CustomerInvoice::STATUS_UNPAID);
        if ($status === CustomerInvoice::STATUS_PAID) {
            return '<span class="text-muted small">'.e(trans('accounting::accounting.supplier_invoice.list_paid_short')).'</span>';
        }

        $invoiceId = (int) ($row->id ?? 0);
        $customerId = (int) ($row->customer_id ?? 0);
        if ($invoiceId <= 0 || $customerId <= 0) {
            return '<span class="text-muted">—</span>';
        }
        $query = [
            'customer_invoice_id' => (string) $invoiceId,
            'customer_id' => (string) $customerId,
        ];
        $balanceDue = (float) ($row->balance_due ?? 0);
        if ($balanceDue > 0) {
            $query['amount'] = rtrim(rtrim(number_format($balanceDue, 4, '.', ''), '0'), '.');
        }
        $url = route('admin.accounting.customer-payments.create', $query);

        return '<a href="'.e($url).'" class="btn btn-sm btn-outline-success"><i class="ph-currency-circle-dollar me-1"></i>'
            .e(trans('accounting::accounting.supplier_invoice.list_pay_cta')).'</a>';
    }

    /**
     * جستجوی مشتری برای Select2 (فرم فاکتور فروش).
     */
    public function searchCustomers(Request $request): JsonResponse
    {
        $term = trim((string) $request->get('q', ''));
        $limit = max(5, min((int) $request->get('limit', 20), 50));

        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        $query = Customer::query();
        if (Schema::hasColumn('customers', 'active')) {
            $query->where('customers.active', true);
        }

        $query->where(function ($q) use ($term) {
            $q->where('customers.name', 'like', '%'.$term.'%')
                ->orWhere('customers.phone', 'like', '%'.$term.'%');
            if (Schema::hasColumn('customers', 'email')) {
                $q->orWhere('customers.email', 'like', '%'.$term.'%');
            }
        });

        $customers = $query->orderBy('customers.name')->limit($limit)->get();

        $results = $customers->map(static function (Customer $c) {
            $phone = $c->phone ? ' — '.$c->phone : '';

            return [
                'id' => (string) $c->id,
                'text' => (string) $c->name.$phone,
                'entity_type' => 'customer',
                'entity_type_label' => (string) trans('accounting::accounting.supplier.party_badge_customer'),
            ];
        })->values()->all();

        return response()->json(['results' => $results]);
    }

    /**
     * ایجاد سریع مشتری از داخل مودال فرم فروش (AJAX).
     */
    public function quickCreateCustomer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:regular,vip,occasional'],
            'national_code' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'default_currency_code' => ['nullable', 'string', 'size:3', 'exists:currencies,code'],
            'active' => ['nullable', 'boolean'],
        ]);

        if (! array_key_exists('credit_limit', $validated) || $validated['credit_limit'] === null || $validated['credit_limit'] === '') {
            $validated['credit_limit'] = 0;
        }
        $validated['active'] = (bool) ($validated['active'] ?? true);
        $validated['default_currency_code'] = $this->normalizeDefaultCurrencyCode($validated['default_currency_code'] ?? null);

        $customer = app(PartyService::class)->createOrLinkCustomer($validated)->fresh();

        $label = (string) $customer->name;
        if (! empty($customer->phone)) {
            $label .= ' — '.(string) $customer->phone;
        }

        return response()->json([
            'ok' => true,
            'id' => (string) $customer->getKey(),
            'text' => $label,
            'customer' => [
                'id' => (int) $customer->getKey(),
                'name' => (string) $customer->name,
                'phone' => (string) ($customer->phone ?? ''),
                'type' => (string) ($customer->type ?? 'regular'),
                'active' => (bool) ($customer->active ?? true),
                'credit_limit' => (string) ($customer->credit_limit ?? '0'),
                'default_currency_code' => (string) ($customer->default_currency_code ?? ''),
            ],
        ]);
    }

    /**
     * یکتایی شماره فاکتور فروش (blur قبل از submit).
     */
    public function checkInvoiceNumber(Request $request): JsonResponse
    {
        $number = trim((string) $request->query('number', ''));
        if ($number === '') {
            return response()->json([
                'available' => false,
                'message' => (string) trans('accounting::accounting.invoice.invoice_number_required'),
            ]);
        }

        $excludeId = (int) $request->query('exclude_id', 0);
        $q = CustomerInvoice::query()->where('invoice_number', $number);
        if ($excludeId > 0) {
            $q->where('id', '!=', $excludeId);
        }

        $available = ! $q->exists();

        return response()->json([
            'available' => $available,
            'message' => $available ? '' : (string) trans('accounting::accounting.invoice.invoice_number_taken'),
        ]);
    }

    protected function afterStructuredAccountingFormPrepareForValidation(Request $request): void
    {
        $this->normalizeDecimalRequestValues($request, [
            'total_amount',
            'upfront_payment_amount',
            'subtotal',
            'tax_amount',
            'discount_amount',
        ]);

        // amount-formatter ممکن است رشتهٔ خالی بفرستد؛ در فروش، خالی را 0 می‌گیریم تا خطای required بی‌مورد رخ ندهد.
        if (! $request->filled('total_amount')) {
            $request->merge(['total_amount' => 0]);
        }
        if (! $request->filled('upfront_payment_amount')) {
            $request->merge(['upfront_payment_amount' => 0]);
        }

        if (! $request->filled('settlement_mode')) {
            $request->merge(['settlement_mode' => CustomerInvoice::SETTLEMENT_CREDIT]);
        }
        $mode = (string) $request->input('settlement_mode', CustomerInvoice::SETTLEMENT_CREDIT);
        if (! in_array($mode, [CustomerInvoice::SETTLEMENT_CREDIT, CustomerInvoice::SETTLEMENT_CASH, CustomerInvoice::SETTLEMENT_MIXED], true)) {
            $request->merge(['settlement_mode' => CustomerInvoice::SETTLEMENT_CREDIT]);
            $mode = CustomerInvoice::SETTLEMENT_CREDIT;
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
            $customer = Customer::query()->find((int) $request->input('customer_id', 0));
            $autoCheque = app(ChequeAutoCreationService::class)->ensureCheque([
                'context' => 'customer_invoice_paid_at_source',
                'source_short' => 'CI',
                'payment_method_id' => $pm,
                'cheque_type' => \RMS\Accounting\Models\Cheque::TYPE_RECEIVED,
                'party_id' => (int) ($customer?->party_id ?? 0),
                'amount' => (float) ($request->input('upfront_payment_amount') ?? $request->input('total_amount') ?? 0),
                'currency_code' => (string) ($request->input('currency_code') ?: 'IRT'),
                'issue_date' => (string) ($request->input('invoice_date') ?: now()->toDateString()),
                'due_date' => (string) ($request->input('due_date') ?: $request->input('invoice_date') ?: now()->toDateString()),
                'notes' => (string) ($request->input('notes') ?: ''),
            ]);
            if ($autoCheque) {
                $request->merge(['paid_at_source_cheque_id' => (int) $autoCheque->id]);
            }
        }

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
        $request->merge([
            'paid_at_source_bank_id' => $bid > 0 ? $bid : null,
            'paid_at_source_cash_box_id' => $cid > 0 ? $cid : null,
            'paid_at_source_cheque_id' => $request->filled('paid_at_source_cheque_id') ? (int) $request->input('paid_at_source_cheque_id') : null,
            'paid_at_source_wallet_id' => $wid > 0 ? $wid : null,
        ]);

        $total = (float) ($this->parseDecimalInput($request->input('total_amount')) ?? 0);
        $upfront = (float) ($this->parseDecimalInput($request->input('upfront_payment_amount')) ?? 0);
        if ($mode === CustomerInvoice::SETTLEMENT_CASH) {
            $upfront = $total;
            if ($bid <= 0 && $cid <= 0 && $wid <= 0) {
                throw ValidationException::withMessages([
                    'paid_at_source_bank_id' => (string) trans('accounting::accounting.customer_invoice.settlement_paid_at_source_required'),
                ]);
            }
        } elseif ($mode === CustomerInvoice::SETTLEMENT_CREDIT) {
            $upfront = 0;
            $request->merge([
                'paid_at_source_bank_id' => null,
                'paid_at_source_cash_box_id' => null,
                'paid_at_source_cheque_id' => null,
                'paid_at_source_wallet_id' => null,
            ]);
        } else {
            $upfront = max(0, min($upfront, $total));
            if ($upfront > 0 && $bid <= 0 && $cid <= 0 && $wid <= 0) {
                throw ValidationException::withMessages([
                    'paid_at_source_bank_id' => (string) trans('accounting::accounting.customer_invoice.settlement_paid_at_source_required'),
                ]);
            }
            if ($upfront <= 0) {
                $request->merge([
                    'paid_at_source_bank_id' => null,
                    'paid_at_source_cash_box_id' => null,
                    'paid_at_source_cheque_id' => null,
                    'paid_at_source_wallet_id' => null,
                ]);
            }
        }

        $request->merge(['upfront_payment_amount' => $upfront]);
    }

    protected function beforeAdd(Request &$request): void
    {
        $request->merge([
            'status' => CustomerInvoice::STATUS_DRAFT,
        ]);
        $this->mergeCustomerInvoiceFinancialDefaults($request, null);
    }

    protected function beforeUpdate(Request &$request, int|string $id): void
    {
        $this->mergeCustomerInvoiceFinancialDefaults($request, (int) $id);
    }

    protected function afterAdd(Request $request, int|string $id, Model $model): void
    {
        if (! $model instanceof CustomerInvoice) {
            return;
        }
        if ((int) ($model->paid_at_source_cheque_id ?? 0) > 0) {
            $cheque = \RMS\Accounting\Models\Cheque::query()->find((int) $model->paid_at_source_cheque_id);
            if ($cheque) {
                app(ChequeAutoCreationService::class)->attachSource($cheque, CustomerInvoice::class, (int) $model->id);
            }
        }
    }

    protected function afterUpdate(Request $request, int|string $id, Model $model): void
    {
        if (! $model instanceof CustomerInvoice) {
            return;
        }
        if ((int) ($model->paid_at_source_cheque_id ?? 0) > 0) {
            $cheque = \RMS\Accounting\Models\Cheque::query()->find((int) $model->paid_at_source_cheque_id);
            if ($cheque) {
                app(ChequeAutoCreationService::class)->attachSource($cheque, CustomerInvoice::class, (int) $model->id);
            }
        }
    }

    private function mergeCustomerInvoiceFinancialDefaults(Request $request, ?int $updateId): void
    {
        $total = (float) ($this->parseDecimalInput($request->input('total_amount')) ?? 0);
        $upfront = (float) ($this->parseDecimalInput($request->input('upfront_payment_amount')) ?? 0);
        $invoiceDate = trim((string) $request->input('invoice_date', ''));
        if ($invoiceDate === '') {
            $invoiceDate = now()->toDateString();
            $request->merge(['invoice_date' => $invoiceDate]);
        }
        $settlementMode = (string) $request->input('settlement_mode', CustomerInvoice::SETTLEMENT_CREDIT);
        if ($settlementMode === CustomerInvoice::SETTLEMENT_CASH) {
            $upfront = $total;
        } elseif ($settlementMode === CustomerInvoice::SETTLEMENT_CREDIT) {
            $upfront = 0;
        } else {
            $upfront = max(0, min($upfront, $total));
        }
        $balance = max(0, $total - $upfront);
        $paymentStatus = $balance <= 0.0001
            ? CustomerInvoice::STATUS_PAID
            : ($upfront > 0 ? CustomerInvoice::STATUS_PARTIALLY_PAID : CustomerInvoice::STATUS_UNPAID);

        if ($updateId !== null) {
            $existing = CustomerInvoice::query()->whereKey($updateId)->first(['document_id', 'status']);
            if ($existing && (int) ($existing->document_id ?? 0) > 0) {
                throw ValidationException::withMessages([
                    '_invoice' => (string) trans('accounting::accounting.customer_invoice.header_locked_document'),
                ]);
            }
            if ($existing) {
                $request->merge([
                    'status' => (string) ($existing->status ?: CustomerInvoice::STATUS_DRAFT),
                ]);
            }
        } else {
            $request->merge([
                'status' => CustomerInvoice::STATUS_DRAFT,
            ]);
        }

        if (! $request->filled('discount_amount')) {
            $request->merge(['discount_amount' => 0]);
        }

        if (! $request->filled('subtotal')) {
            $request->merge(['subtotal' => $total]);
        }
        if (! $request->filled('tax_amount')) {
            $request->merge(['tax_amount' => 0]);
        }
        $taxMethod = strtolower(trim((string) $request->input('tax_method', '')));
        if (! in_array($taxMethod, ['inclusive', 'exclusive'], true)) {
            $existingTaxMethod = null;
            if ($updateId !== null) {
                $existingTaxMethod = (string) (CustomerInvoice::query()->whereKey($updateId)->value('tax_method') ?? '');
            }
            $taxMethod = in_array($existingTaxMethod, ['inclusive', 'exclusive'], true)
                ? $existingTaxMethod
                : (function_exists('tax_calculation_method') ? tax_calculation_method() : 'exclusive');
        }
        $request->merge(['tax_method' => $taxMethod]);
        if (! $request->filled('due_date') && $invoiceDate !== '') {
            $request->merge(['due_date' => $invoiceDate]);
        }

        $currencyCode = strtoupper(trim((string) $request->input('currency_code', '')));
        if ($currencyCode === '') {
            $currencyCode = Currency::resolveBaseCurrencyCode('IRT');
        }

        $request->merge([
            'currency_code' => $currencyCode,
            'fx_rate' => 1,
            'amount_base' => $total,
            'upfront_payment_amount' => $upfront,
            'paid_amount' => $upfront,
            'balance_due' => $balance,
            'payment_status' => $paymentStatus,
        ]);
    }

    public function itemsFragment(Request $request, int|string $customer_invoice): View
    {
        $invoice = CustomerInvoice::query()
            ->with(['items' => static fn ($q) => $q->orderBy('id')])
            ->findOrFail((int) $customer_invoice);

        return view('accounting::admin.customer_invoices._items_table', [
            'invoice' => $invoice,
        ]);
    }

    public function postAccountingDocument(Request $request, int|string $customer_invoice): RedirectResponse
    {
        $invoice = CustomerInvoice::query()->with(['customer.party'])->findOrFail((int) $customer_invoice);
        if ((int) ($invoice->document_id ?? 0) > 0) {
            return redirect()->back()->with('warning', (string) trans('accounting::accounting.customer_invoice.post_document_already'));
        }

        try {
            app(CustomerInvoiceService::class)->postSalesAccountingDocument($invoice);
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            report($e);
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', (string) trans('accounting::accounting.customer_invoice.post_document_success'));
    }

    public function reverseAndCreateReplacement(Request $request, int|string $customer_invoice): RedirectResponse
    {
        $invoice = CustomerInvoice::query()->findOrFail((int) $customer_invoice);
        $reason = trim((string) $request->input('reason', ''));

        try {
            $result = app(CustomerInvoiceService::class)
                ->reverseAndCreateReplacement($invoice, $reason !== '' ? $reason : null);
            /** @var CustomerInvoice $replacement */
            $replacement = $result['replacement_invoice'];
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.accounting.customer-invoices.edit', ['customer_invoice' => $replacement->getKey()])
            ->with('success', (string) trans('accounting::accounting.customer_invoice.correction_reversal_replacement_success'));
    }

    public function createAdjustment(Request $request, int|string $customer_invoice): RedirectResponse
    {
        $invoice = CustomerInvoice::query()->findOrFail((int) $customer_invoice);

        return redirect()->route('admin.accounting.credit-notes.create', [
            'customer_id' => (string) $invoice->customer_id,
            'customer_invoice_id' => (string) $invoice->getKey(),
            'credit_type' => 'correction',
        ]);
    }

    protected function afterConfigureStructuredAccountingFormView(Request $request, bool $isEdit, ?Model $model): void
    {
        if ($this->structuredAccountingFormSlug() !== 'customer_invoices') {
            return;
        }

        $this->view->withCss('vendor/accounting/admin/css/accounting-purchase-summary-cards.css', true);
        $this->view->withCss('vendor/accounting/admin/css/customer-payment-customer-picker.css', true);
        $this->view->withJs('vendor/accounting/admin/js/customer-invoice-structured-form.js', true);
        $this->view->withJs('vendor/accounting/admin/js/accounting-line-items-editor.js', true);
        $this->view->withJs('vendor/accounting/admin/js/customer-payment-structured-form.js', true);
        $this->view->withJs('vendor/accounting/admin/js/payment-destination-picker.js', true);
    }

    /**
     * @return array<string, mixed>
     */
    protected function structuredAccountingFormExtraViewVariables(Request $request, bool $isEdit, ?Model $model): array
    {
        $customerPrefillId = null;
        $customerSelectInitialText = null;
        $defaultCustomerInvoiceNumber = null;
        $invoiceNumberUniquenessUrl = route('admin.accounting.customer-invoices.check-invoice-number');
        $quickCreateCurrencyOptions = [];
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

        if ($isEdit && $model instanceof CustomerInvoice && $model->customer_id) {
            $customer = Customer::query()->find((int) $model->customer_id);
            if ($customer) {
                $customerPrefillId = (string) $customer->id;
                $customerSelectInitialText = (string) $customer->name;
            }
        } else {
            $defaultCustomerInvoiceNumber = $this->suggestNextCustomerInvoiceNumber();
            $oldCustomerId = trim((string) $request->old('customer_id', ''));
            if ($oldCustomerId !== '' && ctype_digit($oldCustomerId)) {
                $customer = Customer::query()->find((int) $oldCustomerId);
            } else {
                $customer = app(PartyService::class)->ensureDefaultSalesCustomer();
            }
            if ($customer) {
                $customerPrefillId = (string) $customer->id;
                $customerSelectInitialText = (string) $customer->name;
            }
        }

        return [
            'customerInvoiceSearchUrl' => route('admin.accounting.customer-invoices.search-customers'),
            'customerQuickCreateUrl' => route('admin.accounting.customer-invoices.quick-create-customer'),
            'customerPrefillId' => $customerPrefillId,
            'customerSelectInitialText' => $customerSelectInitialText,
            'customerPaymentPrefillId' => $customerPrefillId,
            'customerPaymentSelectInitialText' => $customerSelectInitialText,
            'customerPaymentSearchUrl' => route('admin.accounting.customer-invoices.search-customers'),
            'customerQuickCreateTypeOptions' => [
                'regular' => trans('accounting::accounting.customer.type_regular'),
                'vip' => trans('accounting::accounting.customer.type_vip'),
                'occasional' => trans('accounting::accounting.customer.type_occasional'),
            ],
            'customerQuickCreateCurrencyOptions' => $quickCreateCurrencyOptions,
            'defaultCustomerInvoiceNumber' => $defaultCustomerInvoiceNumber,
            'invoiceNumberUniquenessUrl' => $invoiceNumberUniquenessUrl,
            'customerInvoiceItemsFragmentUrl' => ($isEdit && $model instanceof CustomerInvoice)
                ? route('admin.accounting.customer-invoices.items-fragment', ['customer_invoice' => $model->id])
                : null,
            'customerInvoiceItemsStoreUrl' => ($isEdit && $model instanceof CustomerInvoice)
                ? route('admin.accounting.customer-invoices.items.store', ['customer_invoice' => $model->id])
                : null,
            'customerInvoiceCorrectionsTimeline' => ($isEdit && $model instanceof CustomerInvoice)
                ? app(CustomerInvoiceCorrectionService::class)->timelineForInvoice($model)
                : collect(),
        ];
    }

    protected function suggestNextCustomerInvoiceNumber(): string
    {
        $prefix = 'CINV-'.now()->format('Ymd').'-';
        $last = CustomerInvoice::query()
            ->where('invoice_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('invoice_number');

        $next = 1;
        if ($last && preg_match('/-(\d+)$/', (string) $last, $m)) {
            $next = (int) $m[1] + 1;
        }

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function parseDecimalInput(mixed $value): ?float
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

    private function normalizeDefaultCurrencyCode(mixed $value): ?string
    {
        $currency = strtoupper(trim((string) $value));
        if ($currency === '') {
            return $this->resolveDefaultCurrencyCode();
        }

        return $currency;
    }
}
