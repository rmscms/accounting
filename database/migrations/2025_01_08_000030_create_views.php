<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Customer Balances View - Real-time calculation from Ledger
        DB::statement("
            CREATE OR REPLACE VIEW customer_balances_view AS
            SELECT 
                fl.source_reference_id AS customer_id,
                fl.store_id,
                SUM(CASE WHEN a.account_type = 'asset' THEN fl.debit_amount ELSE 0 END) AS total_debit,
                SUM(CASE WHEN a.account_type = 'asset' THEN fl.credit_amount ELSE 0 END) AS total_credit,
                SUM(CASE WHEN a.account_type = 'asset' THEN (fl.debit_amount - fl.credit_amount) ELSE 0 END) AS balance_irr,
                MAX(fl.created_at) AS last_transaction_at
            FROM financial_ledgers fl
            INNER JOIN accounts a ON fl.account_id = a.id
            WHERE a.code LIKE '1-12%'
              AND fl.source_reference_type = 'customer'
            GROUP BY fl.source_reference_id, fl.store_id
        ");

        // Supplier Balances View - Real-time calculation from Ledger
        DB::statement("
            CREATE OR REPLACE VIEW supplier_balances_view AS
            SELECT 
                fl.source_reference_id AS supplier_id,
                fl.store_id,
                SUM(CASE WHEN a.account_type = 'liability' THEN fl.credit_amount ELSE 0 END) AS total_credit,
                SUM(CASE WHEN a.account_type = 'liability' THEN fl.debit_amount ELSE 0 END) AS total_debit,
                SUM(CASE WHEN a.account_type = 'liability' THEN (fl.credit_amount - fl.debit_amount) ELSE 0 END) AS balance_irr,
                MAX(fl.created_at) AS last_transaction_at
            FROM financial_ledgers fl
            INNER JOIN accounts a ON fl.account_id = a.id
            WHERE a.code LIKE '2-21%'
              AND fl.source_reference_type = 'supplier'
            GROUP BY fl.source_reference_id, fl.store_id
        ");
    }

    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS customer_balances_view");
        DB::statement("DROP VIEW IF EXISTS supplier_balances_view");
    }
};
