<?php

declare(strict_types=1);

namespace RMS\Accounting\Services;

use Illuminate\Support\Facades\DB;
use RMS\Accounting\Models\AccountingDocument;
use RMS\Accounting\Models\Bank;
use RMS\Accounting\Models\CashBox;

class TreasuryBalanceCacheSyncService
{
    public function syncForDocument(int $documentId): void
    {
        if ($documentId < 1) {
            return;
        }

        $accountIds = DB::table('financial_ledgers')
            ->where('accounting_document_id', $documentId)
            ->distinct()
            ->pluck('account_id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        $this->syncForAccountIds($accountIds);
    }

    public function syncAllTreasuryCaches(): void
    {
        $accountIds = array_values(array_unique(array_merge(
            Bank::query()
                ->where('active', true)
                ->whereNotNull('account_id')
                ->pluck('account_id')
                ->map(static fn ($id): int => (int) $id)
                ->all(),
            CashBox::query()
                ->where('active', true)
                ->whereNotNull('account_id')
                ->pluck('account_id')
                ->map(static fn ($id): int => (int) $id)
                ->all()
        )));

        $this->syncForAccountIds($accountIds);
    }

    /**
     * @param array<int,int> $accountIds
     */
    public function syncForAccountIds(array $accountIds): void
    {
        $accountIds = array_values(array_unique(array_filter(
            array_map(static fn ($id): int => (int) $id, $accountIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($accountIds === []) {
            return;
        }

        $ledgerTotals = DB::table('financial_ledgers as fl')
            ->join('accounting_documents as ad', 'ad.id', '=', 'fl.accounting_document_id')
            ->selectRaw('fl.account_id, COALESCE(SUM(fl.amount_base),0) as net_total')
            ->whereIn('fl.account_id', $accountIds)
            ->where('ad.status', AccountingDocument::STATUS_POSTED)
            ->groupBy('fl.account_id')
            ->get()
            ->keyBy('account_id');

        $banks = Bank::query()
            ->whereIn('account_id', $accountIds)
            ->get(['id', 'account_id', 'balance']);

        foreach ($banks as $bank) {
            $accountId = (int) $bank->account_id;
            $newBalance = (float) ($ledgerTotals->get($accountId)->net_total ?? 0.0);
            $bank->update(['balance' => $newBalance]);
        }

        $cashBoxes = CashBox::query()
            ->whereIn('account_id', $accountIds)
            ->get(['id', 'account_id', 'balance']);

        foreach ($cashBoxes as $cashBox) {
            $accountId = (int) $cashBox->account_id;
            $newBalance = (float) ($ledgerTotals->get($accountId)->net_total ?? 0.0);
            $cashBox->update(['balance' => $newBalance]);
        }
    }
}
