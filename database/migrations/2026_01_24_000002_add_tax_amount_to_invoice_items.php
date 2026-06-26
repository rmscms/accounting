<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // اضافه کردن tax_amount به supplier_invoice_items
        if (Schema::hasTable('supplier_invoice_items')) {
            Schema::table('supplier_invoice_items', function (Blueprint $table) {
                if (!Schema::hasColumn('supplier_invoice_items', 'tax_amount')) {
                    $table->decimal('tax_amount', 15, 4)->default(0)->after('tax_rate')
                        ->comment('مبلغ مالیات این آیتم');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('supplier_invoice_items')) {
            Schema::table('supplier_invoice_items', function (Blueprint $table) {
                $table->dropColumn('tax_amount');
            });
        }
    }
};
