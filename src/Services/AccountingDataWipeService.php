<?php

namespace RMS\Accounting\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RMS\Accounting\Services\AccountingWipe\WipeMode;
use RMS\Accounting\Services\AccountingWipe\WipeOptions;
use RMS\Accounting\Services\AccountingWipe\WipeResult;
use RMS\Core\Models\Setting;

/**
 * پاک‌سازی متمرکز اسناد حسابداری و وابستگی‌ها (خطرناک).
 */
class AccountingDataWipeService
{
    /**
     * @return array<string, array<int, string>>
     */
    private function preserveDocumentLinkColumns(): array
    {
        return [
            'customer_payments' => ['document_id'],
            'supplier_payments' => ['document_id'],
            'supplier_invoices' => ['document_id'],
            'expenses' => ['document_id'],
            'settlements' => ['document_id'],
            'customer_advances' => ['accounting_document_id'],
            'supplier_advances' => ['accounting_document_id'],
            'customer_refunds' => ['accounting_document_id'],
            'supplier_refunds' => ['accounting_document_id'],
            'credit_notes' => ['accounting_document_id'],
            'debit_notes' => ['accounting_document_id'],
            'bank_transactions' => ['accounting_document_id'],
            'bank_transfers' => ['accounting_document_id'],
            'manual_journals' => ['accounting_document_id'],
            'inventory_adjustments' => ['accounting_document_id'],
            'bad_debt_provisions' => ['accounting_document_id'],
            'bad_debt_writeoffs' => ['accounting_document_id'],
            'accruals' => ['accounting_document_id', 'reversal_document_id'],
            'depreciation_schedules' => ['accounting_document_id'],
            'depreciation_entries' => ['accounting_document_id'],
        ];
    }

    /**
     * ترتیب حذف دادهٔ عملیاتی در حالت accounting-reset (ابتدا وابسته‌ها).
     *
     * @return list<string>
     */
    private function operationalResetTablesInDeleteOrder(): array
    {
        return [
            'expense_status_histories',
            'accounting_attachments',
            'advance_applications',
            'vat_declarations',
            'vat_remittances',
            'credit_note_items',
            'credit_notes',
            'debit_note_items',
            'debit_notes',
            'customer_refunds',
            'supplier_refunds',
            'bad_debt_writeoffs',
            'bad_debt_provisions',
            'accruals',
            'inventory_adjustment_items',
            'inventory_adjustments',
            'depreciation_entries',
            'depreciation_schedules',
            'fixed_assets',
            'fixed_asset_categories',
            'bank_transactions',
            'bank_transfers',
            'shareholder_capital_contributions',
            'shareholder_withdrawals',
            'payment_reconciliations',
            'settlements',
            'customer_payments',
            'supplier_payments',
            'cheques',
            'wallet_transactions',
            'wallets',
            'expense_items',
            'expenses',
            'supplier_invoice_items',
            'supplier_invoices',
            'purchase_order_items',
            'purchase_orders',
            'customer_invoices',
            'customer_balances',
        ];
    }

    /**
     * پاک‌سازی کامل همه جداول حسابداری (به‌جز migrations).
     *
     * @return list<string>
     */
    private function allAccountingTablesInDeleteOrder(): array
    {
        return [
            'expense_status_histories',
            'accounting_attachments',
            'advance_applications',
            'vat_declarations',
            'vat_remittances',
            'credit_note_items',
            'credit_notes',
            'debit_note_items',
            'debit_notes',
            'customer_refunds',
            'supplier_refunds',
            'bad_debt_writeoffs',
            'bad_debt_provisions',
            'accruals',
            'inventory_adjustment_items',
            'inventory_adjustments',
            'depreciation_entries',
            'depreciation_schedules',
            'fixed_assets',
            'fixed_asset_categories',
            'bank_transactions',
            'bank_transfers',
            'shareholder_capital_contributions',
            'shareholder_withdrawals',
            'shareholders',
            'payment_reconciliations',
            'settlements',
            'customer_payments',
            'supplier_payments',
            'cheques',
            'chequebooks',
            'wallet_transactions',
            'wallets',
            'expense_items',
            'expenses',
            'supplier_invoice_items',
            'supplier_invoices',
            'purchase_order_items',
            'purchase_orders',
            'customer_invoice_items',
            'customer_invoices',
            'customer_balances',
            'manual_journal_lines',
            'manual_journals',
            'financial_ledgers',
            'cost_entries',
            'accounting_documents',
            'fiscal_years',
            'tax_rates',
            'payment_methods',
            'cash_boxes',
            'banks',
            'suppliers',
            'customers',
            'parties',
            'accounts',
            'currencies',
        ];
    }

    public function run(WipeOptions $options): WipeResult
    {
        if (
            in_array($options->mode, [WipeMode::AccountingReset, WipeMode::AllTables], true)
            && ! $options->dryRun
            && ! $options->confirmedReset
        ) {
            throw new \InvalidArgumentException($options->mode->value.' requires --confirm=RESET or --force.');
        }

        if ($options->dryRun) {
            return new WipeResult(true, $this->collectDryRunCounts($options));
        }

        $counts = [];
        $useFkToggle = $this->shouldToggleForeignKeyConstraints();

        if ($useFkToggle) {
            Schema::disableForeignKeyConstraints();
        }

        try {
            DB::transaction(function () use ($options, &$counts): void {
                if ($options->mode === WipeMode::AccountingReset) {
                    foreach ($this->operationalResetTablesInDeleteOrder() as $table) {
                        $key = 'delete:'.$table;
                        $counts[$key] = ($counts[$key] ?? 0) + $this->deleteAllFromTable($table);
                    }
                }

                if ($options->mode === WipeMode::AllTables) {
                    foreach ($this->allAccountingTablesInDeleteOrder() as $table) {
                        $key = 'delete:'.$table;
                        $counts[$key] = ($counts[$key] ?? 0) + $this->deleteAllFromTable($table);
                    }
                } else {
                    $counts = array_merge($counts, $this->wipeGeneralLedgerAndFiscal($options->mode));
                }
            });
        } finally {
            if ($useFkToggle) {
                Schema::enableForeignKeyConstraints();
            }
        }

        $this->resetInstallCompletionFlag($options->mode);

        return new WipeResult(false, $counts);
    }

    /**
     * @return array<string, int>
     */
    private function collectDryRunCounts(WipeOptions $options): array
    {
        $counts = [];

        if ($options->mode === WipeMode::AccountingReset) {
            foreach ($this->operationalResetTablesInDeleteOrder() as $table) {
                $counts['would_delete:'.$table] = $this->tableCount($table);
            }
        }
        if ($options->mode === WipeMode::AllTables) {
            foreach ($this->allAccountingTablesInDeleteOrder() as $table) {
                $counts['would_delete:'.$table] = $this->tableCount($table);
            }
            return $counts;
        }

        if ($options->mode === WipeMode::Documents) {
            foreach ($this->preserveDocumentLinkColumns() as $table => $columns) {
                if (! Schema::hasTable($table)) {
                    continue;
                }
                foreach ($columns as $col) {
                    if (! Schema::hasColumn($table, $col)) {
                        continue;
                    }
                    $counts['would_null:'.$table.'.'.$col] = (int) DB::table($table)->whereNotNull($col)->count();
                }
            }
        }

        $counts['would_update:accounting_documents.reversed_by_document_id'] = Schema::hasTable('accounting_documents')
            ? (int) DB::table('accounting_documents')->whereNotNull('reversed_by_document_id')->count()
            : 0;

        if (Schema::hasTable('fiscal_years') && Schema::hasColumn('fiscal_years', 'closing_document_id')) {
            $counts['would_null:fiscal_years.closing_document_id'] = (int) DB::table('fiscal_years')->whereNotNull('closing_document_id')->count();
        }

        $counts['would_delete:manual_journal_lines'] = $this->tableCount('manual_journal_lines');
        $counts['would_delete:manual_journals'] = $this->tableCount('manual_journals');
        $counts['would_delete:financial_ledgers'] = $this->tableCount('financial_ledgers');
        $counts['would_delete:cost_entries'] = $this->tableCount('cost_entries');
        $counts['would_delete:accounting_documents'] = $this->tableCount('accounting_documents');
        $counts['would_delete:fiscal_years'] = $this->tableCount('fiscal_years');

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    private function wipeGeneralLedgerAndFiscal(WipeMode $mode): array
    {
        $counts = [];

        if (Schema::hasTable('accounting_documents')) {
            $n = (int) DB::table('accounting_documents')->whereNotNull('reversed_by_document_id')->update(['reversed_by_document_id' => null]);
            $counts['update:accounting_documents.reversed_by_document_id'] = $n;
        }

        if (Schema::hasTable('fiscal_years') && Schema::hasColumn('fiscal_years', 'closing_document_id')) {
            $n = (int) DB::table('fiscal_years')->whereNotNull('closing_document_id')->update(['closing_document_id' => null]);
            $counts['update:fiscal_years.closing_document_id'] = $n;
        }

        if ($mode === WipeMode::Documents) {
            foreach ($this->preserveDocumentLinkColumns() as $table => $columns) {
                if (! Schema::hasTable($table)) {
                    continue;
                }
                foreach ($columns as $col) {
                    if (! Schema::hasColumn($table, $col)) {
                        continue;
                    }
                    $key = 'null:'.$table.'.'.$col;
                    $counts[$key] = (int) DB::table($table)->whereNotNull($col)->update([$col => null]);
                }
            }
        }

        if (Schema::hasTable('manual_journal_lines')) {
            $counts['delete:manual_journal_lines'] = (int) DB::table('manual_journal_lines')->delete();
        }

        if (Schema::hasTable('manual_journals')) {
            DB::table('manual_journals')->update(['reversed_journal_id' => null]);
            $counts['delete:manual_journals'] = (int) DB::table('manual_journals')->delete();
        }

        if (Schema::hasTable('financial_ledgers')) {
            $counts['delete:financial_ledgers'] = (int) DB::table('financial_ledgers')->delete();
        }

        if (Schema::hasTable('cost_entries')) {
            $counts['delete:cost_entries'] = (int) DB::table('cost_entries')->delete();
        }

        if (Schema::hasTable('accounting_documents')) {
            $counts['delete:accounting_documents'] = (int) DB::table('accounting_documents')->delete();
        }

        if (Schema::hasTable('fiscal_years')) {
            $counts['delete:fiscal_years'] = (int) DB::table('fiscal_years')->delete();
        }

        return $counts;
    }

    private function deleteAllFromTable(string $table): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return (int) DB::table($table)->delete();
    }

    private function tableCount(string $table): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return (int) DB::table($table)->count();
    }

    private function shouldToggleForeignKeyConstraints(): bool
    {
        $driver = DB::getDriverName();

        return $driver === 'mysql' || $driver === 'mariadb' || $driver === 'sqlite';
    }

    private function resetInstallCompletionFlag(WipeMode $mode): void
    {
        if (! in_array($mode, [WipeMode::AccountingReset, WipeMode::AllTables], true)) {
            return;
        }

        if (! Schema::hasTable('settings')) {
            return;
        }

        Setting::set(AccountingInstallService::SETTING_COMPLETED_KEY, '0');
        Setting::clearCache();
    }
}
