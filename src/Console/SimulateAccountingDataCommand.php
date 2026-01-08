<?php

namespace RMS\Accounting\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use RMS\Accounting\Console\Simulators\CurrencySimulator;
use RMS\Accounting\Console\Simulators\AccountsSimulator;
use RMS\Accounting\Console\Simulators\TreasurySimulator;
use RMS\Accounting\Console\Simulators\CustomersSimulator;
use RMS\Accounting\Console\Simulators\SuppliersSimulator;
use RMS\Accounting\Console\Simulators\SalesSimulator;
use RMS\Accounting\Console\Simulators\PurchasesSimulator;
use RMS\Accounting\Console\Simulators\ExpensesSimulator;
use RMS\Accounting\Console\Simulators\PaymentsSimulator;
use RMS\Accounting\Console\Simulators\ChequesSimulator;
use RMS\Accounting\Console\Simulators\ReconciliationSimulator;

/**
 * دستور شبیه‌سازی داده‌های حسابداری
 * شبیه‌سازی یک سال کامل فعالیت کسب‌وکار
 */
class SimulateAccountingDataCommand extends Command
{
    protected $signature = 'accounting:simulate 
        {--year=1403 : سال مالی شمسی}
        {--stores=1 : تعداد فروشگاه}
        {--customers=2000 : تعداد مشتری}
        {--suppliers=300 : تعداد تامین‌کننده}
        {--clean : پاک کردن داده‌های قبلی}';

    protected $description = 'شبیه‌سازی یک سال کامل داده‌های حسابداری برای تست';

    protected int $year;
    protected array $options;
    protected Carbon $startTime;
    protected array $stats = [];

    public function handle()
    {
        $this->startTime = now();
        $this->year = (int) $this->option('year');
        $this->options = [
            'stores' => (int) $this->option('stores'),
            'customers' => (int) $this->option('customers'),
            'suppliers' => (int) $this->option('suppliers'),
        ];

        $this->info('🚀 شروع شبیه‌سازی حسابداری...');
        $this->newLine();

        try {
            // Clean database if requested
            if ($this->option('clean')) {
                $this->cleanDatabase();
            }

            // Phase 1: Setup
            $this->phase1Setup();

            // Phase 2: Entities
            $this->phase2Entities();

            // Phase 3: Monthly Simulation
            $this->phase3MonthlySimulation();

            // Display final summary
            $this->displayFinalSummary();

            $this->newLine();
            $this->info('✅ شبیه‌سازی کامل شد!');

            return 0;
        } catch (\Exception $e) {
            $this->error('❌ خطا در شبیه‌سازی: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }

    /**
     * پاک کردن داده‌های قبلی
     */
    protected function cleanDatabase(): void
    {
        $this->warn('⚠️  در حال پاک کردن داده‌های قبلی...');

        DB::transaction(function () {
            // Disable FK constraints temporarily
            DB::statement('PRAGMA foreign_keys = OFF');
            
            $tables = [
                'payment_reconciliations',
                'settlements',
                'cheques',
                'wallet_transactions',
                'wallets',
                'customer_payments',
                'customer_invoices',
                'customer_balances',
                'supplier_payments',
                'supplier_invoice_items',
                'supplier_invoices',
                'purchase_order_items',
                'purchase_orders',
                'suppliers',
                'expense_items',
                'expenses',
                'expense_categories',
                'cost_entries',
                'tax_rates',
                'financial_ledgers',
                'accounting_documents',
                'pos_terminals',
                'payment_methods',
                'cash_boxes',
                'banks',
                'currency_rates',
                'currencies',
                'fiscal_years',
                'accounts',
            ];

            foreach ($tables as $table) {
                try {
                    DB::statement("DELETE FROM {$table}");
                } catch (\Exception $e) {
                    // Table might not exist yet
                }
            }
            
            // Re-enable FK constraints
            DB::statement('PRAGMA foreign_keys = ON');
        });

        $this->info('  ✅ داده‌های قبلی پاک شدند');
        $this->newLine();
    }

    /**
     * Phase 1: تنظیمات پایه
     */
    protected function phase1Setup(): void
    {
        $this->info('📋 Phase 1: تنظیمات پایه');

        // 1. Currencies
        $currencySimulator = new CurrencySimulator($this, $this->year, $this->options);
        $currencySimulator->simulate();
        $this->stats['currencies'] = 4;
        $this->stats['currency_rates'] = 1460; // 365 days × 4 currencies

        // 2. Accounts
        $accountsSimulator = new AccountsSimulator($this, $this->year, $this->options);
        $accountsSimulator->simulate();
        $this->stats['accounts'] = 45;

        // 3. Fiscal Year
        $this->createFiscalYear();
        $this->stats['fiscal_year'] = $this->year;

        // 4. Treasury
        $treasurySimulator = new TreasurySimulator($this, $this->year, $this->options);
        $treasurySimulator->simulate();
        $this->stats['banks'] = 3;
        $this->stats['cash_boxes'] = 2;
        $this->stats['pos_terminals'] = 2;
        $this->stats['payment_methods'] = 5;

        // 5. Tax Rates
        $this->createTaxRates();
        $this->stats['tax_rates'] = 1;

        $this->newLine();
    }

    /**
     * Phase 2: موجودیت‌ها
     */
    protected function phase2Entities(): void
    {
        $this->info('📋 Phase 2: موجودیت‌ها');

        // 1. Customers
        $customersSimulator = new CustomersSimulator($this, $this->year, $this->options);
        $customersSimulator->simulate();
        $this->stats['customers'] = $this->options['customers'];

        // 2. Suppliers
        $suppliersSimulator = new SuppliersSimulator($this, $this->year, $this->options);
        $suppliersSimulator->simulate();
        $this->stats['suppliers'] = $this->options['suppliers'];

        // 3. Expense Categories
        $this->createExpenseCategories();
        $this->stats['expense_categories'] = 15;

        $this->newLine();
    }

    /**
     * Phase 3: شبیه‌سازی ماهانه
     */
    protected function phase3MonthlySimulation(): void
    {
        $this->info('📋 Phase 3: شبیه‌سازی ماهانه');
        $this->newLine();

        $totalInvoices = 0;
        $totalPurchases = 0;
        $totalExpenses = 0;
        $totalPayments = 0;
        $totalCheques = 0;
        $totalSalesAmount = 0;
        $totalPurchaseAmount = 0;

        // 12 ماه شمسی
        $persianMonths = [
            'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
            'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'
        ];

        for ($month = 1; $month <= 12; $month++) {
            $monthName = $persianMonths[$month - 1];
            $this->info("  📅 {$monthName} {$this->year}:");

            // Sales Simulator
            $salesSimulator = new SalesSimulator($this, $this->year, $this->options);
            $salesStats = $salesSimulator->simulateMonth($month);
            $totalInvoices += $salesStats['invoices'];
            $totalSalesAmount += $salesStats['amount'];

            // Purchases Simulator
            $purchasesSimulator = new PurchasesSimulator($this, $this->year, $this->options);
            $purchaseStats = $purchasesSimulator->simulateMonth($month);
            $totalPurchases += $purchaseStats['invoices'];
            $totalPurchaseAmount += $purchaseStats['amount'];

            // Expenses Simulator
            $expensesSimulator = new ExpensesSimulator($this, $this->year, $this->options);
            $expenseStats = $expensesSimulator->simulateMonth($month);
            $totalExpenses += $expenseStats['expenses'];

            // Payments Simulator
            $paymentsSimulator = new PaymentsSimulator($this, $this->year, $this->options);
            $paymentStats = $paymentsSimulator->simulateMonth($month);
            $totalPayments += $paymentStats['payments'];

            // Cheques Simulator
            $chequesSimulator = new ChequesSimulator($this, $this->year, $this->options);
            $chequeStats = $chequesSimulator->simulateMonth($month);
            $totalCheques += $chequeStats['cheques'];

            // Reconciliation Simulator
            $reconciliationSimulator = new ReconciliationSimulator($this, $this->year, $this->options);
            $reconciliationSimulator->simulateMonth($month);

            $this->newLine();
        }

        // Store final stats
        $this->stats['total_invoices'] = $totalInvoices;
        $this->stats['total_purchases'] = $totalPurchases;
        $this->stats['total_expenses'] = $totalExpenses;
        $this->stats['total_payments'] = $totalPayments;
        $this->stats['total_cheques'] = $totalCheques;
        $this->stats['total_sales_amount'] = $totalSalesAmount;
        $this->stats['total_purchase_amount'] = $totalPurchaseAmount;
        $this->stats['gross_profit'] = $totalSalesAmount - $totalPurchaseAmount;
    }

    /**
     * ایجاد سال مالی
     */
    protected function createFiscalYear(): void
    {
        DB::table('fiscal_years')->insert([
            'year_code' => $this->year,
            'start_date' => "{$this->year}-01-01",
            'end_date' => "{$this->year}-12-29",
            'status' => 'open',
            'is_current' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->info('  ✅ سال مالی ' . $this->year . ' ایجاد شد');
    }

    /**
     * ایجاد نرخ‌های مالیاتی
     */
    protected function createTaxRates(): void
    {
        DB::table('tax_rates')->insert([
            'code' => 'VAT',
            'name' => 'مالیات بر ارزش افزوده',
            'rate' => 9.0,
            'tax_type' => 'vat',
            'is_default' => true,
            'active' => true,
            'effective_from' => "{$this->year}-01-01",
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->info('  ✅ نرخ مالیات: VAT 9%');
    }

    /**
     * ایجاد دسته‌بندی هزینه‌ها
     */
    protected function createExpenseCategories(): void
    {
        $categories = [
            ['code' => 'SALARY', 'name' => 'حقوق و دستمزد'],
            ['code' => 'RENT', 'name' => 'اجاره'],
            ['code' => 'ELECTRICITY', 'name' => 'برق'],
            ['code' => 'WATER', 'name' => 'آب'],
            ['code' => 'GAS', 'name' => 'گاز'],
            ['code' => 'INTERNET', 'name' => 'تلفن و اینترنت'],
            ['code' => 'INSURANCE', 'name' => 'بیمه'],
            ['code' => 'TRANSPORT', 'name' => 'حمل و نقل'],
            ['code' => 'REPAIR', 'name' => 'تعمیرات'],
            ['code' => 'MARKETING', 'name' => 'تبلیغات'],
            ['code' => 'HOSPITALITY', 'name' => 'پذیرایی'],
            ['code' => 'OFFICE', 'name' => 'لوازم اداری'],
            ['code' => 'CLEANING', 'name' => 'نظافت'],
            ['code' => 'SECURITY', 'name' => 'نگهبانی'],
            ['code' => 'OTHER', 'name' => 'سایر هزینه‌ها'],
        ];

        foreach ($categories as $category) {
            DB::table('expense_categories')->insert([
                'code' => $category['code'],
                'name' => $category['name'],
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->info('  ✅ دسته‌بندی هزینه‌ها: 15 دسته');
    }

    /**
     * نمایش خلاصه نهایی
     */
    protected function displayFinalSummary(): void
    {
        $duration = now()->diffInSeconds($this->startTime);
        $minutes = floor($duration / 60);
        $seconds = $duration % 60;

        $this->newLine();
        $this->info('📊 خلاصه آمار:');
        $this->info('  - مدت زمان: 1 سال (365 روز)');
        $this->info('  - مشتریان: ' . number_format($this->stats['customers']));
        $this->info('  - تامین‌کنندگان: ' . number_format($this->stats['suppliers']));
        $this->info('  - فاکتورهای فروش: ' . number_format($this->stats['total_invoices'] ?? 0));
        $this->info('  - فاکتورهای خرید: ' . number_format($this->stats['total_purchases'] ?? 0));
        $this->info('  - هزینه‌ها: ' . number_format($this->stats['total_expenses'] ?? 0) . ' مورد');
        $this->info('  - دریافت‌ها: ' . number_format($this->stats['total_payments'] ?? 0));
        $this->info('  - چک‌ها: ' . number_format($this->stats['total_cheques'] ?? 0));
        
        if (isset($this->stats['total_sales_amount'])) {
            $this->info('  - کل فروش: ' . $this->formatBillion($this->stats['total_sales_amount']) . ' تومان');
            $this->info('  - کل خرید: ' . $this->formatBillion($this->stats['total_purchase_amount']) . ' تومان');
            $this->info('  - سود ناخالص: ' . $this->formatBillion($this->stats['gross_profit']) . ' تومان');
        }
        
        $this->newLine();
        $this->info("⏱️  زمان اجرا: {$minutes} دقیقه و {$seconds} ثانیه");
    }

    /**
     * فرمت کردن به میلیارد
     */
    protected function formatBillion($amount): string
    {
        return number_format($amount / 1_000_000_000, 2) . 'B';
    }
}
