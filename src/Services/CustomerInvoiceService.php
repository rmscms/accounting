<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\AccountingDocument;
use RMS\Accounting\Models\Account;
use RMS\Accounting\Models\Bank;
use RMS\Accounting\Models\CashBox;
use RMS\Accounting\Models\CreditNote;
use RMS\Accounting\Models\Customer;
use RMS\Accounting\Models\CustomerInvoice;
use RMS\Accounting\Models\CustomerPayment;
use RMS\Accounting\Models\CustomerRefund;
use RMS\Accounting\Models\Currency;
use RMS\Accounting\Models\Wallet;
use RMS\Accounting\Support\AccountingVatAccounts;
use RMS\Accounting\Events\InvoiceCreatedEvent;
use RMS\Accounting\Services\Tax\TaxCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
    protected PartyService $partyService;
    protected LedgerFxResolver $ledgerFxResolver;
    protected CustomerInvoiceCorrectionService $correctionService;

    public function __construct(
        LedgerService $ledgerService,
        DocumentService $documentService,
        PartyService $partyService,
        LedgerFxResolver $ledgerFxResolver,
        CustomerInvoiceCorrectionService $correctionService
    ) {
        $this->ledgerService = $ledgerService;
        $this->documentService = $documentService;
        $this->partyService = $partyService;
        $this->ledgerFxResolver = $ledgerFxResolver;
        $this->correctionService = $correctionService;
    }

    /**
     * ثبت فاکتور فروش
     */
    public function createInvoice(array $data): CustomerInvoice
    {
        DB::beginTransaction();
        try {
            $this->enrichInvoiceData($data);

            // تولید شماره فاکتور
            if (empty($data['invoice_number'])) {
                $data['invoice_number'] = $this->generateInvoiceNumber($data['store_id']);
            }

            // ایجاد فاکتور
            $invoice = CustomerInvoice::create($data);

            // ثبت در دفتر کل (اگر وضعیت صادر شده باشد)
            if ($invoice->status === CustomerInvoice::STATUS_ISSUED) {
                $this->postSalesAccountingDocument($invoice);
            }

            // بروزرسانی مانده مشتری
            $this->updateCustomerBalance($invoice->customer_id, $invoice->store_id);

            // Dispatch Event
            event(new InvoiceCreatedEvent($invoice, [
                'store_id' => $invoice->store_id,
                'customer_id' => $invoice->customer_id,
                'total_amount' => $invoice->total_amount,
            ]));

            DB::commit();
            return $invoice;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * ارز و نرخ به پایه: از مشتری، جدول نرخ، یا ۱.
     *
     * @param  array<string, mixed>  $data
     */
    protected function enrichInvoiceData(array &$data): void
    {
        if (empty($data['currency_code'])) {
            $data['currency_code'] = Currency::resolveBaseCurrencyCode('IRR');
        }
        $data['currency_code'] = strtoupper((string) $data['currency_code']);

        $invoiceDate = $data['invoice_date'] ?? now()->toDateString();
        $dateStr = $invoiceDate instanceof \DateTimeInterface
            ? $invoiceDate->format('Y-m-d')
            : (string) $invoiceDate;

        $fx = (float) ($data['fx_rate'] ?? 0);
        if ($fx <= 0) {
            $data['fx_rate'] = $this->ledgerFxResolver->resolveRateToBase($data['currency_code'], $dateStr);
        }

        $fx = (float) $data['fx_rate'];
        if (empty($data['amount_base']) && isset($data['total_amount'])) {
            $data['amount_base'] = round((float) $data['total_amount'] * $fx, 4);
        }
    }

    /**
     * ثبت فاکتور در دفتر کل
     */
    protected function recordInvoiceInLedger(CustomerInvoice $invoice): void
    {
        $this->syncInvoiceTotalsFromItems($invoice);

        // Load customer with party relationship
        if (!$invoice->relationLoaded('customer')) {
            $invoice->load('customer.party');
        }
        $customer = $invoice->customer;
        
        // دریافت یا ایجاد حساب فرعی customer
        $receivableAccountId = null;
        if ($customer) {
            if ($customer->party_id) {
                // استفاده از حساب فرعی party
                $receivableAccount = $this->partyService->getOrCreateCustomerAccount($customer->party_id);
                $receivableAccountId = $receivableAccount->id;
            } elseif ($customer->account_id) {
                // استفاده از حساب موجود customer
                $receivableAccountId = $customer->account_id;
            }
        }
        
        // Fallback به حساب کنترل در صورت عدم وجود حساب فرعی
        if (!$receivableAccountId) {
            $receivableAccountId = config('accounting.accounts.accounts_receivable');
        }

        // دریافت یا ایجاد حساب فرعی درآمد customer
        $revenueAccountId = null;
        if ($customer && $customer->party_id) {
            $revenueAccount = $this->partyService->getOrCreateCustomerRevenueAccount($customer->party_id);
            $revenueAccountId = $revenueAccount->id;
        }
        
        // Fallback به حساب کنترل در صورت عدم وجود حساب فرعی
        if (!$revenueAccountId) {
            $revenueAccountId = config('accounting.accounts.sales_revenue');
        }

        $fx = (float) ($invoice->getAttribute('fx_rate') ?? 1);
        $total = round((float) $invoice->total_amount, 4);
        $tax = round((float) $invoice->tax_amount, 4);
        $discount = round(
            (float) ($invoice->discount_amount ?? 0) + (float) ($invoice->invoice_discount_amount ?? 0),
            4
        );
        $shipAmt = round((float) ($invoice->shipping_amount ?? 0), 4);
        $shipCharged = (bool) ($invoice->shipping_charged_to_customer ?? true);
        $shipCredit = ($shipCharged && $shipAmt > 0.00005) ? $shipAmt : 0.0;
        $merchandiseCredit = round(max(0.0, $total - $tax - $shipCredit), 4);
        $grossRevenueCredit = round(max(0.0, $merchandiseCredit + $discount), 4);
        $upfront = round((float) ($invoice->upfront_payment_amount ?? 0), 4);
        $upfront = max(0.0, min($upfront, $total));
        $receivableLeg = round(max(0.0, $total - $upfront), 4);
        $treasuryAccountId = $upfront > 0.00005 ? $this->resolvePaidAtSourceAccountId($invoice) : null;

        $shippingAccountId = config('accounting.accounts.shipping_revenue') ?: $revenueAccountId;
        $salesDiscountAccountId = config('accounting.accounts.sales_discount') ?: $revenueAccountId;

        // ایجاد سند حسابداری
        $document = $this->documentService->createDocument([
            'document_type' => AccountingDocument::TYPE_SALE,
            'store_id' => $invoice->store_id,
            'fiscal_year_id' => config('accounting.current_fiscal_year_id'),
            'reference_type' => AccountingDocument::REF_EVENT,
            'reference_id' => $invoice->id,
            'description' => "فاکتور فروش {$invoice->invoice_number} - مشتری {$invoice->customer_id}",
            'total_debit' => $total,
            'total_credit' => $total,
        ]);

        if ($receivableLeg > 0.00005) {
            $this->ledgerService->recordEntry([
                'event_type' => 'sale',
                'event_source' => 'shop',
                'source_reference_type' => CustomerInvoice::class,
                'source_reference_id' => $invoice->id,
                'store_id' => $invoice->store_id,
                'account_id' => $receivableAccountId,
                'currency_code' => $invoice->currency_code,
                'debit_amount' => $receivableLeg,
                'credit_amount' => 0,
                'fx_rate_to_base' => $fx,
                'accounting_document_id' => $document->id,
                'description' => "دریافتنی فاکتور فروش {$invoice->invoice_number}",
            ]);
        }

        if ($upfront > 0.00005 && $treasuryAccountId) {
            $this->ledgerService->recordEntry([
                'event_type' => 'sale',
                'event_source' => 'shop',
                'source_reference_type' => CustomerInvoice::class,
                'source_reference_id' => $invoice->id,
                'store_id' => $invoice->store_id,
                'account_id' => $treasuryAccountId,
                'currency_code' => $invoice->currency_code,
                'debit_amount' => $upfront,
                'credit_amount' => 0,
                'fx_rate_to_base' => $fx,
                'accounting_document_id' => $document->id,
                'description' => "دریافت نقدی فاکتور فروش {$invoice->invoice_number}",
            ]);
        }

        // بستانکار: درآمد ناخالص کالا/خدمت (قبل از تخفیف).
        if ($grossRevenueCredit > 0.00005) {
            $this->ledgerService->recordEntry([
                'event_type' => 'sale',
                'event_source' => 'shop',
                'source_reference_type' => CustomerInvoice::class,
                'source_reference_id' => $invoice->id,
                'store_id' => $invoice->store_id,
                'account_id' => $revenueAccountId,
                'currency_code' => $invoice->currency_code,
                'debit_amount' => 0,
                'credit_amount' => $grossRevenueCredit,
                'fx_rate_to_base' => $fx,
                'accounting_document_id' => $document->id,
                'description' => "درآمد ناخالص فروش {$invoice->invoice_number}",
            ]);
        }

        // بدهکار: تخفیف فروش (کاهندهٔ درآمد). اگر حساب اختصاصی تعریف نشده باشد روی همان حساب درآمد ثبت می‌شود.
        if ($discount > 0.00005) {
            $this->ledgerService->recordEntry([
                'event_type' => 'sale',
                'event_source' => 'shop',
                'source_reference_type' => CustomerInvoice::class,
                'source_reference_id' => $invoice->id,
                'store_id' => $invoice->store_id,
                'account_id' => $salesDiscountAccountId,
                'currency_code' => $invoice->currency_code,
                'debit_amount' => $discount,
                'credit_amount' => 0,
                'fx_rate_to_base' => $fx,
                'accounting_document_id' => $document->id,
                'description' => "تخفیف فروش {$invoice->invoice_number}",
            ]);
        }

        // بستانکار: مالیات (حساب از تنظیمات VAT با fallback به config)
        if ($tax > 0.00005) {
            $vatAccountId = AccountingVatAccounts::resolvePayableAccountId();
            if (! $vatAccountId) {
                throw ValidationException::withMessages([
                    '_vat' => (string) trans('accounting::accounting.invoice.errors.vat_payable_account_missing'),
                ]);
            }
            $this->ledgerService->recordEntry([
                'event_type' => 'sale',
                'event_source' => 'shop',
                'source_reference_type' => CustomerInvoice::class,
                'source_reference_id' => $invoice->id,
                'store_id' => $invoice->store_id,
                'account_id' => $vatAccountId,
                'currency_code' => $invoice->currency_code,
                'debit_amount' => 0,
                'credit_amount' => $tax,
                'fx_rate_to_base' => $fx,
                'accounting_document_id' => $document->id,
                'description' => "مالیات فروش {$invoice->invoice_number}",
            ]);
        }

        // بستانکار: درآمد حمل (در صورت فاکتور به مشتری)
        if ($shipCredit > 0.00005) {
            $this->ledgerService->recordEntry([
                'event_type' => 'sale',
                'event_source' => 'shop',
                'source_reference_type' => CustomerInvoice::class,
                'source_reference_id' => $invoice->id,
                'store_id' => $invoice->store_id,
                'account_id' => $shippingAccountId,
                'currency_code' => $invoice->currency_code,
                'debit_amount' => 0,
                'credit_amount' => $shipCredit,
                'fx_rate_to_base' => $fx,
                'accounting_document_id' => $document->id,
                'description' => "درآمد حمل {$invoice->invoice_number}",
            ]);
        }

        // ثبت قطعی سند
        $this->documentService->postDocument($document->id);

        // ذخیره شماره سند در فاکتور
        $invoice->forceFill(['document_id' => $document->id])->saveQuietly();
    }

    protected function syncInvoiceTotalsFromItems(CustomerInvoice $invoice): void
    {
        $invoice->load(['items' => static fn ($query) => $query->orderBy('id')]);
        if ($invoice->items->isEmpty()) {
            throw ValidationException::withMessages([
                '_items' => (string) trans('accounting::accounting.customer_invoice.items_required_for_post'),
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
            $price = (float) ($item->price ?? 0);
            $lineDiscount = (float) ($item->discount_amount ?? 0);
            $lineNet = max(0, $qty * $price - $lineDiscount);
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
                'total' => $lineTotal,
            ])->saveQuietly();

            $subtotal += $lineBase;
            $discount += $lineDiscount;
            $tax += $lineTax;
        }

        $subtotal = round($subtotal, 4);
        $tax = round($tax, 4);
        $discount = round($discount, 4);
        $total = round($subtotal + $tax, 4);

        $settlement = (string) ($invoice->settlement_mode ?: CustomerInvoice::SETTLEMENT_CREDIT);
        $upfront = (float) ($invoice->upfront_payment_amount ?? 0);
        if ($settlement === CustomerInvoice::SETTLEMENT_CASH) {
            $upfront = $total;
        } elseif ($settlement === CustomerInvoice::SETTLEMENT_CREDIT) {
            $upfront = 0;
        } else {
            $upfront = max(0, min($upfront, $total));
        }

        $balance = max(0, $total - $upfront);
        $paymentStatus = $balance <= 0.0001
            ? CustomerInvoice::STATUS_PAID
            : ($upfront > 0 ? CustomerInvoice::STATUS_PARTIALLY_PAID : CustomerInvoice::STATUS_UNPAID);
        $fxRate = (float) ($invoice->fx_rate ?: 1);

        $invoice->forceFill([
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'discount_amount' => $discount,
            'invoice_discount_amount' => 0,
            'total_amount' => $total,
            'upfront_payment_amount' => $upfront,
            'paid_amount' => $upfront,
            'balance_due' => $balance,
            'payment_status' => $paymentStatus,
            'amount_base' => round($total * $fxRate, 4),
        ])->saveQuietly();

        $invoice->refresh();
    }

    public function postSalesAccountingDocument(CustomerInvoice $invoice): CustomerInvoice
    {
        if ((int) ($invoice->document_id ?? 0) > 0) {
            return $invoice;
        }

        if ((string) $invoice->status !== CustomerInvoice::STATUS_ISSUED) {
            $invoice->status = CustomerInvoice::STATUS_ISSUED;
            $invoice->save();
        }

        $this->recordInvoiceInLedger($invoice->fresh(['customer.party']));

        return $invoice->fresh();
    }

    /**
     * Reverse posted invoice document and create linked replacement invoice.
     *
     * @return array{reversal_document: AccountingDocument, replacement_invoice: CustomerInvoice}
     *
     * @throws ValidationException
     */
    public function reverseAndCreateReplacement(CustomerInvoice $invoice, ?string $reason = null): array
    {
        $invoice->refresh();
        $this->assertInvoiceCanBeReversed($invoice);

        return DB::transaction(function () use ($invoice, $reason): array {
            $groupId = $this->correctionService->ensureCorrectionGroupId($invoice);
            $sourceDocumentId = (int) ($invoice->document_id ?? 0);
            $finalReason = trim((string) ($reason ?: trans('accounting::accounting.customer_invoice.correction_default_reason')));

            $reversalDocument = $this->documentService->reverseDocument($sourceDocumentId, $finalReason);
            $replacement = $this->createReplacementInvoice($invoice, $groupId);

            $this->correctionService->record($invoice, 'reversal', [
                'correction_group_id' => $groupId,
                'source_document_id' => $sourceDocumentId,
                'target_document_id' => $reversalDocument->id,
                'source_invoice_id' => $invoice->getKey(),
                'target_invoice_id' => $invoice->getKey(),
                'reason' => $reason,
            ]);

            $this->correctionService->record($replacement, 'replacement', [
                'correction_group_id' => $groupId,
                'source_invoice_id' => $invoice->getKey(),
                'target_invoice_id' => $replacement->getKey(),
                'source_document_id' => $sourceDocumentId,
                'reason' => $reason,
            ]);

            return [
                'reversal_document' => $reversalDocument,
                'replacement_invoice' => $replacement,
            ];
        });
    }

    protected function resolvePaidAtSourceAccountId(CustomerInvoice $invoice): int
    {
        $bankId = (int) ($invoice->paid_at_source_bank_id ?? 0);
        if ($bankId > 0) {
            $bank = Bank::query()->find($bankId);
            if ($bank && (int) $bank->account_id > 0) {
                return (int) $bank->account_id;
            }
            return $this->resolveFallbackAccountId(config('accounting.accounts.bank_default'), ['1102']);
        }

        $cashBoxId = (int) ($invoice->paid_at_source_cash_box_id ?? 0);
        if ($cashBoxId > 0) {
            $cash = CashBox::query()->find($cashBoxId);
            if ($cash && (int) $cash->account_id > 0) {
                return (int) $cash->account_id;
            }
            return $this->resolveFallbackAccountId(config('accounting.accounts.cash_box_default'), ['1101']);
        }

        $walletId = (int) ($invoice->paid_at_source_wallet_id ?? 0);
        if ($walletId > 0) {
            $wallet = Wallet::query()->find($walletId);
            if ($wallet && (int) $wallet->account_id > 0) {
                return (int) $wallet->account_id;
            }
            return $this->resolveFallbackAccountId(config('accounting.accounts.bank_default'), ['1102']);
        }

        return $this->resolveFallbackAccountId(config('accounting.accounts.cash_box_default'), ['1101']);
    }

    protected function assertInvoiceCanBeReversed(CustomerInvoice $invoice): void
    {
        if ((int) ($invoice->document_id ?? 0) <= 0) {
            throw ValidationException::withMessages([
                '_invoice' => (string) trans('accounting::accounting.customer_invoice.correction_requires_posted'),
            ]);
        }

        $document = AccountingDocument::query()->find((int) $invoice->document_id);
        if ($document && (int) ($document->reversed_by_document_id ?? 0) > 0) {
            throw ValidationException::withMessages([
                '_invoice' => (string) trans('accounting::accounting.customer_invoice.correction_already_reversed'),
            ]);
        }

        $hasCompletedPayments = CustomerPayment::query()
            ->where('customer_invoice_id', $invoice->getKey())
            ->where('status', CustomerPayment::STATUS_COMPLETED)
            ->exists();
        if ($hasCompletedPayments) {
            throw ValidationException::withMessages([
                '_invoice' => (string) trans('accounting::accounting.customer_invoice.correction_blocked_by_payments'),
            ]);
        }

        $hasCreditNotes = CreditNote::query()
            ->where(function ($query) use ($invoice) {
                $query->where('customer_invoice_id', $invoice->getKey())
                    ->orWhere('applied_to_invoice_id', $invoice->getKey());
            })
            ->where('status', '!=', CreditNote::STATUS_VOID)
            ->exists();
        if ($hasCreditNotes) {
            throw ValidationException::withMessages([
                '_invoice' => (string) trans('accounting::accounting.customer_invoice.correction_blocked_by_credit_note'),
            ]);
        }

        $hasRefunds = CustomerRefund::query()
            ->where(function ($query) use ($invoice) {
                $query->whereExists(function ($sub) use ($invoice) {
                    $sub->select(DB::raw('1'))
                        ->from('credit_notes')
                        ->whereColumn('credit_notes.id', 'customer_refunds.credit_note_id')
                        ->where(function ($cc) use ($invoice) {
                            $cc->where('credit_notes.customer_invoice_id', $invoice->getKey())
                                ->orWhere('credit_notes.applied_to_invoice_id', $invoice->getKey());
                        });
                });
                if (DB::getSchemaBuilder()->hasColumn('customer_refunds', 'customer_invoice_id')) {
                    $query->orWhere('customer_invoice_id', $invoice->getKey());
                }
            })
            ->exists();
        if ($hasRefunds) {
            throw ValidationException::withMessages([
                '_invoice' => (string) trans('accounting::accounting.customer_invoice.correction_blocked_by_refund'),
            ]);
        }
    }

    protected function createReplacementInvoice(CustomerInvoice $invoice, string $groupId): CustomerInvoice
    {
        $replacement = $invoice->replicate([
            'document_id',
            'payment_status',
            'paid_amount',
            'balance_due',
        ]);
        $replacement->invoice_number = CustomerInvoice::suggestNextInvoiceNumber();
        $replacement->document_id = null;
        $replacement->original_invoice_id = $invoice->getKey();
        $replacement->correction_group_id = $groupId;
        $replacement->status = CustomerInvoice::STATUS_DRAFT;
        $replacement->payment_status = CustomerInvoice::STATUS_UNPAID;
        $replacement->paid_amount = 0;
        $replacement->upfront_payment_amount = 0;
        $replacement->balance_due = (float) ($invoice->total_amount ?? 0);
        $replacement->save();

        foreach ($invoice->items()->orderBy('id')->get() as $item) {
            $newItem = $item->replicate();
            $newItem->customer_invoice_id = $replacement->getKey();
            $newItem->save();
        }

        return $replacement->fresh();
    }

    protected function resolveFallbackAccountId(mixed $configuredId, array $fallbackCodes): int
    {
        $id = (int) ($configuredId ?? 0);
        if ($id > 0) {
            return $id;
        }
        foreach ($fallbackCodes as $code) {
            $resolved = (int) Account::query()->where('code', (string) $code)->value('id');
            if ($resolved > 0) {
                return $resolved;
            }
        }

        return 1;
    }

    /**
     * بروزرسانی مانده مشتری
     */
    protected function updateCustomerBalance(int $customerId, int $storeId): void
    {
        $totalInvoices = (float) CustomerInvoice::where('customer_id', $customerId)
            ->where('store_id', $storeId)
            ->where('status', '!=', CustomerInvoice::STATUS_CANCELLED)
            ->sum('total_amount');

        $totalPayments = (float) DB::table('customer_payments')
            ->where('customer_id', $customerId)
            ->where('store_id', $storeId)
            ->where('status', 'completed')
            ->sum('amount');

        $now = now();

        DB::table('customer_balances')->updateOrInsert(
            [
                'customer_id' => $customerId,
                'store_id' => $storeId,
            ],
            [
                'total_invoices' => round($totalInvoices, 4),
                'total_payments' => round($totalPayments, 4),
                'balance_irr' => round($totalInvoices - $totalPayments, 4),
                'last_invoice_at' => $now,
                'last_transaction_at' => $now,
                'updated_at' => $now,
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

    /**
     * دریافت تعداد فاکتورهای ماه جاری
     */
    public function getMonthlyInvoicesCount(): int
    {
        return CustomerInvoice::whereMonth('invoice_date', now()->month)
            ->whereYear('invoice_date', now()->year)
            ->count();
    }

    /**
     * دریافت درآمد ماه جاری
     */
    public function getMonthlyRevenue(): float
    {
        return CustomerInvoice::whereMonth('invoice_date', now()->month)
            ->whereYear('invoice_date', now()->year)
            ->where('status', '!=', CustomerInvoice::STATUS_CANCELLED)
            ->sum('total_amount');
    }

    /**
     * دریافت آخرین فاکتورها
     */
    public function getRecentInvoices(int $limit = 10)
    {
        return CustomerInvoice::with('customer')
            ->orderBy('invoice_date', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * دریافت درآمد 12 ماه اخیر (بر اساس سال مالی فعال)
     */
    public function getLast12MonthsRevenue(): array
    {
        // دریافت سال مالی فعال
        $fiscalYear = \RMS\Accounting\Models\FiscalYear::where('is_current', true)->first();
        
        if (!$fiscalYear) {
            return array_fill(0, 12, 0);
        }

        // استخراج سال شمسی از year_code (مثلاً 1403)
        $jalaliYear = $fiscalYear->year_code;
        
        $revenues = [];
        for ($month = 1; $month <= 12; $month++) {
            $revenue = CustomerInvoice::where('invoice_date', 'LIKE', "{$jalaliYear}-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-%")
                ->where('status', '!=', CustomerInvoice::STATUS_CANCELLED)
                ->sum('total_amount');
            $revenues[] = $revenue;
        }
        
        return $revenues;
    }
}
