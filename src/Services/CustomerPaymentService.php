<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\Customer;
use RMS\Accounting\Models\CustomerPayment;
use RMS\Accounting\Models\CustomerInvoice;
use RMS\Accounting\Models\Account;
use RMS\Accounting\Models\AccountingDocument;
use RMS\Accounting\Models\Currency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RMS\Accounting\Support\InteractsWithAuditActor;

/**
 * سرویس مدیریت دریافت‌های مشتریان
 * - ثبت دریافت از مشتری
 * - تسویه فاکتورها
 * - ثبت در دفتر کل
 */
class CustomerPaymentService
{
    use InteractsWithAuditActor;

    protected LedgerService $ledgerService;
    protected DocumentService $documentService;
    protected CustomerInvoiceService $invoiceService;
    protected ChequeLedgerService $chequeLedgerService;
    protected LedgerFxResolver $ledgerFxResolver;
    protected PartyService $partyService;

    public function __construct(
        LedgerService $ledgerService,
        DocumentService $documentService,
        CustomerInvoiceService $invoiceService,
        ChequeLedgerService $chequeLedgerService,
        LedgerFxResolver $ledgerFxResolver,
        PartyService $partyService
    ) {
        $this->ledgerService = $ledgerService;
        $this->documentService = $documentService;
        $this->invoiceService = $invoiceService;
        $this->chequeLedgerService = $chequeLedgerService;
        $this->ledgerFxResolver = $ledgerFxResolver;
        $this->partyService = $partyService;
    }

    /**
     * ثبت دریافت از مشتری
     */
    public function createPayment(array $data): CustomerPayment
    {
        DB::beginTransaction();
        try {
            $data = $this->enrichPaymentData($data);
            $status = (string) ($data['status'] ?? CustomerPayment::STATUS_PENDING);
            if ($status === CustomerPayment::STATUS_COMPLETED) {
                $data = $this->stampAudit($data, 'customer_payments', 'processed');
            }

            // تولید شماره دریافت
            if (empty($data['payment_number'])) {
                $data['payment_number'] = $this->generatePaymentNumber();
            }

            // ایجاد دریافت
            $payment = CustomerPayment::create($data);

            // ثبت در دفتر کل (اگر تکمیل شده)
            if ($payment->status === CustomerPayment::STATUS_COMPLETED) {
                $this->recordPaymentInLedger($payment);
            }

            // بروزرسانی وضعیت فاکتور
            if ($payment->customer_invoice_id) {
                $this->syncInvoicePaymentStatus((int) $payment->customer_invoice_id);
            }

            DB::commit();
            return $payment;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * همگام‌سازی اثرات پرداخت ثبت‌شده از فرم ادمین:
     * - اگر پرداخت completed است و سند ندارد، سند دفترکل ایجاد شود.
     * - اگر به فاکتور وصل است، مانده/وضعیت فاکتور به‌روز شود.
     */
    public function processCompletedPayment(CustomerPayment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $fresh = $payment->fresh();
            if (! $fresh instanceof CustomerPayment) {
                return;
            }

            if (
                (string) $fresh->status === CustomerPayment::STATUS_COMPLETED
                && (int) ($fresh->document_id ?? 0) <= 0
            ) {
                $this->recordPaymentInLedger($fresh);
                $fresh = $fresh->fresh() ?? $fresh;
            }

            if ((int) ($fresh->customer_invoice_id ?? 0) > 0) {
                $this->syncInvoicePaymentStatus((int) $fresh->customer_invoice_id);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function enrichPaymentData(array $data): array
    {
        $customerId = (int) ($data['customer_id'] ?? 0);
        if ($customerId > 0) {
            $customer = Customer::query()->find($customerId);
            if ($customer && empty($data['currency_code']) && ! empty($customer->default_currency_code)) {
                $data['currency_code'] = $customer->default_currency_code;
            }
        }
        if (empty($data['currency_code'])) {
            $data['currency_code'] = Currency::resolveBaseCurrencyCode('IRR');
        }
        $data['currency_code'] = strtoupper((string) $data['currency_code']);

        $payDate = $data['payment_date'] ?? now()->toDateString();
        $dateStr = $payDate instanceof \DateTimeInterface
            ? $payDate->format('Y-m-d')
            : (string) $payDate;

        $fx = (float) ($data['fx_rate'] ?? 0);
        if ($fx <= 0) {
            $data['fx_rate'] = $this->ledgerFxResolver->resolveRateToBase($data['currency_code'], $dateStr);
        }

        $amt = (float) ($data['amount'] ?? 0);
        $fx = (float) $data['fx_rate'];
        if ((! isset($data['amount_base']) || (float) $data['amount_base'] <= 0) && $amt > 0 && $fx > 0) {
            $data['amount_base'] = round($amt * $fx, 4);
        }

        return $data;
    }

    /**
     * ثبت دریافت در دفتر کل
     */
    protected function recordPaymentInLedger(CustomerPayment $payment): void
    {
        $channelTitle = $this->resolvePaymentChannelTitle($payment);
        $descriptionSuffix = trim((string) $payment->notes) !== '' ? (' | ' . trim((string) $payment->notes)) : '';

        // ایجاد سند حسابداری
        $document = $this->documentService->createDocument([
            'document_type' => 'receipt',
            'store_id' => $payment->store_id,
            'fiscal_year_id' => config('accounting.current_fiscal_year_id'),
            'reference_type' => CustomerPayment::class,
            'reference_id' => $payment->id,
            'description' => "دریافت از مشتری {$payment->payment_number} | کانال: {$channelTitle}{$descriptionSuffix}",
            'total_debit' => $payment->amount,
            'total_credit' => $payment->amount,
        ]);

        // تعیین حساب دریافت کننده (بانک/صندوق/POS/...)
        $destinationAccountId = $this->getPaymentDestinationAccount($payment);

        // آرتیکل بدهکار: حساب بانک/صندوق
        $fxToBase = (float) ($payment->fx_rate ?? 1);

        $this->ledgerService->recordEntry([
            'event_type' => 'payment_received',
            'event_source' => 'customer',
            'source_reference_type' => CustomerPayment::class,
            'source_reference_id' => $payment->id,
            'store_id' => $payment->store_id,
            'account_id' => $destinationAccountId,
            'currency_code' => $payment->currency_code,
            'debit_amount' => $payment->amount,
            'credit_amount' => 0,
            'fx_rate_to_base' => $fxToBase,
            'accounting_document_id' => $document->id,
            'description' => "دریافت {$payment->payment_number} | {$channelTitle}{$descriptionSuffix}",
        ]);

        // آرتیکل بستانکار: حساب مشتری (کاهش بدهی)
        $this->ledgerService->recordEntry([
            'event_type' => 'payment_received',
            'event_source' => 'customer',
            'source_reference_type' => CustomerPayment::class,
            'source_reference_id' => $payment->id,
            'store_id' => $payment->store_id,
            'account_id' => $this->resolveReceivableAccountId($payment),
            'currency_code' => $payment->currency_code,
            'debit_amount' => 0,
            'credit_amount' => $payment->amount,
            'fx_rate_to_base' => $fxToBase,
            'accounting_document_id' => $document->id,
            'description' => "تسویه دریافت {$payment->payment_number} | مشتری{$descriptionSuffix}",
        ]);

        // اگر اختلاف نرخ ارز دارد
        if ($payment->fx_difference_irr != 0) {
            $this->recordFXDifference($payment, $document);
        }

        // ثبت قطعی سند
        $this->documentService->postDocument($document->id);

        // ذخیره شماره سند
        $payment->update(['document_id' => $document->id]);
    }

    /**
     * تعیین حساب مقصد پرداخت
     */
    protected function getPaymentDestinationAccount(CustomerPayment $payment): int
    {
        if ((int) $payment->cheque_id) {
            $clearing = $this->chequeLedgerService->resolveReceivableClearingAccountId();
            if ($clearing !== null) {
                return $clearing;
            }
        }

        if ($payment->bank_id) {
            $bank = \RMS\Accounting\Models\Bank::find($payment->bank_id);
            return (int) ($bank->account_id ?: $this->resolveFallbackAccountId(config('accounting.accounts.bank_default'), ['1102']));
        }

        if ($payment->cash_box_id) {
            $cashBox = \RMS\Accounting\Models\CashBox::find($payment->cash_box_id);
            return (int) ($cashBox->account_id ?: $this->resolveFallbackAccountId(config('accounting.accounts.cash_box_default'), ['1101']));
        }

        if ($payment->pos_terminal_id) {
            return $this->resolveFallbackAccountId(config('accounting.accounts.pos_terminal'), ['1102']);
        }

        if ($payment->wallet_id) {
            $wallet = \RMS\Accounting\Models\Wallet::find($payment->wallet_id);
            return (int) ($wallet->account_id ?: $this->resolveFallbackAccountId(config('accounting.accounts.bank_default'), ['1102']));
        }

        return $this->resolveFallbackAccountId(config('accounting.accounts.cash_box_default'), ['1101']);
    }

    /**
     * ثبت اختلاف نرخ ارز
     */
    protected function recordFXDifference(CustomerPayment $payment, $document): void
    {
        $accountId = $payment->fx_difference_irr > 0 
            ? config('accounting.accounts.fx_gain') 
            : config('accounting.accounts.fx_loss');

        $this->ledgerService->recordEntry([
            'event_type' => 'fx_difference',
            'event_source' => 'system',
            'store_id' => $payment->store_id,
            'account_id' => $accountId,
            'currency_code' => 'IRR',
            'debit_amount' => $payment->fx_difference_irr > 0 ? 0 : abs($payment->fx_difference_irr),
            'credit_amount' => $payment->fx_difference_irr > 0 ? $payment->fx_difference_irr : 0,
            'fx_rate_to_base' => 1,
            'accounting_document_id' => $document->id,
            'description' => 'اختلاف نرخ ارز',
        ]);
    }

    /**
     * بروزرسانی وضعیت پرداخت فاکتور
     */
    public function syncInvoicePaymentStatus(int $invoiceId): void
    {
        $invoice = CustomerInvoice::findOrFail($invoiceId);

        $totalPaid = CustomerPayment::where('customer_invoice_id', $invoiceId)
            ->where('status', CustomerPayment::STATUS_COMPLETED)
            ->sum('amount');

        $balanceDue = $invoice->total_amount - $totalPaid;

        $paymentStatus = match (true) {
            $totalPaid == 0 => CustomerInvoice::STATUS_UNPAID,
            $totalPaid >= $invoice->total_amount => CustomerInvoice::STATUS_PAID,
            default => CustomerInvoice::STATUS_PARTIALLY_PAID,
        };

        $invoice->update([
            'paid_amount' => $totalPaid,
            'balance_due' => $balanceDue,
            'payment_status' => $paymentStatus,
        ]);
    }

    /**
     * تولید شماره دریافت
     */
    protected function generatePaymentNumber(): string
    {
        $year = date('Y');
        $month = date('m');

        $lastPayment = CustomerPayment::whereYear('payment_date', $year)
            ->whereMonth('payment_date', $month)
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastPayment ? intval(substr($lastPayment->payment_number, -6)) + 1 : 1;

        return sprintf('RCP-%s%s-%06d', $year, $month, $nextNumber);
    }

    /**
     * اصلاح immutable یک دریافت ثبت‌شده:
     * - معکوس سند قبلی
     * - ثبت دریافت جدید با داده صحیح
     * - لینک‌کردن پرداخت قدیم/جدید در فیلدهای audit (در صورت وجود ستون‌ها)
     *
     * @param  array<string, mixed>  $data
     * @return array{original_payment: CustomerPayment, new_payment: CustomerPayment, reversal_document: AccountingDocument}
     */
    public function correctPayment(CustomerPayment $originalPayment, array $data): array
    {
        return DB::transaction(function () use ($originalPayment, $data) {
            if ((string) $originalPayment->status !== CustomerPayment::STATUS_COMPLETED) {
                throw new \DomainException('فقط پرداخت تکمیل‌شده قابل اصلاح است.');
            }
            if (!(int) $originalPayment->document_id) {
                throw new \DomainException('برای پرداخت اصلی سند حسابداری وجود ندارد.');
            }

            $reason = trim((string) ($data['reason'] ?? ''));
            if ($reason === '') {
                throw new \DomainException('علت اصلاح الزامی است.');
            }

            $reversalDocument = $this->ledgerService->reverseDocument(
                (int) $originalPayment->document_id,
                $reason
            );
            $auditContext = $this->auditContext();

            $originalPayment->status = CustomerPayment::STATUS_REVERSED;
            $originalPayment->notes = trim((string) ($originalPayment->notes ?? '')) !== ''
                ? trim((string) $originalPayment->notes) . "\n[CORRECTION] " . $reason
                : '[CORRECTION] ' . $reason;
            if (Schema::hasColumn('customer_payments', 'corrected_by_user_id')) {
                $originalPayment->corrected_by_user_id = $auditContext->userId;
            }
            if (Schema::hasColumn('customer_payments', 'corrected_by_admin_id')) {
                $originalPayment->corrected_by_admin_id = $auditContext->adminId;
            }
            if (Schema::hasColumn('customer_payments', 'corrected_at')) {
                $originalPayment->corrected_at = now();
            }
            if (Schema::hasColumn('customer_payments', 'correction_reason')) {
                $originalPayment->correction_reason = $reason;
            }
            if (Schema::hasColumn('customer_payments', 'correction_document_id')) {
                $originalPayment->correction_document_id = (int) $reversalDocument->id;
            }
            if (Schema::hasColumn('customer_payments', 'correction_group_id') && !empty($data['correction_group_id'])) {
                $originalPayment->correction_group_id = (string) $data['correction_group_id'];
            }
            $originalPayment->save();

            $correctionNote = trim((string) ($data['new_notes'] ?? ''));
            $correctionPrefix = '[CORRECTION-REPOST] ' . $reason;
            if ($correctionNote !== '') {
                $correctionNote = $correctionPrefix . "\n" . $correctionNote;
            } else {
                $correctionNote = $correctionPrefix;
            }

            $newPayment = $this->createPayment([
                'customer_id' => (int) $originalPayment->customer_id,
                'store_id' => (int) ($originalPayment->store_id ?? 0),
                'customer_invoice_id' => $originalPayment->customer_invoice_id,
                'payment_method_id' => (int) $data['new_payment_method_id'],
                'amount' => (float) $data['new_amount'],
                'currency_code' => (string) ($data['new_currency_code'] ?? $originalPayment->currency_code ?? 'IRR'),
                'fx_rate' => (float) ($data['new_fx_rate'] ?? $originalPayment->fx_rate ?? 1),
                'amount_base' => (float) ($data['new_amount_base'] ?? ((float) $data['new_amount'] * (float) ($data['new_fx_rate'] ?? 1))),
                'payment_date' => (string) ($data['new_payment_date'] ?? $originalPayment->payment_date?->format('Y-m-d') ?? now()->toDateString()),
                'status' => CustomerPayment::STATUS_COMPLETED,
                'notes' => $correctionNote,
                'bank_id' => (int) ($data['new_bank_id'] ?? 0) ?: null,
                'cash_box_id' => (int) ($data['new_cash_box_id'] ?? 0) ?: null,
                'pos_terminal_id' => (int) ($data['new_pos_terminal_id'] ?? 0) ?: null,
                'wallet_id' => (int) ($data['new_wallet_id'] ?? 0) ?: null,
                'processed_at' => now(),
            ]);

            if (Schema::hasColumn('customer_payments', 'original_payment_id')) {
                $newPayment->original_payment_id = (int) $originalPayment->id;
            }
            if (Schema::hasColumn('customer_payments', 'correction_reason')) {
                $newPayment->correction_reason = $reason;
            }
            if (Schema::hasColumn('customer_payments', 'correction_group_id') && !empty($data['correction_group_id'])) {
                $newPayment->correction_group_id = (string) $data['correction_group_id'];
            }
            if (Schema::hasColumn('customer_payments', 'corrected_by_user_id')) {
                $newPayment->corrected_by_user_id = $auditContext->userId;
            }
            if (Schema::hasColumn('customer_payments', 'corrected_by_admin_id')) {
                $newPayment->corrected_by_admin_id = $auditContext->adminId;
            }
            if (Schema::hasColumn('customer_payments', 'corrected_at')) {
                $newPayment->corrected_at = now();
            }
            $newPayment->save();

            return [
                'original_payment' => $originalPayment->fresh(),
                'new_payment' => $newPayment->fresh(),
                'reversal_document' => $reversalDocument,
            ];
        });
    }

    protected function resolveFallbackAccountId(mixed $configuredId, array $fallbackCodes): int
    {
        $id = (int) ($configuredId ?? 0);
        if ($id > 0) {
            return $id;
        }
        foreach ($fallbackCodes as $code) {
            $resolved = (int) Account::query()->where('code', (string) $code)->value('id');
            if ($resolved > 0) {
                return $resolved;
            }
        }
        return 1;
    }

    protected function resolvePaymentChannelTitle(CustomerPayment $payment): string
    {
        if ($payment->cash_box_id) {
            return 'صندوق';
        }
        if ($payment->bank_id) {
            return 'بانک';
        }
        if ($payment->pos_terminal_id) {
            return 'پوز';
        }
        if ($payment->wallet_id) {
            return 'کیف پول';
        }
        return 'نامشخص';
    }

    protected function resolveReceivableAccountId(CustomerPayment $payment): int
    {
        $customer = Customer::query()->find((int) $payment->customer_id);
        if ($customer && (int) ($customer->party_id ?? 0) > 0) {
            $acc = $this->partyService->getOrCreateCustomerAccount((int) $customer->party_id);
            if ($acc) {
                return (int) $acc->id;
            }
        }
        if ($customer && (int) ($customer->account_id ?? 0) > 0) {
            return (int) $customer->account_id;
        }
        return $this->resolveFallbackAccountId(config('accounting.accounts.accounts_receivable'), ['1103', '1103-001']);
    }
}
