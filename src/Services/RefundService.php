<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\{Account, CreditNote, CustomerRefund, DebitNote, Supplier, SupplierRefund};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RMS\Accounting\Support\AuditActor;

/**
 * Refund Service
 * 
 * مدیریت بازگشت وجه (هم به مشتری، هم از تامین‌کننده)
 */
class RefundService
{
    public function __construct(
        protected LedgerService $ledgerService,
        protected PartyService $partyService,
    ) {
    }

    /**
     * پردازش بازگشت وجه به مشتری
     */
    public function processCustomerRefund(array $data): CustomerRefund
    {
        DB::beginTransaction();
        
        try {
            if (empty($data['refund_number'])) {
                $data['refund_number'] = $this->generateRefundNumber('CRF');
            }
            
            $customerPayload = [
                'refund_number' => $data['refund_number'],
                'customer_id' => $data['customer_id'],
                'credit_note_id' => $data['credit_note_id'] ?? null,
                'customer_payment_id' => $data['customer_payment_id'] ?? null,
                'store_id' => $data['store_id'] ?? 0,
                'refund_date' => $data['refund_date'] ?? now(),
                'reason' => $data['reason'] ?? null,
                'amount' => $data['amount'],
                'currency_code' => $data['currency_code'] ?? 'IRR',
                'fx_rate' => $data['fx_rate'] ?? 1,
                'amount_base' => $data['amount'] * ($data['fx_rate'] ?? 1),
                'refund_method' => $data['refund_method'] ?? CustomerRefund::METHOD_CASH,
                'bank_id' => $data['bank_id'] ?? null,
                'cash_box_id' => $data['cash_box_id'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ];
            $customerPayload = AuditActor::stamp($customerPayload, 'customer_refunds', 'created');

            $refund = CustomerRefund::create($customerPayload);
            
            // تغییر وضعیت Credit Note (اگر وجود داره)
            if ($refund->credit_note_id) {
                $creditNote = CreditNote::find($refund->credit_note_id);
                if ($creditNote) {
                    $creditNote->update(['status' => CreditNote::STATUS_REFUNDED]);
                }
            }
            
            // ثبت در دفتر کل
            $this->recordCustomerRefundInLedger($refund);
            
            // تغییر وضعیت به processed
            $processedPayload = [
                'status' => CustomerRefund::STATUS_PROCESSED,
                'processed_at' => now(),
                'approved_at' => now(),
            ];
            $processedPayload = AuditActor::stamp($processedPayload, 'customer_refunds', 'approved');

            $refund->update($processedPayload);
            
            DB::commit();
            
            Log::info('Customer refund processed', [
                'refund_id' => $refund->id,
                'customer_id' => $refund->customer_id,
                'amount' => $refund->amount,
            ]);
            
            return $refund;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process customer refund', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * دریافت بازگشت وجه از تامین‌کننده
     */
    public function receiveSupplierRefund(array $data): SupplierRefund
    {
        DB::beginTransaction();
        
        try {
            if (empty($data['refund_number'])) {
                $data['refund_number'] = $this->generateRefundNumber('SRF');
            }
            
            $row = [
                'refund_number' => $data['refund_number'],
                'supplier_id' => $data['supplier_id'],
                'debit_note_id' => $data['debit_note_id'] ?? null,
                'supplier_payment_id' => $data['supplier_payment_id'] ?? null,
                'store_id' => $data['store_id'] ?? 0,
                'refund_date' => $data['refund_date'] ?? now(),
                'reason' => $data['reason'] ?? null,
                'amount' => $data['amount'],
                'currency_code' => $data['currency_code'] ?? 'IRR',
                'fx_rate' => $data['fx_rate'] ?? 1,
                'amount_base' => $data['amount'] * ($data['fx_rate'] ?? 1),
                'refund_method' => $data['refund_method'] ?? SupplierRefund::METHOD_BANK_TRANSFER,
                'bank_id' => $data['bank_id'] ?? null,
                'cash_box_id' => $data['cash_box_id'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ];
            $row = AuditActor::stamp($row, 'supplier_refunds', 'created');
            if (Schema::hasColumn('supplier_refunds', 'supplier_invoice_id')) {
                $invId = (int) ($data['supplier_invoice_id'] ?? 0);
                $row['supplier_invoice_id'] = $invId > 0 ? $invId : null;
            }
            $refund = SupplierRefund::create($row);

            // تغییر وضعیت Debit Note (اگر وجود داره)
            if ($refund->debit_note_id) {
                $debitNote = DebitNote::find($refund->debit_note_id);
                if ($debitNote) {
                    $debitNote->update(['status' => 'refunded']);
                }
            }
            
            // ثبت در دفتر کل
            $this->ensureLedgerPosted($refund);
            
            // تغییر وضعیت به received
            $receivedPayload = [
                'status' => SupplierRefund::STATUS_RECEIVED,
                'received_at' => now(),
                'approved_at' => now(),
            ];
            $receivedPayload = AuditActor::stamp($receivedPayload, 'supplier_refunds', 'approved');

            $refund->update($receivedPayload);
            
            DB::commit();
            
            Log::info('Supplier refund received', [
                'refund_id' => $refund->id,
                'supplier_id' => $refund->supplier_id,
                'amount' => $refund->amount,
            ]);
            
            return $refund;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to receive supplier refund', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * ثبت بازگشت وجه به مشتری در دفتر کل
     * 
     * بدهکار: حساب دریافتنی (افزایش دریافتنی - منفی!)
     * بستانکار: بانک/صندوق (کاهش موجودی)
     */
    protected function recordCustomerRefundInLedger(CustomerRefund $refund): void
    {
        $descriptionSuffix = trim((string) $refund->notes) !== '' ? (' | ' . trim((string) $refund->notes)) : '';
        $channelTitle = $refund->cash_box_id ? 'صندوق' : 'بانک';
        $entries = [];
        
        // بدهکار: دریافتنی از مشتری
        $entries[] = [
            'account_id' => $this->getARAccountId(),
            'event_type' => 'payment',
            'event_source' => 'sales',
            'source_reference_type' => CustomerRefund::class,
            'source_reference_id' => $refund->id,
            'debit' => $refund->amount,
            'credit' => 0,
            'debit_amount' => $refund->amount,
            'credit_amount' => 0,
            'description' => "بازگشت وجه به مشتری - {$refund->refund_number}{$descriptionSuffix}",
        ];
        
        // بستانکار: بانک یا صندوق
        $cashAccountId = $refund->bank_id 
            ? $this->getBankAccountId($refund->bank_id)
            : $this->getCashBoxAccountId($refund->cash_box_id);
            
        $entries[] = [
            'account_id' => $cashAccountId,
            'event_type' => 'payment',
            'event_source' => 'sales',
            'source_reference_type' => CustomerRefund::class,
            'source_reference_id' => $refund->id,
            'debit' => 0,
            'credit' => $refund->amount,
            'debit_amount' => 0,
            'credit_amount' => $refund->amount,
            'description' => "پرداخت بازگشت وجه - {$refund->refund_number} | کانال: {$channelTitle}{$descriptionSuffix}",
        ];
        
        $document = $this->ledgerService->recordTransaction([
            'document_type' => 'customer_refund',
            'store_id' => $refund->store_id,
            'reference_type' => CustomerRefund::class,
            'reference_id' => $refund->id,
            'description' => "بازگشت وجه {$refund->refund_number} - مشتری: {$refund->customer->name} | کانال: {$channelTitle}{$descriptionSuffix}",
        ], $entries);
        
        $refund->update(['accounting_document_id' => $document->id]);
    }

    /**
     * ثبت سند استرداد مشتری در صورت نبود سند (idempotent) و همگام‌سازی وضعیت.
     */
    public function ensureCustomerLedgerPosted(CustomerRefund $refund): void
    {
        $refund = $refund->fresh();
        if (! $refund) {
            return;
        }
        if ((int) ($refund->accounting_document_id ?? 0) <= 0) {
            if ((float) ($refund->amount ?? 0) <= 0) {
                return;
            }
            $this->recordCustomerRefundInLedger($refund);
            $refund = $refund->fresh() ?? $refund;
        }

        if ((int) ($refund->accounting_document_id ?? 0) > 0 && (string) $refund->status !== CustomerRefund::STATUS_PROCESSED) {
            $approvePayload = [
                'status' => CustomerRefund::STATUS_PROCESSED,
                'processed_at' => now(),
                'approved_at' => now(),
            ];
            $approvePayload = AuditActor::stamp($approvePayload, 'customer_refunds', 'approved');

            $refund->update($approvePayload);
        }

        $invoiceId = 0;
        if ((int) ($refund->credit_note_id ?? 0) > 0) {
            $invoiceId = (int) (CreditNote::query()->whereKey((int) $refund->credit_note_id)->value('applied_to_invoice_id') ?? 0);
        }
        if ($invoiceId > 0) {
            app(CustomerPaymentService::class)->syncInvoicePaymentStatus($invoiceId);
        }
    }

    /**
     * ثبت سند استرداد تأمین‌کننده در صورت نبود سند (idempotent).
     */
    public function ensureLedgerPosted(SupplierRefund $refund): void
    {
        $refund = $refund->fresh();
        if (! $refund) {
            return;
        }
        if ((int) ($refund->accounting_document_id ?? 0) > 0) {
            return;
        }
        if ((float) ($refund->amount ?? 0) <= 0) {
            return;
        }

        if ($this->usesNonCashSupplierRefundLedger((string) $refund->refund_method)) {
            $this->recordNonCashSupplierRefundInLedger($refund);

            return;
        }

        $this->recordCashSupplierRefundInLedger($refund);
    }

    protected function usesNonCashSupplierRefundLedger(string $method): bool
    {
        return in_array($method, [
            SupplierRefund::METHOD_OFFSET_PAYABLE,
            SupplierRefund::METHOD_SUPPLIER_CREDIT_ON_ACCOUNT,
            SupplierRefund::METHOD_DEDUCT,
        ], true);
    }

    /**
     * دریافت نقد از تأمین‌کننده: بدهکار بانک/صندوق، بستانکار پرداختنی (ترجیحاً حساب فرعی طرف).
     */
    protected function recordCashSupplierRefundInLedger(SupplierRefund $refund): void
    {
        $refund->loadMissing('supplier.party');
        $descriptionSuffix = trim((string) $refund->notes) !== '' ? (' | ' . trim((string) $refund->notes)) : '';
        $channelTitle = $refund->cash_box_id ? 'صندوق' : 'بانک';
        $entries = [];

        $cashAccountId = $refund->bank_id
            ? $this->getBankAccountId((int) $refund->bank_id)
            : $this->getCashBoxAccountId($refund->cash_box_id ? (int) $refund->cash_box_id : null);

        $entries[] = [
            'account_id' => $cashAccountId,
            'event_type' => 'receipt',
            'event_source' => 'inventory',
            'source_reference_type' => SupplierRefund::class,
            'source_reference_id' => $refund->id,
            'debit' => $refund->amount,
            'credit' => 0,
            'debit_amount' => $refund->amount,
            'credit_amount' => 0,
            'description' => "دریافت بازگشت وجه - {$refund->refund_number} | کانال: {$channelTitle}{$descriptionSuffix}",
        ];

        $payableId = $this->resolveSupplierPayableAccountIdForRefund($refund->supplier);
        $entries[] = [
            'account_id' => $payableId,
            'event_type' => 'receipt',
            'event_source' => 'inventory',
            'source_reference_type' => SupplierRefund::class,
            'source_reference_id' => $refund->id,
            'debit' => 0,
            'credit' => $refund->amount,
            'debit_amount' => 0,
            'credit_amount' => $refund->amount,
            'description' => "بازگشت از تامین‌کننده - {$refund->refund_number}{$descriptionSuffix}",
        ];

        $supplierName = $refund->supplier ? (string) ($refund->supplier->name ?? '') : '';
        $document = $this->ledgerService->recordTransaction([
            'document_type' => 'supplier_refund',
            'store_id' => $refund->store_id,
            'reference_type' => SupplierRefund::class,
            'reference_id' => $refund->id,
            'description' => "دریافت بازگشت {$refund->refund_number} - تامین‌کننده: {$supplierName} | کانال: {$channelTitle}{$descriptionSuffix}",
        ], $entries);

        $refund->update(['accounting_document_id' => $document->id]);
    }

    /**
     * بدون خزانه: بدهکار پرداختنی طرف، بستانکار بهای تمام‌شده/موجودی (برگشت خرید روی حساب).
     */
    protected function recordNonCashSupplierRefundInLedger(SupplierRefund $refund): void
    {
        $refund->loadMissing('supplier.party');
        $descriptionSuffix = trim((string) $refund->notes) !== '' ? (' | ' . trim((string) $refund->notes)) : '';
        $supplierName = $refund->supplier ? (string) ($refund->supplier->name ?? '') : '';

        $payableId = $this->resolveSupplierPayableAccountIdForRefund($refund->supplier);
        $costId = $this->resolveSupplierCostAccountIdForRefund($refund->supplier);

        $entries = [];
        $entries[] = [
            'account_id' => $payableId,
            'event_type' => 'receipt',
            'event_source' => 'inventory',
            'source_reference_type' => SupplierRefund::class,
            'source_reference_id' => $refund->id,
            'debit' => $refund->amount,
            'credit' => 0,
            'debit_amount' => $refund->amount,
            'credit_amount' => 0,
            'description' => "تسویه استرداد (بدون نقد) - {$refund->refund_number}{$descriptionSuffix}",
        ];
        $entries[] = [
            'account_id' => $costId,
            'event_type' => 'receipt',
            'event_source' => 'inventory',
            'source_reference_type' => SupplierRefund::class,
            'source_reference_id' => $refund->id,
            'debit' => 0,
            'credit' => $refund->amount,
            'debit_amount' => 0,
            'credit_amount' => $refund->amount,
            'description' => "برگشت بهای خرید / موجودی - {$refund->refund_number}{$descriptionSuffix}",
        ];

        $document = $this->ledgerService->recordTransaction([
            'document_type' => 'supplier_refund',
            'store_id' => $refund->store_id,
            'reference_type' => SupplierRefund::class,
            'reference_id' => $refund->id,
            'description' => "استرداد تأمین‌کننده (بدون نقد) {$refund->refund_number} - {$supplierName}{$descriptionSuffix}",
        ], $entries);

        $refund->update(['accounting_document_id' => $document->id]);
    }

    protected function resolveSupplierPayableAccountIdForRefund(?Supplier $supplier): int
    {
        if ($supplier && (int) ($supplier->party_id ?? 0) > 0) {
            $acc = $this->partyService->getOrCreateSupplierAccount((int) $supplier->party_id);

            return $acc ? (int) $acc->id : $this->getAPAccountId();
        }
        if ($supplier && (int) ($supplier->account_id ?? 0) > 0) {
            return (int) $supplier->account_id;
        }

        return $this->getAPAccountId();
    }

    protected function resolveSupplierCostAccountIdForRefund(?Supplier $supplier): int
    {
        if ($supplier && (int) ($supplier->party_id ?? 0) > 0) {
            $acc = $this->partyService->getOrCreateSupplierCostAccount((int) $supplier->party_id);

            return $acc ? (int) $acc->id : $this->resolveInventoryFallbackAccountId();
        }

        return $this->resolveInventoryFallbackAccountId();
    }

    protected function resolveInventoryFallbackAccountId(): int
    {
        $inventoryId = config('accounting.accounts.inventory');
        if ($inventoryId) {
            return (int) $inventoryId;
        }

        return (int) Account::query()
            ->where('account_type', 'asset')
            ->where('active', true)
            ->where(function ($q) {
                $q->where('name', 'like', '%موجودی%')
                    ->orWhere('name', 'like', '%Inventory%')
                    ->orWhere('code', 'like', '13%');
            })
            ->orderBy('code')
            ->value('id') ?: $this->getAPAccountId();
    }

    public function allocateNextSupplierRefundNumber(): string
    {
        return $this->generateRefundNumber('SRF');
    }

    protected function generateRefundNumber(string $prefix): string
    {
        $year = date('Y');
        $month = date('m');
        
        $table = $prefix === 'CRF' ? 'customer_refunds' : 'supplier_refunds';
        
        $lastRefund = DB::table($table)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();
        
        $nextNumber = $lastRefund ? intval(substr($lastRefund->refund_number, -6)) + 1 : 1;
        
        return sprintf('%s-%s%s-%06d', $prefix, $year, $month, $nextNumber);
    }

    protected function getARAccountId(): int
    {
        return $this->resolveConfiguredOrCodeAccount(
            \RMS\Core\Models\Setting::get('accounting.ar_account_id', config('accounting.accounts.accounts_receivable')),
            ['1103', '1103-001']
        );
    }

    protected function getAPAccountId(): int
    {
        return $this->resolveConfiguredOrCodeAccount(
            \RMS\Core\Models\Setting::get('accounting.ap_account_id', config('accounting.accounts.accounts_payable')),
            ['2101']
        );
    }

    protected function getBankAccountId(int $bankId): int
    {
        $bank = DB::table('banks')->where('id', $bankId)->first();
        return (int) (($bank->account_id ?? null) ?: $this->resolveConfiguredOrCodeAccount(config('accounting.accounts.bank_default'), ['1102']));
    }

    protected function getCashBoxAccountId(?int $cashBoxId): int
    {
        if (!$cashBoxId) {
            return $this->resolveConfiguredOrCodeAccount(config('accounting.accounts.cash_box_default'), ['1101']);
        }
        $cashBox = DB::table('cash_boxes')->where('id', $cashBoxId)->first();
        return (int) (($cashBox->account_id ?? null) ?: $this->resolveConfiguredOrCodeAccount(config('accounting.accounts.cash_box_default'), ['1101']));
    }

    protected function resolveConfiguredOrCodeAccount(mixed $configuredId, array $fallbackCodes): int
    {
        $configured = (int) ($configuredId ?? 0);
        if ($configured > 0) {
            return $configured;
        }

        foreach ($fallbackCodes as $code) {
            $id = (int) Account::query()->where('code', (string) $code)->value('id');
            if ($id > 0) {
                return $id;
            }
        }

        return 1;
    }

    /**
     * اصلاح immutable برای بازگشت وجه مشتری:
     * - سند قبلی reverse می‌شود
     * - رکورد قدیمی cancelled می‌شود
     * - رکورد جدید processed ثبت می‌گردد
     *
     * @param array<string,mixed> $data
     * @return array{original_refund: CustomerRefund, new_refund: CustomerRefund, reversal_document: \RMS\Accounting\Models\AccountingDocument}
     */
    public function correctCustomerRefund(CustomerRefund $originalRefund, array $data): array
    {
        return DB::transaction(function () use ($originalRefund, $data) {
            if ((string) $originalRefund->status !== CustomerRefund::STATUS_PROCESSED) {
                throw new \DomainException('فقط بازگشت وجه processed قابل اصلاح است.');
            }
            if (!(int) ($originalRefund->accounting_document_id ?? 0)) {
                throw new \DomainException('برای بازگشت وجه اصلی سند حسابداری معتبر یافت نشد.');
            }
            if (
                Schema::hasColumn('customer_refunds', 'original_refund_id')
                && (int) ($originalRefund->original_refund_id ?? 0) > 0
            ) {
                throw new \DomainException('اصلاح زنجیره‌ای مستقیم برای بازگشت وجه مجاز نیست.');
            }

            $reason = trim((string) ($data['reason'] ?? ''));
            if ($reason === '') {
                throw new \DomainException('علت اصلاح الزامی است.');
            }

            $reversalDocument = $this->ledgerService->reverseDocument(
                (int) $originalRefund->accounting_document_id,
                $reason
            );

            $originalRefund->status = CustomerRefund::STATUS_CANCELLED;
            $originalRefund->notes = trim((string) ($originalRefund->notes ?? '')) !== ''
                ? trim((string) $originalRefund->notes) . "\n[CORRECTION] " . $reason
                : '[CORRECTION] ' . $reason;
            if (Schema::hasColumn('customer_refunds', 'correction_reason')) {
                $originalRefund->correction_reason = $reason;
            }
            if (Schema::hasColumn('customer_refunds', 'correction_document_id')) {
                $originalRefund->correction_document_id = (int) $reversalDocument->id;
            }
            if (Schema::hasColumn('customer_refunds', 'correction_group_id') && !empty($data['correction_group_id'])) {
                $originalRefund->correction_group_id = (string) $data['correction_group_id'];
            }
            if (Schema::hasColumn('customer_refunds', 'corrected_by_user_id')) {
                $originalRefund->corrected_by_user_id = AuditActor::userId();
            }
            if (Schema::hasColumn('customer_refunds', 'corrected_by_admin_id')) {
                $originalRefund->corrected_by_admin_id = AuditActor::adminId();
            }
            if (Schema::hasColumn('customer_refunds', 'corrected_at')) {
                $originalRefund->corrected_at = now();
            }
            $originalRefund->save();

            $repostNotes = trim((string) ($data['new_notes'] ?? ''));
            $prefix = '[CORRECTION-REPOST] ' . $reason;
            $repostNotes = $repostNotes !== '' ? ($prefix . "\n" . $repostNotes) : $prefix;

            $newRefund = $this->processCustomerRefund([
                'customer_id' => (int) $originalRefund->customer_id,
                'store_id' => (int) ($originalRefund->store_id ?? 0),
                'credit_note_id' => $originalRefund->credit_note_id,
                'customer_payment_id' => $originalRefund->customer_payment_id,
                'refund_date' => (string) ($data['new_refund_date'] ?? $originalRefund->refund_date?->format('Y-m-d') ?? now()->toDateString()),
                'reason' => $reason,
                'amount' => (float) $data['new_amount'],
                'currency_code' => (string) ($data['new_currency_code'] ?? $originalRefund->currency_code ?? 'IRR'),
                'fx_rate' => (float) ($data['new_fx_rate'] ?? $originalRefund->fx_rate ?? 1),
                'refund_method' => (string) ($data['new_refund_method'] ?? $originalRefund->refund_method ?? CustomerRefund::METHOD_BANK_TRANSFER),
                'bank_id' => (int) ($data['new_bank_id'] ?? 0) ?: null,
                'cash_box_id' => (int) ($data['new_cash_box_id'] ?? 0) ?: null,
                'reference_number' => (string) ($data['new_reference_number'] ?? ''),
                'notes' => $repostNotes,
            ]);

            if (Schema::hasColumn('customer_refunds', 'original_refund_id')) {
                $newRefund->original_refund_id = (int) $originalRefund->id;
            }
            if (Schema::hasColumn('customer_refunds', 'correction_reason')) {
                $newRefund->correction_reason = $reason;
            }
            if (Schema::hasColumn('customer_refunds', 'correction_group_id') && !empty($data['correction_group_id'])) {
                $newRefund->correction_group_id = (string) $data['correction_group_id'];
            }
            if (Schema::hasColumn('customer_refunds', 'corrected_by_user_id')) {
                $newRefund->corrected_by_user_id = AuditActor::userId();
            }
            if (Schema::hasColumn('customer_refunds', 'corrected_by_admin_id')) {
                $newRefund->corrected_by_admin_id = AuditActor::adminId();
            }
            if (Schema::hasColumn('customer_refunds', 'corrected_at')) {
                $newRefund->corrected_at = now();
            }
            $newRefund->save();

            return [
                'original_refund' => $originalRefund->fresh(),
                'new_refund' => $newRefund->fresh(),
                'reversal_document' => $reversalDocument,
            ];
        });
    }
}
