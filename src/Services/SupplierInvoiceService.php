<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\SupplierInvoice;
use RMS\Accounting\Models\SupplierInvoiceItem;
use Illuminate\Support\Facades\DB;

/**
 * سرویس مدیریت فاکتورهای خرید
 */
class SupplierInvoiceService
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
     * ثبت فاکتور خرید
     */
    public function createInvoice(array $data, array $items = []): SupplierInvoice
    {
        DB::beginTransaction();
        try {
            // تولید شماره فاکتور
            if (empty($data['invoice_number'])) {
                $data['invoice_number'] = $this->generateInvoiceNumber($data['store_id']);
            }

            // ایجاد فاکتور
            $invoice = SupplierInvoice::create($data);

            // ثبت آیتم‌ها
            foreach ($items as $item) {
                SupplierInvoiceItem::create([
                    'supplier_invoice_id' => $invoice->id,
                    'product_id' => $item['product_id'] ?? null,
                    'product_sku' => $item['product_sku'] ?? null,
                    'product_name' => $item['product_name'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => $item['tax_rate'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'total_price' => $item['total_price'],
                ]);
            }

            // ثبت در دفتر کل
            $this->recordInvoiceInLedger($invoice);

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
    protected function recordInvoiceInLedger(SupplierInvoice $invoice): void
    {
        // ایجاد سند حسابداری
        $document = $this->documentService->createDocument([
            'document_type' => 'purchase',
            'store_id' => $invoice->store_id,
            'fiscal_year_id' => config('accounting.current_fiscal_year_id'),
            'reference_type' => SupplierInvoice::class,
            'reference_id' => $invoice->id,
            'description' => "فاکتور خرید {$invoice->invoice_number}",
            'total_debit' => $invoice->total_amount,
            'total_credit' => $invoice->total_amount,
        ]);

        // آرتیکل بدهکار: حساب موجودی کالا یا هزینه
        $this->ledgerService->recordEntry([
            'event_type' => 'purchase',
            'event_source' => 'supplier',
            'source_reference_type' => SupplierInvoice::class,
            'source_reference_id' => $invoice->id,
            'store_id' => $invoice->store_id,
            'account_id' => config('accounting.accounts.inventory'),
            'currency_code' => $invoice->currency_code,
            'debit_amount' => $invoice->subtotal,
            'credit_amount' => 0,
            'fx_rate_to_irr' => $invoice->fx_rate_at_invoice,
            'accounting_document_id' => $document->id,
            'description' => "خرید کالا {$invoice->invoice_number}",
        ]);

        // آرتیکل بستانکار: حساب تامین‌کننده
        $this->ledgerService->recordEntry([
            'event_type' => 'purchase',
            'event_source' => 'supplier',
            'source_reference_type' => SupplierInvoice::class,
            'source_reference_id' => $invoice->id,
            'store_id' => $invoice->store_id,
            'account_id' => config('accounting.accounts.accounts_payable'),
            'currency_code' => $invoice->currency_code,
            'debit_amount' => 0,
            'credit_amount' => $invoice->total_amount,
            'fx_rate_to_irr' => $invoice->fx_rate_at_invoice,
            'accounting_document_id' => $document->id,
            'description' => "بدهی به تامین‌کننده {$invoice->invoice_number}",
        ]);

        // ثبت قطعی سند
        $this->documentService->postDocument($document->id);

        // ذخیره شماره سند
        $invoice->update(['document_id' => $document->id]);
    }

    /**
     * تولید شماره فاکتور
     */
    protected function generateInvoiceNumber(int $storeId): string
    {
        $year = date('Y');
        $month = date('m');

        $lastInvoice = SupplierInvoice::where('store_id', $storeId)
            ->whereYear('invoice_date', $year)
            ->whereMonth('invoice_date', $month)
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastInvoice ? intval(substr($lastInvoice->invoice_number, -6)) + 1 : 1;

        return sprintf('PINV-%d-%s%s-%06d', $storeId, $year, $month, $nextNumber);
    }

    /**
     * بروزرسانی وضعیت پرداخت
     */
    public function updatePaymentStatus(int $invoiceId): void
    {
        $invoice = SupplierInvoice::findOrFail($invoiceId);

        $totalPaid = \RMS\Accounting\Models\SupplierPayment::where('supplier_invoice_id', $invoiceId)
            ->where('status', 'completed')
            ->sum('amount');

        $balanceDue = $invoice->total_amount - $totalPaid;

        $paymentStatus = match (true) {
            $totalPaid == 0 => SupplierInvoice::STATUS_UNPAID,
            $totalPaid >= $invoice->total_amount => SupplierInvoice::STATUS_PAID,
            default => SupplierInvoice::STATUS_PARTIALLY_PAID,
        };

        $invoice->update([
            'paid_amount' => $totalPaid,
            'balance_due' => $balanceDue,
            'payment_status' => $paymentStatus,
        ]);
    }
}
