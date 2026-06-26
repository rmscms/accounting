<?php

declare(strict_types=1);

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\Account;
use RMS\Accounting\Models\AccountingDocument;
use RMS\Accounting\Models\FinancialLedger;

class LedgerSnapshotService
{
    /**
     * @param  array<int,int>  $accountIds
     * @return array<int,array<string,mixed>>
     */
    public function snapshotPostedBalances(array $accountIds): array
    {
        $accountIds = array_values(array_unique(array_map(static fn ($id): int => (int) $id, $accountIds)));
        if ($accountIds === []) {
            return [];
        }

        $accounts = Account::query()
            ->whereIn('id', $accountIds)
            ->get(['id', 'code', 'name', 'account_type'])
            ->keyBy('id');

        $rows = FinancialLedger::query()
            ->from('financial_ledgers as fl')
            ->join('accounting_documents as ad', 'ad.id', '=', 'fl.accounting_document_id')
            ->selectRaw('fl.account_id, COALESCE(SUM(fl.debit_amount),0) as total_debit')
            ->selectRaw('COALESCE(SUM(fl.credit_amount),0) as total_credit')
            ->selectRaw('COALESCE(SUM(fl.amount_base),0) as net_base')
            ->whereIn('fl.account_id', $accountIds)
            ->where('ad.status', AccountingDocument::STATUS_POSTED)
            ->groupBy('fl.account_id')
            ->get()
            ->keyBy('account_id');

        $result = [];
        foreach ($accountIds as $accountId) {
            $account = $accounts->get($accountId);
            $totalDebit = (float) ($rows->get($accountId)->total_debit ?? 0);
            $totalCredit = (float) ($rows->get($accountId)->total_credit ?? 0);
            $net = (float) ($rows->get($accountId)->net_base ?? 0);
            $isDebitNormal = in_array((string) ($account?->account_type ?? ''), [Account::TYPE_ASSET, Account::TYPE_EXPENSE], true);
            $balance = $isDebitNormal ? ($totalDebit - $totalCredit) : ($totalCredit - $totalDebit);

            $result[] = [
                'account_id' => $accountId,
                'account_code' => (string) ($account?->code ?? ''),
                'account_name' => (string) ($account?->name ?? ''),
                'account_type' => (string) ($account?->account_type ?? ''),
                'total_debit' => round($totalDebit, 4),
                'total_credit' => round($totalCredit, 4),
                'net_base' => round($net, 4),
                'balance' => round($balance, 4),
            ];
        }

        return $result;
    }

    /**
     * @param  array<int,int>  $documentIds
     * @return array<int,array<string,mixed>>
     */
    public function fetchDocumentsWithLines(array $documentIds): array
    {
        $documentIds = array_values(array_unique(array_map(static fn ($id): int => (int) $id, $documentIds)));
        if ($documentIds === []) {
            return [];
        }

        return AccountingDocument::query()
            ->whereIn('id', $documentIds)
            ->with(['ledgerEntries.account'])
            ->orderBy('id')
            ->get()
            ->map(static function (AccountingDocument $doc): array {
                return [
                    'id' => (int) $doc->id,
                    'document_number' => (string) $doc->document_number,
                    'document_type' => (string) $doc->document_type,
                    'status' => (string) $doc->status,
                    'reference_type' => (string) ($doc->reference_type ?? ''),
                    'reference_id' => (int) ($doc->reference_id ?? 0),
                    'total_debit' => (float) $doc->total_debit,
                    'total_credit' => (float) $doc->total_credit,
                    'lines' => $doc->ledgerEntries->map(static function (FinancialLedger $line): array {
                        return [
                            'id' => (int) $line->id,
                            'account_id' => (int) $line->account_id,
                            'account_code' => (string) ($line->account?->code ?? ''),
                            'account_name' => (string) ($line->account?->name ?? ''),
                            'debit_amount' => (float) $line->debit_amount,
                            'credit_amount' => (float) $line->credit_amount,
                            'event_type' => (string) $line->event_type,
                            'event_source' => (string) $line->event_source,
                            'description' => (string) ($line->description ?? ''),
                        ];
                    })->values()->all(),
                ];
            })
            ->values()
            ->all();
    }
}

