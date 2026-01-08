<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_document_id')->constrained('accounting_documents')->onDelete('cascade');
            $table->enum('cost_type', ['purchase', 'manufacturing', 'overhead', 'adjustment']);
            $table->string('reference_type', 50)->nullable()->comment('sale, invoice');
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_sku', 100)->nullable();
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_cost', 20, 4);
            $table->decimal('total_cost', 20, 4);
            $table->string('currency_code', 3)->default('IRR');
            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->decimal('fx_rate', 12, 6)->default(1);
            $table->decimal('cost_irr', 20, 4);
            $table->enum('cost_method', ['FIFO', 'LIFO', 'AVG'])->default('FIFO');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['reference_type', 'reference_id']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_entries');
    }
};
