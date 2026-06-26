<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\Party;
use RMS\Accounting\Models\FinancialLedger;
use RMS\Accounting\Models\Account;
use Carbon\Carbon;

/**
 * سرویس محاسبه مانده و گردش حساب Parties
 */
class PartyBalanceService
{
    protected LedgerService $ledgerService;
    protected AccountService $accountService;
    protected PartyService $partyService;

    public function __construct(
        LedgerService $ledgerService,
        AccountService $accountService,
        PartyService $partyService
    ) {
        $this->ledgerService = $ledgerService;
        $this->accountService = $accountService;
        $this->partyService = $partyService;
    }

    /**
     * محاسبه مانده کلی party
     */
    public function getPartyTotalBalance(int $partyId): float
    {
        $balance = $this->partyService->getPartyBalance($partyId);
        return $balance['net_balance'];
    }

    /**
     * محاسبه مانده دریافتنی (از customer)
     */
    public function getPartyReceivable(int $partyId): float
    {
        $balance = $this->partyService->getPartyBalance($partyId);
        return $balance['customer_balance'];
    }

    /**
     * محاسبه مانده پرداختنی (به supplier)
     */
    public function getPartyPayable(int $partyId): float
    {
        $balance = $this->partyService->getPartyBalance($partyId);
        return $balance['supplier_balance'];
    }

    /**
     * محاسبه کل درآمد از customer revenue account
     */
    public function getPartyRevenue(int $partyId, array $filters = []): float
    {
        $party = $this->partyService->getPartyWithRoles($partyId);
        
        if (!$party->customer) {
            return 0;
        }

        // دریافت حساب فرعی درآمد
        $revenueAccount = $this->partyService->getOrCreateCustomerRevenueAccount($partyId);
        
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;

        $balance = $this->accountService->getAccountBalance(
            accountId: $revenueAccount->id,
            fromDate: $startDate,
            toDate: $endDate
        );

        // برای حساب درآمد، مانده = مجموع بستانکار
        return abs($balance['credit'] ?? 0);
    }

    /**
     * محاسبه کل هزینه از supplier cost account
     */
    public function getPartyCost(int $partyId, array $filters = []): float
    {
        $party = $this->partyService->getPartyWithRoles($partyId);
        
        if (!$party->supplier) {
            return 0;
        }

        // دریافت حساب فرعی هزینه
        $costAccount = $this->partyService->getOrCreateSupplierCostAccount($partyId);
        
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;

        $balance = $this->accountService->getAccountBalance(
            accountId: $costAccount->id,
            fromDate: $startDate,
            toDate: $endDate
        );

        // برای حساب هزینه، مانده = مجموع بدهکار
        return abs($balance['debit'] ?? 0);
    }

    /**
     * محاسبه سود (درآمد - هزینه)
     */
    public function getPartyProfit(int $partyId, array $filters = []): float
    {
        $revenue = $this->getPartyRevenue($partyId, $filters);
        $cost = $this->getPartyCost($partyId, $filters);
        
        return $revenue - $cost;
    }

    /**
     * گردش حساب کلی party
     */
    public function getPartyStatement(int $partyId, array $filters = []): array
    {
        $party = $this->partyService->getPartyWithRoles($partyId);
        
        $startDate = $filters['start_date'] ?? Carbon::now()->startOfYear()->toDateString();
        $endDate = $filters['end_date'] ?? Carbon::now()->toDateString();

        $entries = [];
        $accountIds = [];

        // دریافت حساب customer
        if ($party->customer && $party->customer->account_id) {
            $accountIds[] = $party->customer->account_id;
        }

        // دریافت حساب supplier
        if ($party->supplier && $party->supplier->account_id) {
            $accountIds[] = $party->supplier->account_id;
        }

        if (empty($accountIds)) {
            return [
                'party' => $party,
                'opening_balance' => 0,
                'entries' => [],
                'closing_balance' => 0,
                'summary' => [
                    'total_debit' => 0,
                    'total_credit' => 0,
                    'customer_balance' => 0,
                    'supplier_balance' => 0,
                    'net_balance' => 0,
                ],
            ];
        }

        // دریافت ledger entries برای همه حساب‌ها
        foreach ($accountIds as $accountId) {
            $account = Account::find($accountId);
            if (!$account) {
                continue;
            }

            $ledgerEntries = $this->ledgerService->getLedgerForAccount($accountId, $startDate, $endDate);
            
            foreach ($ledgerEntries as $entry) {
                $entries[] = [
                    'date' => $entry->created_at->format('Y-m-d'),
                    'description' => $entry->description,
                    'debit' => $entry->debit_amount,
                    'credit' => $entry->credit_amount,
                    'account_id' => $accountId,
                    'account_name' => $account->name,
                    'account_type' => $party->customer && $party->customer->account_id == $accountId ? 'customer' : 'supplier',
                    'entry' => $entry,
                ];
            }
        }

        // مرتب‌سازی بر اساس تاریخ
        usort($entries, function ($a, $b) {
            return strcmp($a['date'], $b['date']);
        });

        // محاسبه opening balance (قبل از start_date)
        $openingBalance = 0;
        foreach ($accountIds as $accountId) {
            $account = Account::find($accountId);
            if (!$account) {
                continue;
            }

            $balance = $this->accountService->getAccountBalance(
                accountId: $accountId,
                fromDate: null,
                toDate: $startDate
            );
            $accountBalance = $account->isDebitNormal() 
                ? ($balance['debit'] - $balance['credit'])
                : ($balance['credit'] - $balance['debit']);
            
            if ($party->customer && $party->customer->account_id == $accountId) {
                $openingBalance += $accountBalance;
            } else {
                $openingBalance -= $accountBalance;
            }
        }

        // محاسبه running balance
        $runningBalance = $openingBalance;
        foreach ($entries as &$entry) {
            $account = Account::find($entry['account_id']);
            $isDebitNormal = $account->isDebitNormal();
            
            if ($entry['account_type'] === 'customer') {
                $runningBalance += ($entry['debit'] - $entry['credit']);
            } else {
                $runningBalance -= ($entry['debit'] - $entry['credit']);
            }
            
            $entry['running_balance'] = $runningBalance;
        }

        // محاسبه closing balance
        $closingBalance = $runningBalance;

        // محاسبه summary
        $totalDebit = array_sum(array_column($entries, 'debit'));
        $totalCredit = array_sum(array_column($entries, 'credit'));
        
        $customerBalance = 0;
        $supplierBalance = 0;
        
        if ($party->customer && $party->customer->account_id) {
            $customerBalance = $this->getPartyReceivable($partyId);
        }
        
        if ($party->supplier && $party->supplier->account_id) {
            $supplierBalance = $this->getPartyPayable($partyId);
        }

        return [
            'party' => $party,
            'opening_balance' => $openingBalance,
            'entries' => $entries,
            'closing_balance' => $closingBalance,
            'summary' => [
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'customer_balance' => $customerBalance,
                'supplier_balance' => $supplierBalance,
                'net_balance' => $customerBalance - $supplierBalance,
            ],
        ];
    }
}
