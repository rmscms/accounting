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
use RMS\Accounting\Console\Simulators\SupplierPaymentsSimulator;
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

        // Disable FK constraints temporarily (for SQLite)
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        }
        
        $tables = [
            'payment_reconciliations',
            'settlements',
            'cheques',
            'wallet_transactions',
            'wallets',
            'customer_payments',
            'customer_invoices',
            'customer_balances',
            'customers', // Add this
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
                DB::table($table)->delete();
                // Reset auto increment for SQLite
                if (DB::getDriverName() === 'sqlite') {
                    DB::statement("DELETE FROM sqlite_sequence WHERE name = '{$table}'");
                }
            } catch (\Exception $e) {
                // Table might not exist yet
            }
        }
        
        // Re-enable FK constraints (for SQLite)
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON');
        }

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
        $totalSupplierPayments = 0;
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

            // Payments Simulator (دریافت از مشتریان)
            $paymentsSimulator = new PaymentsSimulator($this, $this->year, $this->options);
            $paymentStats = $paymentsSimulator->simulateMonth($month);
            $totalPayments += $paymentStats['payments'];

            // Supplier Payments Simulator (پرداخت به تامین‌کنندگان)
            $supplierPaymentsSimulator = new SupplierPaymentsSimulator($this, $this->year, $this->options);
            $supplierPaymentStats = $supplierPaymentsSimulator->simulateMonth($month);
            $totalSupplierPayments += $supplierPaymentStats['payments'];

            // Cheques Simulator
            $chequesSimulator = new ChequesSimulator($this, $this->year, $this->options);
            $chequeStats = $chequesSimulator->simulateMonth($month);
            $totalCheques += $chequeStats['cheques'];

            // Reconciliation Simulator
            $reconciliationSimulator = new ReconciliationSimulator($this, $this->year, $this->options);
            $reconciliationSimulator->simulateMonth($month);

            $this->newLine();
        }

        // به‌روزرسانی موجودی مشتریان و تامین‌کنندگان
        $this->updateBalances();

        // به‌روزرسانی دفتر کل (financial_ledgers)
        $this->updateFinancialLedger();

        // Store final stats
        $this->stats['total_invoices'] = $totalInvoices;
        $this->stats['total_purchases'] = $totalPurchases;
        $this->stats['total_expenses'] = $totalExpenses;
        $this->stats['total_payments'] = $totalPayments;
        $this->stats['total_supplier_payments'] = $totalSupplierPayments;
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
        $existingYear = DB::table('fiscal_years')
            ->where('year_code', $this->year)
            ->first();

        if ($existingYear) {
            // Update existing fiscal year
            DB::table('fiscal_years')
                ->where('year_code', $this->year)
                ->update([
                    'start_date' => "{$this->year}-01-01",
                    'end_date' => "{$this->year}-12-29",
                    'status' => 'open',
                    'is_current' => true,
                    'updated_at' => now(),
                ]);
            
            $this->info('  ✅ سال مالی ' . $this->year . ' بروزرسانی شد');
        } else {
            // Create new fiscal year
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

        // Set other fiscal years as non-current
        DB::table('fiscal_years')
            ->where('year_code', '!=', $this->year)
            ->update(['is_current' => false]);
    }

    /**
     * ایجاد نرخ‌های مالیاتی
     */
    protected function createTaxRates(): void
    {
        // چک کردن وجود نرخ مالیات
        $exists = DB::table('tax_rates')->where('code', 'VAT')->exists();
        
        if (!$exists) {
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
        } else {
            $this->info('  ✅ نرخ مالیات: VAT از قبل موجود');
        }
    }

    /**
     * به‌روزرسانی موجودی مشتریان و تامین‌کنندگان
     */
    protected function updateBalances(): void
    {
        $this->info('📊 در حال به‌روزرسانی موجودی‌ها...');
        
        // به‌روزرسانی موجودی مشتریان
        $customers = DB::table('customers')->pluck('id');
        $updatedCustomers = 0;
        
        foreach ($customers as $customerId) {
            // محاسبه کل فاکتورها
            $totalInvoices = DB::table('customer_invoices')
                ->where('customer_id', $customerId)
                ->where('status', '!=', 'cancelled')
                ->sum('total_amount');
            
            // محاسبه کل پرداخت‌ها
            $totalPayments = DB::table('customer_payments')
                ->where('customer_id', $customerId)
                ->where('status', 'completed')
                ->sum('amount');
            
            // محاسبه مانده
            $balance = $totalInvoices - $totalPayments;
            
            if ($totalInvoices > 0 || $totalPayments > 0) {
                // آخرین تاریخ فاکتور
                $lastInvoiceAt = DB::table('customer_invoices')
                    ->where('customer_id', $customerId)
                    ->max('invoice_date');
                
                // آخرین تاریخ پرداخت
                $lastPaymentAt = DB::table('customer_payments')
                    ->where('customer_id', $customerId)
                    ->max('payment_date');
                
                DB::table('customer_balances')->updateOrInsert(
                    ['customer_id' => $customerId, 'store_id' => 1],
                    [
                        'balance_irr' => $balance,
                        'total_invoices' => $totalInvoices,
                        'total_payments' => $totalPayments,
                        'last_invoice_at' => $lastInvoiceAt,
                        'last_payment_at' => $lastPaymentAt,
                        'last_transaction_at' => max($lastInvoiceAt, $lastPaymentAt),
                        'updated_at' => now(),
                    ]
                );
                $updatedCustomers++;
            }
        }
        
        $this->info("  ✅ موجودی {$updatedCustomers} مشتری به‌روز شد");
        
        // به‌روزرسانی موجودی بانک‌ها و صندوق‌ها
        $this->updateTreasuryBalances();
    }
    
    /**
     * به‌روزرسانی موجودی خزانه‌داری (بانک، صندوق، POS)
     */
    protected function updateTreasuryBalances(): void
    {
        // به‌روزرسانی موجودی بانک‌ها
        $banks = DB::table('banks')->get();
        foreach ($banks as $bank) {
            // محاسبه دریافت‌ها از این بانک
            $deposits = DB::table('customer_payments')
                ->where('bank_id', $bank->id)
                ->where('status', 'completed')
                ->sum('amount');
            
            // محاسبه پرداخت‌ها از این بانک
            $withdrawals = DB::table('supplier_payments')
                ->where('bank_id', $bank->id)
                ->where('status', 'completed')
                ->sum('amount');
            
            $balance = $deposits - $withdrawals;
            
            DB::table('banks')
                ->where('id', $bank->id)
                ->update([
                    'balance' => $balance,
                    'updated_at' => now(),
                ]);
        }
        
        // به‌روزرسانی موجودی صندوق‌ها
        $cashBoxes = DB::table('cash_boxes')->get();
        foreach ($cashBoxes as $cashBox) {
            // محاسبه دریافت‌ها نقدی
            $deposits = DB::table('customer_payments')
                ->where('cash_box_id', $cashBox->id)
                ->where('status', 'completed')
                ->sum('amount');
            
            // محاسبه پرداخت‌های نقدی (از supplier_payments)
            $withdrawals = DB::table('supplier_payments')
                ->where('cash_box_id', $cashBox->id)
                ->where('status', 'completed')
                ->sum('amount');
            
            $balance = $deposits - $withdrawals;
            
            DB::table('cash_boxes')
                ->where('id', $cashBox->id)
                ->update([
                    'balance' => $balance,
                    'updated_at' => now(),
                ]);
        }
        
        $this->info("  ✅ موجودی {$banks->count()} بانک و {$cashBoxes->count()} صندوق به‌روز شد");
    }

    /**
     * به‌روزرسانی دفتر کل (financial_ledgers)
     */
    protected function updateFinancialLedger(): void
    {
        $this->info('💼 در حال به‌روزرسانی دفتر کل...');
        
        $fiscalYear = DB::table('fiscal_years')->where('year_code', $this->year)->first();
        
        if (!$fiscalYear) {
            $this->warn('  ⚠️  سال مالی یافت نشد!');
            return;
        }
        
        // 1. پیدا کردن حساب‌ها
        $cashAccount = DB::table('accounts')->where('code', '1-1-1')->first(); // صندوق
        $bankAccount = DB::table('accounts')->where('code', '1-1-2')->first(); // بانک
        $receivableAccount = DB::table('accounts')->where('code', '1-2-1')->first(); // مطالبات
        $payableAccount = DB::table('accounts')->where('code', '2-1-1')->first(); // بدهی‌ها
        
        // 2. محاسبه موجودی‌ها
        $totalCash = DB::table('cash_boxes')->sum('balance');
        $totalBank = DB::table('banks')->sum('balance');
        $totalReceivable = DB::table('customer_balances')->where('balance_irr', '>', 0)->sum('balance_irr');
        $totalPayable = DB::table('supplier_invoices')
            ->where('payment_status', '!=', 'paid')
            ->sum('balance_due');
        
        // 3. ایجاد سند حسابداری خلاصه (یا گرفتن سند موجود)
        $existingDocument = DB::table('accounting_documents')
            ->where('document_number', 'SIM-' . $this->year . '-OPENING')
            ->first();
        
        if ($existingDocument) {
            $documentId = $existingDocument->id;
            $this->info('  ℹ️  سند حسابداری موجود استفاده شد');
        } else {
            $documentId = DB::table('accounting_documents')->insertGetId([
                'document_number' => 'SIM-' . $this->year . '-OPENING',
                'document_type' => 'OPENING',
                'fiscal_year_id' => $fiscalYear->id,
                'reference_type' => 'manual',
                'description' => 'موجودی اولیه از شبیه‌سازی سال ' . $this->year,
                'total_debit' => $totalCash + $totalBank + $totalReceivable,
                'total_credit' => $totalPayable,
                'status' => 'posted',
                'posted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        // 4. ثبت در دفتر کل
        $entries = [];
        
        // صندوق
        if ($cashAccount && $totalCash != 0) {
            $entries[] = [
                'event_type' => 'ADJUSTMENT',
                'event_source' => 'system',
                'store_id' => 0,
                'account_id' => $cashAccount->id,
                'currency_code' => 'IRT',
                'debit_amount' => $totalCash > 0 ? $totalCash : 0,
                'credit_amount' => $totalCash < 0 ? abs($totalCash) : 0,
                'fx_rate_to_base' => 1,
                'amount_base' => $totalCash,
                'accounting_document_id' => $documentId,
                'description' => 'موجودی اولیه صندوق',
                'created_at' => now(),
            ];
        }
        
        // بانک
        if ($bankAccount && $totalBank != 0) {
            $entries[] = [
                'event_type' => 'ADJUSTMENT',
                'event_source' => 'system',
                'store_id' => 0,
                'account_id' => $bankAccount->id,
                'currency_code' => 'IRT',
                'debit_amount' => $totalBank > 0 ? $totalBank : 0,
                'credit_amount' => $totalBank < 0 ? abs($totalBank) : 0,
                'fx_rate_to_base' => 1,
                'amount_base' => $totalBank,
                'accounting_document_id' => $documentId,
                'description' => 'موجودی اولیه بانک',
                'created_at' => now(),
            ];
        }
        
        // مطالبات (بدهکار)
        if ($receivableAccount && $totalReceivable > 0) {
            $entries[] = [
                'event_type' => 'ADJUSTMENT',
                'event_source' => 'system',
                'store_id' => 0,
                'account_id' => $receivableAccount->id,
                'currency_code' => 'IRT',
                'debit_amount' => $totalReceivable,
                'credit_amount' => 0,
                'fx_rate_to_base' => 1,
                'amount_base' => $totalReceivable,
                'accounting_document_id' => $documentId,
                'description' => 'مطالبات از مشتریان',
                'created_at' => now(),
            ];
        }
        
        // بدهی‌ها (بستانکار)
        if ($payableAccount && $totalPayable > 0) {
            $entries[] = [
                'event_type' => 'ADJUSTMENT',
                'event_source' => 'system',
                'store_id' => 0,
                'account_id' => $payableAccount->id,
                'currency_code' => 'IRT',
                'debit_amount' => 0,
                'credit_amount' => $totalPayable,
                'fx_rate_to_base' => 1,
                'amount_base' => -$totalPayable,
                'accounting_document_id' => $documentId,
                'description' => 'بدهی به تامین‌کنندگان',
                'created_at' => now(),
            ];
        }
        
        // Bulk insert
        if (!empty($entries)) {
            // حذف رکوردهای قبلی اگر موجود باشد
            DB::table('financial_ledgers')
                ->where('accounting_document_id', $documentId)
                ->delete();
            
            DB::table('financial_ledgers')->insert($entries);
        }
        
        $totalCashB = number_format($totalCash / 1000000000, 2);
        $totalBankB = number_format($totalBank / 1000000000, 2);
        $totalReceivableB = number_format($totalReceivable / 1000000000, 2);
        $totalPayableB = number_format($totalPayable / 1000000000, 2);
        
        $this->info("  ✅ دفتر کل: {$totalCashB}B صندوق + {$totalBankB}B بانک + {$totalReceivableB}B مطالبات - {$totalPayableB}B بدهی");
    }

    /**
     * ایجاد دسته‌بندی هزینه‌ها
     */
    protected function createExpenseCategories(): void
    {
        // چک کردن دسته‌بندی‌های موجود
        $existingCount = DB::table('expense_categories')->count();
        if ($existingCount >= 15) {
            $this->info('  ✅ دسته‌بندی هزینه‌ها: از قبل موجود');
            return;
        }
        
        // Get operational expenses account ID
        $operationalExpensesAccount = DB::table('accounts')
            ->where('code', '5-2')
            ->first();
            
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
            // چک کنیم این دسته‌بندی وجود داره یا نه
            $exists = DB::table('expense_categories')->where('code', $category['code'])->exists();
            if (!$exists) {
                DB::table('expense_categories')->insert([
                    'code' => $category['code'],
                    'name' => $category['name'],
                    'account_id' => $operationalExpensesAccount->id,
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
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
