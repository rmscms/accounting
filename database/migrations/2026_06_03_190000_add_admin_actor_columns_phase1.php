<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, array<int, string>>
     */
    private array $phaseOneAuditColumns = [
        'bank_transfers' => ['created_by_admin_id', 'processed_by_admin_id'],
        'bank_transactions' => ['created_by_admin_id', 'posted_by_admin_id'],
        'accounting_documents' => ['created_by_admin_id', 'posted_by_admin_id'],
        'customer_payments' => ['processed_by_admin_id', 'corrected_by_admin_id'],
        'supplier_payments' => ['processed_by_admin_id', 'corrected_by_admin_id'],
        'customer_refunds' => ['created_by_admin_id', 'approved_by_admin_id'],
        'supplier_refunds' => ['created_by_admin_id', 'approved_by_admin_id'],
        'customer_advances' => ['created_by_admin_id'],
        'supplier_advances' => ['created_by_admin_id'],
        'manual_journals' => ['created_by_admin_id', 'posted_by_admin_id'],
        'inventory_adjustments' => ['created_by_admin_id', 'approved_by_admin_id', 'posted_by_admin_id'],
    ];

    public function up(): void
    {
        $adminsTableExists = Schema::hasTable('admins');
        $driver = Schema::getConnection()->getDriverName();
        $canAddForeignKeys = $adminsTableExists && $driver !== 'sqlite';

        foreach ($this->phaseOneAuditColumns as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                    $blueprint->unsignedBigInteger($column)->nullable()->index();
                });

                if (! $canAddForeignKeys) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                    $blueprint
                        ->foreign($column)
                        ->references('id')
                        ->on('admins')
                        ->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->phaseOneAuditColumns as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($table, $column): void {
                    try {
                        $blueprint->dropForeign($table . '_' . $column . '_foreign');
                    } catch (\Throwable) {
                        // Ignore when FK does not exist (e.g. sqlite/no constraint setup).
                    }
                });

                Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                    try {
                        $blueprint->dropIndex([$column]);
                    } catch (\Throwable) {
                        // Ignore when index name differs or index not present.
                    }
                    $blueprint->dropColumn($column);
                });
            }
        }
    }
};
