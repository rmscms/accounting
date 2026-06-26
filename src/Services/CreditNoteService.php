<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\{CreditNote, CreditNoteItem, Customer, CustomerInvoice};
use RMS\Accounting\Services\CustomerInvoiceCorrectionService;
use RMS\Accounting\Support\AccountingVatAccounts;
use RMS\Accounting\Support\AuditActor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Credit Note Service
 * 
 * مدیریت اعتبار برگشتی (Sales Returns)
 * استاندارد: IFRS 15
 */
class CreditNoteService
{
    protected LedgerService $ledgerService;
    protected TaxService $taxService;
    protected PartyService $partyService;

    public function __construct(
        LedgerService $ledgerService,
        TaxService $taxService,
        PartyService $partyService
    ) {
        $this->ledgerService = $ledgerService;
        $this->taxService = $taxService;
        $this->partyService = $partyService;
    }

    /**
     * ایجاد Credit Note جدید
     */
    public function createCreditNote(array $data): CreditNote
    {
        DB::beginTransaction();
        
        try {
            $data = $this->normalizeCreditNotePayload($data);

            // 1. تولید شماره خودکار
            if (empty($data['credit_note_number'])) {
                $data['credit_note_number'] = $this->generateCreditNoteNumber();
            }
            
            // 2. ایجاد Credit Note
            $createPayload = [
                'credit_note_number' => $data['credit_note_number'],
                'customer_id' => $data['customer_id'],
                'customer_invoice_id' => $data['customer_invoice_id'] ?? null,
                'store_id' => $data['store_id'] ?? 0,
                'credit_date' => $data['credit_date'],
                'reason' => $data['reason'] ?? null,
                'credit_type' => $data['credit_type'] ?? CreditNote::TYPE_RETURN,
                'currency_code' => $data['currency_code'],
                'fx_rate' => $data['fx_rate'],
                'subtotal' => $data['subtotal'],
                'tax_amount' => $data['tax_amount'],
                'discount_amount' => $data['discount_amount'],
                'total_amount' => $data['total_amount'],
                'amount_base' => $data['amount_base'],
                'status' => CreditNote::STATUS_DRAFT,
                'notes' => $data['notes'] ?? null,
            ];
            $createPayload = AuditActor::stamp($createPayload, 'credit_notes', 'created');

            $creditNote = CreditNote::create($createPayload);
            
            // 3. ایجاد آیتم‌ها
            if (!empty($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    $this->addItem($creditNote, $itemData);
                }
            }
            
            // 4. محاسبه مجموع
            $this->recalculateTotals($creditNote);
            
            // 5. محاسبه مالیات
            if (!empty($data['apply_tax'])) {
                $this->applyTax($creditNote);
            }
            
            DB::commit();
            
            Log::info('Credit Note created', [
                'credit_note_id' => $creditNote->id,
                'credit_note_number' => $creditNote->credit_note_number,
            ]);
            
            return $creditNote->fresh(['items']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create Credit Note', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * اضافه کردن آیتم به Credit Note
     */
    public function addItem(CreditNote $creditNote, array $itemData): CreditNoteItem
    {
        if (!$creditNote->canBeEdited()) {
            throw new \Exception('Credit Note cannot be edited in current status');
        }
        
        $item = $creditNote->items()->create([
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
        
        // محاسبه tax_amount و total برای آیتم
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

    /**
     * محاسبه مجدد مجموع
     */
    public function recalculateTotals(CreditNote $creditNote): CreditNote
    {
        $items = $creditNote->items;
        
        $creditNote->subtotal = $items->sum(static function ($item) {
            $gross = (float) $item->quantity * (float) $item->price;
            return max(0, $gross - (float) $item->discount_amount);
        });
        $creditNote->tax_amount = $items->sum('tax_amount');
        $creditNote->discount_amount = $items->sum('discount_amount');
        $creditNote->total_amount = $creditNote->subtotal + $creditNote->tax_amount;
        $creditNote->amount_base = $creditNote->total_amount * $creditNote->fx_rate;
        
        $creditNote->save();
        
        return $creditNote;
    }

    /**
     * اعمال مالیات
     */
    public function applyTax(CreditNote $creditNote): CreditNote
    {
        // استفاده از TaxService برای محاسبه مالیات
        foreach ($creditNote->items as $item) {
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
        
        return $this->recalculateTotals($creditNote);
    }

    /**
     * صدور Credit Note (تغییر وضعیت به issued)
     */
    public function issueCreditNote(CreditNote $creditNote): CreditNote
    {
        if ($creditNote->isIssued() || $creditNote->isApplied()) {
            return $creditNote;
        }
        if (!$creditNote->isDraft()) {
            throw new \DomainException('Only draft credit notes can be issued');
        }
        
        DB::beginTransaction();
        
        try {
            $approvePayload = [
                'status' => CreditNote::STATUS_ISSUED,
                'approved_at' => now(),
            ];
            $approvePayload = AuditActor::stamp($approvePayload, 'credit_notes', 'approved');

            $creditNote->update($approvePayload);
            
            // ثبت در دفتر کل
            $this->recordInLedger($creditNote);
            
            DB::commit();
            
            Log::info('Credit Note issued', [
                'credit_note_id' => $creditNote->id,
            ]);
            
            return $creditNote;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * اعمال Credit Note به فاکتور
     */
    public function applyToInvoice(CreditNote $creditNote, int $invoiceId): CreditNote
    {
        if (! in_array((string) $creditNote->status, [CreditNote::STATUS_ISSUED, CreditNote::STATUS_APPLIED], true)) {
            throw new \DomainException('Credit Note cannot be applied in current status');
        }
        
        DB::beginTransaction();
        
        try {
            $invoice = CustomerInvoice::findOrFail($invoiceId);
            
            // بررسی اینکه مشتری یکی باشه
            if ($invoice->customer_id !== $creditNote->customer_id) {
                throw new \DomainException('Customer mismatch');
            }

            // آپدیت Credit Note
            $creditNote->update([
                'status' => CreditNote::STATUS_APPLIED,
                'applied_to_invoice_id' => $invoice->id,
                'applied_at' => now(),
            ]);

            $this->syncInvoiceFinancials($invoice->id);
            app(CustomerInvoiceCorrectionService::class)->recordAdjustmentFromCreditNote($creditNote->fresh());
            
            DB::commit();
            
            Log::info('Credit Note applied to invoice', [
                'credit_note_id' => $creditNote->id,
                'invoice_id' => $invoice->id,
            ]);
            
            return $creditNote;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * ثبت در دفتر کل
     * 
     * بدهکار: فروش (کاهش فروش)
     * بدهکار: مالیات پرداختنی (کاهش مالیات)
     * بستانکار: دریافتنی از مشتری (کاهش دریافتنی)
     */
    protected function recordInLedger(CreditNote $creditNote): void
    {
        $totalAmount = round((float) ($creditNote->total_amount ?? 0), 4);
        $taxAmount = round(max(0, (float) ($creditNote->tax_amount ?? 0)), 4);
        $discountAmount = round(max(0, (float) ($creditNote->discount_amount ?? 0)), 4);
        $subtotalAmount = round((float) ($creditNote->subtotal ?? 0), 4);

        if ($subtotalAmount <= 0 && $totalAmount > 0) {
            $subtotalAmount = round(max(0, $totalAmount - $taxAmount + $discountAmount), 4);
        }
        if ($subtotalAmount <= 0 && $totalAmount > 0) {
            $subtotalAmount = $totalAmount;
        }

        $debitTotal = round($subtotalAmount + $taxAmount, 4);
        if (abs($debitTotal - $totalAmount) > 0.0001) {
            $subtotalAmount = round(max(0, $subtotalAmount + ($totalAmount - $debitTotal)), 4);
        }

        $entries = [];
        
        // بدهکار: حساب فروش (کاهش فروش)
        $entries[] = [
            'account_id' => $this->getSalesAccountId(),
            'debit_amount' => $subtotalAmount,
            'credit_amount' => 0,
            'description' => "برگشت فروش - {$creditNote->credit_note_number}",
        ];
        
        // بدهکار: حساب مالیات پرداختنی (کاهش مالیات)
        if ($taxAmount > 0) {
            $entries[] = [
                'account_id' => $this->getTaxPayableAccountId(),
                'debit_amount' => $taxAmount,
                'credit_amount' => 0,
                'description' => "مالیات برگشت فروش - {$creditNote->credit_note_number}",
            ];
        }
        
        // بستانکار: حساب دریافتنی از مشتری (کاهش دریافتنی)
        $entries[] = [
            'account_id' => $this->resolveCustomerReceivableAccountId($creditNote),
            'debit_amount' => 0,
            'credit_amount' => $totalAmount,
            'description' => "کاهش دریافتنی - {$creditNote->credit_note_number}",
        ];
        
        // ثبت سند حسابداری
        $document = $this->ledgerService->recordTransaction([
            'document_type' => 'credit_note',
            'store_id' => $creditNote->store_id,
            'reference_type' => CreditNote::class,
            'reference_id' => $creditNote->id,
            'description' => "اعتبار برگشتی {$creditNote->credit_note_number} - مشتری: {$creditNote->customer->name}",
        ], $entries);
        
        // لینک سند به Credit Note
        $creditNote->update(['accounting_document_id' => $document->id]);
    }

    /**
     * تولید شماره Credit Note
     */
    protected function generateCreditNoteNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        
        $lastCN = CreditNote::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();
        
        $nextNumber = $lastCN ? intval(substr($lastCN->credit_note_number, -6)) + 1 : 1;
        
        return sprintf('CN-%s%s-%06d', $year, $month, $nextNumber);
    }

    /**
     * دریافت ID حساب فروش
     */
    protected function getSalesAccountId(): int
    {
        return \RMS\Core\Models\Setting::get('accounting.sales_account_id', 1);
    }

    /**
     * دریافت ID حساب مالیات پرداختنی
     */
    protected function getTaxPayableAccountId(): int
    {
        return (int) (AccountingVatAccounts::resolvePayableAccountId() ?: 1);
    }

    /**
     * دریافت ID حساب دریافتنی
     */
    protected function getARAccountId(): int
    {
        return \RMS\Core\Models\Setting::get('accounting.ar_account_id', 1);
    }

    protected function resolveCustomerReceivableAccountId(CreditNote $creditNote): int
    {
        /** @var Customer|null $customer */
        $customer = $creditNote->customer;
        if (! $customer && (int) ($creditNote->customer_id ?? 0) > 0) {
            $customer = Customer::query()->find((int) $creditNote->customer_id);
        }

        if ($customer && (int) ($customer->party_id ?? 0) > 0) {
            try {
                $account = $this->partyService->getOrCreateCustomerAccount((int) $customer->party_id);
                if ((int) ($account->id ?? 0) > 0) {
                    return (int) $account->id;
                }
            } catch (\Throwable) {
                // Fall back to legacy AR account resolution.
            }
        }

        if ($customer && (int) ($customer->account_id ?? 0) > 0) {
            return (int) $customer->account_id;
        }

        return $this->getARAccountId();
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    protected function normalizeCreditNotePayload(array $data): array
    {
        $creditDateRaw = (string) ($data['credit_date'] ?? '');
        $data['credit_date'] = trim($creditDateRaw) !== '' ? $creditDateRaw : now()->toDateString();
        $data['currency_code'] = strtoupper((string) ($data['currency_code'] ?? 'IRR'));
        if ($data['currency_code'] === '') {
            $data['currency_code'] = 'IRR';
        }
        $data['fx_rate'] = max(0.000001, (float) ($data['fx_rate'] ?? 1));

        $subtotal = (float) ($data['subtotal'] ?? 0);
        $taxAmount = (float) ($data['tax_amount'] ?? 0);
        $discountAmount = (float) ($data['discount_amount'] ?? 0);
        $totalAmount = (float) ($data['total_amount'] ?? ($subtotal + $taxAmount - $discountAmount));
        if ($totalAmount < 0) {
            $totalAmount = 0;
        }

        $data['subtotal'] = $subtotal;
        $data['tax_amount'] = $taxAmount;
        $data['discount_amount'] = $discountAmount;
        $data['total_amount'] = $totalAmount;
        $data['amount_base'] = (float) ($data['amount_base'] ?? ($totalAmount * (float) $data['fx_rate']));

        return $data;
    }

    protected function syncInvoiceFinancials(int $invoiceId): void
    {
        $invoice = CustomerInvoice::query()->find($invoiceId);
        if (! $invoice) {
            return;
        }

        $payments = (float) DB::table('customer_payments')
            ->where('customer_invoice_id', $invoiceId)
            ->where('status', 'completed')
            ->sum('amount');

        $appliedCredits = (float) DB::table('credit_notes')
            ->where('applied_to_invoice_id', $invoiceId)
            ->whereIn('status', [CreditNote::STATUS_APPLIED, CreditNote::STATUS_REFUNDED])
            ->sum('total_amount');

        $paidAmount = round(max(0, $payments + $appliedCredits), 4);
        $balanceDue = round(max(0, (float) $invoice->total_amount - $paidAmount), 4);
        $paymentStatus = $paidAmount <= 0
            ? CustomerInvoice::STATUS_UNPAID
            : ($balanceDue <= 0.0001 ? CustomerInvoice::STATUS_PAID : CustomerInvoice::STATUS_PARTIALLY_PAID);

        $invoice->update([
            'paid_amount' => $paidAmount,
            'balance_due' => $balanceDue,
            'payment_status' => $paymentStatus,
        ]);
    }
}
