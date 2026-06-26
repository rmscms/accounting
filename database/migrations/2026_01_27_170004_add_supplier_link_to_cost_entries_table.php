<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('cost_entries', 'source_supplier_id')) {
            return;
        }
        
        Schema::table('cost_entries', function (Blueprint $table) {
            $table->foreignId('source_supplier_id')->nullable()->after('reference_id')->constrained('suppliers')->onDelete('set null');
            $table->foreignId('source_supplier_invoice_id')->nullable()->after('source_supplier_id')->constrained('supplier_invoices')->onDelete('set null');
            $table->unsignedBigInteger('source_purchase_invoice_id')->nullable()->after('source_supplier_invoice_id')->comment('لینک به purchase_invoices در پروژه (nullable)');
            
            $table->index('source_supplier_id');
            $table->index('source_supplier_invoice_id');
            $table->index('source_purchase_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('cost_entries', function (Blueprint $table) {
            $table->dropForeign(['source_supplier_id']);
            $table->dropForeign(['source_supplier_invoice_id']);
            $table->dropIndex(['source_supplier_id']);
            $table->dropIndex(['source_supplier_invoice_id']);
            $table->dropIndex(['source_purchase_invoice_id']);
            $table->dropColumn(['source_supplier_id', 'source_supplier_invoice_id', 'source_purchase_invoice_id']);
        });
    }
};
