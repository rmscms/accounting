<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\AccountingDocument;
use RMS\Accounting\Models\Bank;
use RMS\Accounting\Models\CashBox;
use RMS\Accounting\Models\DebitNote;
use RMS\Accounting\Models\SupplierPayment;
use RMS\Accounting\Models\SupplierRefund;
use RMS\Accounting\Models\SupplierInvoice;
use RMS\Accounting\Models\SupplierInvoiceItem;
use RMS\Accounting\Models\Wallet;
use RMS\Accounting\Support\AccountingVatAccounts;
use RMS\Accounting\Services\Tax\TaxCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * سرویس مدیریت فاکتورهای خرید
 */
class SupplierInvoiceService
{
    protected LedgerService $ledgerService;
    protected DocumentService $documentService;
    protected PartyService $partyService;
    protected SupplierInvoiceCorrectionService $correctionService;

    public function __construct(
        LedgerService $ledgerService,
        DocumentService $documentService,
        PartyService $partyService,
        SupplierInvoiceCorrectionService $correctionService
    ) {
        $this->ledgerService = $ledgerService;
        $this->documentService = $documentService;
        $this->partyService = $partyService;
        $this->correctionService = $correctionService;
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
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'shipping_amount' => (float) ($item['shipping_amount'] ?? 0),
                ]);
            }

            // ثبت در دفتر کل
            $this->recordInvoiceInLedger($invoice->fresh());

            DB::commit();
            return $invoice;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * ثبت سند خرید و خطوط دفتر کل برای فاکتوری که از فرم ادمین ذخیره شده و هنوز سند ندارد.
     *
     * @throws ValidationException
     */
    public function postPurchaseAccountingDocument(SupplierInvoice $invoice): void
    {
        $invoice->refresh();

        if ($invoice->document_id) {
            throw ValidationException::withMessages([
                '_invoice' => (string) trans('accounting::accounting.supplier_invoice.post_document_already'),
            ]);
        }

        $this->recordInvoiceInLedger($invoice);
    }

    /**
     * ثبت فاکتور در دفتر کل
     */
    protected function recordInvoiceInLedger(SupplierInvoice $invoice): void
    {
        $this->syncInvoiceTotalsFromItems($invoice);

        if ($invoice->document_id) {
            throw new \LogicException((string) trans('accounting::accounting.supplier_invoice.post_document_already'));
        }

        // Load supplier with party relationship
        if (! $invoice->relationLoaded('supplier')) {
            $invoice->load('supplier.party');
        }
        $supplier = $invoice->supplier;

        $settlementMode = (string) ($invoice->getAttribute('settlement_mode') ?: SupplierInvoice::SETTLEMENT_ON_ACCOUNT);
        $paidAtSource = ($settlementMode === SupplierInvoice::SETTLEMENT_PAID_AT_SOURCE)
            && (
                ((int) ($invoice->paid_at_source_bank_id ?? 0)) > 0
                || ((int) ($invoice->paid_at_source_cash_box_id ?? 0)) > 0
                || ((int) ($invoice->paid_at_source_wallet_id ?? 0)) > 0
            );

        $treasuryCreditAccountId = null;
        if ($paidAtSource) {
            if ((int) ($invoice->paid_at_source_bank_id ?? 0) > 0) {
                $bank = Bank::query()->find((int) $invoice->paid_at_source_bank_id);
                $treasuryCreditAccountId = $bank && $bank->account_id ? (int) $bank->account_id : null;
            }
            if (! $treasuryCreditAccountId && (int) ($invoice->paid_at_source_cash_box_id ?? 0) > 0) {
                $box = CashBox::query()->find((int) $invoice->paid_at_source_cash_box_id);
                $treasuryCreditAccountId = $box && $box->account_id ? (int) $box->account_id : null;
            }
            if (! $treasuryCreditAccountId && (int) ($invoice->paid_at_source_wallet_id ?? 0) > 0) {
                $wallet = Wallet::query()
                    ->whereKey((int) $invoice->paid_at_source_wallet_id)
                    ->where('wallet_type', Wallet::TYPE_TREASURY)
                    ->where('active', true)
                    ->first();
                $treasuryCreditAccountId = $wallet && $wallet->account_id ? (int) $wallet->account_id : null;
            }
        }
        if (! $treasuryCreditAccountId) {
            $paidAtSource = false;
        }

        // دریافت یا ایجاد حساب فرعی supplier (بستانکار پرداختنی — فقط حالت نسیه)
        $payableAccountId = null;
        if (! $paidAtSource) {
            if ($supplier) {
                if ($supplier->party_id) {
                    $payableAccount = $this->partyService->getOrCreateSupplierAccount($supplier->party_id);
                    $payableAccountId = $payableAccount->id;
                } elseif ($supplier->account_id) {
                    $payableAccountId = $supplier->account_id;
                }
            }
            if (! $payableAccountId) {
                $payableAccountId = config('accounting.accounts.accounts_payable');
            }
        }

        // دریافت یا ایجاد حساب فرعی هزینه supplier
        $costAccountId = null;
        if ($supplier && $supplier->party_id) {
            $costAccount = $this->partyService->getOrCreateSupplierCostAccount($supplier->party_id);
            $costAccountId = $costAccount->id;
        }
        
        // Fallback به حساب کنترل در صورت عدم وجود حساب فرعی
        if (!$costAccountId) {
            $costAccountId = config('accounting.accounts.inventory');
        }

        // ایجاد سند حسابداری
        $document = $this->documentService->createDocument([
            'document_type' => AccountingDocument::TYPE_PURCHASE,
            'store_id' => $invoice->store_id,
            'fiscal_year_id' => config('accounting.current_fiscal_year_id'),
            'reference_type' => AccountingDocument::REF_EVENT,
            'reference_id' => $invoice->id,
            'description' => "فاکتور خرید {$invoice->invoice_number}",
            'total_debit' => $invoice->total_amount,
            'total_credit' => $invoice->total_amount,
        ]);

        $creditTotal = round((float) $invoice->total_amount, 4);
        $taxAmt = round((float) ($invoice->tax_amount ?? 0), 4);
        $costDebit = round((float) $invoice->subtotal + (float) ($invoice->shipping_amount ?? 0), 4);
        $vatReceivableId = null;

        if ($taxAmt > 0.00005) {
            $partsSum = round($costDebit + $taxAmt, 4);
            if (abs($partsSum - $creditTotal) > 0.02) {
                $costDebit = round(max(0.0, $creditTotal - $taxAmt), 4);
            }
            $vatReceivableId = AccountingVatAccounts::resolveReceivableAccountId();
            if (! $vatReceivableId) {
                throw ValidationException::withMessages([
                    '_vat' => (string) trans('accounting::accounting.supplier_invoice.errors.vat_receivable_account_missing'),
                ]);
            }
        }

        // آرتیکل بدهکار: بهای تمام‌شده (خالص + حمل)
        $this->ledgerService->recordEntry([
            'event_type' => 'purchase',
            'event_source' => 'supplier',
            'source_reference_type' => SupplierInvoice::class,
            'source_reference_id' => $invoice->id,
            'store_id' => $invoice->store_id,
            'account_id' => $costAccountId,
            'currency_code' => $invoice->currency_code,
            'debit_amount' => $costDebit,
            'credit_amount' => 0,
            'fx_rate_to_base' => $invoice->fx_rate_at_invoice,
            'accounting_document_id' => $document->id,
            'description' => "خرید کالا {$invoice->invoice_number}",
        ]);

        // آرتیکل بدهکار: مالیات بر ارزش افزوده ورودی
        if ($taxAmt > 0.00005 && $vatReceivableId) {
            $this->ledgerService->recordEntry([
                'event_type' => 'purchase',
                'event_source' => 'supplier',
                'source_reference_type' => SupplierInvoice::class,
                'source_reference_id' => $invoice->id,
                'store_id' => $invoice->store_id,
                'account_id' => (int) $vatReceivableId,
                'currency_code' => $invoice->currency_code,
                'debit_amount' => $taxAmt,
                'credit_amount' => 0,
                'fx_rate_to_base' => $invoice->fx_rate_at_invoice,
                'accounting_document_id' => $document->id,
                'description' => "مالیات خرید {$invoice->invoice_number}",
            ]);
        }

        // آرتیکل بستانکار: پرداختنی (نسیه) یا بانک/صندوق (خرید نقد مستقیم)
        $creditAccountId = $paidAtSource ? $treasuryCreditAccountId : $payableAccountId;
        $creditDescription = $paidAtSource
            ? "پرداخت نقد در منبع — {$invoice->invoice_number}"
            : "بدهی به تامین‌کننده {$invoice->invoice_number}";

        $this->ledgerService->recordEntry([
            'event_type' => 'purchase',
            'event_source' => 'supplier',
            'source_reference_type' => SupplierInvoice::class,
            'source_reference_id' => $invoice->id,
            'store_id' => $invoice->store_id,
            'account_id' => $creditAccountId,
            'currency_code' => $invoice->currency_code,
            'debit_amount' => 0,
            'credit_amount' => $creditTotal,
            'fx_rate_to_base' => $invoice->fx_rate_at_invoice,
            'accounting_document_id' => $document->id,
            'description' => $creditDescription,
        ]);

        // ثبت قطعی سند
        $this->documentService->postDocument($document->id);

        // ذخیره شماره سند
        $invoice->update(['document_id' => $document->id]);
    }

    protected function syncInvoiceTotalsFromItems(SupplierInvoice $invoice): void
    {
        $invoice->load(['items' => static fn ($query) => $query->orderBy('id')]);
        if ($invoice->items->isEmpty()) {
            throw ValidationException::withMessages([
                '_items' => (string) trans('accounting::accounting.supplier_invoice.items_required_for_post'),
            ]);
        }

        $method = in_array((string) ($invoice->tax_method ?? ''), ['inclusive', 'exclusive'], true)
            ? (string) $invoice->tax_method
            : tax_calculation_method();
        $subtotal = 0.0;
        $discount = 0.0;
        $tax = 0.0;

        foreach ($invoice->items as $item) {
            $qty = (float) ($item->quantity ?? 0);
            $unitPrice = (float) ($item->unit_price ?? 0);
            $lineDiscount = (float) ($item->discount_amount ?? 0);
            $lineNet = max(0, $qty * $unitPrice - $lineDiscount);
            $rate = is_vat_enabled() ? (float) ($item->tax_rate ?? 0) : 0.0;

            if (is_vat_enabled()) {
                $result = TaxCalculator::calculateVAT($lineNet, $rate, $method);
                $lineTax = (float) ($result['tax_amount'] ?? 0);
                $lineBase = (float) ($result['base_amount'] ?? $lineNet);
                $lineTotal = (float) ($result['total_amount'] ?? ($lineBase + $lineTax));
            } else {
                $lineTax = 0.0;
                $lineBase = $lineNet;
                $lineTotal = $lineNet;
            }

            $item->forceFill([
                'tax_amount' => $lineTax,
                'total_price' => $lineTotal,
            ])->saveQuietly();

            $subtotal += $lineBase;
            $discount += $lineDiscount;
            $tax += $lineTax;
        }

        $subtotal = round($subtotal, 4);
        $tax = round($tax, 4);
        $discount = round($discount, 4);
        $total = round($subtotal + $tax, 4);

        $settlementMode = (string) ($invoice->getAttribute('settlement_mode') ?: SupplierInvoice::SETTLEMENT_ON_ACCOUNT);
        if ($settlementMode === SupplierInvoice::SETTLEMENT_PAID_AT_SOURCE) {
            $paid = $total;
            $balance = 0.0;
            $paymentStatus = SupplierInvoice::STATUS_PAID;
        } else {
            $paid = min(max(0.0, (float) ($invoice->paid_amount ?? 0)), $total);
            $balance = max(0.0, $total - $paid);
            $paymentStatus = $paid <= 0.0001
                ? SupplierInvoice::STATUS_UNPAID
                : ($balance <= 0.0001 ? SupplierInvoice::STATUS_PAID : SupplierInvoice::STATUS_PARTIALLY_PAID);
        }

        $fxRate = (float) ($invoice->fx_rate_at_invoice ?: 1);

        $invoice->forceFill([
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'discount_amount' => $discount,
            'total_amount' => $total,
            'amount_base_at_invoice' => round($total * $fxRate, 4),
            'paid_amount' => $paid,
            'balance_due' => $balance,
            'payment_status' => $paymentStatus,
        ])->saveQuietly();

        $invoice->refresh();
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

    /**
     * Reverse posted invoice document and create linked replacement invoice.
     *
     * @return array{reversal_document: AccountingDocument, replacement_invoice: SupplierInvoice}
     *
     * @throws ValidationException
     */
    public function reverseAndCreateReplacement(SupplierInvoice $invoice, ?string $reason = null): array
    {
        $invoice->refresh();
        $this->assertInvoiceCanBeReversed($invoice);

        return DB::transaction(function () use ($invoice, $reason): array {
            $groupId = $this->correctionService->ensureCorrectionGroupId($invoice);

            $reversalDocument = $this->documentService->reverseDocument(
                (int) $invoice->document_id,
                trim((string) ($reason ?: trans('accounting::accounting.supplier_invoice.correction_default_reason')))
            );

            $replacement = $this->createReplacementInvoice($invoice, $groupId);

            $this->correctionService->record($invoice, 'reversal', [
                'correction_group_id' => $groupId,
                'source_document_id' => $invoice->document_id,
                'target_document_id' => $reversalDocument->id,
                'source_invoice_id' => $invoice->getKey(),
                'target_invoice_id' => $invoice->getKey(),
                'reason' => $reason,
            ]);

            $this->correctionService->record($replacement, 'replacement', [
                'correction_group_id' => $groupId,
                'source_invoice_id' => $invoice->getKey(),
                'target_invoice_id' => $replacement->getKey(),
                'source_document_id' => $invoice->document_id,
                'reason' => $reason,
            ]);

            return [
                'reversal_document' => $reversalDocument,
                'replacement_invoice' => $replacement,
            ];
        });
    }

    protected function assertInvoiceCanBeReversed(SupplierInvoice $invoice): void
    {
        if ((int) ($invoice->document_id ?? 0) <= 0) {
            throw ValidationException::withMessages([
                '_invoice' => (string) trans('accounting::accounting.supplier_invoice.correction_requires_posted'),
            ]);
        }

        $doc = AccountingDocument::query()->find((int) $invoice->document_id);
        if ($doc && (int) ($doc->reversed_by_document_id ?? 0) > 0) {
            throw ValidationException::withMessages([
                '_invoice' => (string) trans('accounting::accounting.supplier_invoice.correction_already_reversed'),
            ]);
        }

        $hasCompletedPayments = SupplierPayment::query()
            ->where('supplier_invoice_id', $invoice->getKey())
            ->where('status', SupplierPayment::STATUS_COMPLETED)
            ->exists();
        if ($hasCompletedPayments) {
            throw ValidationException::withMessages([
                '_invoice' => (string) trans('accounting::accounting.supplier_invoice.correction_blocked_by_payments'),
            ]);
        }

        $hasDebitNotes = DebitNote::query()
            ->where('supplier_invoice_id', $invoice->getKey())
            ->where('status', '!=', DebitNote::STATUS_VOID)
            ->exists();
        if ($hasDebitNotes) {
            throw ValidationException::withMessages([
                '_invoice' => (string) trans('accounting::accounting.supplier_invoice.correction_blocked_by_debit_note'),
            ]);
        }

        $hasRefunds = SupplierRefund::query()
            ->where('supplier_invoice_id', $invoice->getKey())
            ->exists();
        if ($hasRefunds) {
            throw ValidationException::withMessages([
                '_invoice' => (string) trans('accounting::accounting.supplier_invoice.correction_blocked_by_refund'),
            ]);
        }
    }

    protected function createReplacementInvoice(SupplierInvoice $invoice, string $groupId): SupplierInvoice
    {
        $replacement = $invoice->replicate([
            'document_id',
            'payment_status',
            'paid_amount',
            'balance_due',
        ]);
        $replacement->invoice_number = SupplierInvoice::suggestNextInvoiceNumber();
        $replacement->document_id = null;
        $replacement->original_invoice_id = $invoice->getKey();
        $replacement->correction_group_id = $groupId;
        $replacement->payment_status = SupplierInvoice::STATUS_UNPAID;
        $replacement->paid_amount = 0;
        $replacement->balance_due = $invoice->total_amount;
        $replacement->save();

        foreach ($invoice->items()->orderBy('id')->get() as $item) {
            $newItem = $item->replicate();
            $newItem->supplier_invoice_id = $replacement->getKey();
            $newItem->save();
        }

        return $replacement->fresh();
    }
}
