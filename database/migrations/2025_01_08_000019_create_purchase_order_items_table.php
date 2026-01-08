<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->onDelete('cascade');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_sku', 100)->nullable();
            $table->string('product_name', 255);
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_price', 20, 4);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('discount_amount', 20, 4)->default(0);
            $table->decimal('total_price', 20, 4);
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index('purchase_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
