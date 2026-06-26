<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\Account;
use RMS\Accounting\Models\AccountingDocument;
use RMS\Accounting\Models\Currency;
use RMS\Accounting\Models\FinancialLedger;
use RMS\Accounting\Models\FiscalYear;
use Illuminate\Support\Facades\DB;
use RMS\Accounting\Support\InteractsWithAuditActor;

class LedgerService
{
    use InteractsWithAuditActor;

    protected function resolveLedgerBaseCurrency(): string
    {
        return Currency::resolveBaseCurrencyCode('IRR');
    }

    /**
     * نرخ به ارز پایه و خالص به ارز پایه (مبلغ‌های debit/credit به ارز تراکنش هستند).
     *
     * @return array{0: float, 1: float} [fx_rate_to_base, amount_base]
     */
    protected function resolveFxRateAndAmountBase(array $data, float $debit, float $credit): array
    {
        $currency = strtoupper((string) ($data['currency_code'] ?? $this->resolveLedgerBaseCurrency()));
        $base = $this->resolveLedgerBaseCurrency();

        if ($currency === $base) {
            return [1.0, round($debit - $credit, 4)];
        }

        $explicitFx = $data['fx_rate_to_base'] ?? $data['fx_rate_to_irr'] ?? $data['fx_rate'] ?? null;
        $fx = (float) ($explicitFx ?? 1);
        if ($fx <= 0) {
            $fx = 1.0;
        }

        return [$fx, round(($debit - $credit) * $fx, 4)];
    }

    /**
     * Record a double-entry transaction
     * 
     * @param array $documentData
     * @param array $entries [['account_id' => 1, 'debit' => 100, 'credit' => 0], ...]
     * @return AccountingDocument
     */
    public function recordTransaction(array $documentData, array $entries): AccountingDocument
    {
        return DB::transaction(function () use ($documentData, $entries) {
            // 1. Validate Double Entry
            $this->validateDoubleEntry($entries);

            // 2. Create Document
            $document = $this->createDocument($documentData, $entries);

            // 3. Create Ledger Entries
            foreach ($entries as $entry) {
                $this->createLedgerEntry($document, $entry);
            }

            // 4. Post Document (via centralized service path)
            app(DocumentService::class)->postDocument((int) $document->id);

            return $document->fresh('ledgerEntries');
        });
    }

    /**
     * Create accounting document
     */
    protected function createDocument(array $data, array $entries): AccountingDocument
    {
        $totalDebit = array_reduce($entries, static fn (float $sum, array $entry) => $sum + (float) ($entry['debit'] ?? $entry['debit_amount'] ?? 0), 0.0);
        $totalCredit = array_reduce($entries, static fn (float $sum, array $entry) => $sum + (float) ($entry['credit'] ?? $entry['credit_amount'] ?? 0), 0.0);

        $documentPayload = [
            'document_number' => $data['document_number'] ?? AccountingDocument::generateDocumentNumber(),
            'document_type' => $data['document_type'],
            'store_id' => $data['store_id'] ?? null,
            'fiscal_year_id' => $data['fiscal_year_id'] ?? $this->getCurrentFiscalYearId(),
            'reference_type' => $data['reference_type'] ?? AccountingDocument::REF_MANUAL,
            'reference_id' => $data['reference_id'] ?? null,
            'description' => $data['description'],
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'status' => AccountingDocument::STATUS_DRAFT,
        ];
        $documentPayload = $this->stampAudit($documentPayload, 'accounting_documents', 'created');

        return AccountingDocument::create($documentPayload);
    }

    /**
     * Create ledger entry
     */
    protected function createLedgerEntry(AccountingDocument $document, array $entry): FinancialLedger
    {
        $account = Account::findOrFail($entry['account_id']);
        $currencyCode = strtoupper((string) ($entry['currency_code']
            ?? Currency::resolveBaseCurrencyCode('IRR')));
        $debit = (float) ($entry['debit'] ?? $entry['debit_amount'] ?? 0);
        $credit = (float) ($entry['credit'] ?? $entry['credit_amount'] ?? 0);

        $eventType = $this->normalizeFinancialLedgerEventType($entry['event_type'] ?? $document->document_type);
        $eventSource = $this->normalizeFinancialLedgerEventSource($entry['event_source'] ?? null);

        [$fx, $amountBase] = $this->resolveFxRateAndAmountBase(array_merge($entry, ['currency_code' => $currencyCode]), $debit, $credit);

        return FinancialLedger::create([
            'event_type' => $eventType,
            'event_source' => $eventSource,
            'source_reference_type' => $entry['source_reference_type'] ?? null,
            'source_reference_id' => $entry['source_reference_id'] ?? null,
            'store_id' => $document->store_id ?? 0,
            'account_id' => $account->id,
            'currency_code' => $currencyCode,
            'debit_amount' => $debit,
            'credit_amount' => $credit,
            'fx_rate_to_base' => $fx,
            'amount_base' => $amountBase,
            'accounting_document_id' => $document->id,
            'description' => $entry['description'] ?? $document->description,
        ]);
    }

    /**
     * Validate double entry (debit must equal credit)
     */
    protected function validateDoubleEntry(array $entries): void
    {
        $totalDebit = array_reduce($entries, static fn (float $sum, array $entry) => $sum + (float) ($entry['debit'] ?? $entry['debit_amount'] ?? 0), 0.0);
        $totalCredit = array_reduce($entries, static fn (float $sum, array $entry) => $sum + (float) ($entry['credit'] ?? $entry['credit_amount'] ?? 0), 0.0);

        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new \Exception("Double Entry Validation Failed: Debit ({$totalDebit}) must equal Credit ({$totalCredit})");
        }

        if (count($entries) < 2) {
            throw new \Exception('Double Entry requires at least 2 ledger entries');
        }
    }

    /**
     * نگاشت event_type قدیمی / حروف کوچک به مقادیر مجاز enum جدول financial_ledgers (SQLite).
     */
    protected function normalizeFinancialLedgerEventType(?string $raw): string
    {
        if ($raw === null || $raw === '') {
            return FinancialLedger::EVENT_ADJUSTMENT;
        }

        $lower = strtolower(trim($raw));
        $fromLower = [
            'purchase' => FinancialLedger::EVENT_PURCHASE,
            'sale' => FinancialLedger::EVENT_SALE,
            'payment' => FinancialLedger::EVENT_PAYMENT,
            'receipt' => FinancialLedger::EVENT_RECEIPT,
            'payment_received' => FinancialLedger::EVENT_RECEIPT,
            'fx_difference' => FinancialLedger::EVENT_FX_DIFF,
            'fx_diff' => FinancialLedger::EVENT_FX_DIFF,
            'tax' => FinancialLedger::EVENT_TAX,
            'cogs' => FinancialLedger::EVENT_COST,
            'cost' => FinancialLedger::EVENT_COST,
            'expense' => FinancialLedger::EVENT_EXPENSE,
            'document_reversal' => FinancialLedger::EVENT_REVERSAL,
            'reversal' => FinancialLedger::EVENT_REVERSAL,
            'manual' => FinancialLedger::EVENT_ADJUSTMENT,
            'adjustment' => FinancialLedger::EVENT_ADJUSTMENT,
        ];
        if (isset($fromLower[$lower])) {
            return $fromLower[$lower];
        }

        $upper = strtoupper(trim($raw));
        $allowed = [
            FinancialLedger::EVENT_SALE,
            FinancialLedger::EVENT_PURCHASE,
            FinancialLedger::EVENT_PAYMENT,
            FinancialLedger::EVENT_RECEIPT,
            FinancialLedger::EVENT_FX_DIFF,
            FinancialLedger::EVENT_TAX,
            FinancialLedger::EVENT_COST,
            FinancialLedger::EVENT_ADJUSTMENT,
            FinancialLedger::EVENT_REVERSAL,
            FinancialLedger::EVENT_EXPENSE,
        ];
        if (in_array($upper, $allowed, true)) {
            return $upper;
        }

        return FinancialLedger::EVENT_ADJUSTMENT;
    }

    /**
     * نگاشت event_source قدیمی به مقادیر مجاز enum (sales|inventory|system|manual).
     */
    protected function normalizeFinancialLedgerEventSource(?string $raw): string
    {
        if ($raw === null || $raw === '') {
            return FinancialLedger::SOURCE_MANUAL;
        }

        $lower = strtolower(trim($raw));
        $map = [
            'supplier' => FinancialLedger::SOURCE_INVENTORY,
            'customer' => FinancialLedger::SOURCE_SALES,
            'shop' => FinancialLedger::SOURCE_SALES,
            'sales' => FinancialLedger::SOURCE_SALES,
            'inventory' => FinancialLedger::SOURCE_INVENTORY,
            'system' => FinancialLedger::SOURCE_SYSTEM,
            'manual' => FinancialLedger::SOURCE_MANUAL,
        ];

        return $map[$lower] ?? FinancialLedger::SOURCE_MANUAL;
    }

    /**
     * ثبت یک ردیف دفتر کل برای سندی که از قبل ایجاد شده (الگوی legacy: فاکتور خرید/فروش، پرداخت‌ها، …).
     *
     * @param  array<string, mixed>  $data  account_id یا account_code، debit_amount/debit، credit_amount/credit، accounting_document_id/document_id
     */
    public function recordEntry(array $data): FinancialLedger
    {
        $accountId = $data['account_id'] ?? null;
        if ($accountId === null && ! empty($data['account_code'])) {
            $account = Account::query()->where('code', $data['account_code'])->first();
            if (! $account) {
                throw new \InvalidArgumentException('حساب با کد «'.$data['account_code'].'» یافت نشد.');
            }
            $accountId = $account->id;
        }
        if ($accountId === null) {
            throw new \InvalidArgumentException('recordEntry نیاز به account_id یا account_code دارد.');
        }

        $debit = (float) ($data['debit_amount'] ?? $data['debit'] ?? 0);
        $credit = (float) ($data['credit_amount'] ?? $data['credit'] ?? 0);

        $currencyCode = strtoupper((string) ($data['currency_code'] ?? Currency::resolveBaseCurrencyCode('IRR')));
        [$fx, $amountBase] = $this->resolveFxRateAndAmountBase(array_merge($data, ['currency_code' => $currencyCode]), $debit, $credit);

        $docId = $data['accounting_document_id'] ?? $data['document_id'] ?? null;

        $sourceType = $data['source_reference_type'] ?? $data['reference_type'] ?? null;
        $sourceId = $data['source_reference_id'] ?? $data['reference_id'] ?? null;

        $eventType = $this->normalizeFinancialLedgerEventType($data['event_type'] ?? null);
        $eventSource = $this->normalizeFinancialLedgerEventSource($data['event_source'] ?? null);

        return FinancialLedger::create([
            'event_type' => $eventType,
            'event_source' => $eventSource,
            'source_reference_type' => $sourceType,
            'source_reference_id' => $sourceId !== null ? (int) $sourceId : null,
            'store_id' => (int) ($data['store_id'] ?? 0),
            'account_id' => (int) $accountId,
            'currency_code' => $currencyCode,
            'debit_amount' => $debit,
            'credit_amount' => $credit,
            'fx_rate_to_base' => $fx,
            'amount_base' => $amountBase,
            'accounting_document_id' => $docId !== null ? (int) $docId : null,
            'description' => (string) ($data['description'] ?? ''),
        ]);
    }

    /**
     * Reverse/Correct a document
     */
    public function reverseDocument(int $documentId, string $reason): AccountingDocument
    {
        return DB::transaction(function () use ($documentId, $reason) {
            $originalDocument = AccountingDocument::with('ledgerEntries')->findOrFail($documentId);

            if ($originalDocument->isReversed()) {
                throw new \Exception('Document is already reversed');
            }

            // Create reversal entries (swap debit & credit)
            $reversalEntries = [];
            foreach ($originalDocument->ledgerEntries as $entry) {
                $reversalEntries[] = [
                    'account_id' => $entry->account_id,
                    'debit' => $entry->credit_amount,  // Swap
                    'credit' => $entry->debit_amount,  // Swap
                    'currency_code' => $entry->currency_code,
                    'fx_rate' => $entry->fx_rate_to_base,
                    'event_type' => FinancialLedger::EVENT_REVERSAL,
                    'source_reference_type' => 'document',
                    'source_reference_id' => $originalDocument->id,
                ];
            }

            // Create reversal document
            $reversalDocument = $this->recordTransaction([
                'document_type' => AccountingDocument::TYPE_CORRECTION,
                'store_id' => $originalDocument->store_id,
                'reference_type' => AccountingDocument::REF_SYSTEM,
                'reference_id' => $originalDocument->id,
                'description' => "Reversal of Document #{$originalDocument->document_number}: {$reason}",
            ], $reversalEntries);

            // Mark original as reversed
            $originalDocument->update([
                'status' => AccountingDocument::STATUS_REVERSED,
                'reversed_by_document_id' => $reversalDocument->id,
            ]);

            return $reversalDocument;
        });
    }

    /**
     * Get account balance (alias for getAccountBalance)
     */
    public function getBalance(int $accountId, ?string $startDate = null, ?string $endDate = null)
    {
        $result = $this->getAccountBalance($accountId, $startDate, $endDate);
        return $result['balance'] ?? 0;
    }

    /**
     * Get accounts receivable balance
     */
    public function getAccountsReceivable(): float
    {
        $account = Account::where('code', '1-2-1')->first(); // حساب مطالبات
        if (!$account) {
            return 0;
        }
        return $this->getBalance($account->id);
    }

    /**
     * Get accounts payable balance
     */
    public function getAccountsPayable(): float
    {
        $account = Account::where('code', '2-1-1')->first(); // حساب بدهی‌ها
        if (!$account) {
            return 0;
        }
        return abs($this->getBalance($account->id));
    }

    /**
     * Get account balance
     */
    public function getAccountBalance(int $accountId, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = FinancialLedger::forAccount($accountId);

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $debit = $query->sum('debit_amount');
        $credit = $query->sum('credit_amount');

        $account = Account::findOrFail($accountId);
        $balance = $account->isDebitNormal() ? ($debit - $credit) : ($credit - $debit);

        return [
            'account_id' => $accountId,
            'account_code' => $account->code,
            'account_name' => $account->name,
            'debit' => $debit,
            'credit' => $credit,
            'balance' => $balance,
        ];
    }

    /**
     * Get current fiscal year ID
     */
    protected function getCurrentFiscalYearId(): ?int
    {
        return FiscalYear::current()->value('id');
    }

    /**
     * Get ledger entries for an account
     */
    public function getLedgerForAccount(int $accountId, ?string $startDate = null, ?string $endDate = null)
    {
        $query = FinancialLedger::with(['document', 'account'])
            ->forAccount($accountId)
            ->orderBy('created_at', 'asc');

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        return $query->get();
    }

    /**
     * Calculate running balance for ledger entries
     */
    public function calculateRunningBalance(array $entries, Account $account): array
    {
        $runningBalance = 0;
        $isDebitNormal = $account->isDebitNormal();

        foreach ($entries as &$entry) {
            if ($isDebitNormal) {
                $runningBalance += ($entry->debit_amount - $entry->credit_amount);
            } else {
                $runningBalance += ($entry->credit_amount - $entry->debit_amount);
            }

            $entry->running_balance = $runningBalance;
        }

        return $entries;
    }
    
    /**
     * ثبت مالیات در دفتر کل
     * متد کمکی برای TaxService
     * 
     * @param array $entry ['account_id', 'debit_amount'|'credit_amount', 'description', 'document_id']
     * @return FinancialLedger|null
     */
    public function recordTaxEntry(array $entry): ?FinancialLedger
    {
        if (!isset($entry['account_id'])) {
            return null;
        }
        
        $debit = (float) ($entry['debit_amount'] ?? 0);
        $credit = (float) ($entry['credit_amount'] ?? 0);
        $ccy = strtoupper((string) ($entry['currency_code'] ?? 'IRR'));
        [$fx, $amountBase] = $this->resolveFxRateAndAmountBase(array_merge($entry, ['currency_code' => $ccy]), $debit, $credit);

        return FinancialLedger::create([
            'event_type' => FinancialLedger::EVENT_TAX,
            'event_source' => FinancialLedger::SOURCE_SYSTEM,
            'store_id' => $entry['store_id'] ?? 0,
            'account_id' => $entry['account_id'],
            'currency_code' => $ccy,
            'debit_amount' => $debit,
            'credit_amount' => $credit,
            'fx_rate_to_base' => $fx,
            'amount_base' => $amountBase,
            'accounting_document_id' => $entry['document_id'] ?? null,
            'description' => $entry['description'] ?? 'ثبت مالیات',
        ]);
    }

    /**
     * ثبت قطعی سند از مسیرهای legacy (همان قوانین DocumentService از جمله سال مالی).
     */
    public function postDocument(AccountingDocument $document): bool
    {
        return app(DocumentService::class)->postDocument($document->id);
    }
}
