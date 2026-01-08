<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\CustomerInvoice;
use RMS\Accounting\Models\CustomerBalance;
use Illuminate\Support\Facades\DB;

/**
 * سرویس مدیریت فاکتورهای مشتریان
 * - ثبت فاکتور فروش
 * - بروزرسانی مانده مشتری
 * - ثبت در دفتر کل
 */
class CustomerInvoiceService
{
    protected LedgerService $ledgerService;
    protected DocumentService $documentService;

    public function __construct(
        LedgerService $ledgerService,
        DocumentService $documentService
    ) {
        $this->ledgerService = $ledgerService;
        $this->documentService = $documentService;
    }

    /**
     * ثبت فاکتور فروش
     */
    public function createInvoice(array $data): CustomerInvoice
    {
        DB::beginTransaction();
        try {
            // تولید شماره فاکتور
            if (empty($data['invoice_number'])) {
                $data['invoice_number'] = $this->generateInvoiceNumber($data['store_id']);
            }

            // ایجاد فاکتور
            $invoice = CustomerInvoice::create($data);

            // ثبت در دفتر کل (اگر وضعیت صادر شده باشد)
            if ($invoice->status === CustomerInvoice::STATUS_ISSUED) {
                $this->recordInvoiceInLedger($invoice);
            }

            // بروزرسانی مانده مشتری
            $this->updateCustomerBalance($invoice->customer_id, $invoice->store_id);

            DB::commit();
            return $invoice;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * ثبت فاکتور در دفتر کل
     */
    protected function recordInvoiceInLedger(CustomerInvoice $invoice): void
    {
        // ایجاد سند حسابداری
        $document = $this->documentService->createDocument([
            'document_type' => 'sale',
            'store_id' => $invoice->store_id,
            'fiscal_year_id' => config('accounting.current_fiscal_year_id'),
            'reference_type' => CustomerInvoice::class,
            'reference_id' => $invoice->id,
            'description' => "فاکتور فروش {$invoice->invoice_number} - مشتری {$invoice->customer_id}",
            'total_debit' => $invoice->total_amount,
            'total_credit' => $invoice->total_amount,
        ]);

        // آرتیکل بدهکار: حساب مشتری (بدهکار)
        $this->ledgerService->recordEntry([
            'event_type' => 'sale',
            'event_source' => 'shop',
            'source_reference_type' => CustomerInvoice::class,
            'source_reference_id' => $invoice->id,
            'store_id' => $invoice->store_id,
            'account_id' => config('accounting.accounts.accounts_receivable'),
            'currency_code' => $invoice->currency_code,
            'debit_amount' => $invoice->total_amount,
            'credit_amount' => 0,
            'fx_rate_to_irr' => $invoice->fx_rate_at_invoice,
            'accounting_document_id' => $document->id,
            'description' => "فاکتور فروش {$invoice->invoice_number}",
        ]);

        // آرتیکل بستانکار: حساب فروش (بستانکار)
        $this->ledgerService->recordEntry([
            'event_type' => 'sale',
            'event_source' => 'shop',
            'source_reference_type' => CustomerInvoice::class,
            'source_reference_id' => $invoice->id,
            'store_id' => $invoice->store_id,
            'account_id' => config('accounting.accounts.sales_revenue'),
            'currency_code' => $invoice->currency_code,
            'debit_amount' => 0,
            'credit_amount' => $invoice->subtotal,
            'fx_rate_to_irr' => $invoice->fx_rate_at_invoice,
            'accounting_document_id' => $document->id,
            'description' => "درآمد فروش {$invoice->invoice_number}",
        ]);

        // اگر مالیات دارد
        if ($invoice->tax_amount > 0) {
            $this->ledgerService->recordEntry([
                'event_type' => 'sale',
                'event_source' => 'shop',
                'source_reference_type' => CustomerInvoice::class,
                'source_reference_id' => $invoice->id,
                'store_id' => $invoice->store_id,
                'account_id' => config('accounting.accounts.vat_payable'),
                'currency_code' => $invoice->currency_code,
                'debit_amount' => 0,
                'credit_amount' => $invoice->tax_amount,
                'fx_rate_to_irr' => $invoice->fx_rate_at_invoice,
                'accounting_document_id' => $document->id,
                'description' => "مالیات فروش {$invoice->invoice_number}",
            ]);
        }

        // ثبت قطعی سند
        $this->documentService->postDocument($document->id);

        // ذخیره شماره سند در فاکتور
        $invoice->update(['document_id' => $document->id]);
    }

    /**
     * بروزرسانی مانده مشتری
     */
    protected function updateCustomerBalance(int $customerId, int $storeId): void
    {
        $totalInvoices = CustomerInvoice::where('customer_id', $customerId)
            ->where('store_id', $storeId)
            ->where('status', '!=', CustomerInvoice::STATUS_CANCELLED)
            ->sum('total_amount');

        $totalPayments = DB::table('customer_payments')
            ->where('customer_id', $customerId)
            ->where('store_id', $storeId)
            ->where('status', 'completed')
            ->sum('amount');

        CustomerBalance::updateOrCreate(
            [
                'customer_id' => $customerId,
                'store_id' => $storeId,
            ],
            [
                'total_invoices' => $totalInvoices,
                'total_payments' => $totalPayments,
                'balance' => $totalInvoices - $totalPayments,
                'last_invoice_date' => now(),
            ]
        );
    }

    /**
     * تولید شماره فاکتور
     */
    protected function generateInvoiceNumber(int $storeId): string
    {
        $year = date('Y');
        $month = date('m');

        $lastInvoice = CustomerInvoice::where('store_id', $storeId)
            ->whereYear('invoice_date', $year)
            ->whereMonth('invoice_date', $month)
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastInvoice ? intval(substr($lastInvoice->invoice_number, -6)) + 1 : 1;

        return sprintf('INV-%d-%s%s-%06d', $storeId, $year, $month, $nextNumber);
    }

    /**
     * دریافت فاکتورهای معوق
     */
    public function getOverdueInvoices(?int $storeId = null)
    {
        $query = CustomerInvoice::overdue();

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        return $query->with('customer')->get();
    }
}
