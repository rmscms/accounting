# ماتریس تکمیل اکانتینگ (Flow → Scenario → Test → Report)

این سند خط مبنای اجرای برنامه تکمیل اکانتینگ است و برای هر Flow حیاتی، وضعیت سناریو، تست و تاثیر گزارش را مشخص می‌کند.

## KPIهای کنترل پیشرفت

- `kpi.scenario_coverage_percent`: درصد Flowهای حیاتی که حداقل یک سناریوی Scenario Runner اجرایی دارند.
- `kpi.postcheck_coverage_percent`: درصد سناریوهای P0/P1 که post-check معتبر (تراز/گزارش/مانده) دارند.
- `kpi.report_non_stub_percent`: درصد گزارش‌هایی که خروجی واقعی دارند (نه placeholder).
- `kpi.financial_test_coverage_percent`: درصد دامنه‌های مالی با تست Feature/Integration قابل اجرا.
- `kpi.sensitive_endpoint_hardening_percent`: درصد endpointهای حساس که auth/throttle/idempotency مناسب دارند.

## ماتریس دامنه‌های حیاتی

| Flow Domain | سناریوهای اجرایی | تست‌ها | گزارش‌های متاثر |
|---|---|---|---|
| AR / Sales | `sales_invoice_credit`, `customer_receipt_cash`, `customer_receipt_wallet`, `credit_note_issue`, `credit_note_apply`, `customer_advance_cash`, `customer_advance_apply`, `customer_refund_cash`, `bad_debt_writeoff` | `tests/Feature/Accounting/ScenarioRunnerCoveragePhase2Test.php`, `tests/Feature/Accounting/CreditNoteCustomerSubledgerTest.php` | `accounts-receivable`, `customer-statement`, `aging-analysis-ar`, `taxable-transactions` |
| AP / Purchase | `purchase_invoice_on_account`, `supplier_payment_cash`, `supplier_payment_wallet`, `debit_note_issue`, `debit_note_apply`, `supplier_advance_cash`, `supplier_advance_apply`, `supplier_refund_cash` | `tests/Feature/Accounting/ScenarioRunnerCoveragePhase2Test.php` | `accounts-payable`, `supplier-statement`, `purchase-orders-history`, `taxable-transactions` |
| Treasury / Cheque | `bank_transfer_treasury`, `bank_transfer_cashbox_to_bank`, `received_cheque_cash`, `issued_cheque_cash`, `issued_cheque_bounce` | `tests/Feature/Accounting/ScenarioRunnerCoveragePhase2Test.php` | `bank-transactions`, `cashbox-balances`, `discrepancies` |
| Expense / Inventory / Accrual | `expense_paid_cash`, `inventory_adjustment_post`, `accrual_post_reverse` | `tests/Feature/Accounting/ScenarioRunnerCoveragePhase2Test.php` | `expense-summary`, `cogs-monthly-trend` |
| Fixed Asset | `fixed_asset_purchase_cash`, `fixed_asset_depreciation`, `fixed_asset_disposal` | `tests/Feature/Accounting/ScenarioRunnerCoveragePhase2Test.php` | `balance-sheet`, `income-statement` |
| Payroll / HR | `payroll_accrual_basic`, `payroll_insurance_remittance`, `payroll_loan_settlement` | `tests/Feature/Accounting/ScenarioRunnerCoveragePhase2Test.php`, `tests/Unit/AccountingDataWipeServicePayrollTablesTest.php` | `employee-loan-balances`, `employee-loan-installments-due` |
| VAT / Tax | `vat_declaration_submit`, `vat_remittance_bank` | `tests/Feature/Accounting/ScenarioRunnerCoveragePhase2Test.php`, `tests/Feature/Accounting/ReportServiceConversionTest.php` | `vat-report`, `vat-payable`, `income-tax-report`, `taxable-transactions` |
| Reports / Reconciliation | `reconciliation_discrepancies_check` (post-check oriented) | `tests/Feature/Accounting/ReportServiceConversionTest.php` | `discrepancies`, `currency-transactions`, `fx-gain-loss`, `currency-balances` |
| Security / API / Ops | `api_tax_secured`, `service_api_idempotent`, `dev_tools_guard`, `accounting_health_check` | `tests/Feature/Accounting/SecurityApiHardeningTest.php` | عملیاتی (نه گزارش مالی مستقیم) |

## معیار پذیرش هر موج

- هیچ سناریوی `P0/P1` در اجرای Run نباید `ok=false` باشد.
- حداقل ۹۵٪ سناریوهای P0/P1 دارای `post-check` معتبر باشند.
- هیچ گزارش `P0` نباید در وضعیت `stub` باقی بماند.
- endpointهای مالی write باید `auth + throttle + idempotency` داشته باشند.
- فرمان `accounting:health` باید وضعیت `ok` برگرداند.

## KPI Baseline (قبل از این فاز)

- `scenario_coverage_percent`: 0.58
- `postcheck_coverage_percent`: 0.08
- `report_non_stub_percent`: 0.74
- `financial_test_coverage_percent`: 0.32
- `sensitive_endpoint_hardening_percent`: 0.41

## KPI Target (پایان برنامه)

- `scenario_coverage_percent >= 0.90`
- `postcheck_coverage_percent >= 0.80`
- `report_non_stub_percent >= 0.92`
- `financial_test_coverage_percent >= 0.75`
- `sensitive_endpoint_hardening_percent >= 0.95`
