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
            // ایجاد رکورد COGS
            $costEntry = CostEntry::create($data);

            // ثبت در دفتر کل
            $this->recordInLedger($costEntry);

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
    protected function recordInLedger(CostEntry $costEntry): void
    {
        // ایجاد سند حسابداری
        $document = $this->documentService->createDocument([
            'document_type' => 'cogs',
            'store_id' => $costEntry->store_id,
            'fiscal_year_id' => config('accounting.current_fiscal_year_id'),
            'reference_type' => CostEntry::class,
            'reference_id' => $costEntry->id,
            'description' => "بهای تمام شده - {$costEntry->reference_type}",
            'total_debit' => $costEntry->cost_amount,
            'total_credit' => $costEntry->cost_amount,
        ]);

        // آرتیکل بدهکار: بهای تمام شده کالای فروش رفته
        $this->ledgerService->recordEntry([
            'event_type' => 'cogs',
            'event_source' => 'system',
            'source_reference_type' => CostEntry::class,
            'source_reference_id' => $costEntry->id,
            'store_id' => $costEntry->store_id,
            'account_id' => config('accounting.accounts.cogs'),
            'currency_code' => 'IRR',
            'debit_amount' => $costEntry->cost_amount,
            'credit_amount' => 0,
            'fx_rate_to_irr' => 1,
            'accounting_document_id' => $document->id,
            'description' => "بهای تمام شده",
        ]);

        // آرتیکل بستانکار: موجودی کالا
        $this->ledgerService->recordEntry([
            'event_type' => 'cogs',
            'event_source' => 'system',
            'source_reference_type' => CostEntry::class,
            'source_reference_id' => $costEntry->id,
            'store_id' => $costEntry->store_id,
            'account_id' => config('accounting.accounts.inventory'),
            'currency_code' => 'IRR',
            'debit_amount' => 0,
            'credit_amount' => $costEntry->cost_amount,
            'fx_rate_to_irr' => 1,
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
            $query->whereDate('cost_date', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('cost_date', '<=', $toDate);
        }

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        return $query->sum('cost_amount');
    }
}
