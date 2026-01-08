<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\FinancialLedger;
use RMS\Accounting\Models\Account;
use RMS\Accounting\Models\CustomerInvoice;
use RMS\Accounting\Models\CustomerPayment;
use RMS\Accounting\Models\Expense;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Report Service
 * 
 * مدیریت گزارش‌های مالی
 */
class ReportService
{
    /**
     * گزارش ترازنامه (Balance Sheet)
     */
    public function balanceSheet(int $storeId, Carbon $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? Carbon::now();

        // Assets (دارایی‌ها)
        $assets = $this->getAccountBalances(['1'], $storeId, $asOfDate);
        
        // Liabilities (بدهی‌ها)
        $liabilities = $this->getAccountBalances(['2'], $storeId, $asOfDate);
        
        // Equity (حقوق صاحبان سهام)
        $equity = $this->getAccountBalances(['3'], $storeId, $asOfDate);

        $totalAssets = collect($assets)->sum('balance');
        $totalLiabilities = collect($liabilities)->sum('balance');
        $totalEquity = collect($equity)->sum('balance');

        return [
            'as_of_date' => $asOfDate->format('Y-m-d'),
            'store_id' => $storeId,
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'totals' => [
                'assets' => $totalAssets,
                'liabilities' => $totalLiabilities,
                'equity' => $totalEquity,
                'liabilities_and_equity' => $totalLiabilities + $totalEquity,
                'balanced' => abs($totalAssets - ($totalLiabilities + $totalEquity)) < 0.01,
            ],
        ];
    }

    /**
     * گزارش سود و زیان (Income Statement / Profit & Loss)
     */
    public function incomeStatement(int $storeId, Carbon $from, Carbon $to): array
    {
        // Revenue (درآمدها) - Account codes starting with 4
        $revenue = $this->getAccountBalances(['4'], $storeId, $to, $from);
        
        // Cost of Goods Sold (بهای تمام شده) - Account codes starting with 5
        $cogs = $this->getAccountBalances(['5'], $storeId, $to, $from);
        
        // Expenses (هزینه‌ها) - Account codes starting with 6
        $expenses = $this->getAccountBalances(['6'], $storeId, $to, $from);

        $totalRevenue = collect($revenue)->sum('balance');
        $totalCOGS = collect($cogs)->sum('balance');
        $totalExpenses = collect($expenses)->sum('balance');

        $grossProfit = $totalRevenue - $totalCOGS;
        $netProfit = $grossProfit - $totalExpenses;

        return [
            'period_from' => $from->format('Y-m-d'),
            'period_to' => $to->format('Y-m-d'),
            'store_id' => $storeId,
            'revenue' => $revenue,
            'cogs' => $cogs,
            'expenses' => $expenses,
            'totals' => [
                'revenue' => $totalRevenue,
                'cogs' => $totalCOGS,
                'gross_profit' => $grossProfit,
                'operating_expenses' => $totalExpenses,
                'net_profit' => $netProfit,
                'profit_margin' => $totalRevenue > 0 ? ($netProfit / $totalRevenue * 100) : 0,
            ],
        ];
    }

    /**
     * گزارش جریان وجوه نقد (Cash Flow Statement)
     */
    public function cashFlowStatement(int $storeId, Carbon $from, Carbon $to): array
    {
        // Operating Activities
        $operatingCashIn = CustomerPayment::where('store_id', $storeId)
            ->whereBetween('payment_date', [$from, $to])
            ->where('status', 'confirmed')
            ->sum('amount');

        $operatingCashOut = Expense::where('store_id', $storeId)
            ->whereBetween('expense_date', [$from, $to])
            ->where('status', 'approved')
            ->sum('amount');

        // Investing Activities (فعالیت‌های سرمایه‌گذاری)
        $investingActivities = $this->getInvestingCashFlow($storeId, $from, $to);

        // Financing Activities (فعالیت‌های تامین مالی)
        $financingActivities = $this->getFinancingCashFlow($storeId, $from, $to);

        $netOperating = $operatingCashIn - $operatingCashOut;
        $netInvesting = $investingActivities['in'] - $investingActivities['out'];
        $netFinancing = $financingActivities['in'] - $financingActivities['out'];
        $netCashFlow = $netOperating + $netInvesting + $netFinancing;

        return [
            'period_from' => $from->format('Y-m-d'),
            'period_to' => $to->format('Y-m-d'),
            'store_id' => $storeId,
            'operating_activities' => [
                'cash_in' => $operatingCashIn,
                'cash_out' => $operatingCashOut,
                'net' => $netOperating,
            ],
            'investing_activities' => [
                'cash_in' => $investingActivities['in'],
                'cash_out' => $investingActivities['out'],
                'net' => $netInvesting,
            ],
            'financing_activities' => [
                'cash_in' => $financingActivities['in'],
                'cash_out' => $financingActivities['out'],
                'net' => $netFinancing,
            ],
            'net_cash_flow' => $netCashFlow,
        ];
    }

    /**
     * گزارش حساب‌های دریافتنی (Accounts Receivable Aging)
     */
    public function accountsReceivableAging(int $storeId, Carbon $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? Carbon::now();

        $invoices = CustomerInvoice::where('store_id', $storeId)
            ->where('status', '!=', 'paid')
            ->where('invoice_date', '<=', $asOfDate)
            ->get();

        $aging = [
            'current' => 0,      // 0-30 days
            '30_60' => 0,        // 31-60 days
            '60_90' => 0,        // 61-90 days
            'over_90' => 0,      // 90+ days
        ];

        foreach ($invoices as $invoice) {
            $daysOld = Carbon::parse($invoice->invoice_date)->diffInDays($asOfDate);
            $balance = $invoice->balance ?? ($invoice->total_amount - ($invoice->paid_amount ?? 0));

            if ($daysOld <= 30) {
                $aging['current'] += $balance;
            } elseif ($daysOld <= 60) {
                $aging['30_60'] += $balance;
            } elseif ($daysOld <= 90) {
                $aging['60_90'] += $balance;
            } else {
                $aging['over_90'] += $balance;
            }
        }

        return [
            'as_of_date' => $asOfDate->format('Y-m-d'),
            'store_id' => $storeId,
            'aging' => $aging,
            'total' => array_sum($aging),
            'invoice_count' => $invoices->count(),
        ];
    }

    /**
     * گزارش عملکرد فروش
     */
    public function salesPerformance(int $storeId, Carbon $from, Carbon $to): array
    {
        $invoices = CustomerInvoice::where('store_id', $storeId)
            ->whereBetween('invoice_date', [$from, $to])
            ->get();

        $totalSales = $invoices->sum('total_amount');
        $totalPaid = $invoices->sum('paid_amount');
        $avgInvoiceValue = $invoices->count() > 0 ? $totalSales / $invoices->count() : 0;

        // Sales by day
        $salesByDay = $invoices->groupBy(function ($invoice) {
            return Carbon::parse($invoice->invoice_date)->format('Y-m-d');
        })->map(function ($dayInvoices) {
            return [
                'count' => $dayInvoices->count(),
                'total' => $dayInvoices->sum('total_amount'),
                'paid' => $dayInvoices->sum('paid_amount'),
            ];
        });

        return [
            'period_from' => $from->format('Y-m-d'),
            'period_to' => $to->format('Y-m-d'),
            'store_id' => $storeId,
            'summary' => [
                'total_invoices' => $invoices->count(),
                'total_sales' => $totalSales,
                'total_paid' => $totalPaid,
                'total_outstanding' => $totalSales - $totalPaid,
                'average_invoice_value' => $avgInvoiceValue,
                'collection_rate' => $totalSales > 0 ? ($totalPaid / $totalSales * 100) : 0,
            ],
            'daily_breakdown' => $salesByDay,
        ];
    }

    /**
     * دریافت موجودی حساب‌ها
     */
    protected function getAccountBalances(array $prefixes, int $storeId, Carbon $toDate, Carbon $fromDate = null): array
    {
        $accounts = Account::where('store_id', $storeId)
            ->where(function ($query) use ($prefixes) {
                foreach ($prefixes as $prefix) {
                    $query->orWhere('code', 'LIKE', $prefix . '%');
                }
            })
            ->get();

        $balances = [];

        foreach ($accounts as $account) {
            $balance = $this->calculateAccountBalance($account->code, $storeId, $toDate, $fromDate);
            
            if (abs($balance) > 0.01) { // Only include non-zero balances
                $balances[] = [
                    'account_code' => $account->code,
                    'account_name' => $account->name,
                    'balance' => $balance,
                ];
            }
        }

        return $balances;
    }

    /**
     * محاسبه موجودی حساب
     */
    protected function calculateAccountBalance(string $accountCode, int $storeId, Carbon $toDate, Carbon $fromDate = null): float
    {
        $query = FinancialLedger::where('account_code', $accountCode)
            ->where('store_id', $storeId)
            ->where('entry_date', '<=', $toDate);

        if ($fromDate) {
            $query->where('entry_date', '>=', $fromDate);
        }

        $debits = $query->sum('debit');
        $credits = $query->sum('credit');

        return $debits - $credits;
    }

    /**
     * جریان نقدی سرمایه‌گذاری
     */
    protected function getInvestingCashFlow(int $storeId, Carbon $from, Carbon $to): array
    {
        // Simplified - در پروژه واقعی باید از دفتر کل استفاده شود
        return [
            'in' => 0,
            'out' => 0,
        ];
    }

    /**
     * جریان نقدی تامین مالی
     */
    protected function getFinancingCashFlow(int $storeId, Carbon $from, Carbon $to): array
    {
        // Simplified - در پروژه واقعی باید از دفتر کل استفاده شود
        return [
            'in' => 0,
            'out' => 0,
        ];
    }
}
