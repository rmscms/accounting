# گزارش‌های پکیج Accounting — فهرست و وضعیت

این سند نگاشت **نام route** (`admin.accounting.reports.*`)، **متد `ReportService`**, **قالب ویو** (`renderReport` → `table-report` مگر خلاف آن ذکر شده)، و **وضعیت پیاده‌سازی** را مشخص می‌کند.

**وضعیت:** `implemented` داده واقعی؛ `partial` داده محدود یا وابسته به داده ناقص؛ `stub` فقط پیام placeholder؛ `custom` ویو اختصاصی.

## گزارش‌های هسته مالی

| Route name | ReportService | View | Status |
|------------|---------------|------|--------|
| `general-ledger` | `getGeneralLedger` | `table-report` (+ tree partials) | implemented |
| `general-ledger-branch` | `getGeneralLedgerBranchNodes` | JSON partial | implemented |
| `subsidiary-ledger` | `getSubsidiaryLedger` | `table-report` | implemented |
| `trial-balance` | `getTrialBalance` | `table-report` | implemented |
| `balance-sheet` | `getBalanceSheet` | `balance-sheet` | custom |
| `income-statement` / `profit-loss` | `getIncomeStatement` | `income-statement` | custom |
| `cash-flow` | `getCashFlow` | `table-report` | partial |

## دریافتنی (AR)

| Route name | ReportService | View | Status |
|------------|---------------|------|--------|
| `accounts-receivable` | `getAccountsReceivable` | `table-report` | implemented |
| `customer-balances` | `getCustomerBalances` | `table-report` | implemented |
| `customer-statement` | `getCustomerStatement` | `table-report` | implemented |
| `overdue-customers` | `getOverdueCustomers` | `table-report` | implemented |
| `aging-analysis-ar` | `getAgingAnalysisAR` | `table-report` | implemented |
| `customer-invoices-history` | `getCustomerInvoicesHistory` | `table-report` | implemented |
| `payments-received-history` | `getPaymentsReceivedHistory` | `table-report` | implemented |

## پرداختنی (AP)

| Route name | ReportService | View | Status |
|------------|---------------|------|--------|
| `accounts-payable` | `getAccountsPayable` | `table-report` | implemented |
| `supplier-balances` | `getSupplierBalances` | `table-report` | implemented |
| `supplier-statement` | `getSupplierStatement` | `table-report` | implemented |
| `overdue-payables` | `getOverduePayables` | `table-report` | implemented |
| `aging-analysis-ap` | `getAgingAnalysisAP` | `table-report` | implemented |
| `purchase-orders-history` | `getPurchaseOrdersHistory` | `table-report` | implemented |
| `supplier-invoices-history` | `getSupplierInvoicesHistory` | `table-report` | implemented |
| `payments-made-history` | `getPaymentsMadeHistory` | `table-report` | implemented |

## خزانه و بانک

| Route name | ReportService | View | Status |
|------------|---------------|------|--------|
| `bank-balances` | `getBankBalances` | `table-report` | implemented |
| `cashbox-balances` | `getCashboxBalances` | `table-report` | implemented |
| `bank-transactions` | `getBankTransactions` | `bank-transactions` | custom |
| `bank-transactions.export.pdf` | — | PDF | custom |
| `bank-transactions.export.excel` | — | export | custom |
| `bank-transactions.document.lines` | `getBankStatementDocumentLines` | JSON | implemented |
| `cash-transactions` | `getCashTransactions` | `table-report` | **stub** |
| `cheques-received` | `getChequesReceived` | `table-report` | implemented |
| `cheques-issued` | `getChequesIssued` | `table-report` | implemented |
| `cheque-reminders` | `getChequeReminders` | `table-report` | implemented |
| `pos-report` | `getPOSReport` | `table-report` | **stub** |
| `wallet-report` | `getWalletReport` | `table-report` (`columns`+`rows`) | implemented |

## مالیات

| Route name | ReportService | View | Status |
|------------|---------------|------|--------|
| `vat-report` | `getVATReport` | `table-report` | partial |
| `vat-payable` | `getVATPayable` | `table-report` | partial |
| `vat-receivable` | `getVATReceivable` | `table-report` | partial |
| `income-tax-report` | `getIncomeTaxReport` | `table-report` | implemented |
| `taxable-transactions` | `getTaxableTransactions` | `table-report` | implemented |

## هزینه

| Route name | ReportService | View | Status |
|------------|---------------|------|--------|
| `expense-summary` | `getExpenseSummary` | `table-report` | implemented |
| `expense-monthly` | `getExpenseMonthly` | `table-report` | implemented |
| `expense-by-category` | `getExpenseByCategory` | `table-report` | implemented |
| `recurring-expenses` | `getRecurringExpenses` | `table-report` | **stub** |
| `expense-vs-budget` | `getExpenseVsBudget` | `table-report` | **stub** |
| `top-expenses` | `getTopExpenses` | `table-report` | partial |

## ارزی / FX

| Route name | ReportService | View | Status |
|------------|---------------|------|--------|
| `currency-transactions` | `getCurrencyTransactions` | `table-report` | **stub** |
| `fx-gain-loss` | `getFXGainLoss` | `table-report` | **stub** |
| `fx-rates-used` | `getFXRatesUsed` | `table-report` | **stub** |
| `foreign-purchases` | `getForeignPurchases` | `table-report` | **stub** |
| `currency-balances` | `getCurrencyBalances` | `table-report` | **stub** |

## COGS / فروش

| Route name | ReportService | View | Status |
|------------|---------------|------|--------|
| `cogs-report` | `getCOGSReport` | `table-report` | **stub** |
| `product-profitability` | `getProductProfitability` | `table-report` | **stub** |
| `sales-vs-cogs` | `getSalesVsCOGS` | `table-report` | **stub** |
| `cogs-monthly-trend` | `getCOGSMonthlyTrend` | `table-report` | **stub** |
| `sales-summary` | `getSalesSummary` | `table-report` | implemented |
| `sales-by-customer` | `getSalesByCustomer` | `table-report` | implemented |
| `sales-by-product` | `getSalesByProduct` | `table-report` | **stub** |
| `sales-trend` | `getSalesTrend` | `table-report` | implemented |

## تطبیق

| Route name | ReportService | View | Status |
|------------|---------------|------|--------|
| `bank-reconciliation` | `getBankReconciliation` | `table-report` | placeholder + hint |
| `cashbox-reconciliation` | `getCashboxReconciliation` | `table-report` | **stub** |
| `unreconciled-items` | `getUnreconciledItems` | `table-report` | **stub** |
| `reconciliation-history` | `getReconciliationHistory` | `table-report` | **stub** |

## تحلیلی

| Route name | ReportService | View | Status |
|------------|---------------|------|--------|
| `cash-flow-forecast` | `getCashFlowForecast` | `table-report` | **stub** |
| `financial-ratios` | `getFinancialRatios` | `table-report` | partial |
| `profitability-analysis` | `getProfitabilityAnalysis` | `table-report` | partial |
| `revenue-trend` | `getRevenueTrend` | `table-report` | implemented |
| `period-comparison` | `getPeriodComparison` | `table-report` | **stub** |

## سال مالی

| Route name | ReportService | View | Status |
|------------|---------------|------|--------|
| `fiscal-year-performance` | `getFiscalYearPerformance` | `table-report` | partial |
| `year-over-year` | `getYearOverYear` | `table-report` | **stub** |
| `closing-report` | `getClosingReport` | `table-report` | **stub** |

## Audit

| Route name | ReportService | View | Status |
|------------|---------------|------|--------|
| `audit-trail` | `getAuditTrail` | `table-report` | **stub** |
| `document-reversals` | `getDocumentReversals` | `table-report` | implemented |
| `accounting-activity-log` | `getAccountingActivityLog` | `table-report` | **stub** |
| `discrepancies` | `getDiscrepancies` | `table-report` | implemented |

## Party-based

| Route name | ReportService | View | Status |
|------------|---------------|------|--------|
| `party-balances` | `getPartyBalances` | `table-report` | implemented |
| `party-statement` | `getPartyStatement` | `table-report` | implemented |
| `party-profitability` | `getPartyProfitability` | `table-report` | implemented |
| `all-parties-profitability` | `getAllPartiesProfitability` | `table-report` | implemented |
| `customer-supplier-profitability` | `getCustomerSupplierProfitability` | `table-report` | implemented |
| `parties-with-both-roles` | `getPartiesWithBothRoles` | `table-report` | implemented |
| `party-aging-analysis` | `getPartyAgingAnalysis` | `table-report` | implemented |

---

**یادداشت:** `renderReport` در `ReportsController` فقط برای `balance-sheet`, `income-statement`, `bank-transactions` ویو اختصاصی انتخاب می‌کند؛ بقیه `table-report` هستند. مرجع کد: `ReportsController::renderReport`.

برای **اولویت تکمیل استاب‌ها** به [`REPORT_STUB_PHASES.md`](REPORT_STUB_PHASES.md) مراجعه کنید.
