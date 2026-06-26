<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\AccountingDocument;
use RMS\Accounting\Models\FiscalYear;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RMS\Accounting\Support\InteractsWithAuditActor;

/**
 * سرویس مدیریت اسناد حسابداری
 * - ایجاد سند
 * - ثبت قطعی سند
 * - برگشت سند
 */
class DocumentService
{
    use InteractsWithAuditActor;

    protected LedgerService $ledgerService;
    protected TreasuryBalanceCacheSyncService $treasuryBalanceCacheSyncService;

    public function __construct(
        LedgerService $ledgerService,
        TreasuryBalanceCacheSyncService $treasuryBalanceCacheSyncService
    )
    {
        $this->ledgerService = $ledgerService;
        $this->treasuryBalanceCacheSyncService = $treasuryBalanceCacheSyncService;
    }

    /**
     * ایجاد سند حسابداری جدید
     */
    public function createDocument(array $data): AccountingDocument
    {
        $data['document_number'] = $data['document_number'] ?? $this->generateDocumentNumber($data['document_type']);
        $data['status'] = $data['status'] ?? 'draft';
        $data = $this->stampAudit($data, 'accounting_documents', 'created');

        return AccountingDocument::create($data);
    }

    /**
     * ثبت قطعی سند
     */
    public function postDocument(int $documentId): bool
    {
        $document = AccountingDocument::findOrFail($documentId);

        if ($document->status === 'posted') {
            throw new \Exception('سند قبلاً ثبت قطعی شده است');
        }

        $fiscalYear = FiscalYear::find($document->fiscal_year_id);
        if ($fiscalYear && !$fiscalYear->isOpen()) {
            throw new \Exception('سال مالی بسته است و امکان ثبت سند وجود ندارد');
        }

        DB::beginTransaction();
        try {
            $postPayload = [
                'status' => 'posted',
                'posted_at' => now(),
            ];
            $postPayload = $this->stampAudit($postPayload, 'accounting_documents', 'posted');

            $document->update($postPayload);
            $this->treasuryBalanceCacheSyncService->syncForDocument((int) $document->id);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * برگشت سند (ایجاد سند معکوس)
     */
    public function reverseDocument(int $documentId, string $reason): AccountingDocument
    {
        $originalDocument = AccountingDocument::with('ledgers')->findOrFail($documentId);

        if ($originalDocument->status !== 'posted') {
            throw new \Exception('فقط اسناد ثبت شده قابل برگشت هستند');
        }

        if ($originalDocument->reversed_by_document_id) {
            throw new \Exception('این سند قبلاً برگشت خورده است');
        }

        DB::beginTransaction();
        try {
            // ایجاد سند معکوس
            $reversalDocument = $this->createDocument([
                'document_type' => AccountingDocument::TYPE_CORRECTION,
                'store_id' => $originalDocument->store_id,
                'fiscal_year_id' => $originalDocument->fiscal_year_id,
                'reference_type' => get_class($originalDocument),
                'reference_id' => $originalDocument->id,
                'description' => "برگشت سند {$originalDocument->document_number}: {$reason}",
                'total_debit' => $originalDocument->total_credit,
                'total_credit' => $originalDocument->total_debit,
            ]);

            // ایجاد آرتیکل‌های معکوس
            foreach ($originalDocument->ledgerEntries as $ledger) {
                $this->ledgerService->recordEntry([
                    'event_type' => 'document_reversal',
                    'event_source' => 'manual',
                    'store_id' => $ledger->store_id,
                    'account_id' => $ledger->account_id,
                    'currency_code' => $ledger->currency_code,
                    'debit_amount' => $ledger->credit_amount, // معکوس
                    'credit_amount' => $ledger->debit_amount, // معکوس
                    'fx_rate_to_base' => $ledger->fx_rate_to_base,
                    'accounting_document_id' => $reversalDocument->id,
                    'description' => "برگشت: {$ledger->description}",
                ]);
            }

            // ثبت قطعی سند معکوس
            $this->postDocument($reversalDocument->id);

            // بروزرسانی سند اصلی
            $originalDocument->update([
                'status' => AccountingDocument::STATUS_REVERSED,
                'reversed_by_document_id' => $reversalDocument->id,
            ]);

            DB::commit();
            return $reversalDocument;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * تولید شماره سند خودکار
     */
    protected function generateDocumentNumber(string $type): string
    {
        $prefix = strtoupper(substr($type, 0, 3));
        $year = date('Y');
        $month = date('m');
        
        $lastDocument = AccountingDocument::where('document_type', $type)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastDocument ? intval(substr($lastDocument->document_number, -6)) + 1 : 1;

        return sprintf('%s-%s%s-%06d', $prefix, $year, $month, $nextNumber);
    }

    /**
     * دریافت اسناد بر اساس فیلتر
     */
    public function getDocuments(array $filters = [])
    {
        $query = AccountingDocument::with(['fiscalYear', 'ledgers']);

        if (!empty($filters['document_type'])) {
            $query->where('document_type', $filters['document_type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['store_id'])) {
            $query->where('store_id', $filters['store_id']);
        }

        if (!empty($filters['fiscal_year_id'])) {
            $query->where('fiscal_year_id', $filters['fiscal_year_id']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }

        return $query->orderBy('created_at', 'desc')->paginate(50);
    }
}
