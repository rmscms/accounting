<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $this->alterMySqlCustomerAdvanceEnum(true);
            $this->alterMySqlSupplierAdvanceEnum(true);

            return;
        }

        if ($driver === 'sqlite') {
            $this->rewriteSqlitePaymentMethodCheck(
                'customer_advances',
                ['cash', 'bank_transfer', 'cheque', 'online', 'pos'],
                ['cash', 'bank_transfer', 'card_transfer', 'cheque', 'online', 'pos']
            );
            $this->rewriteSqlitePaymentMethodCheck(
                'supplier_advances',
                ['cash', 'bank_transfer', 'cheque', 'online'],
                ['cash', 'bank_transfer', 'card_transfer', 'cheque', 'online']
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customer_advances')) {
            DB::table('customer_advances')->where('payment_method', 'card_transfer')->update(['payment_method' => 'bank_transfer']);
        }
        if (Schema::hasTable('supplier_advances')) {
            DB::table('supplier_advances')->where('payment_method', 'card_transfer')->update(['payment_method' => 'bank_transfer']);
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $this->alterMySqlCustomerAdvanceEnum(false);
            $this->alterMySqlSupplierAdvanceEnum(false);

            return;
        }

        if ($driver === 'sqlite') {
            $this->rewriteSqlitePaymentMethodCheck(
                'customer_advances',
                ['cash', 'bank_transfer', 'card_transfer', 'cheque', 'online', 'pos'],
                ['cash', 'bank_transfer', 'cheque', 'online', 'pos']
            );
            $this->rewriteSqlitePaymentMethodCheck(
                'supplier_advances',
                ['cash', 'bank_transfer', 'card_transfer', 'cheque', 'online'],
                ['cash', 'bank_transfer', 'cheque', 'online']
            );
        }
    }

    private function alterMySqlCustomerAdvanceEnum(bool $includeCardTransfer): void
    {
        if (! Schema::hasTable('customer_advances') || ! Schema::hasColumn('customer_advances', 'payment_method')) {
            return;
        }

        $values = $includeCardTransfer
            ? "'cash','bank_transfer','card_transfer','cheque','online','pos'"
            : "'cash','bank_transfer','cheque','online','pos'";

        DB::statement("ALTER TABLE customer_advances MODIFY COLUMN payment_method ENUM({$values}) NOT NULL DEFAULT 'cash'");
    }

    private function alterMySqlSupplierAdvanceEnum(bool $includeCardTransfer): void
    {
        if (! Schema::hasTable('supplier_advances') || ! Schema::hasColumn('supplier_advances', 'payment_method')) {
            return;
        }

        $values = $includeCardTransfer
            ? "'cash','bank_transfer','card_transfer','cheque','online'"
            : "'cash','bank_transfer','cheque','online'";

        DB::statement("ALTER TABLE supplier_advances MODIFY COLUMN payment_method ENUM({$values}) NOT NULL DEFAULT 'bank_transfer'");
    }

    /**
     * @param array<int, string> $fromValues
     * @param array<int, string> $toValues
     */
    private function rewriteSqlitePaymentMethodCheck(string $table, array $fromValues, array $toValues): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'payment_method')) {
            return;
        }

        $row = DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?", [$table]);
        $createSql = (string) ($row->sql ?? '');
        if ($createSql === '') {
            return;
        }

        $toNeedle = implode("', '", $toValues);
        if (str_contains(strtolower($createSql), strtolower($toNeedle))) {
            return;
        }

        $fromPattern = implode('\s*,\s*', array_map(static fn (string $value): string => "'".preg_quote($value, '/')."'", $fromValues));
        $replacement = "check (\"payment_method\" in ('".implode("', '", $toValues)."'))";
        $pattern = '/check\s*\(\s*"?payment_method"?\s+in\s*\('.$fromPattern.'\)\s*\)/i';
        $updatedCreateSql = (string) preg_replace($pattern, $replacement, $createSql, 1);

        if ($updatedCreateSql === $createSql) {
            return;
        }

        $tempTable = $table.'_tmp_card_transfer';
        $updatedCreateSql = (string) preg_replace(
            '/^CREATE TABLE(?: IF NOT EXISTS)?\s+"?'.preg_quote($table, '/').'"?/i',
            'CREATE TABLE "'.$tempTable.'"',
            $updatedCreateSql,
            1
        );

        $indexes = DB::select(
            "SELECT sql FROM sqlite_master WHERE type = 'index' AND tbl_name = ? AND sql IS NOT NULL",
            [$table]
        );
        $cols = DB::select('PRAGMA table_info("'.$table.'")');
        $colList = implode(', ', array_map(static fn (object $c): string => '"'.$c->name.'"', $cols));

        DB::statement('PRAGMA foreign_keys=OFF');
        try {
            DB::unprepared($updatedCreateSql);
            DB::statement("INSERT INTO \"{$tempTable}\" ({$colList}) SELECT {$colList} FROM \"{$table}\"");
            DB::statement("DROP TABLE \"{$table}\"");
            DB::statement("ALTER TABLE \"{$tempTable}\" RENAME TO \"{$table}\"");

            foreach ($indexes as $index) {
                if (! empty($index->sql)) {
                    DB::unprepared((string) $index->sql);
                }
            }
        } finally {
            DB::statement('PRAGMA foreign_keys=ON');
        }
    }
};
