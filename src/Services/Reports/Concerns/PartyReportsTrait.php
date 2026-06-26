<?php

namespace RMS\Accounting\Services\Reports\Concerns;

use RMS\Accounting\Services\PartyBalanceService;
use RMS\Accounting\Models\{
    CostEntry,
    Customer,
    CustomerInvoice,
    Party,
    Supplier,
};

/**
 * گزارش‌های مبتنی بر Party (طرف تجاری).
 */
trait PartyReportsTrait
{
    /**
     * گزارش مانده همه parties
     */
    public function getPartyBalances(array $filters = []): array
    {
        $parties = Party::with(['customer', 'supplier'])->get();
        $partyBalanceService = app(PartyBalanceService::class);

        $data = [];
        $totalCustomerBalance = 0;
        $totalSupplierBalance = 0;

        foreach ($parties as $party) {
            $balance = $partyBalanceService->getPartyTotalBalance($party->id);
            $customerBalance = $partyBalanceService->getPartyReceivable($party->id);
            $supplierBalance = $partyBalanceService->getPartyPayable($party->id);

            $totalCustomerBalance += $customerBalance;
            $totalSupplierBalance += $supplierBalance;

            if ($customerBalance != 0 || $supplierBalance != 0) {
                $data[] = [
                    'party_id' => $party->id,
                    'party_name' => $party->name,
                    'is_customer' => $party->isCustomer(),
                    'is_supplier' => $party->isSupplier(),
                    'is_both' => $party->isBoth(),
                    'customer_balance' => $customerBalance,
                    'supplier_balance' => $supplierBalance,
                    'net_balance' => $balance,
                ];
            }
        }

        return [
            'title' => 'مانده حساب طرف‌های تجاری',
            'parties' => $data,
            'summary' => [
                'total_customer_balance' => $totalCustomerBalance,
                'total_supplier_balance' => $totalSupplierBalance,
                'total_net_balance' => $totalCustomerBalance - $totalSupplierBalance,
            ],
        ];
    }

    /**
     * گردش حساب کلی یک party
     */
    public function getPartyStatement(int $partyId, array $filters = []): array
    {
        $partyBalanceService = app(PartyBalanceService::class);

        return $partyBalanceService->getPartyStatement($partyId, $filters);
    }

    /**
     * لیست parties که هم customer هستن هم supplier
     */
    public function getPartiesWithBothRoles(array $filters = []): array
    {
        $parties = Party::both()->with(['customer', 'supplier'])->get();

        $data = [];
        foreach ($parties as $party) {
            $data[] = [
                'party_id' => $party->id,
                'party_name' => $party->name,
                'customer_id' => $party->customer->id ?? null,
                'supplier_id' => $party->supplier->id ?? null,
            ];
        }

        return [
            'title' => 'طرف‌های تجاری با هر دو نقش',
            'parties' => $data,
            'count' => count($data),
        ];
    }

    /**
     * تحلیل سررسید برای یک party
     */
    public function getPartyAgingAnalysis(int $partyId, array $filters = []): array
    {
        $party = Party::with(['customer', 'supplier'])->findOrFail($partyId);

        $customerAging = [];
        $supplierAging = [];

        if ($party->customer) {
            $customerAging = $this->getAgingAnalysisAR(['customer_id' => $party->customer->id] + $filters);
        }

        if ($party->supplier) {
            $supplierAging = $this->getAgingAnalysisAP(['supplier_id' => $party->supplier->id] + $filters);
        }

        return [
            'title' => 'تحلیل سررسید - '.$party->name,
            'party' => $party,
            'customer_aging' => $customerAging,
            'supplier_aging' => $supplierAging,
        ];
    }

    /**
     * گزارش سودآوری یک party
     */
    public function getPartyProfitability(int $partyId, array $filters = []): array
    {
        $partyBalanceService = app(PartyBalanceService::class);
        $party = Party::with(['customer', 'supplier'])->findOrFail($partyId);

        $revenue = $partyBalanceService->getPartyRevenue($partyId, $filters);
        $cost = $partyBalanceService->getPartyCost($partyId, $filters);
        $profit = $partyBalanceService->getPartyProfit($partyId, $filters);

        $profitMargin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;

        return [
            'title' => 'سودآوری طرف تجاری - '.$party->name,
            'party' => $party,
            'period' => $this->getDateRange($filters),
            'revenue' => $revenue,
            'cost' => $cost,
            'profit' => $profit,
            'profit_margin' => $profitMargin,
        ];
    }

    /**
     * گزارش سودآوری همه parties
     */
    public function getAllPartiesProfitability(array $filters = []): array
    {
        $parties = Party::with(['customer', 'supplier'])->get();
        $partyBalanceService = app(PartyBalanceService::class);

        $data = [];
        foreach ($parties as $party) {
            $revenue = $partyBalanceService->getPartyRevenue($party->id, $filters);
            $cost = $partyBalanceService->getPartyCost($party->id, $filters);
            $profit = $revenue - $cost;

            if ($revenue > 0 || $cost > 0) {
                $data[] = [
                    'party_id' => $party->id,
                    'party_name' => $party->name,
                    'revenue' => $revenue,
                    'cost' => $cost,
                    'profit' => $profit,
                    'profit_margin' => $revenue > 0 ? ($profit / $revenue) * 100 : 0,
                ];
            }
        }

        usort($data, function ($a, $b) {
            return $b['profit'] <=> $a['profit'];
        });

        return [
            'title' => 'سودآوری طرف‌های تجاری',
            'period' => $this->getDateRange($filters),
            'parties' => $data,
            'summary' => [
                'total_revenue' => array_sum(array_column($data, 'revenue')),
                'total_cost' => array_sum(array_column($data, 'cost')),
                'total_profit' => array_sum(array_column($data, 'profit')),
            ],
        ];
    }

    /**
     * گزارش سودآوری یک customer از یک supplier خاص
     */
    public function getCustomerSupplierProfitability(int $customerId, int $supplierId, array $filters = []): array
    {
        $customer = Customer::findOrFail($customerId);
        $supplier = Supplier::findOrFail($supplierId);

        $dateRange = $this->getDateRange($filters);

        $customerInvoices = CustomerInvoice::where('customer_id', $customerId)
            ->whereBetween('invoice_date', [$dateRange['start'], $dateRange['end']])
            ->get();

        $revenue = $customerInvoices->sum('total_amount');

        $costEntries = CostEntry::where('source_supplier_id', $supplierId)
            ->where('reference_type', CustomerInvoice::class)
            ->whereIn('reference_id', $customerInvoices->pluck('id'))
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->get();

        $cost = $costEntries->sum('total_cost');
        $profit = $revenue - $cost;

        return [
            'title' => "سودآوری {$customer->name} از {$supplier->name}",
            'customer' => $customer,
            'supplier' => $supplier,
            'period' => $dateRange,
            'revenue' => $revenue,
            'cost' => $cost,
            'profit' => $profit,
            'profit_margin' => $revenue > 0 ? ($profit / $revenue) * 100 : 0,
            'invoices_count' => $customerInvoices->count(),
            'cost_entries_count' => $costEntries->count(),
        ];
    }
}
