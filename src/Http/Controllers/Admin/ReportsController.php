<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RMS\Accounting\Services\ReportService;
use RMS\Accounting\Support\AccountingDateUi;

/**
 * کنترلر گزارش‌های مالی
 * 70+ گزارش مالی جامع
 */
class ReportsController extends AccountingAdminController
{
    /**
     * صفحه اصلی گزارش‌ها
     */
    public function index(Request $request)
    {
        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('reports.index')
            ->withCss('vendor/accounting/admin/css/reports.css', true);

        return $this->view();
    }

    // ==========================================
    // گزارش‌های مالی اصلی (Core Financial)
    // ==========================================

    /**
     * گزارش دفتر کل
     */
    public function generalLedger(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getGeneralLedger($request->all());

        return $this->renderReport('general-ledger', $data);
    }

    /**
     * یک سطح زیرشاخهٔ دفتر کل (برای بارگذاری تنبل با AJAX)
     */
    public function generalLedgerBranch(Request $request)
    {
        $request->validate([
            'account_id' => 'required|integer|exists:accounts,id',
            'from_date' => 'nullable|string|max:64',
            'to_date' => 'nullable|string|max:64',
            'start_date' => 'nullable|string|max:64',
            'end_date' => 'nullable|string|max:64',
        ]);

        $nodes = app(ReportService::class)->getGeneralLedgerBranchNodes(
            (int) $request->input('account_id'),
            $request->all()
        );

        $html = view('accounting::admin.reports.partials.general-ledger-branch-fragment', [
            'nodes' => $nodes,
        ])->render();

        return response()->json(['html' => $html]);
    }

    /**
     * گزارش دفتر معین
     */
    public function subsidiaryLedger(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getSubsidiaryLedger($request->all());

        return $this->renderReport('subsidiary-ledger', $data);
    }

    /**
     * گزارش تراز آزمایشی
     */
    public function trialBalance(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getTrialBalance($request->all());

        return $this->renderReport('trial-balance', $data);
    }

    /**
     * ترازنامه
     */
    public function balanceSheet(Request $request)
    {
        $reportService = app(ReportService::class);
        $filters = $request->all();
        $data = $reportService->getBalanceSheet($filters);

        $compareAsOf = trim((string) $request->input('compare_as_of_date', ''));
        if ($compareAsOf !== '') {
            $compareFilters = $filters;
            $compareFilters['as_of_date'] = $compareAsOf;
            $comparison = $reportService->getBalanceSheet($compareFilters);
            $data['comparison'] = [
                'as_of_date' => $comparison['as_of_date'] ?? $compareAsOf,
                'assets_total' => (float) ($comparison['assets']['total'] ?? 0),
                'liabilities_total' => (float) ($comparison['liabilities']['total'] ?? 0),
                'equity_total' => (float) ($comparison['equity']['total'] ?? 0),
                'retained_earnings' => (float) ($comparison['equity']['retained_earnings'] ?? 0),
                'equation_total' => (float) ($comparison['equation']['liabilities_plus_equity'] ?? 0),
                'is_balanced' => (bool) ($comparison['equation']['is_balanced'] ?? false),
            ];
        }

        return $this->renderReport('balance-sheet', $data);
    }

    /**
     * صورت سود و زیان
     */
    public function incomeStatement(Request $request)
    {
        $reportService = app(ReportService::class);
        $filters = $request->all();
        $data = $reportService->getIncomeStatement($filters);

        $compareFrom = trim((string) $request->input('compare_from_date', ''));
        $compareTo = trim((string) $request->input('compare_to_date', ''));
        if ($compareFrom !== '' || $compareTo !== '') {
            $compareFilters = $filters;
            if ($compareFrom !== '') {
                $compareFilters['from_date'] = $compareFrom;
                $compareFilters['start_date'] = $compareFrom;
            }
            if ($compareTo !== '') {
                $compareFilters['to_date'] = $compareTo;
                $compareFilters['end_date'] = $compareTo;
            }

            $comparison = $reportService->getIncomeStatement($compareFilters);
            $data['comparison'] = [
                'period' => $comparison['period'] ?? null,
                'revenue_total' => (float) ($comparison['revenue']['total'] ?? 0),
                'cost_of_goods_sold' => (float) ($comparison['cost_of_goods_sold'] ?? 0),
                'gross_profit' => (float) ($comparison['gross_profit'] ?? 0),
                'operating_expenses_total' => (float) ($comparison['operating_expenses']['total'] ?? 0),
                'operating_income' => (float) ($comparison['operating_income'] ?? 0),
                'income_before_tax' => (float) ($comparison['income_before_tax'] ?? 0),
                'income_tax_expense' => (float) ($comparison['income_tax_expense'] ?? 0),
                'net_income' => (float) ($comparison['net_income'] ?? 0),
            ];
        }

        return $this->renderReport('income-statement', $data);
    }

    /**
     * صورت سود و زیان (نام جایگزین)
     */
    public function profitLoss(Request $request)
    {
        return $this->incomeStatement($request);
    }

    /**
     * گزارش جریان وجوه نقد
     */
    public function cashFlow(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getCashFlow($request->all());

        return $this->renderReport('cash-flow', $data);
    }

    public function employeeLoanBalances(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getEmployeeLoanBalances($request->all());

        return $this->renderReport('employee-loan-balances', $data);
    }

    public function employeeLoanInstallmentsDue(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getEmployeeLoanInstallmentsDue($request->all());

        return $this->renderReport('employee-loan-installments-due', $data);
    }

    public function employeeContracts(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getEmployeeContracts($request->all());

        return $this->renderReport('employee-contracts', $data);
    }

    public function attendanceMonthlySummary(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getAttendanceMonthlySummary($request->all());

        return $this->renderReport('attendance-monthly-summary', $data);
    }

    public function attendanceOvertimeDetail(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getAttendanceOvertimeDetail($request->all());

        return $this->renderReport('attendance-overtime-detail', $data);
    }

    public function attendanceLeaveAbsence(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getAttendanceLeaveAbsence($request->all());

        return $this->renderReport('attendance-leave-absence', $data);
    }

    public function attendanceTerminationSettlement(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getAttendanceTerminationSettlement($request->all());

        return $this->renderReport('attendance-termination-settlement', $data);
    }

    public function attendancePayrollReconciliation(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getAttendancePayrollReconciliation($request->all());

        return $this->renderReport('attendance-payroll-reconciliation', $data);
    }

    public function insuranceMonthly(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getInsuranceMonthlyReport($request->all());

        return $this->renderReport('insurance-monthly', $data);
    }

    public function insuranceMonthlyExportPdf(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getInsuranceMonthlyReport($request->all());
        if (! empty($data['error'])) {
            return redirect()
                ->route('admin.accounting.reports.insurance-monthly', $request->query())
                ->with('warning', (string) $data['error']);
        }

        $html = view('accounting::admin.reports.pdf.insurance-monthly', ['data' => $data])->render();
        $filename = 'insurance-monthly-'.now()->format('Ymd-His').'.pdf';

        if (class_exists(\App\Services\Pdf\SitePdfService::class)) {
            return app(\App\Services\Pdf\SitePdfService::class)->downloadHtml($html, $filename, ['rtl' => true]);
        }

        return response()->view('accounting::admin.reports.pdf.bank-statement-html-fallback', [
            'data' => $data,
            'title' => $filename,
        ]);
    }

    public function insuranceMonthlyExportExcel(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getInsuranceMonthlyReport($request->all());
        if (! empty($data['error'])) {
            return redirect()
                ->route('admin.accounting.reports.insurance-monthly', $request->query())
                ->with('warning', (string) $data['error']);
        }

        $headings = [
            trans('accounting::accounting.reports.insurance_monthly.columns.posted_at'),
            trans('accounting::accounting.reports.insurance_monthly.columns.source'),
            trans('accounting::accounting.reports.insurance_monthly.columns.reference'),
            trans('accounting::accounting.reports.insurance_monthly.columns.document_number'),
            trans('accounting::accounting.reports.insurance_monthly.columns.description'),
            trans('accounting::accounting.reports.insurance_monthly.columns.accrual_employee'),
            trans('accounting::accounting.reports.insurance_monthly.columns.accrual_employer'),
            trans('accounting::accounting.reports.insurance_monthly.columns.accrual_total'),
            trans('accounting::accounting.reports.insurance_monthly.columns.payment_total'),
            trans('accounting::accounting.reports.insurance_monthly.columns.net_change'),
        ];
        $rows = $reportService->insuranceMonthlyExcelRows((array) ($data['source_rows'] ?? []));
        $baseName = 'insurance-monthly-'.now()->format('Ymd-His');

        $sheet = [
            'title' => trans('accounting::accounting.reports.insurance_monthly.title'),
            'headings' => $headings,
            'rows' => $rows,
            'currency_columns' => ['F', 'G', 'H', 'I', 'J'],
            'freeze_pane' => 'A2',
            'rtl' => true,
        ];

        if (class_exists(\App\Services\Excel\ReportExportService::class)) {
            return app(\App\Services\Excel\ReportExportService::class)->downloadReport($baseName, [$sheet]);
        }

        return $this->bankStatementCsvDownload($baseName, $headings, $rows);
    }

    // ==========================================
    // گزارش‌های دریافتنی (AR)
    // ==========================================

    /**
     * گزارش حساب‌های دریافتنی
     */
    public function accountsReceivable(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getAccountsReceivable($request->all());

        return $this->renderReport('accounts-receivable', $data);
    }

    /**
     * مانده حساب مشتریان
     */
    public function customerBalances(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getCustomerBalances($request->all());

        return $this->renderReport('customer-balances', $data);
    }

    /**
     * گردش حساب مشتری
     */
    public function customerStatement(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getCustomerStatement($request->all());

        return $this->renderReport('customer-statement', $data);
    }

    /**
     * مشتریان بدهکار (سررسید شده)
     */
    public function overdueCustomers(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getOverdueCustomers($request->all());

        return $this->renderReport('overdue-customers', $data);
    }

    /**
     * سن بدهی مشتریان (Aging Analysis)
     */
    public function agingAnalysisAR(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getAgingAnalysisAR($request->all());

        return $this->renderReport('aging-analysis-ar', $data);
    }

    /**
     * تاریخچه فاکتورهای مشتری
     */
    public function customerInvoicesHistory(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getCustomerInvoicesHistory($request->all());

        return $this->renderReport('customer-invoices-history', $data);
    }

    /**
     * تاریخچه پرداخت‌های دریافتی
     */
    public function paymentsReceivedHistory(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getPaymentsReceivedHistory($request->all());

        return $this->renderReport('payments-received-history', $data);
    }

    // ==========================================
    // گزارش‌های پرداختنی (AP)
    // ==========================================

    /**
     * گزارش حساب‌های پرداختنی
     */
    public function accountsPayable(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getAccountsPayable($request->all());

        return $this->renderReport('accounts-payable', $data);
    }

    /**
     * مانده حساب تامین‌کنندگان
     */
    public function supplierBalances(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getSupplierBalances($request->all());

        return $this->renderReport('supplier-balances', $data);
    }

    /**
     * گردش حساب تامین‌کننده
     */
    public function supplierStatement(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getSupplierStatement($request->all());

        return $this->renderReport('supplier-statement', $data);
    }

    /**
     * بدهی‌های سررسید شده
     */
    public function overduePayables(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getOverduePayables($request->all());

        return $this->renderReport('overdue-payables', $data);
    }

    /**
     * سن بدهی به تامین‌کنندگان
     */
    public function agingAnalysisAP(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getAgingAnalysisAP($request->all());

        return $this->renderReport('aging-analysis-ap', $data);
    }

    // ==========================================
    // گزارش‌های Party-Based
    // ==========================================

    /**
     * گزارش مانده parties
     */
    public function partyBalances(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getPartyBalances($request->all());

        return $this->renderReport('party-balances', $data);
    }

    /**
     * گردش حساب یک party
     */
    public function partyStatement(Request $request, int $partyId)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getPartyStatement($partyId, $request->all());

        return $this->renderReport('party-statement', $data);
    }

    /**
     * گزارش سودآوری یک party
     */
    public function partyProfitability(Request $request, int $partyId)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getPartyProfitability($partyId, $request->all());

        return $this->renderReport('party-profitability', $data);
    }

    /**
     * گزارش سودآوری همه parties
     */
    public function allPartiesProfitability(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getAllPartiesProfitability($request->all());

        return $this->renderReport('all-parties-profitability', $data);
    }

    /**
     * گزارش سودآوری یک customer از یک supplier
     */
    public function customerSupplierProfitability(Request $request, int $customerId, int $supplierId)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getCustomerSupplierProfitability($customerId, $supplierId, $request->all());

        return $this->renderReport('customer-supplier-profitability', $data);
    }

    /**
     * لیست parties که هم customer هستن هم supplier
     */
    public function partiesWithBothRoles(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getPartiesWithBothRoles($request->all());

        return $this->renderReport('parties-with-both-roles', $data);
    }

    /**
     * تحلیل سررسید برای یک party
     */
    public function partyAgingAnalysis(Request $request, int $partyId)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getPartyAgingAnalysis($partyId, $request->all());

        return $this->renderReport('party-aging-analysis', $data);
    }

    /**
     * تاریخچه سفارش‌های خرید
     */
    public function purchaseOrdersHistory(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getPurchaseOrdersHistory($request->all());

        return $this->renderReport('purchase-orders-history', $data);
    }

    /**
     * تاریخچه فاکتورهای خرید
     */
    public function supplierInvoicesHistory(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getSupplierInvoicesHistory($request->all());

        return $this->renderReport('supplier-invoices-history', $data);
    }

    /**
     * تاریخچه پرداخت‌های انجام شده
     */
    public function paymentsMadeHistory(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getPaymentsMadeHistory($request->all());

        return $this->renderReport('payments-made-history', $data);
    }

    // ==========================================
    // گزارش‌های خزانه‌داری (Treasury)
    // ==========================================

    /**
     * موجودی بانک‌ها
     */
    public function bankBalances(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getBankBalances($request->all());

        return $this->renderReport('bank-balances', $data);
    }

    /**
     * موجودی صندوق‌ها
     */
    public function cashboxBalances(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getCashboxBalances($request->all());

        return $this->renderReport('cashbox-balances', $data);
    }

    /**
     * گزارش تراکنش‌های بانکی
     */
    public function bankTransactions(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getBankTransactions($request->all());

        return $this->renderReport('bank-transactions', $data);
    }

    /**
     * خروجی PDF صورتحساب بانکی (هم‌راستا با SitePdfService در اپ در صورت وجود)
     */
    public function bankTransactionsExportPdf(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getBankTransactions($request->all());

        $html = view('accounting::admin.reports.pdf.bank-statement', ['data' => $data])->render();
        $mode = (string) ($data['mode'] ?? 'detail');
        $bankId = (int) ($data['bank_id'] ?? 0);
        $filename = 'bank-statement-'.$mode.'-'.$bankId.'-'.now()->format('Ymd-His').'.pdf';

        if (class_exists(\App\Services\Pdf\SitePdfService::class)) {
            return app(\App\Services\Pdf\SitePdfService::class)->downloadHtml($html, $filename, ['rtl' => true]);
        }

        return response()->view('accounting::admin.reports.pdf.bank-statement-html-fallback', [
            'data' => $data,
            'title' => $filename,
        ]);
    }

    /**
     * خروجی Excel صورتحساب بانکی (هم‌راستا با ReportExportService در اپ در صورت وجود)
     */
    public function bankTransactionsExportExcel(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getBankTransactions($request->all());

        if (! empty($data['error'])) {
            return redirect()
                ->route('admin.accounting.reports.bank-transactions', $request->query())
                ->with('warning', $data['error']);
        }

        $mode = (string) ($data['mode'] ?? 'detail');
        $bankId = (int) ($data['bank_id'] ?? 0);
        $bankName = isset($data['bank']) ? (string) $data['bank']->name : 'bank';
        $baseName = 'bank-statement-'.$mode.'-'.$bankId.'-'.now()->format('Ymd-His');

        $headings = [
            trans('accounting::accounting.reports.bank_statement.col_date'),
            trans('accounting::accounting.reports.bank_statement.col_document'),
            trans('accounting::accounting.reports.bank_statement.col_type'),
            trans('accounting::accounting.reports.bank_statement.col_description'),
            trans('accounting::accounting.reports.bank_statement.col_debit'),
            trans('accounting::accounting.reports.bank_statement.col_credit'),
            trans('accounting::accounting.reports.bank_statement.col_balance'),
        ];

        if ($mode === 'summary') {
            $rows = $reportService->bankStatementSummaryExcelRows((array) ($data['summary_rows'] ?? []));
        } else {
            $rows = $reportService->bankStatementDetailExcelRows((array) ($data['detail_rows'] ?? []));
        }

        $sheet = [
            'title' => $bankName.' — '.trans('accounting::accounting.reports.bank_statement.title'),
            'headings' => $headings,
            'rows' => $rows,
            'currency_columns' => ['E', 'F', 'G'],
            'freeze_pane' => 'A2',
            'rtl' => true,
        ];

        if (class_exists(\App\Services\Excel\ReportExportService::class)) {
            return app(\App\Services\Excel\ReportExportService::class)->downloadReport($baseName, [$sheet]);
        }

        return $this->bankStatementCsvDownload($baseName, $headings, $rows);
    }

    /**
     * خطوط دفتر یک سند (برای AJAX گزارش بانک)
     */
    public function bankStatementDocumentLines(Request $request, int $document): JsonResponse
    {
        $payload = app(ReportService::class)->getBankStatementDocumentLines($document);
        if ($payload['document'] === null) {
            return response()->json(['ok' => false, 'message' => 'Not found'], 404);
        }

        return response()->json([
            'ok' => true,
            'document' => [
                'id' => $payload['document']->id,
                'document_number' => $payload['document']->document_number,
                'document_type' => $payload['document']->document_type,
                'description' => $payload['document']->description,
            ],
            'lines' => $payload['lines'],
        ]);
    }

    /**
     * خروجی ساده CSV وقتی ReportExportService در اپ نیست
     *
     * @param  array<int, array<int, mixed>>  $rows
     */
    protected function bankStatementCsvDownload(string $baseName, array $headings, array $rows): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $fn = $baseName.'.csv';

        return response()->streamDownload(function () use ($headings, $rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headings);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $fn, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * گزارش تراکنش‌های نقدی
     */
    public function cashTransactions(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getCashTransactions($request->all());

        return $this->renderReport('cash-transactions', $data);
    }

    /**
     * گزارش چک‌های دریافتی
     */
    public function chequesReceived(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getChequesReceived($request->all());

        return $this->renderReport('cheques-received', $data);
    }

    /**
     * گزارش چک‌های پرداختی
     */
    public function chequesIssued(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getChequesIssued($request->all());

        return $this->renderReport('cheques-issued', $data);
    }

    /**
     * چک‌های سررسید
     */
    public function chequeReminders(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getChequeReminders($request->all());

        return $this->renderReport('cheque-reminders', $data);
    }

    /**
     * گزارش POS
     */
    public function posReport(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getPOSReport($request->all());

        return $this->renderReport('pos-report', $data);
    }

    /**
     * گزارش کیف پول‌ها
     */
    public function walletReport(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getWalletReport($request->all());

        return $this->renderReport('wallet-report', $data);
    }

    // ==========================================
    // گزارش‌های مالیاتی (Tax)
    // ==========================================

    /**
     * گزارش مالیات بر ارزش افزوده
     */
    public function vatReport(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getVATReport($request->all());

        return $this->renderReport('vat-report', $data);
    }

    /**
     * مالیات پرداختی
     */
    public function vatPayable(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getVATPayable($request->all());

        return $this->renderReport('vat-payable', $data);
    }

    /**
     * مالیات دریافتنی
     */
    public function vatReceivable(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getVATReceivable($request->all());

        return $this->renderReport('vat-receivable', $data);
    }

    /**
     * گزارش مالیات بر درآمد
     */
    public function incomeTaxReport(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getIncomeTaxReport($request->all());

        return $this->renderReport('income-tax-report', $data);
    }

    /**
     * گزارش معاملات مشمول مالیات
     */
    public function taxableTransactions(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getTaxableTransactions($request->all());

        return $this->renderReport('taxable-transactions', $data);
    }

    // ==========================================
    // گزارش‌های هزینه (Expense)
    // ==========================================

    /**
     * خلاصه هزینه‌ها
     */
    public function expenseSummary(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getExpenseSummary($request->all());

        return $this->renderReport('expense-summary', $data);
    }

    /**
     * گزارش هزینه‌های ماهانه
     */
    public function expenseMonthly(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getExpenseMonthly($request->all());

        return $this->renderReport('expense-monthly', $data);
    }

    /**
     * گزارش هزینه به تفکیک دسته
     */
    public function expenseByCategory(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getExpenseByCategory($request->all());

        return $this->renderReport('expense-by-category', $data);
    }

    /**
     * گزارش هزینه‌های تکرارشونده
     */
    public function recurringExpenses(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getRecurringExpenses($request->all());

        return $this->renderReport('recurring-expenses', $data);
    }

    /**
     * مقایسه هزینه واقعی با بودجه
     */
    public function expenseVsBudget(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getExpenseVsBudget($request->all());

        return $this->renderReport('expense-vs-budget', $data);
    }

    /**
     * بزرگ‌ترین هزینه‌ها
     */
    public function topExpenses(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getTopExpenses($request->all());

        return $this->renderReport('top-expenses', $data);
    }

    // ==========================================
    // گزارش‌های ارزی (Currency/FX)
    // ==========================================

    /**
     * گزارش تراکنش‌های ارزی
     */
    public function currencyTransactions(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getCurrencyTransactions($request->all());

        return $this->renderReport('currency-transactions', $data);
    }

    /**
     * اختلاف تسعیر ارز (FX Gain/Loss)
     */
    public function fxGainLoss(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getFXGainLoss($request->all());

        return $this->renderReport('fx-gain-loss', $data);
    }

    /**
     * نرخ‌های تبدیل استفاده شده
     */
    public function fxRatesUsed(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getFXRatesUsed($request->all());

        return $this->renderReport('fx-rates-used', $data);
    }

    /**
     * گزارش خریدهای ارزی
     */
    public function foreignPurchases(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getForeignPurchases($request->all());

        return $this->renderReport('foreign-purchases', $data);
    }

    /**
     * مانده‌های ارزی
     */
    public function currencyBalances(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getCurrencyBalances($request->all());

        return $this->renderReport('currency-balances', $data);
    }

    // ==========================================
    // گزارش‌های COGS
    // ==========================================

    /**
     * بهای تمام شده کالای فروش رفته
     */
    public function cogsReport(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getCOGSReport($request->all());

        return $this->renderReport('cogs-report', $data);
    }

    /**
     * تحلیل سودآوری محصولات
     */
    public function productProfitability(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getProductProfitability($request->all());

        return $this->renderReport('product-profitability', $data);
    }

    /**
     * مقایسه قیمت فروش و COGS
     */
    public function salesVsCogs(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getSalesVsCOGS($request->all());

        return $this->renderReport('sales-vs-cogs', $data);
    }

    /**
     * روند COGS ماهانه
     */
    public function cogsMonthlyTrend(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getCOGSMonthlyTrend($request->all());

        return $this->renderReport('cogs-monthly-trend', $data);
    }

    // ==========================================
    // گزارش‌های فروش (Sales)
    // ==========================================

    /**
     * خلاصه فروش
     */
    public function salesSummary(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getSalesSummary($request->all());

        return $this->renderReport('sales-summary', $data);
    }

    /**
     * فروش به تفکیک مشتری
     */
    public function salesByCustomer(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getSalesByCustomer($request->all());

        return $this->renderReport('sales-by-customer', $data);
    }

    /**
     * فروش به تفکیک محصول
     */
    public function salesByProduct(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getSalesByProduct($request->all());

        return $this->renderReport('sales-by-product', $data);
    }

    /**
     * روند فروش
     */
    public function salesTrend(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getSalesTrend($request->all());

        return $this->renderReport('sales-trend', $data);
    }

    // ==========================================
    // گزارش‌های تطبیق (Reconciliation)
    // ==========================================

    /**
     * گزارش تطبیق بانک
     */
    public function bankReconciliation(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getBankReconciliation($request->all());

        return $this->renderReport('bank-reconciliation', $data);
    }

    /**
     * گزارش تطبیق صندوق
     */
    public function cashboxReconciliation(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getCashboxReconciliation($request->all());

        return $this->renderReport('cashbox-reconciliation', $data);
    }

    /**
     * اختلافات تطبیق نشده
     */
    public function unreconciledItems(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getUnreconciledItems($request->all());

        return $this->renderReport('unreconciled-items', $data);
    }

    /**
     * تاریخچه تطبیق‌ها
     */
    public function reconciliationHistory(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getReconciliationHistory($request->all());

        return $this->renderReport('reconciliation-history', $data);
    }

    // ==========================================
    // گزارش‌های تحلیلی (Analytics)
    // ==========================================

    /**
     * پیش‌بینی نقدینگی
     */
    public function cashFlowForecast(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getCashFlowForecast($request->all());

        return $this->renderReport('cash-flow-forecast', $data);
    }

    /**
     * نسبت‌های مالی
     */
    public function financialRatios(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getFinancialRatios($request->all());

        return $this->renderReport('financial-ratios', $data);
    }

    /**
     * تحلیل سودآوری
     */
    public function profitabilityAnalysis(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getProfitabilityAnalysis($request->all());

        return $this->renderReport('profitability-analysis', $data);
    }

    /**
     * تحلیل روند درآمد
     */
    public function revenueTrend(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getRevenueTrend($request->all());

        return $this->renderReport('revenue-trend', $data);
    }

    /**
     * مقایسه دوره‌ای
     */
    public function periodComparison(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getPeriodComparison($request->all());

        return $this->renderReport('period-comparison', $data);
    }

    // ==========================================
    // گزارش‌های سال مالی
    // ==========================================

    /**
     * عملکرد سال مالی
     */
    public function fiscalYearPerformance(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getFiscalYearPerformance($request->all());

        return $this->renderReport('fiscal-year-performance', $data);
    }

    /**
     * مقایسه سال جاری با سال قبل
     */
    public function yearOverYear(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getYearOverYear($request->all());

        return $this->renderReport('year-over-year', $data);
    }

    /**
     * گزارش بستن حساب‌ها
     */
    public function closingReport(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getClosingReport($request->all());

        return $this->renderReport('closing-report', $data);
    }

    // ==========================================
    // گزارش‌های Audit
    // ==========================================

    /**
     * تاریخچه تغییرات
     */
    public function auditTrail(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getAuditTrail($request->all());

        return $this->renderReport('audit-trail', $data);
    }

    /**
     * گزارش سندهای اصلاحی
     */
    public function documentReversals(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getDocumentReversals($request->all());

        return $this->renderReport('document-reversals', $data);
    }

    /**
     * Log فعالیت‌های حسابداری
     */
    public function accountingActivityLog(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getAccountingActivityLog($request->all());

        return $this->renderReport('accounting-activity-log', $data);
    }

    /**
     * گزارش مغایرت‌ها
     */
    public function discrepancies(Request $request)
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getDiscrepancies($request->all());

        return $this->renderReport('discrepancies', $data);
    }

    // ==========================================
    // Helper Methods
    // ==========================================

    /**
     * متد کمکی برای render گزارش‌ها
     */
    protected function renderReport(string $template, array $data)
    {
        // تشخیص view مناسب بر اساس نوع گزارش
        $viewTemplate = 'reports.table-report'; // default
        
        if ($template === 'balance-sheet') {
            $viewTemplate = 'reports.balance-sheet';
        } elseif ($template === 'income-statement' || $template === 'profit-loss') {
            $viewTemplate = 'reports.income-statement';
        } elseif ($template === 'bank-transactions') {
            $viewTemplate = 'reports.bank-transactions';
        } elseif ($template === 'cash-transactions') {
            $viewTemplate = 'reports.cash-transactions';
        } elseif ($template === 'insurance-monthly') {
            $viewTemplate = 'reports.insurance_monthly';
        }
        
        $plugins = AccountingDateUi::calendarMode() === AccountingDateUi::MODE_JALALI
            ? ['persian-datepicker']
            : [];

        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl($viewTemplate)
            ->withCss('vendor/accounting/admin/css/reports.css', true)
            ->withPlugins($plugins)
            ->withJs('vendor/accounting/admin/js/accounting-date-ui.js', true);

        if (! empty($data['lazy_branch'] ?? false)) {
            $this->view->withJs('vendor/accounting/admin/js/general-ledger-tree.js', true);
        }

        $this->view->withVariables(['data' => $data]);

        return $this->view();
    }

    // متدهای abstract
    public function table(): string
    {
        return '';
    }

    public function modelName(): string
    {
        return '';
    }
}
