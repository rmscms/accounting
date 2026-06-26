<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use RMS\Accounting\Http\Controllers\Admin\Concerns\RendersAccountingStructuredResourceForm;
use RMS\Accounting\Models\Currency;
use RMS\Accounting\Models\Customer;
use RMS\Accounting\Models\CustomerInvoice;
use RMS\Accounting\Models\CustomerPayment;
use RMS\Accounting\Models\PaymentMethod;
use RMS\Accounting\Services\ChequeAutoCreationService;
use RMS\Accounting\Services\CustomerPaymentService;
use RMS\Accounting\Services\PaymentDestinationCatalog;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RMS\Core\Data\Field;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;

class CustomerPaymentsController extends AccountingAdminController implements HasList, HasForm, ShouldFilter
{
    use RendersAccountingStructuredResourceForm;

    public function table(): string
    {
        return 'customer_payments';
    }

    public function modelName(): string
    {
        return CustomerPayment::class;
    }

    public function baseRoute(): string
    {
        return 'accounting.customer-payments';
    }

    public function routeParameter(): string
    {
        return 'customer_payment';
    }

    public function getFieldsForm(): array
    {
        return [
            Field::string('payment_number', trans('accounting::accounting.payment.payment_number'))->required(),
            Field::date('payment_date', trans('accounting::accounting.payment.payment_date'))
                ->withDefaultValue(now()->toDateString())
                ->optional(),
            Field::number('customer_id', trans('accounting::accounting.payment.customer_id'))
                ->required()
                ->withAttributes(['structured_widget' => 'customer_payment_customer_picker']),
            Field::hidden('customer_invoice_id'),
            Field::number('amount', trans('accounting::accounting.payment.amount'))->required(),
            Field::number('payment_method_id', trans('accounting::accounting.payment.payment_method'))
                ->required()
                ->withAttributes([
                    'structured_widget' => 'payment_destination_picker',
                    'payment_destination_context' => PaymentDestinationCatalog::CONTEXT_CUSTOMER_PAYMENT,
                ]),
            Field::hidden('status', CustomerPayment::STATUS_COMPLETED),
            Field::textarea('notes', trans('accounting::accounting.payment.notes'))->optional(),
        ];
    }

    /**
     * نمایش نام مشتری به جای شناسهٔ عددی.
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

    public function getListFields(): array
    {
        return [
            Field::make('id', 'a.id')->withTitle(trans('accounting::accounting.common.id'))->sortable()->width('80px'),
            Field::make('payment_number')->withTitle(trans('accounting::accounting.payment.payment_number'))->searchable()->sortable()->width('150px'),
            Field::make('payment_date')->withTitle(trans('accounting::accounting.payment.payment_date'))->sortable()->width('120px'),
            Field::make('customer_name', 'customers.name')
                ->withTitle(trans('accounting::accounting.payment.customer_id'))
                ->customMethod('renderCustomerName')
                ->sortable()
                ->searchable()
                ->width('220px'),
            Field::make('amount')->withTitle(trans('accounting::accounting.payment.amount'))->sortable()->width('120px'),
            Field::make('payment_method_id')->withTitle(trans('accounting::accounting.payment.payment_method'))->sortable()->width('120px'),
            Field::make('created_at')->withTitle(trans('accounting::accounting.common.created_at'))->sortable()->width('150px'),
        ];
    }

    public function filters(): array
    {
        return [
            Field::select('payment_method_id', trans('accounting::accounting.payment.payment_method'))
                ->setOptions(array_merge(
                    ['' => trans('accounting::accounting.common.all')],
                    $this->getPaymentMethodOptions()
                )),
        ];
    }

    /**
     * @return array<int|string, string>
     */
    protected function getPaymentMethodOptions(): array
    {
        return PaymentMethod::query()->where('active', true)->orderBy('sort_order')->orderBy('name')
            ->pluck('name', 'id')
            ->all();
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
                Rule::unique('customer_payments', 'payment_number')->ignore($id),
            ],
            'payment_date' => ['nullable'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'customer_invoice_id' => ['nullable', 'integer', 'exists:customer_invoices,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'status' => ['nullable', 'in:pending,completed,failed,reversed,cancelled'],
            'bank_id' => ['nullable', 'integer', 'exists:banks,id'],
            'cash_box_id' => ['nullable', 'integer', 'exists:cash_boxes,id'],
            'cheque_id' => ['nullable', 'integer', 'exists:cheques,id'],
            'pos_terminal_id' => ['nullable', 'integer', 'exists:pos_terminals,id'],
            'wallet_id' => ['nullable', 'integer', 'exists:wallets,id'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function renderCustomerName($row): string
    {
        if (! $row->customer_id) {
            return '<span class="text-muted">-</span>';
        }

        $name = e((string) ($row->customer_name ?: ('#' . $row->customer_id)));
        $phone = trim((string) ($row->customer_phone ?? ''));
        $phoneHtml = $phone !== '' ? ('<br><small class="text-muted">' . e($phone) . '</small>') : '';

        return '<div>' . $name . $phoneHtml . '</div>';
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
        $q = CustomerPayment::query()->where('payment_number', $number);
        if ($excludeId > 0) {
            $q->where('id', '!=', $excludeId);
        }

        $available = ! $q->exists();

        return response()->json([
            'available' => $available,
            'message' => $available ? '' : (string) trans('accounting::accounting.payment.payment_number_taken'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function structuredAccountingFormExtraViewVariables(Request $request, bool $isEdit, ?Model $model): array
    {
        $customerPaymentPrefillId = null;
        $customerPaymentSelectInitialText = null;
        $customerPaymentPrefillInvoiceId = null;
        $customerPaymentPrefillAmount = null;
        $defaultCustomerPaymentNumber = $isEdit ? null : $this->suggestNextCustomerPaymentNumber();

        if ($isEdit && $model instanceof CustomerPayment && (int) ($model->customer_id ?? 0) > 0) {
            $customer = Customer::query()->find((int) $model->customer_id);
            if ($customer) {
                $customerPaymentPrefillId = (string) $customer->id;
                $customerPaymentSelectInitialText = (string) $customer->name;
            }
        } else {
            $queryCustomerId = trim((string) $request->query('customer_id', ''));
            $oldCustomerId = trim((string) $request->old('customer_id', ''));
            $targetCustomerId = '';
            if ($oldCustomerId !== '' && ctype_digit($oldCustomerId)) {
                $targetCustomerId = $oldCustomerId;
            } elseif ($queryCustomerId !== '' && ctype_digit($queryCustomerId)) {
                $targetCustomerId = $queryCustomerId;
            }
            if ($targetCustomerId !== '') {
                $customer = Customer::query()->find((int) $targetCustomerId);
                if ($customer) {
                    $customerPaymentPrefillId = (string) $customer->id;
                    $customerPaymentSelectInitialText = (string) $customer->name;
                }
            }

            $invoiceRaw = trim((string) $request->query('customer_invoice_id', ''));
            $oldInvoiceRaw = trim((string) $request->old('customer_invoice_id', ''));
            if ($oldInvoiceRaw !== '' && ctype_digit($oldInvoiceRaw)) {
                $customerPaymentPrefillInvoiceId = $oldInvoiceRaw;
            } elseif ($invoiceRaw !== '' && ctype_digit($invoiceRaw)) {
                $customerPaymentPrefillInvoiceId = $invoiceRaw;
            }

            $amountQuery = trim((string) $request->query('amount', ''));
            if ($amountQuery !== '' && is_numeric(str_replace(',', '', $amountQuery))) {
                $customerPaymentPrefillAmount = (string) $amountQuery;
            } elseif ($customerPaymentPrefillInvoiceId !== null) {
                $invoice = CustomerInvoice::query()->find((int) $customerPaymentPrefillInvoiceId);
                if ($invoice) {
                    $due = (float) ($invoice->balance_due ?? 0);
                    if ($due > 0) {
                        $customerPaymentPrefillAmount = rtrim(rtrim(number_format($due, 4, '.', ''), '0'), '.');
                    }
                }
            }
        }

        return [
            'customerPaymentSearchUrl' => route('admin.accounting.customer-invoices.search-customers'),
            'customerPaymentPrefillId' => $customerPaymentPrefillId,
            'customerPaymentSelectInitialText' => $customerPaymentSelectInitialText,
            'customerPaymentPrefillInvoiceId' => $customerPaymentPrefillInvoiceId,
            'customerPaymentPrefillAmount' => $customerPaymentPrefillAmount,
            'defaultCustomerPaymentNumber' => $defaultCustomerPaymentNumber,
            'customerPaymentNumberUniquenessUrl' => route('admin.accounting.customer-payments.check-payment-number'),
        ];
    }

    protected function afterConfigureStructuredAccountingFormView(Request $request, bool $isEdit, ?Model $model): void
    {
        if ($this->structuredAccountingFormSlug() === 'customer_payments') {
            $this->view->withCss('vendor/accounting/admin/css/customer-payment-customer-picker.css', true);
            $this->view->withJs('vendor/accounting/admin/js/payment-destination-picker.js', true);
            $this->view->withJs('vendor/accounting/admin/js/customer-payment-structured-form.js', true);
        }
    }

    protected function afterStructuredAccountingFormPrepareForValidation(Request $request): void
    {
        if ($this->structuredAccountingFormSlug() === 'customer_payments') {
            $this->applyPaymentDestinationValidation($request, PaymentDestinationCatalog::CONTEXT_CUSTOMER_PAYMENT);
        }
    }

    protected function beforeAdd(Request &$request): void
    {
        $this->mergeCustomerPaymentDefaults($request);
    }

    protected function beforeUpdate(Request &$request, int|string $id): void
    {
        $this->mergeCustomerPaymentDefaults($request);
    }

    protected function afterAdd(Request $request, int|string $id, Model $model): void
    {
        if (! $model instanceof CustomerPayment) {
            return;
        }
        if ((int) ($model->cheque_id ?? 0) > 0) {
            $cheque = \RMS\Accounting\Models\Cheque::query()->find((int) $model->cheque_id);
            if ($cheque) {
                app(ChequeAutoCreationService::class)->attachSource($cheque, CustomerPayment::class, (int) $model->id);
            }
        }
        app(CustomerPaymentService::class)->processCompletedPayment($model->fresh());
    }

    protected function afterUpdate(Request $request, int|string $id, Model $model): void
    {
        if (! $model instanceof CustomerPayment) {
            return;
        }
        if ((int) ($model->cheque_id ?? 0) > 0) {
            $cheque = \RMS\Accounting\Models\Cheque::query()->find((int) $model->cheque_id);
            if ($cheque) {
                app(ChequeAutoCreationService::class)->attachSource($cheque, CustomerPayment::class, (int) $model->id);
            }
        }
        app(CustomerPaymentService::class)->processCompletedPayment($model->fresh());
    }

    protected function suggestNextCustomerPaymentNumber(): string
    {
        $prefix = 'RCP-' . now()->format('Ym') . '-';
        $last = CustomerPayment::query()
            ->where('payment_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('payment_number');

        $next = 1;
        if ($last && preg_match('/-(\d+)$/', (string) $last, $m)) {
            $next = (int) $m[1] + 1;
        }

        return $prefix . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    private function mergeCustomerPaymentDefaults(Request $request): void
    {
        $paymentDate = trim((string) $request->input('payment_date', ''));
        if ($paymentDate === '') {
            $paymentDate = now()->toDateString();
        }

        $paymentNumber = trim((string) $request->input('payment_number', ''));
        if ($paymentNumber === '') {
            $paymentNumber = $this->suggestNextCustomerPaymentNumber();
        }

        $amount = (float) ($this->parseDecimalInput($request->input('amount')) ?? 0);
        $fxRate = (float) ($this->parseDecimalInput($request->input('fx_rate')) ?? 1);
        if ($fxRate <= 0) {
            $fxRate = 1;
        }
        $amountBase = (float) ($this->parseDecimalInput($request->input('amount_base')) ?? 0);
        if ($amountBase <= 0 && $amount > 0) {
            $amountBase = round($amount * $fxRate, 4);
        }

        $currencyCode = strtoupper(trim((string) $request->input('currency_code', '')));
        if ($currencyCode === '') {
            $currencyCode = Currency::resolveBaseCurrencyCode('IRR');
        }

        $customerInvoiceId = $request->input('customer_invoice_id');
        if ($customerInvoiceId === null || $customerInvoiceId === '' || (string) $customerInvoiceId === '0') {
            $customerInvoiceId = null;
        } elseif (is_numeric($customerInvoiceId)) {
            $customerInvoiceId = (int) $customerInvoiceId;
        }

        $status = (string) $request->input('status', CustomerPayment::STATUS_COMPLETED);
        if (! in_array($status, [
            CustomerPayment::STATUS_PENDING,
            CustomerPayment::STATUS_COMPLETED,
            CustomerPayment::STATUS_FAILED,
            CustomerPayment::STATUS_REVERSED,
            CustomerPayment::STATUS_CANCELLED,
        ], true)) {
            $status = CustomerPayment::STATUS_COMPLETED;
        }

        $merge = [
            'payment_number' => $paymentNumber,
            'payment_date' => $paymentDate,
            'amount' => $amount,
            'currency_code' => $currencyCode,
            'fx_rate' => $fxRate,
            'amount_base' => $amountBase,
            'customer_invoice_id' => $customerInvoiceId,
            'status' => $status,
        ];
        if ($status === CustomerPayment::STATUS_COMPLETED) {
            $merge['processed_by_user_id'] = \RMS\Accounting\Support\AuditActor::userId();
            $merge['processed_at'] = now();
        }
        $request->merge($merge);
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
            $customer = Customer::query()->find((int) $request->input('customer_id', 0));
            $autoCheque = app(ChequeAutoCreationService::class)->ensureCheque([
                'context' => 'customer_payment',
                'source_short' => 'CP',
                'payment_method_id' => $pm,
                'cheque_id' => null,
                'cheque_type' => \RMS\Accounting\Models\Cheque::TYPE_RECEIVED,
                'party_id' => (int) ($customer?->party_id ?? 0),
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
            if ($hasAnyDestination || $status === CustomerPayment::STATUS_COMPLETED) {
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
}
