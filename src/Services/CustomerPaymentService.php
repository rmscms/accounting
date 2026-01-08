<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\CustomerPayment;
use RMS\Accounting\Models\CustomerInvoice;
use Illuminate\Support\Facades\DB;

/**
 * سرویس مدیریت دریافت‌های مشتریان
 * - ثبت دریافت از مشتری
 * - تسویه فاکتورها
 * - ثبت در دفتر کل
 */
class CustomerPaymentService
{
    protected LedgerService $ledgerService;
    protected DocumentService $documentService;
    protected CustomerInvoiceService $invoiceService;

    public function __construct(
        LedgerService $ledgerService,
        DocumentService $documentService,
        CustomerInvoiceService $invoiceService
    ) {
        $this->ledgerService = $ledgerService;
        $this->documentService = $documentService;
        $this->invoiceService = $invoiceService;
    }

    /**
     * ثبت دریافت از مشتری
     */
    public function createPayment(array $data): CustomerPayment
    {
        DB::beginTransaction();
        try {
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
                $this->updateInvoicePaymentStatus($payment->customer_invoice_id);
            }

            DB::commit();
            return $payment;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * ثبت دریافت در دفتر کل
     */
    protected function recordPaymentInLedger(CustomerPayment $payment): void
    {
        // ایجاد سند حسابداری
        $document = $this->documentService->createDocument([
            'document_type' => 'receipt',
            'store_id' => $payment->store_id,
            'fiscal_year_id' => config('accounting.current_fiscal_year_id'),
            'reference_type' => CustomerPayment::class,
            'reference_id' => $payment->id,
            'description' => "دریافت از مشتری {$payment->payment_number}",
            'total_debit' => $payment->amount,
            'total_credit' => $payment->amount,
        ]);

        // تعیین حساب دریافت کننده (بانک/صندوق/POS/...)
        $destinationAccountId = $this->getPaymentDestinationAccount($payment);

        // آرتیکل بدهکار: حساب بانک/صندوق
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
            'fx_rate_to_irr' => $payment->fx_rate_at_payment,
            'accounting_document_id' => $document->id,
            'description' => "دریافت {$payment->payment_number}",
        ]);

        // آرتیکل بستانکار: حساب مشتری (کاهش بدهی)
        $this->ledgerService->recordEntry([
            'event_type' => 'payment_received',
            'event_source' => 'customer',
            'source_reference_type' => CustomerPayment::class,
            'source_reference_id' => $payment->id,
            'store_id' => $payment->store_id,
            'account_id' => config('accounting.accounts.accounts_receivable'),
            'currency_code' => $payment->currency_code,
            'debit_amount' => 0,
            'credit_amount' => $payment->amount,
            'fx_rate_to_irr' => $payment->fx_rate_at_payment,
            'accounting_document_id' => $document->id,
            'description' => "دریافت {$payment->payment_number}",
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
        if ($payment->bank_id) {
            $bank = \RMS\Accounting\Models\Bank::find($payment->bank_id);
            return $bank->account_id ?? config('accounting.accounts.bank_default');
        }

        if ($payment->cash_box_id) {
            $cashBox = \RMS\Accounting\Models\CashBox::find($payment->cash_box_id);
            return $cashBox->account_id ?? config('accounting.accounts.cash_box_default');
        }

        if ($payment->pos_terminal_id) {
            return config('accounting.accounts.pos_terminal');
        }

        return config('accounting.accounts.cash_box_default');
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
            'fx_rate_to_irr' => 1,
            'accounting_document_id' => $document->id,
            'description' => 'اختلاف نرخ ارز',
        ]);
    }

    /**
     * بروزرسانی وضعیت پرداخت فاکتور
     */
    protected function updateInvoicePaymentStatus(int $invoiceId): void
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
}
