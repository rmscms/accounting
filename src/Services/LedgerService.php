<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\Account;
use RMS\Accounting\Models\AccountingDocument;
use RMS\Accounting\Models\FinancialLedger;
use RMS\Accounting\Models\FiscalYear;
use Illuminate\Support\Facades\DB;

class LedgerService
{
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

            // 4. Post Document
            $document->post();

            return $document->fresh('ledgerEntries');
        });
    }

    /**
     * Create accounting document
     */
    protected function createDocument(array $data, array $entries): AccountingDocument
    {
        $totalDebit = array_sum(array_column($entries, 'debit'));
        $totalCredit = array_sum(array_column($entries, 'credit'));

        return AccountingDocument::create([
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
            'created_by_user_id' => $data['created_by_user_id'] ?? auth()->id(),
        ]);
    }

    /**
     * Create ledger entry
     */
    protected function createLedgerEntry(AccountingDocument $document, array $entry): FinancialLedger
    {
        $account = Account::findOrFail($entry['account_id']);
        $currencyCode = $entry['currency_code'] ?? config('accounting.default_currency', 'IRR');
        $fxRate = $entry['fx_rate'] ?? 1;

        // محاسبه مبلغ ریالی
        $debitIRR = ($entry['debit'] ?? 0) * $fxRate;
        $creditIRR = ($entry['credit'] ?? 0) * $fxRate;
        $amountIRR = $debitIRR - $creditIRR;

        return FinancialLedger::create([
            'event_type' => $entry['event_type'] ?? $document->document_type,
            'event_source' => $entry['event_source'] ?? FinancialLedger::SOURCE_MANUAL,
            'source_reference_type' => $entry['source_reference_type'] ?? null,
            'source_reference_id' => $entry['source_reference_id'] ?? null,
            'store_id' => $document->store_id ?? 0,
            'account_id' => $account->id,
            'currency_code' => $currencyCode,
            'debit_amount' => $entry['debit'] ?? 0,
            'credit_amount' => $entry['credit'] ?? 0,
            'fx_rate_to_irr' => $fxRate,
            'amount_irr' => $amountIRR,
            'accounting_document_id' => $document->id,
            'description' => $entry['description'] ?? $document->description,
        ]);
    }

    /**
     * Validate double entry (debit must equal credit)
     */
    protected function validateDoubleEntry(array $entries): void
    {
        $totalDebit = array_sum(array_column($entries, 'debit'));
        $totalCredit = array_sum(array_column($entries, 'credit'));

        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new \Exception("Double Entry Validation Failed: Debit ({$totalDebit}) must equal Credit ({$totalCredit})");
        }

        if (count($entries) < 2) {
            throw new \Exception('Double Entry requires at least 2 ledger entries');
        }
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
                    'fx_rate' => $entry->fx_rate_to_irr,
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
}
