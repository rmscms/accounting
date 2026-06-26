<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_refunds', function (Blueprint $table) {
            if (! Schema::hasColumn('supplier_refunds', 'supplier_invoice_id')) {
                $table->foreignId('supplier_invoice_id')
                    ->nullable()
                    ->after('supplier_id')
                    ->constrained('supplier_invoices')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('supplier_refunds', function (Blueprint $table) {
            if (Schema::hasColumn('supplier_refunds', 'supplier_invoice_id')) {
                $table->dropForeign(['supplier_invoice_id']);
            }
        });
    }
};
