<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\{
    AdvanceApplication,
    Customer,
    CustomerAdvance,
    CustomerInvoice,
    PaymentMethod,
    POSTerminal,
    Supplier,
    SupplierAdvance,
    SupplierInvoice,
    Wallet,
};
use Illuminate\Support\Facades\{DB, Log};
use RMS\Accounting\Support\AuditActor;

/**
 * Advance Payment Service
 * پیش دریافت/پرداخت
 * استاندارد: IFRS 15
 */
class AdvancePaymentService
{
    protected LedgerService $ledgerService;

    protected ChequeLedgerService $chequeLedgerService;

    protected PartyService $partyService;

    public function __construct(
        LedgerService $ledgerService,
        ChequeLedgerService $chequeLedgerService,
        PartyService $partyService
    )
    {
        $this->ledgerService = $ledgerService;
        $this->chequeLedgerService = $chequeLedgerService;
        $this->partyService = $partyService;
    }

    // ========================================
    // Customer Advance (پیش دریافت)
    // ========================================

    public function receiveCustomerAdvance(array $data): CustomerAdvance
    {
        DB::beginTransaction();
        try {
            $customerPayload = [
                'advance_number' => $data['advance_number'] ?? $this->generateNumber('CAD'),
                'customer_id' => $data['customer_id'],
                'store_id' => $data['store_id'] ?? 0,
                'advance_date' => $data['advance_date'] ?? now(),
                'amount' => $data['amount'],
                'currency_code' => $data['currency_code'] ?? 'IRR',
                'fx_rate' => $data['fx_rate'] ?? 1,
                'amount_base' => $data['amount'] * ($data['fx_rate'] ?? 1),
                'remaining_amount' => $data['amount'],
                'payment_method' => $data['payment_method'] ?? 'cash',
                'bank_id' => $data['bank_id'] ?? null,
                'cash_box_id' => $data['cash_box_id'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ];
            $customerPayload = AuditActor::stamp($customerPayload, 'customer_advances', 'created');

            $advance = CustomerAdvance::create($customerPayload);
            
            $this->recordCustomerAdvanceInLedger($advance);
            DB::commit();
            return $advance;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function recordCustomerAdvanceInLedger(CustomerAdvance $advance): void
    {
        $cashAccountId = $advance->bank_id 
            ? $this->getBankAccountId($advance->bank_id)
            : $this->getCashBoxAccountId($advance->cash_box_id);
        $customerAdvanceAccountId = $this->resolveCustomerAdvanceLiabilityAccountId($advance);
            
        $document = $this->ledgerService->recordTransaction([
            'document_type' => 'customer_advance',
            'store_id' => $advance->store_id,
            'reference_type' => CustomerAdvance::class,
            'reference_id' => $advance->id,
            'description' => "پیش دریافت {$advance->advance_number}",
        ], [
            ['account_id' => $cashAccountId, 'debit_amount' => $advance->amount, 'credit_amount' => 0, 'description' => 'افزایش موجودی'],
            ['account_id' => $customerAdvanceAccountId, 'debit_amount' => 0, 'credit_amount' => $advance->amount, 'description' => 'پیش دریافت (بدهی)'],
        ]);
        
        $advance->update(['accounting_document_id' => $document->id]);
    }

    public function applyCustomerAdvanceToInvoice(int $advanceId, int $invoiceId, float $amount): void
    {
        DB::beginTransaction();
        try {
            $advance = CustomerAdvance::findOrFail($advanceId);
            $invoice = CustomerInvoice::findOrFail($invoiceId);
            
            if ($advance->customer_id !== $invoice->customer_id) {
                throw new \Exception('Customer mismatch');
            }
            
            if ($amount > $advance->remaining_amount) {
                throw new \Exception('Amount exceeds remaining advance');
            }
            
            // ثبت اعمال
            AdvanceApplication::create([
                'advance_type' => 'customer',
                'advance_id' => $advanceId,
                'invoice_type' => CustomerInvoice::class,
                'invoice_id' => $invoiceId,
                'applied_amount' => $amount,
                'application_date' => now(),
            ]);
            
            // آپدیت Advance
            $advance->applied_amount += $amount;
            $advance->remaining_amount -= $amount;
            if ($advance->remaining_amount <= 0) {
                $advance->status = CustomerAdvance::STATUS_FULLY_APPLIED;
            }
            $advance->save();
            
            // آپدیت Invoice (کاهش balance_due)
            $invoice->paid_amount += $amount;
            $invoice->balance_due -= $amount;
            if ($invoice->balance_due <= 0) {
                $invoice->payment_status = 'paid';
                $invoice->balance_due = 0;
            } elseif ($invoice->paid_amount > 0) {
                $invoice->payment_status = 'partially_paid';
            }
            $invoice->save();
            
            // ثبت در دفتر کل
            $this->recordAdvanceApplication($advance, $invoice, $amount);
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function recordAdvanceApplication(CustomerAdvance $advance, CustomerInvoice $invoice, float $amount): void
    {
        $customerAdvanceAccountId = $this->resolveCustomerAdvanceLiabilityAccountId($advance);
        $customerReceivableAccountId = $this->resolveCustomerReceivableAccountId($advance, $invoice);

        if ($customerAdvanceAccountId === $customerReceivableAccountId) {
            // با سیاست «تفصیلی مشترک مشتری»، اعمال پیش‌دریافت اثر مالی جدیدی در دفترکل ندارد.
            return;
        }

        $this->ledgerService->recordTransaction([
            'document_type' => 'advance_application',
            'store_id' => $advance->store_id,
            'description' => "اعمال پیش دریافت به فاکتور {$invoice->invoice_number}",
        ], [
            ['account_id' => $customerAdvanceAccountId, 'debit_amount' => $amount, 'credit_amount' => 0, 'description' => 'کاهش پیش دریافت'],
            ['account_id' => $customerReceivableAccountId, 'debit_amount' => 0, 'credit_amount' => $amount, 'description' => 'کاهش دریافتنی'],
        ]);
    }

    // ========================================
    // Supplier Advance (پیش پرداخت)
    // ========================================

    public function paySupplierAdvance(array $data): SupplierAdvance
    {
        DB::beginTransaction();
        try {
            $supplierPayload = [
                'advance_number' => $data['advance_number'] ?? $this->generateNumber('SAD'),
                'supplier_id' => $data['supplier_id'],
                'store_id' => $data['store_id'] ?? 0,
                'advance_date' => $data['advance_date'] ?? now(),
                'amount' => $data['amount'],
                'currency_code' => $data['currency_code'] ?? 'IRR',
                'fx_rate' => $data['fx_rate'] ?? 1,
                'amount_base' => $data['amount'] * ($data['fx_rate'] ?? 1),
                'remaining_amount' => $data['amount'],
                'payment_method' => $data['payment_method'] ?? 'bank_transfer',
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'bank_id' => $data['bank_id'] ?? null,
                'cash_box_id' => $data['cash_box_id'] ?? null,
                'cheque_id' => $data['cheque_id'] ?? null,
                'pos_terminal_id' => $data['pos_terminal_id'] ?? null,
                'wallet_id' => $data['wallet_id'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ];
            $supplierPayload = AuditActor::stamp($supplierPayload, 'supplier_advances', 'created');

            $advance = SupplierAdvance::create($supplierPayload);
            
            $this->recordSupplierAdvanceInLedger($advance);
            DB::commit();
            return $advance;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function recordSupplierAdvanceInLedger(SupplierAdvance $advance): void
    {
        $cashAccountId = $this->resolveSupplierAdvanceCreditAccountId($advance);
        $supplierAdvanceAccountId = $this->resolveSupplierAdvanceAssetAccountId($advance);
            
        $document = $this->ledgerService->recordTransaction([
            'document_type' => 'supplier_advance',
            'store_id' => $advance->store_id,
            'reference_type' => SupplierAdvance::class,
            'reference_id' => $advance->id,
            'description' => "پیش پرداخت {$advance->advance_number}",
        ], [
            ['account_id' => $supplierAdvanceAccountId, 'debit_amount' => $advance->amount, 'credit_amount' => 0, 'description' => 'پیش پرداخت تامین‌کننده'],
            ['account_id' => $cashAccountId, 'debit_amount' => 0, 'credit_amount' => $advance->amount, 'description' => 'کاهش موجودی'],
        ]);
        
        $advance->update(['accounting_document_id' => $document->id]);
    }

    public function applySupplierAdvanceToInvoice(int $advanceId, int $invoiceId, float $amount): void
    {
        DB::beginTransaction();
        try {
            $advance = SupplierAdvance::findOrFail($advanceId);
            $invoice = SupplierInvoice::findOrFail($invoiceId);
            
            if ($advance->supplier_id !== $invoice->supplier_id) throw new \Exception('Supplier mismatch');
            if ($amount > $advance->remaining_amount) throw new \Exception('Amount exceeds remaining advance');
            
            AdvanceApplication::create(['advance_type' => 'supplier', 'advance_id' => $advanceId, 'invoice_type' => SupplierInvoice::class, 'invoice_id' => $invoiceId, 'applied_amount' => $amount, 'application_date' => now()]);
            
            $advance->applied_amount += $amount;
            $advance->remaining_amount -= $amount;
            if ($advance->remaining_amount <= 0) $advance->status = SupplierAdvance::STATUS_FULLY_APPLIED;
            $advance->save();
            
            $invoice->paid_amount += $amount;
            $invoice->balance_due -= $amount;
            if ($invoice->balance_due <= 0) { $invoice->payment_status = 'paid'; $invoice->balance_due = 0; }
            elseif ($invoice->paid_amount > 0) $invoice->payment_status = 'partially_paid';
            $invoice->save();
            
            $supplierPayableAccountId = $this->resolveSupplierPayableAccountId($advance, $invoice);
            $supplierAdvanceAccountId = $this->resolveSupplierAdvanceAssetAccountId($advance);
            if ($supplierPayableAccountId !== $supplierAdvanceAccountId) {
                $this->ledgerService->recordTransaction(
                    [
                        'document_type' => 'advance_application',
                        'store_id' => $advance->store_id,
                        'description' => "اعمال پیش پرداخت به فاکتور {$invoice->invoice_number}",
                    ],
                    [
                        ['account_id' => $supplierPayableAccountId, 'debit_amount' => $amount, 'credit_amount' => 0, 'description' => 'کاهش بدهی تامین‌کننده'],
                        ['account_id' => $supplierAdvanceAccountId, 'debit_amount' => 0, 'credit_amount' => $amount, 'description' => 'کاهش پیش‌پرداخت تامین‌کننده'],
                    ]
                );
            }
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function generateNumber(string $prefix): string
    {
        $year = date('Y'); $month = date('m');
        $table = $prefix === 'CAD' ? 'customer_advances' : 'supplier_advances';
        $last = DB::table($table)->whereYear('created_at', $year)->whereMonth('created_at', $month)->orderBy('id', 'desc')->first();
        $nextNumber = $last ? intval(substr($last->advance_number, -6)) + 1 : 1;
        return sprintf('%s-%s%s-%06d', $prefix, $year, $month, $nextNumber);
    }

    protected function getARAccountId(): int { return \RMS\Core\Models\Setting::get('accounting.ar_account_id', 1); }
    protected function getAPAccountId(): int { return \RMS\Core\Models\Setting::get('accounting.ap_account_id', 1); }
    protected function getBankAccountId(int $bankId): int { $bank = DB::table('banks')->find($bankId); return $bank->account_id ?? 1; }
    protected function getCashBoxAccountId(?int $id): int { if (!$id) return 1; $cb = DB::table('cash_boxes')->find($id); return $cb->account_id ?? 1; }

    /**
     * حساب دفترکل بستانکار (کاهش نقد/بانک/…) برای پیش‌پرداخت تأمین‌کننده.
     */
    protected function resolveSupplierAdvanceCreditAccountId(SupplierAdvance $advance): int
    {
        if ((int) $advance->bank_id > 0) {
            return $this->getBankAccountId((int) $advance->bank_id);
        }
        if ((int) $advance->cash_box_id > 0) {
            return $this->getCashBoxAccountId((int) $advance->cash_box_id);
        }
        if ((int) $advance->cheque_id > 0) {
            $clearingId = $this->chequeLedgerService->resolvePayableClearingAccountId();

            return ($clearingId !== null && $clearingId > 0) ? $clearingId : 1;
        }
        if ((int) $advance->wallet_id > 0) {
            $w = Wallet::query()->find((int) $advance->wallet_id);

            return $w && $w->account_id ? (int) $w->account_id : 1;
        }
        if ((int) $advance->pos_terminal_id > 0) {
            $pos = POSTerminal::query()->with('bank')->find((int) $advance->pos_terminal_id);
            if ($pos && (int) $pos->bank_id > 0) {
                return $this->getBankAccountId((int) $pos->bank_id);
            }
        }
        if ((int) $advance->payment_method_id > 0) {
            $pm = PaymentMethod::query()->find((int) $advance->payment_method_id);
            if ($pm && $pm->account_id) {
                return (int) $pm->account_id;
            }
        }

        return 1;
    }

    /**
     * حساب مشتری یکپارچه (تفصیلی مشترک) را resolve می‌کند.
     */
    protected function resolveCustomerAdvanceLiabilityAccountId(CustomerAdvance $advance): int
    {
        return $this->resolveUnifiedCustomerAccountId((int) $advance->customer_id);
    }

    /**
     * حساب دریافتنی مشتری را بر اساس تفصیلی مشترک resolve می‌کند.
     */
    protected function resolveCustomerReceivableAccountId(CustomerAdvance $advance, CustomerInvoice $invoice): int
    {
        $customerId = (int) ($invoice->customer_id ?: $advance->customer_id);
        return $this->resolveUnifiedCustomerAccountId($customerId);
    }

    protected function resolveUnifiedCustomerAccountId(int $customerId): int
    {
        $customer = Customer::query()->find($customerId);
        if (! $customer) {
            throw new \RuntimeException("مشتری با شناسه {$customerId} یافت نشد.");
        }

        $this->partyService->ensurePartyForCustomer($customer);
        $customer->refresh();

        if ((int) ($customer->account_id ?? 0) <= 0) {
            $partyId = (int) ($customer->party_id ?? 0);
            if ($partyId <= 0) {
                throw new \RuntimeException("برای مشتری {$customer->id} party_id تعریف نشده است.");
            }
            $account = $this->partyService->getOrCreateCustomerAccount($partyId);
            $customer->account_id = (int) $account->id;
            $customer->save();
            $customer->refresh();
        }

        $accountId = (int) ($customer->account_id ?? 0);
        if ($accountId <= 0) {
            throw new \RuntimeException("حساب تفصیلی مشتری {$customer->id} ایجاد/یافت نشد.");
        }

        return $accountId;
    }

    /**
     * حساب پیش‌پرداخت تامین‌کننده را با سیاست «تفصیلی مشترک تامین‌کننده» resolve می‌کند.
     */
    protected function resolveSupplierAdvanceAssetAccountId(SupplierAdvance $advance): int
    {
        return $this->resolveUnifiedSupplierAccountId((int) $advance->supplier_id);
    }

    protected function resolveSupplierPayableAccountId(SupplierAdvance $advance, SupplierInvoice $invoice): int
    {
        $supplierId = (int) ($invoice->supplier_id ?: $advance->supplier_id);
        return $this->resolveUnifiedSupplierAccountId($supplierId);
    }

    protected function resolveUnifiedSupplierAccountId(int $supplierId): int
    {
        $supplier = Supplier::query()->find($supplierId);
        if (! $supplier) {
            throw new \RuntimeException("تامین‌کننده با شناسه {$supplierId} یافت نشد.");
        }

        $this->partyService->ensurePartyForSupplier($supplier);
        $supplier->refresh();

        if ((int) ($supplier->account_id ?? 0) <= 0) {
            $partyId = (int) ($supplier->party_id ?? 0);
            if ($partyId <= 0) {
                throw new \RuntimeException("برای تامین‌کننده {$supplier->id} party_id تعریف نشده است.");
            }
            $account = $this->partyService->getOrCreateSupplierAccount($partyId);
            $supplier->account_id = (int) $account->id;
            $supplier->save();
            $supplier->refresh();
        }

        $accountId = (int) ($supplier->account_id ?? 0);
        if ($accountId <= 0) {
            throw new \RuntimeException("حساب تفصیلی تامین‌کننده {$supplier->id} ایجاد/یافت نشد.");
        }

        return $accountId;
    }
}
