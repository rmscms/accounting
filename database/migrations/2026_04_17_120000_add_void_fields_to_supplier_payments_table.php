<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('supplier_payments', 'voided_at')) {
                $table->timestamp('voided_at')->nullable()->after('processed_at');
            }
            if (! Schema::hasColumn('supplier_payments', 'void_reason')) {
                $table->text('void_reason')->nullable()->after('voided_at');
            }
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE supplier_payments MODIFY COLUMN status ENUM("
                ."'pending','completed','failed','reversed','cancelled','voided'"
                .") NOT NULL DEFAULT 'pending'"
            );
        }
    }

    public function down(): void
    {
        Schema::table('supplier_payments', function (Blueprint $table) {
            if (Schema::hasColumn('supplier_payments', 'void_reason')) {
                $table->dropColumn('void_reason');
            }
            if (Schema::hasColumn('supplier_payments', 'voided_at')) {
                $table->dropColumn('voided_at');
            }
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE supplier_payments MODIFY COLUMN status ENUM("
                ."'pending','completed','failed','reversed','cancelled'"
                .") NOT NULL DEFAULT 'pending'"
            );
        }
    }
};
