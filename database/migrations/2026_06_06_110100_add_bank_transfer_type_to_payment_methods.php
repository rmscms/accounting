<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_methods') || ! Schema::hasColumn('payment_methods', 'type')) {
            return;
        }

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement(
                "ALTER TABLE payment_methods MODIFY COLUMN type ENUM('cash','pos','online','cheque','card_transfer','bank_transfer','wallet') NOT NULL"
            );
        } elseif ($driver === 'sqlite') {
            $this->rewriteSqliteTypeCheck(
                ['cash', 'pos', 'online', 'cheque', 'card_transfer', 'wallet'],
                ['cash', 'pos', 'online', 'cheque', 'card_transfer', 'bank_transfer', 'wallet']
            );
        }

        DB::table('payment_methods')
            ->where('code', 'bank_transfer')
            ->where('type', '!=', 'bank_transfer')
            ->update(['type' => 'bank_transfer']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('payment_methods') || ! Schema::hasColumn('payment_methods', 'type')) {
            return;
        }

        DB::table('payment_methods')
            ->where('type', 'bank_transfer')
            ->update(['type' => 'card_transfer']);

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement(
                "ALTER TABLE payment_methods MODIFY COLUMN type ENUM('cash','pos','online','cheque','card_transfer','wallet') NOT NULL"
            );
        } elseif ($driver === 'sqlite') {
            $this->rewriteSqliteTypeCheck(
                ['cash', 'pos', 'online', 'cheque', 'card_transfer', 'bank_transfer', 'wallet'],
                ['cash', 'pos', 'online', 'cheque', 'card_transfer', 'wallet']
            );
        }
    }

    /**
     * @param array<int, string> $fromValues
     * @param array<int, string> $toValues
     */
    private function rewriteSqliteTypeCheck(array $fromValues, array $toValues): void
    {
        $row = DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'payment_methods'");
        $createSql = (string) ($row->sql ?? '');
        if ($createSql === '') {
            return;
        }

        $toNeedle = implode("', '", $toValues);
        if (str_contains(strtolower($createSql), strtolower($toNeedle))) {
            return;
        }

        $fromPattern = implode('\s*,\s*', array_map(static fn (string $value): string => "'".preg_quote($value, '/')."'", $fromValues));
        $replacement = "check (\"type\" in ('".implode("', '", $toValues)."'))";
        $pattern = '/check\s*\(\s*"?type"?\s+in\s*\('.$fromPattern.'\)\s*\)/i';
        $updatedCreateSql = (string) preg_replace($pattern, $replacement, $createSql, 1);

        if ($updatedCreateSql === $createSql) {
            return;
        }

        $tempTable = 'payment_methods_tmp_bank_transfer';
        $updatedCreateSql = (string) preg_replace(
            '/^CREATE TABLE(?: IF NOT EXISTS)?\s+"?payment_methods"?/i',
            'CREATE TABLE "'.$tempTable.'"',
            $updatedCreateSql,
            1
        );

        $indexes = DB::select(
            "SELECT sql FROM sqlite_master WHERE type = 'index' AND tbl_name = 'payment_methods' AND sql IS NOT NULL"
        );
        $cols = DB::select('PRAGMA table_info("payment_methods")');
        $colList = implode(', ', array_map(static fn (object $c): string => '"'.$c->name.'"', $cols));

        DB::statement('PRAGMA foreign_keys=OFF');
        try {
            DB::unprepared($updatedCreateSql);
            DB::statement("INSERT INTO \"{$tempTable}\" ({$colList}) SELECT {$colList} FROM \"payment_methods\"");
            DB::statement('DROP TABLE "payment_methods"');
            DB::statement("ALTER TABLE \"{$tempTable}\" RENAME TO \"payment_methods\"");

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
