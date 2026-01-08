<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_invoice_id')->constrained('supplier_invoices')->onDelete('cascade');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_sku', 100)->nullable();
            $table->string('product_name', 255);
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_price', 20, 4);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('discount_amount', 20, 4)->default(0);
            $table->decimal('total_price', 20, 4);
            $table->timestamp('created_at')->useCurrent();
            
            $table->index('supplier_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoice_items');
    }
};
