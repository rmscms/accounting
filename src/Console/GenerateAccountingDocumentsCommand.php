<?php

namespace RMS\Accounting\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RMS\Accounting\Models\Account;
use RMS\Accounting\Models\AccountingDocument;
use RMS\Accounting\Models\FinancialLedger;
use RMS\Accounting\Models\FiscalYear;

/**
 * کامند ایجاد اسناد حسابداری از روی تراکنش‌های موجود
 */
class GenerateAccountingDocumentsCommand extends Command
{
    protected $signature = 'accounting:generate-documents 
                          {--year=1403 : سال مالی}
                          {--type= : نوع سند (sales|PURCHASEs|EXPENSEs|payments|all)}';

    protected $description = 'ایجاد اسناد حسابداری از روی فاکتورها و تراکنش‌های موجود';

    private array $accounts = [];
    private ?FiscalYear $fiscalYear = null;

    public function handle(): int
    {
        $year = $this->option('year');
        $type = $this->option('type') ?: 'all';

        $this->info("🚀 شروع ایجاد اسناد حسابداری برای سال {$year}...\n");

        // دریافت سال مالی
        $this->fiscalYear = FiscalYear::where('year_code', $year)->first();
        if (!$this->fiscalYear) {
            $this->error("❌ سال مالی {$year} یافت نشد!");
            return 1;
        }

        // Cache کردن حساب‌ها
        $this->cacheAccounts();

        // ایجاد اسناد بر اساس نوع
        if ($type === 'all' || $type === 'sales') {
            $this->generateSalesDocuments();
        }

        if ($type === 'all' || $type === 'purchases') {
            $this->generatePurchasesDocuments();
        }

        if ($type === 'all' || $type === 'expenses') {
            $this->generateExpensesDocuments();
        }

        if ($type === 'all' || $type === 'payments') {
            $this->generatePaymentsDocuments();
        }

        $this->info("\n✅ ایجاد اسناد حسابداری کامل شد!");

        return 0;
    }

    /**
     * Cache کردن حساب‌های مورد نیاز
     */
    private function cacheAccounts(): void
    {
        $codes = [
            '1-1-1', // نقد (صندوق)
            '1-1-2', // بانک
            '1-2-1', // مطالبات (حساب مشتریان)
            '2-1-1', // بدهی‌ها (حساب تامین‌کنندگان)
            '3-1-1', // سرمایه
            '4-1-1', // فروش
            '5-1',   // بهای تمام شده
            '5-2',   // هزینه‌های عملیاتی
        ];

        foreach ($codes as $code) {
            $account = Account::where('code', $code)->first();
            if ($account) {
                $this->accounts[$code] = $account;
            }
        }
    }

    /**
     * ایجاد اسناد فروش
     */
    private function generateSalesDocuments(): void
    {
        $this->info("📝 ایجاد اسناد فروش...");

        $invoices = DB::table('customer_invoices')
            ->whereBetween('invoice_date', [$this->fiscalYear->start_date, $this->fiscalYear->end_date])
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('financial_ledgers')
                    ->whereColumn('customer_invoices.id', 'financial_ledgers.source_reference_id')
                    ->where('financial_ledgers.source_reference_type', 'customer_invoice');
            })
            ->orderBy('invoice_date')
            ->get();

        $progressBar = $this->output->createProgressBar($invoices->count());
        $progressBar->start();

        foreach ($invoices as $invoice) {
            DB::transaction(function () use ($invoice) {
                // ایجاد سند حسابداری
                $document = AccountingDocument::create([
                    'document_number' => 'DOC-S-' . $invoice->invoice_number,
                    'document_type' => 'SALE',
                    'store_id' => $invoice->store_id,
                    'fiscal_year_id' => $this->fiscalYear->id,
                    'reference_type' => 'event',
                    'reference_id' => $invoice->id,
                    'description' => "فاکتور فروش {$invoice->invoice_number}",
                    'total_debit' => $invoice->total_amount,
                    'total_credit' => $invoice->total_amount,
                    'status' => 'posted',
                    'created_at' => $invoice->created_at,
                    'updated_at' => $invoice->updated_at,
                ]);

                // بدهکار: مطالبات (یا نقد/بانک اگر پرداخت شده)
                if ($invoice->payment_status === 'paid') {
                    // اگر پرداخت شده، 50% نقد و 50% بانک
                    $useBank = (rand(0, 1) === 1);
                    $accountId = $useBank ? 
                        ($this->accounts['1-1-2']->id ?? null) : // بانک
                        ($this->accounts['1-1-1']->id ?? null);  // نقد
                    
                    FinancialLedger::create([
                        'event_type' => 'SALE',
                        'event_source' => 'sales',
                        'source_reference_type' => 'customer_invoice',
                        'source_reference_id' => $invoice->id,
                        'store_id' => $invoice->store_id ?? 0,
                        'account_id' => $accountId,
                        'currency_code' => $invoice->currency_code,
                        'debit_amount' => $invoice->total_amount,
                        'credit_amount' => 0,
                        'fx_rate_to_base' => $invoice->fx_rate,
                        'amount_base' => $invoice->amount_base,
                        'accounting_document_id' => $document->id,
                        'description' => ($useBank ? "دریافت بانکی" : "دریافت نقدی") . " فاکتور {$invoice->invoice_number}",
                        'created_at' => $invoice->created_at,
                    ]);
                } else {
                    // اگر پرداخت نشده، حساب مطالبات بدهکار
                    FinancialLedger::create([
                        'event_type' => 'SALE',
                        'event_source' => 'sales',
                        'source_reference_type' => 'customer_invoice',
                        'source_reference_id' => $invoice->id,
                        'store_id' => $invoice->store_id ?? 0,
                        'account_id' => $this->accounts['1-2-1']->id ?? null, // مطالبات
                        'currency_code' => $invoice->currency_code,
                        'debit_amount' => $invoice->total_amount,
                        'credit_amount' => 0,
                        'fx_rate_to_base' => $invoice->fx_rate,
                        'amount_base' => $invoice->amount_base,
                        'accounting_document_id' => $document->id,
                        'description' => "مطالبات فاکتور {$invoice->invoice_number}",
                        'created_at' => $invoice->created_at,
                    ]);
                }

                // بستانکار: فروش
                FinancialLedger::create([
                    'event_type' => 'SALE',
                    'event_source' => 'sales',
                    'source_reference_type' => 'customer_invoice',
                    'source_reference_id' => $invoice->id,
                    'store_id' => $invoice->store_id ?? 0,
                    'account_id' => $this->accounts['4-1-1']->id ?? null, // فروش
                    'currency_code' => $invoice->currency_code,
                    'debit_amount' => 0,
                    'credit_amount' => $invoice->total_amount,
                    'fx_rate_to_base' => $invoice->fx_rate,
                    'amount_base' => -$invoice->amount_base,
                    'accounting_document_id' => $document->id,
                    'description' => "فروش فاکتور {$invoice->invoice_number}",
                    'created_at' => $invoice->created_at,
                ]);
            });

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->info("\n  ✅ {$invoices->count()} سند فروش ایجاد شد");
    }

    /**
     * ایجاد اسناد خرید
     */
    private function generatePurchasesDocuments(): void
    {
        $this->info("\n📝 ایجاد اسناد خرید...");

        $invoices = DB::table('supplier_invoices')
            ->whereBetween('invoice_date', [$this->fiscalYear->start_date, $this->fiscalYear->end_date])
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('financial_ledgers')
                    ->whereColumn('supplier_invoices.id', 'financial_ledgers.source_reference_id')
                    ->where('financial_ledgers.source_reference_type', 'supplier_invoice');
            })
            ->orderBy('invoice_date')
            ->get();

        $progressBar = $this->output->createProgressBar($invoices->count());
        $progressBar->start();

        foreach ($invoices as $invoice) {
            DB::transaction(function () use ($invoice) {
                // ایجاد سند حسابداری
                $document = AccountingDocument::create([
                    'document_number' => 'DOC-P-' . $invoice->invoice_number,
                    'document_type' => 'PURCHASE',
                    'store_id' => $invoice->store_id,
                    'fiscal_year_id' => $this->fiscalYear->id,
                    'reference_type' => 'event',
                    'reference_id' => $invoice->id,
                    'description' => "فاکتور خرید {$invoice->invoice_number}",
                    'total_debit' => $invoice->total_amount,
                    'total_credit' => $invoice->total_amount,
                    'status' => 'posted',
                    'created_at' => $invoice->created_at,
                    'updated_at' => $invoice->updated_at,
                ]);

                // بدهکار: بهای تمام شده (خرید کالا)
                FinancialLedger::create([
                    'event_type' => 'PURCHASE',
                    'event_source' => 'sales',
                    'source_reference_type' => 'supplier_invoice',
                    'source_reference_id' => $invoice->id,
                    'store_id' => $invoice->store_id ?? 0,
                        'account_id' => $this->accounts['5-1']->id ?? null, // بهای تمام شده
                    'currency_code' => $invoice->currency_code,
                    'debit_amount' => $invoice->total_amount,
                    'credit_amount' => 0,
                    'fx_rate_to_base' => $invoice->fx_rate_at_invoice,
                    'amount_base' => $invoice->amount_base_at_invoice,
                    'accounting_document_id' => $document->id,
                    'description' => "خرید کالا فاکتور {$invoice->invoice_number}",
                    'created_at' => $invoice->created_at,
                ]);

                // بستانکار: بدهی‌ها (یا نقد/بانک اگر پرداخت شده)
                if ($invoice->payment_status === 'paid') {
                    // اگر پرداخت شده، 50% نقد و 50% بانک
                    $useBank = (rand(0, 1) === 1);
                    $accountId = $useBank ? 
                        ($this->accounts['1-1-2']->id ?? null) : // بانک
                        ($this->accounts['1-1-1']->id ?? null);  // نقد
                    
                    FinancialLedger::create([
                        'event_type' => 'PURCHASE',
                        'event_source' => 'sales',
                        'source_reference_type' => 'supplier_invoice',
                        'source_reference_id' => $invoice->id,
                        'store_id' => $invoice->store_id ?? 0,
                        'account_id' => $accountId,
                        'currency_code' => $invoice->currency_code,
                        'debit_amount' => 0,
                        'credit_amount' => $invoice->total_amount,
                        'fx_rate_to_base' => $invoice->fx_rate_at_invoice,
                        'amount_base' => -$invoice->amount_base_at_invoice,
                        'accounting_document_id' => $document->id,
                        'description' => ($useBank ? "پرداخت بانکی" : "پرداخت نقدی") . " فاکتور {$invoice->invoice_number}",
                        'created_at' => $invoice->created_at,
                    ]);
                } else {
                    // اگر پرداخت نشده، حساب بدهی‌ها بستانکار
                    FinancialLedger::create([
                        'event_type' => 'PURCHASE',
                        'event_source' => 'sales',
                        'source_reference_type' => 'supplier_invoice',
                        'source_reference_id' => $invoice->id,
                        'store_id' => $invoice->store_id ?? 0,
                        'account_id' => $this->accounts['2-1-1']->id ?? null, // بدهی‌ها
                        'currency_code' => $invoice->currency_code,
                        'debit_amount' => 0,
                        'credit_amount' => $invoice->total_amount,
                        'fx_rate_to_base' => $invoice->fx_rate_at_invoice,
                        'amount_base' => -$invoice->amount_base_at_invoice,
                        'accounting_document_id' => $document->id,
                        'description' => "بدهی فاکتور {$invoice->invoice_number}",
                        'created_at' => $invoice->created_at,
                    ]);
                }
            });

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->info("\n  ✅ {$invoices->count()} سند خرید ایجاد شد");
    }

    /**
     * ایجاد اسناد هزینه
     */
    private function generateExpensesDocuments(): void
    {
        $this->info("\n📝 ایجاد اسناد هزینه...");

        $expenses = DB::table('expenses')
            ->whereBetween('expense_date', [$this->fiscalYear->start_date, $this->fiscalYear->end_date])
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('financial_ledgers')
                    ->whereColumn('expenses.id', 'financial_ledgers.source_reference_id')
                    ->where('financial_ledgers.source_reference_type', 'expense');
            })
            ->orderBy('expense_date')
            ->get();

        $progressBar = $this->output->createProgressBar($expenses->count());
        $progressBar->start();

        foreach ($expenses as $expense) {
            DB::transaction(function () use ($expense) {
                // ایجاد سند حسابداری
                $document = AccountingDocument::create([
                    'document_number' => 'DOC-E-' . $expense->expense_number,
                    'document_type' => 'EXPENSE',
                    'store_id' => null,
                    'fiscal_year_id' => $this->fiscalYear->id,
                    'reference_type' => 'event',
                    'reference_id' => $expense->id,
                    'description' => $expense->description,
                    'total_debit' => $expense->amount,
                    'total_credit' => $expense->amount,
                    'status' => 'posted',
                    'created_at' => $expense->created_at,
                    'updated_at' => $expense->updated_at,
                ]);

                // بدهکار: هزینه‌های عملیاتی
                FinancialLedger::create([
                    'event_type' => 'EXPENSE',
                    'event_source' => 'manual',
                    'source_reference_type' => 'expense',
                    'source_reference_id' => $expense->id,
                    'store_id' => 0,
                    'account_id' => $this->accounts['5-2']->id ?? null, // هزینه‌های عملیاتی
                    'currency_code' => $expense->currency_code,
                    'debit_amount' => $expense->amount,
                    'credit_amount' => 0,
                    'fx_rate_to_base' => $expense->fx_rate,
                    'amount_base' => $expense->amount_base,
                    'accounting_document_id' => $document->id,
                    'description' => $expense->description,
                    'created_at' => $expense->created_at,
                ]);

                // بستانکار: نقد یا بانک (70% بانک، 30% نقد)
                $useBank = (rand(0, 10) > 3); // 70% احتمال بانک
                $accountId = $useBank ? 
                    ($this->accounts['1-1-2']->id ?? null) : // بانک
                    ($this->accounts['1-1-1']->id ?? null);  // نقد
                
                FinancialLedger::create([
                    'event_type' => 'EXPENSE',
                    'event_source' => 'manual',
                    'source_reference_type' => 'expense',
                    'source_reference_id' => $expense->id,
                    'store_id' => 0,
                    'account_id' => $accountId,
                    'currency_code' => $expense->currency_code,
                    'debit_amount' => 0,
                    'credit_amount' => $expense->amount,
                    'fx_rate_to_base' => $expense->fx_rate,
                    'amount_base' => -$expense->amount_base,
                    'accounting_document_id' => $document->id,
                    'description' => ($useBank ? "پرداخت بانکی" : "پرداخت نقدی") . " {$expense->description}",
                    'created_at' => $expense->created_at,
                ]);
            });

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->info("\n  ✅ {$expenses->count()} سند هزینه ایجاد شد");
    }

    /**
     * ایجاد اسناد دریافت پول از مشتریان
     */
    private function generatePaymentsDocuments(): void
    {
        $this->info("\n📝 ایجاد اسناد دریافت...");

        $payments = DB::table('customer_payments')
            ->whereBetween('payment_date', [$this->fiscalYear->start_date, $this->fiscalYear->end_date])
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('financial_ledgers')
                    ->whereColumn('customer_payments.id', 'financial_ledgers.source_reference_id')
                    ->where('financial_ledgers.source_reference_type', 'customer_payment');
            })
            ->orderBy('payment_date')
            ->get();

        $progressBar = $this->output->createProgressBar($payments->count());
        $progressBar->start();

        foreach ($payments as $payment) {
            // فقط پرداخت‌هایی که فاکتورشون unpaid بوده رو پردازش کن
            $invoice = DB::table('customer_invoices')->find($payment->customer_invoice_id);
            if (!$invoice) {
                $progressBar->advance();
                continue;
            }

            // اگر فاکتور قبلاً paid بوده، پس قبلاً سند ساخته شده
            if ($invoice->payment_status === 'paid') {
                $progressBar->advance();
                continue;
            }

            DB::transaction(function () use ($payment, $invoice) {
                // ایجاد سند حسابداری
                $document = AccountingDocument::create([
                    'document_number' => 'DOC-PAY-' . $payment->payment_number,
                    'document_type' => 'RECEIPT',
                    'store_id' => $payment->store_id,
                    'fiscal_year_id' => $this->fiscalYear->id,
                    'reference_type' => 'event',
                    'reference_id' => $payment->id,
                    'description' => "دریافت بابت فاکتور {$invoice->invoice_number}",
                    'total_debit' => $payment->amount,
                    'total_credit' => $payment->amount,
                    'status' => 'posted',
                    'created_at' => $payment->created_at,
                    'updated_at' => $payment->updated_at,
                ]);

                // بدهکار: نقد/بانک
                FinancialLedger::create([
                    'event_type' => 'RECEIPT',
                    'event_source' => 'sales',
                    'source_reference_type' => 'customer_payment',
                    'source_reference_id' => $payment->id,
                    'store_id' => $payment->store_id ?? 0,
                    'account_id' => $this->accounts['1-1-1']->id ?? null, // نقد
                    'currency_code' => $payment->currency_code,
                    'debit_amount' => $payment->amount,
                    'credit_amount' => 0,
                    'fx_rate_to_base' => $payment->fx_rate,
                    'amount_base' => $payment->amount_base,
                    'accounting_document_id' => $document->id,
                    'description' => "دریافت بابت فاکتور {$invoice->invoice_number}",
                    'created_at' => $payment->created_at,
                ]);

                // بستانکار: مطالبات
                FinancialLedger::create([
                    'event_type' => 'RECEIPT',
                    'event_source' => 'sales',
                    'source_reference_type' => 'customer_payment',
                    'source_reference_id' => $payment->id,
                    'store_id' => $payment->store_id ?? 0,
                    'account_id' => $this->accounts['1-2-1']->id ?? null, // مطالبات
                    'currency_code' => $payment->currency_code,
                    'debit_amount' => 0,
                    'credit_amount' => $payment->amount,
                    'fx_rate_to_base' => $payment->fx_rate,
                    'amount_base' => -$payment->amount_base,
                    'accounting_document_id' => $document->id,
                    'description' => "کاهش مطالبات فاکتور {$invoice->invoice_number}",
                    'created_at' => $payment->created_at,
                ]);
            });

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->info("\n  ✅ {$payments->count()} سند دریافت ایجاد شد");
    }
}
