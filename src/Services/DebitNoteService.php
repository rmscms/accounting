<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\{DebitNote, DebitNoteItem, SupplierInvoice};
use RMS\Accounting\Support\AccountingVatAccounts;
use RMS\Accounting\Support\AuditActor;
use RMS\Core\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Debit Note Service
 * 
 * مدیریت یادداشت بدهکار (Purchase Returns)
 * استاندارد: IAS 2
 */
class DebitNoteService
{
    protected LedgerService $ledgerService;
    protected TaxService $taxService;
    protected InventoryAdjustmentService $inventoryAdjustmentService;
    protected PartyService $partyService;

    public function __construct(
        LedgerService $ledgerService,
        TaxService $taxService,
        InventoryAdjustmentService $inventoryAdjustmentService,
        PartyService $partyService
    ) {
        $this->ledgerService = $ledgerService;
        $this->taxService = $taxService;
        $this->inventoryAdjustmentService = $inventoryAdjustmentService;
        $this->partyService = $partyService;
    }

    /**
     * ایجاد Debit Note جدید
     */
    public function createDebitNote(array $data): DebitNote
    {
        DB::beginTransaction();
        
        try {
            if (empty($data['debit_note_number'])) {
                $data['debit_note_number'] = $this->generateDebitNoteNumber();
            }
            
            $createPayload = [
                'debit_note_number' => $data['debit_note_number'],
                'supplier_id' => $data['supplier_id'],
                'supplier_invoice_id' => $data['supplier_invoice_id'] ?? null,
                'store_id' => $data['store_id'] ?? 0,
                'debit_date' => $data['debit_date'] ?? now(),
                'reason' => $data['reason'] ?? null,
                'debit_type' => $data['debit_type'] ?? DebitNote::TYPE_RETURN,
                'currency_code' => $data['currency_code'] ?? 'IRR',
                'fx_rate' => $data['fx_rate'] ?? 1,
                'status' => DebitNote::STATUS_DRAFT,
                'notes' => $data['notes'] ?? null,
            ];
            $createPayload = AuditActor::stamp($createPayload, 'debit_notes', 'created');

            $debitNote = DebitNote::create($createPayload);
            
            if (!empty($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    $this->addItem($debitNote, $itemData);
                }
            }
            
            $this->recalculateTotals($debitNote);
            
            if (!empty($data['apply_tax'])) {
                $this->applyTax($debitNote);
            }
            
            DB::commit();
            
            Log::info('Debit Note created', [
                'debit_note_id' => $debitNote->id,
                'debit_note_number' => $debitNote->debit_note_number,
            ]);
            
            return $debitNote->fresh(['items']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create Debit Note', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }

    public function addItem(DebitNote $debitNote, array $itemData): DebitNoteItem
    {
        if (!$debitNote->canBeEdited()) {
            throw new \Exception('Debit Note cannot be edited in current status');
        }
        
        $item = $debitNote->items()->create([
            'product_id' => $itemData['product_id'] ?? null,
            'product_sku' => $itemData['product_sku'] ?? null,
            'product_name' => $itemData['product_name'],
            'quantity' => $itemData['quantity'],
            'price' => $itemData['price'],
            'tax_rate' => $itemData['tax_rate'] ?? 0,
            'discount_amount' => $itemData['discount_amount'] ?? 0,
            'return_reason' => $itemData['return_reason'] ?? null,
            'notes' => $itemData['notes'] ?? null,
        ]);
        
        $subtotal = (float) $item->quantity * (float) $item->price;
        $lineNet = max(0, $subtotal - (float) $item->discount_amount);
        $taxResult = \RMS\Accounting\Services\Tax\TaxCalculator::calculateVAT(
            $lineNet,
            (float) ($item->tax_rate ?? 0),
            tax_calculation_method()
        );
        $item->tax_amount = (float) ($taxResult['tax_amount'] ?? 0);
        $item->total = (float) ($taxResult['total_amount'] ?? $lineNet);
        $item->save();
        
        return $item;
    }

    public function recalculateTotals(DebitNote $debitNote): DebitNote
    {
        $items = $debitNote->items;
        
        $debitNote->subtotal = $items->sum(static function ($item) {
            $gross = (float) $item->quantity * (float) $item->price;
            return max(0, $gross - (float) $item->discount_amount);
        });
        $debitNote->tax_amount = $items->sum('tax_amount');
        $debitNote->discount_amount = $items->sum('discount_amount');
        $debitNote->total_amount = $debitNote->subtotal + $debitNote->tax_amount;
        $debitNote->amount_base = $debitNote->total_amount * $debitNote->fx_rate;
        
        $debitNote->save();
        
        return $debitNote;
    }

    public function applyTax(DebitNote $debitNote): DebitNote
    {
        foreach ($debitNote->items as $item) {
            $lineNet = max(
                0,
                ((float) $item->quantity * (float) $item->price) - (float) ($item->discount_amount ?? 0)
            );
            $taxResult = \RMS\Accounting\Services\Tax\TaxCalculator::calculateVAT(
                $lineNet,
                (float) $item->tax_rate,
                tax_calculation_method()
            );
            
            $item->tax_amount = $taxResult['tax_amount'];
            $item->total = $taxResult['total_amount'];
            $item->save();
        }
        
        return $this->recalculateTotals($debitNote);
    }

    public function issueDebitNote(DebitNote $debitNote): DebitNote
    {
        if (!$debitNote->isDraft()) {
            throw new \Exception('Only draft debit notes can be issued');
        }
        
        DB::beginTransaction();
        
        try {
            $approvePayload = [
                'status' => DebitNote::STATUS_ISSUED,
                'approved_at' => now(),
            ];
            $approvePayload = AuditActor::stamp($approvePayload, 'debit_notes', 'approved');

            $debitNote->update($approvePayload);
            
            $this->recordInLedger($debitNote);

            $this->createApprovedInventoryAdjustmentForReturnIfEnabled($debitNote->fresh(['items']));
            
            DB::commit();
            
            Log::info('Debit Note issued', [
                'debit_note_id' => $debitNote->id,
            ]);
            
            return $debitNote;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function applyToInvoice(DebitNote $debitNote, int $invoiceId): DebitNote
    {
        if (!$debitNote->canBeApplied()) {
            throw new \Exception('Debit Note cannot be applied in current status');
        }
        
        DB::beginTransaction();
        
        try {
            $invoice = SupplierInvoice::findOrFail($invoiceId);
            
            if ($invoice->supplier_id !== $debitNote->supplier_id) {
                throw new \Exception('Supplier mismatch');
            }
            
            // کسر از مانده فاکتور
            $invoice->balance_due -= $debitNote->total_amount;
            
            if ($invoice->balance_due < 0) {
                $invoice->balance_due = 0;
            }
            
            // آپدیت وضعیت پرداخت
            if ($invoice->balance_due == 0) {
                $invoice->payment_status = SupplierInvoice::STATUS_PAID;
            } elseif ($invoice->balance_due < $invoice->total_amount) {
                $invoice->payment_status = SupplierInvoice::STATUS_PARTIALLY_PAID;
            }
            
            $invoice->save();
            
            $debitNote->update([
                'status' => DebitNote::STATUS_APPLIED,
                'applied_to_invoice_id' => $invoice->id,
                'applied_at' => now(),
            ]);
            
            DB::commit();
            
            Log::info('Debit Note applied to invoice', [
                'debit_note_id' => $debitNote->id,
                'invoice_id' => $invoice->id,
            ]);
            
            return $debitNote;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * ثبت در دفتر کل
     * 
     * بدهکار: پرداختنی به تامین‌کننده (کاهش بدهی)
     * بستانکار: خرید (کاهش خرید)
     * بستانکار: مالیات دریافتنی (کاهش مالیات)
     */
    protected function recordInLedger(DebitNote $debitNote): void
    {
        $debitNote->loadMissing('supplier.party');
        $payableAccountId = $this->resolvePayableAccountId($debitNote);
        $purchaseAccountId = $this->resolvePurchaseAccountId($debitNote);

        $entries = [];
        
        // بدهکار: حساب پرداختنی (کاهش بدهی)
        $entries[] = [
            'account_id' => $payableAccountId,
            'debit_amount' => $debitNote->total_amount,
            'credit_amount' => 0,
            'description' => "کاهش بدهی - {$debitNote->debit_note_number}",
        ];
        
        // بستانکار: حساب خرید (کاهش خرید)
        $entries[] = [
            'account_id' => $purchaseAccountId,
            'debit_amount' => 0,
            'credit_amount' => $debitNote->subtotal,
            'description' => "برگشت خرید - {$debitNote->debit_note_number}",
        ];
        
        // بستانکار: مالیات دریافتنی (کاهش مالیات)
        if ($debitNote->tax_amount > 0) {
            $entries[] = [
                'account_id' => $this->getTaxReceivableAccountId(),
                'debit_amount' => 0,
                'credit_amount' => $debitNote->tax_amount,
                'description' => "مالیات برگشت خرید - {$debitNote->debit_note_number}",
            ];
        }
        
        $document = $this->ledgerService->recordTransaction([
            'document_type' => 'debit_note',
            'store_id' => $debitNote->store_id,
            'reference_type' => DebitNote::class,
            'reference_id' => $debitNote->id,
            'description' => "یادداشت بدهکار {$debitNote->debit_note_number} - تامین‌کننده: {$debitNote->supplier->name}",
        ], $entries);
        
        $debitNote->update(['accounting_document_id' => $document->id]);
    }

    protected function generateDebitNoteNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        
        $lastDN = DebitNote::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();
        
        $nextNumber = $lastDN ? intval(substr($lastDN->debit_note_number, -6)) + 1 : 1;
        
        return sprintf('DN-%s%s-%06d', $year, $month, $nextNumber);
    }

    protected function getPurchaseAccountId(): int
    {
        return $this->resolveConfiguredAccountId('accounting.purchase_account_id', 'accounting.accounts.inventory', 1);
    }

    protected function getTaxReceivableAccountId(): int
    {
        $resolved = AccountingVatAccounts::resolveReceivableAccountId();
        if ($resolved) {
            return (int) $resolved;
        }

        return $this->resolveConfiguredAccountId('accounting.vat.account_receivable_id', 'accounting.accounts.vat_receivable', 1);
    }

    protected function getAPAccountId(): int
    {
        return $this->resolveConfiguredAccountId('accounting.ap_account_id', 'accounting.accounts.accounts_payable', 1);
    }

    protected function resolvePayableAccountId(DebitNote $debitNote): int
    {
        $supplier = $debitNote->supplier;
        if ($supplier) {
            if ((int) ($supplier->party_id ?? 0) > 0) {
                try {
                    return (int) $this->partyService->getOrCreateSupplierAccount((int) $supplier->party_id)->id;
                } catch (\Throwable $e) {
                    // fallback to supplier/account settings below
                }
            }
            if ((int) ($supplier->account_id ?? 0) > 0) {
                return (int) $supplier->account_id;
            }
        }

        return $this->getAPAccountId();
    }

    protected function resolvePurchaseAccountId(DebitNote $debitNote): int
    {
        $supplier = $debitNote->supplier;
        if ($supplier && (int) ($supplier->party_id ?? 0) > 0) {
            try {
                return (int) $this->partyService->getOrCreateSupplierCostAccount((int) $supplier->party_id)->id;
            } catch (\Throwable $e) {
                // fallback to configured/default account below
            }
        }

        return $this->getPurchaseAccountId();
    }

    protected function resolveConfiguredAccountId(string $settingKey, string $configKey, int $fallback): int
    {
        $fromSetting = Setting::get($settingKey);
        if (is_numeric($fromSetting) && (int) $fromSetting > 0) {
            return (int) $fromSetting;
        }

        $fromConfig = config($configKey);
        if (is_numeric($fromConfig) && (int) $fromConfig > 0) {
            return (int) $fromConfig;
        }

        return $fallback;
    }

    /**
     * پس از صدور یادداشت بدهکار «برگشت»، رکورد تعدیل موجودی تأییدشده (بدون post به دفترکل تعدیل) برای ردیابی کاهش موجودی.
     */
    protected function createApprovedInventoryAdjustmentForReturnIfEnabled(DebitNote $debitNote): void
    {
        if (! config('accounting.purchases.debit_note_issue_inventory_adjustment', true)) {
            return;
        }
        if ($debitNote->debit_type !== DebitNote::TYPE_RETURN) {
            return;
        }

        $lines = $debitNote->items->filter(static function ($item) {
            return $item->product_id !== null
                && (string) $item->product_id !== ''
                && (float) $item->quantity > 0;
        });
        if ($lines->isEmpty()) {
            return;
        }

        $warehouseId = config('accounting.purchases.debit_note_inventory_warehouse_id');
        $warehouseId = $warehouseId !== null && $warehouseId !== '' ? (string) $warehouseId : null;

        $adjustment = $this->inventoryAdjustmentService->createAdjustment([
            'adjustment_date' => $debitNote->debit_date?->format('Y-m-d') ?? now()->toDateString(),
            'adjustment_type' => 'other',
            'warehouse_id' => $warehouseId,
            'reason' => 'برگشت خرید — یادداشت بدهکار '.$debitNote->debit_note_number.' (#'.(int) $debitNote->getKey().')',
            'notes' => 'debit_note_id:'.(int) $debitNote->getKey(),
        ]);

        $lineNo = 1;
        foreach ($lines as $line) {
            $qty = (float) $line->quantity;
            $this->inventoryAdjustmentService->addItem((int) $adjustment->id, [
                'line_number' => $lineNo,
                'product_id' => (string) $line->product_id,
                'product_name' => (string) ($line->product_name ?: '—'),
                'sku' => $line->product_sku,
                'system_quantity' => $qty,
                'actual_quantity' => 0,
                'unit_cost' => (float) $line->price,
                'reason' => (string) ($line->return_reason ?? ''),
            ]);
            $lineNo++;
        }

        $this->inventoryAdjustmentService->approveAdjustment((int) $adjustment->id);
    }
}
