<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RMS\Accounting\Models\Account;
use RMS\Accounting\Models\Bank;
use RMS\Accounting\Models\CashBox;
use RMS\Accounting\Models\FinancialLedger;
use RMS\Accounting\Models\Wallet;

class TreasuryBalanceSyncController extends AccountingAdminController
{
    public function index(Request $request)
    {
        $rows = $this->buildRows();
        $summary = [
            'total' => count($rows),
            'synced' => count(array_filter($rows, static fn (array $row): bool => $row['status'] === 'synced')),
            'out_of_sync' => count(array_filter($rows, static fn (array $row): bool => $row['status'] === 'out_of_sync')),
            'missing_account' => count(array_filter($rows, static fn (array $row): bool => $row['status'] === 'missing_account')),
        ];

        $this->title(trans('accounting::accounting.treasury_sync.page_title'));
        $this->use_package_namespace = true;
        $this->view->usePackageNamespace('accounting');
        $this->view
            ->setTpl('treasury_sync.index')
            ->withVariables([
                'rows' => $rows,
                'summary' => $summary,
            ]);

        return $this->view();
    }

    public function syncOne(Request $request, string $type, int $id): RedirectResponse
    {
        $resolved = $this->resolveEndpoint($type, $id);
        if ($resolved === null) {
            return redirect()
                ->route('admin.accounting.treasury-sync.index')
                ->with('error', trans('accounting::accounting.treasury_sync.messages.endpoint_not_found'));
        }

        [$model, $label] = $resolved;
        $accountId = (int) ($model->account_id ?? 0);
        if ($accountId <= 0) {
            return redirect()
                ->route('admin.accounting.treasury-sync.index')
                ->with('error', trans('accounting::accounting.treasury_sync.messages.endpoint_missing_account', ['name' => $label]));
        }

        $recordedBefore = (float) ($model->balance ?? 0);
        $ledgerBalance = $this->ledgerBalanceForAccount($accountId);
        $model->update(['balance' => $ledgerBalance]);

        return redirect()
            ->route('admin.accounting.treasury-sync.index')
            ->with('success', trans('accounting::accounting.treasury_sync.messages.synced_success', [
                'name' => $label,
                'old' => number_format($recordedBefore, 2, '.', ','),
                'new' => number_format($ledgerBalance, 2, '.', ','),
            ]));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildRows(): array
    {
        $banks = Bank::query()->where('active', true)->get(['id', 'name', 'balance', 'account_id']);
        $cashBoxes = CashBox::query()->where('active', true)->get(['id', 'name', 'balance', 'account_id']);
        $wallets = Wallet::query()->where('active', true)->get(['id', 'wallet_type', 'balance', 'account_id']);

        $accountIds = [];
        foreach ($banks as $item) {
            if ($item->account_id) {
                $accountIds[] = (int) $item->account_id;
            }
        }
        foreach ($cashBoxes as $item) {
            if ($item->account_id) {
                $accountIds[] = (int) $item->account_id;
            }
        }
        foreach ($wallets as $item) {
            if ($item->account_id) {
                $accountIds[] = (int) $item->account_id;
            }
        }
        $accountIds = array_values(array_unique($accountIds));

        $accounts = Account::query()
            ->whereIn('id', $accountIds)
            ->get(['id', 'code', 'name'])
            ->keyBy('id');

        $ledgerTotals = FinancialLedger::query()
            ->from('financial_ledgers as fl')
            ->join('accounting_documents as ad', 'ad.id', '=', 'fl.accounting_document_id')
            ->selectRaw('fl.account_id, COALESCE(SUM(fl.amount_base),0) as net_total')
            ->whereIn('account_id', $accountIds)
            ->where('ad.status', \RMS\Accounting\Models\AccountingDocument::STATUS_POSTED)
            ->groupBy('fl.account_id')
            ->get()
            ->keyBy('account_id');

        $rows = [];
        foreach ($banks as $bank) {
            $rows[] = $this->makeRow('bank', (int) $bank->id, (string) $bank->name, (float) $bank->balance, (int) ($bank->account_id ?? 0), $accounts, $ledgerTotals);
        }
        foreach ($cashBoxes as $cashBox) {
            $rows[] = $this->makeRow('cashbox', (int) $cashBox->id, (string) $cashBox->name, (float) $cashBox->balance, (int) ($cashBox->account_id ?? 0), $accounts, $ledgerTotals);
        }
        foreach ($wallets as $wallet) {
            $rows[] = $this->makeRow('wallet', (int) $wallet->id, 'Wallet #' . $wallet->id . ' (' . $wallet->wallet_type . ')', (float) $wallet->balance, (int) ($wallet->account_id ?? 0), $accounts, $ledgerTotals);
        }

        usort($rows, static function (array $a, array $b): int {
            if ($a['status'] !== $b['status']) {
                $weight = ['out_of_sync' => 1, 'missing_account' => 2, 'synced' => 3];
                return ($weight[$a['status']] ?? 99) <=> ($weight[$b['status']] ?? 99);
            }

            return strcmp((string) $a['label'], (string) $b['label']);
        });

        return $rows;
    }

    /**
     * @param  array<int, Account>  $accounts
     * @param  \Illuminate\Support\Collection<int, \stdClass>  $ledgerTotals
     * @return array<string, mixed>
     */
    protected function makeRow(string $type, int $id, string $label, float $recorded, int $accountId, $accounts, $ledgerTotals): array
    {
        $account = $accountId > 0 ? $accounts->get($accountId) : null;
        $ledger = null;
        $difference = null;
        $status = 'missing_account';

        if ($accountId > 0) {
            $totals = $ledgerTotals->get($accountId);
            $ledger = (float) ($totals->net_total ?? 0);
            $difference = $ledger - $recorded;
            $status = abs($difference) < 0.0001 ? 'synced' : 'out_of_sync';
        }

        return [
            'type' => $type,
            'id' => $id,
            'label' => $label,
            'type_label' => trans('accounting::accounting.treasury_sync.types.' . $type),
            'account_id' => $accountId,
            'account_code' => $account?->code,
            'account_name' => $account?->name,
            'recorded_balance' => $recorded,
            'ledger_balance' => $ledger,
            'difference' => $difference,
            'status' => $status,
            'can_sync' => $accountId > 0,
        ];
    }

    protected function ledgerBalanceForAccount(int $accountId): float
    {
        return (float) FinancialLedger::query()
            ->from('financial_ledgers as fl')
            ->join('accounting_documents as ad', 'ad.id', '=', 'fl.accounting_document_id')
            ->where('fl.account_id', $accountId)
            ->where('ad.status', \RMS\Accounting\Models\AccountingDocument::STATUS_POSTED)
            ->sum('fl.amount_base');
    }

    /**
     * @return array{0:object,1:string}|null
     */
    protected function resolveEndpoint(string $type, int $id): ?array
    {
        if ($type === 'bank') {
            $model = Bank::query()->find($id);
            return $model ? [$model, (string) $model->name] : null;
        }
        if ($type === 'cashbox') {
            $model = CashBox::query()->find($id);
            return $model ? [$model, (string) $model->name] : null;
        }
        if ($type === 'wallet') {
            $model = Wallet::query()->find($id);
            return $model ? [$model, 'Wallet #' . $model->id . ' (' . $model->wallet_type . ')'] : null;
        }

        return null;
    }

    public function table(): string
    {
        return '';
    }

    public function modelName(): string
    {
        return '';
    }
}

