<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\SupplierPayment;
use RMS\Accounting\Models\SupplierInvoice;
use RMS\Accounting\Models\FinancialLedger;
use RMS\Accounting\Events\SupplierPaymentMadeEvent;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Supplier Payment Service
 * 
 * مدیریت پرداخت‌ها به تامین‌کنندگان
 */
class SupplierPaymentService
{
    protected LedgerService $ledgerService;

    public function __construct(LedgerService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
    }

    /**
     * ثبت پرداخت به تامین‌کننده
     */
    public function recordPayment(array $data): SupplierPayment
    {
        try {
            DB::beginTransaction();

            // Create payment record
            $payment = new SupplierPayment();
            $payment->store_id = $data['store_id'];
            $payment->supplier_id = $data['supplier_id'];
            $payment->supplier_invoice_id = $data['supplier_invoice_id'] ?? null;
            $payment->payment_date = $data['payment_date'] ?? Carbon::now();
            $payment->amount = $data['amount'];
            $payment->currency_code = $data['currency_code'] ?? config('accounting.default_currency');
            $payment->payment_method_id = $data['payment_method_id'];
            $payment->reference_number = $data['reference_number'] ?? null;
            $payment->cheque_number = $data['cheque_number'] ?? null;
            $payment->cheque_date = $data['cheque_date'] ?? null;
            $payment->bank_account_id = $data['bank_account_id'] ?? null;
            $payment->status = $data['status'] ?? 'completed';
            $payment->notes = $data['notes'] ?? null;
            $payment->paid_by = auth()->id();
            $payment->save();

            // Update invoice if linked
            if ($payment->supplier_invoice_id) {
                $this->applyPaymentToInvoice($payment);
            }

            // Record in ledger
            $this->recordInLedger($payment);

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
     * اعمال پرداخت به فاکتور
     */
    protected function applyPaymentToInvoice(SupplierPayment $payment): void
    {
        $invoice = SupplierInvoice::findOrFail($payment->supplier_invoice_id);

        $invoice->paid_amount = ($invoice->paid_amount ?? 0) + $payment->amount;
        $invoice->balance = $invoice->total_amount - $invoice->paid_amount;

        if ($invoice->balance <= 0) {
            $invoice->status = 'paid';
            $invoice->paid_at = Carbon::now();
        } elseif ($invoice->paid_amount > 0) {
            $invoice->status = 'partial';
        }

        $invoice->save();
    }

    /**
     * ثبت در دفتر کل
     */
    protected function recordInLedger(SupplierPayment $payment): void
    {
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
            'account_code' => '2010', // Accounts Payable
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

        // Post the document
        $this->ledgerService->postDocument($document);
    }

    /**
     * دریافت کد حساب پرداخت
     */
    protected function getPaymentAccountCode(SupplierPayment $payment): string
    {
        // Check payment method to determine account
        $method = $payment->paymentMethod;
        
        if ($method && $method->code === 'cash') {
            return '1010'; // Cash
        }
        
        if ($payment->bank_account_id) {
            return '1020'; // Bank Account
        }

        return '1020'; // Default to bank
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

    /**
     * لغو پرداخت
     */
    public function voidPayment(SupplierPayment $payment, string $reason = null): SupplierPayment
    {
        try {
            DB::beginTransaction();

            // Reverse invoice payment
            if ($payment->supplier_invoice_id) {
                $invoice = $payment->invoice;
                $invoice->paid_amount = ($invoice->paid_amount ?? 0) - $payment->amount;
                $invoice->balance = $invoice->total_amount - $invoice->paid_amount;
                $invoice->status = $invoice->balance > 0 ? 'pending' : 'paid';
                $invoice->save();
            }

            // Reverse ledger entry
            $this->reverseLedgerEntry($payment);

            // Update payment status
            $payment->status = 'voided';
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
            ->whereIn('status', ['completed', 'pending'])
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
}
