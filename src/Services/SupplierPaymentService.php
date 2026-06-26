<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\Account;
use RMS\Accounting\Models\SupplierPayment;
use RMS\Accounting\Models\SupplierInvoice;
use RMS\Accounting\Models\FinancialLedger;
use RMS\Accounting\Models\AccountingDocument;
use RMS\Accounting\Models\Currency;
use RMS\Accounting\Events\SupplierPaymentMadeEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use RMS\Core\Models\Setting;
use RMS\Accounting\Support\InteractsWithAuditActor;

/**
 * Supplier Payment Service
 * 
 * مدیریت پرداخت‌ها به تامین‌کنندگان
 */
class SupplierPaymentService
{
    use InteractsWithAuditActor;

    protected LedgerService $ledgerService;

    protected ChequeLedgerService $chequeLedgerService;

    protected PartyService $partyService;

    public function __construct(
        LedgerService $ledgerService,
        ChequeLedgerService $chequeLedgerService,
        PartyService $partyService
    )
    {
        $this->ledgerService = $ledgerService;
        $this->chequeLedgerService = $chequeLedgerService;
        $this->partyService = $partyService;
    }

    /**
     * ثبت پرداخت به تامین‌کننده
     */
    public function recordPayment(array $data): SupplierPayment
    {
        try {
            DB::beginTransaction();

            static $columns = null;
            if ($columns === null) {
                $columns = array_flip(Schema::getColumnListing('supplier_payments'));
            }

            $currency = (string) ($data['currency_code']
                ?? Currency::resolveBaseCurrencyCode('IRR'));
            $fxRate = (float) ($data['fx_rate'] ?? $data['fx_rate_at_payment'] ?? 1);
            if ($fxRate <= 0) {
                $fxRate = 1;
            }
            $amount = (float) ($data['amount'] ?? 0);
            $attrs = array_intersect_key([
                'payment_number' => $data['payment_number'] ?? ('SP-' . now()->format('YmdHis') . '-' . random_int(100, 999)),
                'store_id' => $data['store_id'] ?? null,
                'supplier_id' => $data['supplier_id'],
                'supplier_invoice_id' => $data['supplier_invoice_id'] ?? null,
                'payment_method_id' => $data['payment_method_id'],
                'amount' => $amount,
                'currency_code' => $currency,
                'fx_rate_at_payment' => $fxRate,
                'amount_base_at_payment' => (float) ($data['amount_base'] ?? round($amount * $fxRate, 4)),
                'payment_date' => $data['payment_date'] ?? Carbon::now(),
                'bank_id' => $data['bank_id'] ?? ($data['bank_account_id'] ?? null),
                'cash_box_id' => $data['cash_box_id'] ?? null,
                'cheque_id' => $data['cheque_id'] ?? null,
                'wallet_id' => $data['wallet_id'] ?? null,
                'status' => $data['status'] ?? 'completed',
                'notes' => $data['notes'] ?? null,
                'processed_at' => now(),
            ], $columns);
            if ((string) ($attrs['status'] ?? '') === SupplierPayment::STATUS_COMPLETED) {
                $attrs = $this->stampAudit($attrs, 'supplier_payments', 'processed');
            }
            $payment = SupplierPayment::query()->create($attrs);

            if ((string) $payment->status === SupplierPayment::STATUS_COMPLETED) {
                $payment = $this->processCompletedPayment($payment);
            }

            // Dispatch event
            event(new SupplierPaymentMadeEvent($payment));

            DB::commit();
            return $payment->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * اعمال پرداخت به فاکتور (فرم ادمین یا API هر دو پس از ذخیرهٔ رکورد پرداخت قابل فراخوانی).
     */
    public function applyPaymentToInvoice(SupplierPayment $payment): void
    {
        $invoice = SupplierInvoice::findOrFail($payment->supplier_invoice_id);
        $invoice->paid_amount = (float) SupplierPayment::query()
            ->where('supplier_invoice_id', $invoice->getKey())
            ->where('status', SupplierPayment::STATUS_COMPLETED)
            ->sum('amount');
        $balanceDue = (float) $invoice->total_amount - (float) $invoice->paid_amount;
        $invoice->balance_due = max(0, $balanceDue);

        if ($invoice->balance_due <= 0) {
            $invoice->payment_status = SupplierInvoice::STATUS_PAID;
        } elseif ((float) $invoice->paid_amount > 0) {
            $invoice->payment_status = SupplierInvoice::STATUS_PARTIALLY_PAID;
        } else {
            $invoice->payment_status = SupplierInvoice::STATUS_UNPAID;
        }

        $invoice->save();
    }

    public function processCompletedPayment(SupplierPayment $payment): SupplierPayment
    {
        if ((string) ($payment->status ?? '') !== SupplierPayment::STATUS_COMPLETED) {
            return $payment;
        }

        if ((int) ($payment->supplier_invoice_id ?? 0) > 0) {
            $this->applyPaymentToInvoice($payment);
        }

        if ((int) ($payment->document_id ?? 0) <= 0) {
            $this->recordInLedger($payment);
            $payment->refresh();
        }

        return $payment;
    }

    /**
     * ثبت در دفتر کل
     */
    protected function recordInLedger(SupplierPayment $payment): void
    {
        $baseCurrency = Currency::resolveBaseCurrencyCode('IRR');
        $payableAccountId = $this->resolvePayableAccountId($payment);
        $fxDifference = $this->calculateRealizedFxDifference($payment);
        if ((float) ($payment->fx_difference_irr ?? 0) !== $fxDifference) {
            $payment->forceFill(['fx_difference_irr' => $fxDifference])->saveQuietly();
        }

        $document = $this->ledgerService->createDocument([
            'document_type' => 'supplier_payment',
            'document_number' => $this->generatePaymentNumber($payment),
            'document_date' => $payment->payment_date,
            'description' => trans('accounting::accounting.supplier_payment_description', [
                'supplier' => $payment->supplier->name ?? '#' . $payment->supplier_id,
                'amount' => number_format($payment->amount),
            ]),
            'store_id' => $payment->store_id,
        ]);

        // Debit: Accounts Payable (کاهش بدهی)
        $this->ledgerService->recordEntry([
            'document_id' => $document->id,
            'entry_date' => $payment->payment_date,
            'account_id' => $payableAccountId,
            'debit' => $payment->amount,
            'credit' => 0,
            'description' => 'پرداخت به تامین‌کننده',
            'currency_code' => $payment->currency_code,
            'reference_type' => 'supplier_payment',
            'reference_id' => $payment->id,
            'store_id' => $payment->store_id,
        ]);

        // Credit: Bank/Cash (کاهش دارایی)
        $this->ledgerService->recordEntry([
            'document_id' => $document->id,
            'entry_date' => $payment->payment_date,
            'account_code' => $this->getPaymentAccountCode($payment),
            'debit' => 0,
            'credit' => $payment->amount,
            'description' => 'پرداخت از ' . $this->getPaymentMethodName($payment),
            'currency_code' => $payment->currency_code,
            'reference_type' => 'supplier_payment',
            'reference_id' => $payment->id,
            'store_id' => $payment->store_id,
        ]);

        if (abs($fxDifference) > 0.0001) {
            $absDiff = abs($fxDifference);
            $isLoss = $fxDifference > 0;
            $fxSettlementMode = $this->resolveFxSettlementMode();
            $fxAccountCode = $fxSettlementMode === 'single_account'
                ? $this->resolveFxDifferenceAccountCode()
                : $this->resolveFxGainLossAccountCode(! $isLoss);

            // Revalue AP in base currency so invoice AP base is fully settled.
            $this->ledgerService->recordEntry([
                'document_id' => $document->id,
                'entry_date' => $payment->payment_date,
                'account_id' => $payableAccountId,
                'debit' => $isLoss ? 0 : $absDiff,
                'credit' => $isLoss ? $absDiff : 0,
                'description' => $isLoss ? 'تعدیل تسعیر بدهی تامین‌کننده (زیان)' : 'تعدیل تسعیر بدهی تامین‌کننده (سود)',
                'currency_code' => $baseCurrency,
                'reference_type' => 'supplier_payment',
                'reference_id' => $payment->id,
                'store_id' => $payment->store_id,
            ]);

            // Realized FX gain/loss line.
            $this->ledgerService->recordEntry([
                'document_id' => $document->id,
                'entry_date' => $payment->payment_date,
                'account_code' => $fxAccountCode,
                'debit' => $isLoss ? $absDiff : 0,
                'credit' => $isLoss ? 0 : $absDiff,
                'description' => $isLoss ? 'زیان تسعیر تحقق‌یافته پرداخت تامین‌کننده' : 'سود تسعیر تحقق‌یافته پرداخت تامین‌کننده',
                'currency_code' => $baseCurrency,
                'reference_type' => 'supplier_payment',
                'reference_id' => $payment->id,
                'store_id' => $payment->store_id,
            ]);
        }

        // Post the document
        $this->ledgerService->postDocument($document);
        $payment->forceFill(['document_id' => (int) $document->id])->saveQuietly();
    }

    /**
     * دریافت کد حساب پرداخت
     */
    protected function getPaymentAccountCode(SupplierPayment $payment): string
    {
        if ((int) $payment->cheque_id) {
            $clearingId = $this->chequeLedgerService->resolvePayableClearingAccountId();
            if ($clearingId) {
                $code = Account::query()->whereKey($clearingId)->value('code');
                if ($code) {
                    return (string) $code;
                }
            }
        }

        // Check payment method to determine account
        $method = $payment->paymentMethod;

        if ($method && $method->code === 'cash') {
            return '1010'; // Cash
        }

        if ($payment->bank_id) {
            $bank = \RMS\Accounting\Models\Bank::find($payment->bank_id);
            if ($bank && $bank->account_id) {
                $code = Account::query()->whereKey($bank->account_id)->value('code');
                if ($code) {
                    return (string) $code;
                }
            }
        }

        if ((int) ($payment->wallet_id ?? 0) > 0) {
            $wallet = \RMS\Accounting\Models\Wallet::query()->find((int) $payment->wallet_id);
            if ($wallet && (int) ($wallet->account_id ?? 0) > 0) {
                $code = Account::query()->whereKey((int) $wallet->account_id)->value('code');
                if ($code) {
                    return (string) $code;
                }
            }
        }

        return '1020'; // Default to bank
    }

    protected function resolvePayableAccountId(SupplierPayment $payment): int
    {
        $supplier = $payment->supplier;
        if (! $supplier && (int) ($payment->supplier_id ?? 0) > 0) {
            $supplier = \RMS\Accounting\Models\Supplier::query()->find((int) $payment->supplier_id);
        }

        if ($supplier && (int) ($supplier->party_id ?? 0) > 0) {
            $account = $this->partyService->getOrCreateSupplierAccount((int) $supplier->party_id);
            if ((int) ($account->id ?? 0) > 0) {
                return (int) $account->id;
            }
        }

        if ($supplier && (int) ($supplier->account_id ?? 0) > 0) {
            return (int) $supplier->account_id;
        }

        $preferredCode = trim((string) config('accounting.accounts.accounts_payable', ''));
        if ($preferredCode !== '') {
            $preferredId = (int) Account::query()->where('code', $preferredCode)->value('id');
            if ($preferredId > 0) {
                return $preferredId;
            }
        }

        $fallbackId = (int) Account::query()
            ->whereIn('code', ['2010', '2010-001'])
            ->orderByRaw("CASE WHEN code = '2010' THEN 0 ELSE 1 END")
            ->value('id');
        if ($fallbackId > 0) {
            return $fallbackId;
        }

        return (int) Account::query()
            ->where('account_type', Account::TYPE_LIABILITY)
            ->orderBy('id')
            ->value('id');
    }

    /**
     * دریافت نام روش پرداخت
     */
    protected function getPaymentMethodName(SupplierPayment $payment): string
    {
        if ($payment->paymentMethod) {
            return $payment->paymentMethod->name;
        }

        return 'نامشخص';
    }

    /**
     * تولید شماره پرداخت
     */
    protected function generatePaymentNumber(SupplierPayment $payment): string
    {
        return 'SP-' . $payment->id . '-' . Carbon::now()->format('YmdHis');
    }

    protected function calculateRealizedFxDifference(SupplierPayment $payment): float
    {
        $invoice = $payment->supplierInvoice;
        if (! $invoice) {
            return 0.0;
        }

        $settledAmount = (float) ($payment->amount ?? 0);
        if ($settledAmount <= 0) {
            return 0.0;
        }

        $basePaid = (float) ($payment->amount_base_at_payment ?? 0);
        if ($basePaid <= 0) {
            $basePaid = $settledAmount * (float) ($payment->fx_rate_at_payment ?? 1);
        }

        $invoiceFx = (float) ($invoice->fx_rate_at_invoice ?? 0);
        if ($invoiceFx <= 0) {
            $invoiceAmount = (float) ($invoice->total_amount ?? 0);
            if ($invoiceAmount > 0) {
                $invoiceFx = (float) ($invoice->amount_base_at_invoice ?? 0) / $invoiceAmount;
            }
        }
        if ($invoiceFx <= 0) {
            $invoiceFx = 1.0;
        }

        $baseAtInvoice = $settledAmount * $invoiceFx;
        $diff = round($basePaid - $baseAtInvoice, 4);

        return abs($diff) < 0.0001 ? 0.0 : $diff;
    }

    protected function resolveFxGainLossAccountCode(bool $isGain): string
    {
        $settingKey = $isGain
            ? 'accounting.system_accounts.gains.fx_gain'
            : 'accounting.system_accounts.expenses.fx_loss';
        $fallback = $isGain
            ? config('accounting.system_accounts.gains.fx_gain')
            : config('accounting.system_accounts.expenses.fx_loss');
        $code = strtoupper(trim((string) Setting::get($settingKey, $fallback)));
        if ($code === '') {
            throw new \RuntimeException(
                $isGain
                    ? 'حساب سود تسعیر یافت نشد. برای تکمیل mapping به __ACCOUNTING_SETTINGS_URL__ مراجعه کنید.'
                    : 'حساب زیان تسعیر یافت نشد. برای تکمیل mapping به __ACCOUNTING_SETTINGS_URL__ مراجعه کنید.'
            );
        }

        return $code;
    }

    protected function resolveFxDifferenceAccountCode(): string
    {
        $code = strtoupper(trim((string) Setting::get('accounting.system_accounts.fx_difference.account', '')));
        if ($code === '') {
            throw new \RuntimeException('حساب تفاوت تسعیر یافت نشد. برای تکمیل mapping به __ACCOUNTING_SETTINGS_URL__ مراجعه کنید.');
        }

        return $code;
    }

    protected function resolveFxSettlementMode(): string
    {
        $mode = strtolower(trim((string) Setting::get('accounting.fx.settlement_mode', 'split_accounts')));
        return in_array($mode, ['single_account', 'split_accounts'], true) ? $mode : 'split_accounts';
    }

    /**
     * لغو پرداخت
     */
    public function voidPayment(SupplierPayment $payment, string $reason = null): SupplierPayment
    {
        try {
            DB::beginTransaction();

            // Reverse invoice payment
            if ($payment->supplier_invoice_id) {
                $invoice = $payment->supplierInvoice;
                if ($invoice) {
                    $invoice->paid_amount = max(0, ((float) ($invoice->paid_amount ?? 0)) - (float) $payment->amount);
                    $balanceDue = (float) $invoice->total_amount - (float) $invoice->paid_amount;
                    $invoice->balance_due = max(0, $balanceDue);
                    if ($invoice->balance_due <= 0) {
                        $invoice->payment_status = SupplierInvoice::STATUS_PAID;
                    } elseif ((float) $invoice->paid_amount > 0) {
                        $invoice->payment_status = SupplierInvoice::STATUS_PARTIALLY_PAID;
                    } else {
                        $invoice->payment_status = SupplierInvoice::STATUS_UNPAID;
                    }
                    $invoice->save();
                }
            }

            // Reverse ledger entry
            $this->reverseLedgerEntry($payment);

            // Update payment status
            $payment->status = SupplierPayment::STATUS_VOIDED;
            $payment->voided_at = Carbon::now();
            $payment->void_reason = $reason;
            $payment->save();

            DB::commit();
            return $payment;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * برگشت ثبت دفتر
     */
    protected function reverseLedgerEntry(SupplierPayment $payment): void
    {
        $originalEntries = FinancialLedger::where('reference_type', 'supplier_payment')
            ->where('reference_id', $payment->id)
            ->get();

        if ($originalEntries->isEmpty()) {
            return;
        }

        $document = $this->ledgerService->createDocument([
            'document_type' => 'supplier_payment_reversal',
            'document_number' => 'REV-SP-' . $payment->id,
            'document_date' => Carbon::now(),
            'description' => 'برگشت پرداخت به تامین‌کننده',
            'store_id' => $payment->store_id,
        ]);

        foreach ($originalEntries as $entry) {
            $this->ledgerService->recordEntry([
                'document_id' => $document->id,
                'entry_date' => Carbon::now(),
                'account_code' => $entry->account_code,
                'debit' => $entry->credit, // Reverse: swap debit/credit
                'credit' => $entry->debit,
                'description' => 'برگشت: ' . $entry->description,
                'currency_code' => $entry->currency_code,
                'reference_type' => 'supplier_payment_reversal',
                'reference_id' => $payment->id,
                'store_id' => $payment->store_id,
            ]);
        }

        $this->ledgerService->postDocument($document);
    }

    /**
     * دریافت تاریخچه پرداخت‌های تامین‌کننده
     */
    public function getSupplierPaymentHistory(int $supplierId, int $storeId = null)
    {
        $query = SupplierPayment::where('supplier_id', $supplierId)
            ->whereIn('status', [SupplierPayment::STATUS_COMPLETED, SupplierPayment::STATUS_PENDING])
            ->orderBy('payment_date', 'desc');

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        return $query->get();
    }

    /**
     * محاسبه موجودی تامین‌کننده
     */
    public function getSupplierBalance(int $supplierId, int $storeId = null): array
    {
        $invoicesQuery = SupplierInvoice::where('supplier_id', $supplierId);
        $paymentsQuery = SupplierPayment::where('supplier_id', $supplierId);

        if ($storeId) {
            $invoicesQuery->where('store_id', $storeId);
            $paymentsQuery->where('store_id', $storeId);
        }

        $totalInvoiced = $invoicesQuery->sum('total_amount');
        $totalPaid = $paymentsQuery->where('status', 'completed')->sum('amount');

        return [
            'total_invoiced' => $totalInvoiced,
            'total_paid' => $totalPaid,
            'balance' => $totalInvoiced - $totalPaid,
        ];
    }

    /**
     * اصلاح immutable پرداخت تامین‌کننده (reverse + repost)
     *
     * @param array<string,mixed> $data
     * @return array{original_payment: SupplierPayment, new_payment: SupplierPayment, reversal_document: AccountingDocument}
     */
    public function correctPayment(SupplierPayment $originalPayment, array $data): array
    {
        return DB::transaction(function () use ($originalPayment, $data) {
            if ((string) $originalPayment->status !== SupplierPayment::STATUS_COMPLETED) {
                throw new \DomainException('Only completed supplier payments can be corrected.');
            }
            if (!(int) ($originalPayment->document_id ?? 0)) {
                throw new \DomainException('Original supplier payment has no accounting document.');
            }
            if (
                Schema::hasColumn('supplier_payments', 'original_supplier_payment_id')
                && (int) ($originalPayment->original_supplier_payment_id ?? 0) > 0
            ) {
                throw new \DomainException('Chained correction is not allowed.');
            }

            $reason = trim((string) ($data['reason'] ?? ''));
            if ($reason === '') {
                throw new \DomainException('Correction reason is required.');
            }

            $reversalDocument = $this->ledgerService->reverseDocument((int) $originalPayment->document_id, $reason);
            $auditContext = $this->auditContext();

            $originalPayment->status = SupplierPayment::STATUS_REVERSED;
            $originalPayment->notes = trim((string) ($originalPayment->notes ?? '')) !== ''
                ? trim((string) $originalPayment->notes) . "\n[CORRECTION] " . $reason
                : '[CORRECTION] ' . $reason;
            if (Schema::hasColumn('supplier_payments', 'correction_reason')) {
                $originalPayment->correction_reason = $reason;
            }
            if (Schema::hasColumn('supplier_payments', 'correction_document_id')) {
                $originalPayment->correction_document_id = (int) $reversalDocument->id;
            }
            if (Schema::hasColumn('supplier_payments', 'correction_group_id') && !empty($data['correction_group_id'])) {
                $originalPayment->correction_group_id = (string) $data['correction_group_id'];
            }
            if (Schema::hasColumn('supplier_payments', 'corrected_by_user_id')) {
                $originalPayment->corrected_by_user_id = $auditContext->userId;
            }
            if (Schema::hasColumn('supplier_payments', 'corrected_by_admin_id')) {
                $originalPayment->corrected_by_admin_id = $auditContext->adminId;
            }
            if (Schema::hasColumn('supplier_payments', 'corrected_at')) {
                $originalPayment->corrected_at = now();
            }
            $originalPayment->save();

            $newNotes = trim((string) ($data['new_notes'] ?? ''));
            $prefix = '[CORRECTION-REPOST] ' . $reason;
            $newNotes = $newNotes !== '' ? ($prefix . "\n" . $newNotes) : $prefix;
            $newPayment = $this->recordPayment([
                'supplier_id' => (int) $originalPayment->supplier_id,
                'store_id' => (int) ($data['store_id'] ?? $originalPayment->store_id ?? 0),
                'supplier_invoice_id' => (int) ($data['supplier_invoice_id'] ?? $originalPayment->supplier_invoice_id ?? 0) ?: null,
                'payment_method_id' => (int) $data['new_payment_method_id'],
                'amount' => (float) $data['new_amount'],
                'currency_code' => (string) ($data['new_currency_code'] ?? $originalPayment->currency_code ?? 'IRR'),
                'fx_rate' => (float) ($data['new_fx_rate'] ?? $originalPayment->fx_rate_at_payment ?? 1),
                'amount_base' => (float) ($data['new_amount_base'] ?? ((float) $data['new_amount'] * (float) ($data['new_fx_rate'] ?? 1))),
                'payment_date' => (string) ($data['new_payment_date'] ?? $originalPayment->payment_date?->format('Y-m-d') ?? now()->toDateString()),
                'bank_id' => (int) ($data['new_bank_id'] ?? 0) ?: null,
                'cash_box_id' => (int) ($data['new_cash_box_id'] ?? 0) ?: null,
                'status' => SupplierPayment::STATUS_COMPLETED,
                'notes' => $newNotes,
            ]);

            if (Schema::hasColumn('supplier_payments', 'original_supplier_payment_id')) {
                $newPayment->original_supplier_payment_id = (int) $originalPayment->id;
            }
            if (Schema::hasColumn('supplier_payments', 'correction_reason')) {
                $newPayment->correction_reason = $reason;
            }
            if (Schema::hasColumn('supplier_payments', 'correction_group_id') && !empty($data['correction_group_id'])) {
                $newPayment->correction_group_id = (string) $data['correction_group_id'];
            }
            if (Schema::hasColumn('supplier_payments', 'corrected_by_user_id')) {
                $newPayment->corrected_by_user_id = $auditContext->userId;
            }
            if (Schema::hasColumn('supplier_payments', 'corrected_by_admin_id')) {
                $newPayment->corrected_by_admin_id = $auditContext->adminId;
            }
            if (Schema::hasColumn('supplier_payments', 'corrected_at')) {
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
}
