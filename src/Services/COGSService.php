<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\CostEntry;
use Illuminate\Support\Facades\DB;

/**
 * سرویس مدیریت بهای تمام شده (COGS)
 */
class COGSService
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
     * ثبت بهای تمام شده برای فروش
     */
    public function recordCOGS(array $data): CostEntry
    {
        DB::beginTransaction();
        try {
            // اگر source_supplier_id یا source_supplier_invoice_id در data نیست،
            // باید از inventory tracking شناسایی شود
            // این بخش باید با inventory adapter یکپارچه شود
            // فعلاً فقط داده‌های ارسالی را ثبت می‌کنیم
            
            // ذخیره store_id برای استفاده در recordInLedger
            $this->storeId = $data['store_id'] ?? 0;
            
            // ایجاد رکورد COGS
            $costEntry = CostEntry::create($data);

            // ثبت در دفتر کل
            $this->recordInLedger($costEntry, $data);

            DB::commit();
            return $costEntry;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * ثبت COGS در دفتر کل
     */
    protected function recordInLedger(CostEntry $costEntry, array $data = []): void
    {
        // استفاده از total_cost به جای cost_amount (مطابق با migration)
        $costAmount = $costEntry->total_cost ?? 0;
        $storeId = $data['store_id'] ?? 0; // از data که به recordCOGS ارسال شده
        
        // ایجاد سند حسابداری
        $document = $this->documentService->createDocument([
            'document_type' => 'cogs',
            'store_id' => $storeId,
            'fiscal_year_id' => config('accounting.current_fiscal_year_id'),
            'reference_type' => CostEntry::class,
            'reference_id' => $costEntry->id,
            'description' => "بهای تمام شده - {$costEntry->reference_type}",
            'total_debit' => $costAmount,
            'total_credit' => $costAmount,
        ]);

        // آرتیکل بدهکار: بهای تمام شده کالای فروش رفته
        $this->ledgerService->recordEntry([
            'event_type' => 'cogs',
            'event_source' => 'system',
            'source_reference_type' => CostEntry::class,
            'source_reference_id' => $costEntry->id,
            'store_id' => $storeId,
            'account_id' => config('accounting.accounts.cogs'),
            'currency_code' => $costEntry->currency_code ?? 'IRR',
            'debit_amount' => $costAmount,
            'credit_amount' => 0,
            'fx_rate_to_base' => $costEntry->fx_rate ?? 1,
            'accounting_document_id' => $document->id,
            'description' => "بهای تمام شده",
        ]);

        // آرتیکل بستانکار: موجودی کالا
        $this->ledgerService->recordEntry([
            'event_type' => 'cogs',
            'event_source' => 'system',
            'source_reference_type' => CostEntry::class,
            'source_reference_id' => $costEntry->id,
            'store_id' => $storeId,
            'account_id' => config('accounting.accounts.inventory'),
            'currency_code' => $costEntry->currency_code ?? 'IRR',
            'debit_amount' => 0,
            'credit_amount' => $costAmount,
            'fx_rate_to_base' => $costEntry->fx_rate ?? 1,
            'accounting_document_id' => $document->id,
            'description' => "کاهش موجودی کالا",
        ]);

        // ثبت قطعی سند
        $this->documentService->postDocument($document->id);
    }

    /**
     * محاسبه بهای تمام شده با روش FIFO
     */
    public function calculateFIFO(int $productId, float $quantity, int $storeId): float
    {
        // منطق محاسبه FIFO
        // این بخش باید با inventory یکپارچه شود
        return 0;
    }

    /**
     * محاسبه بهای تمام شده با روش میانگین موزون
     */
    public function calculateWeightedAverage(int $productId, float $quantity, int $storeId): float
    {
        // منطق محاسبه میانگین موزون
        // این بخش باید با inventory یکپارچه شود
        return 0;
    }

    /**
     * دریافت مجموع COGS برای یک دوره
     */
    public function getTotalCOGS(?string $fromDate = null, ?string $toDate = null, ?int $storeId = null): float
    {
        $query = CostEntry::query();

        if ($fromDate) {
            $query->whereDate('created_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        // Note: store_id در cost_entries وجود ندارد، اگر نیاز باشد باید از reference_type/reference_id استفاده کرد

        return $query->sum('total_cost');
    }
}
