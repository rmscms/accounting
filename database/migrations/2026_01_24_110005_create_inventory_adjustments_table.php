<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // سند تعدیل موجودی
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('adjustment_number', 50)->unique();
            $table->date('adjustment_date');
            $table->enum('adjustment_type', ['physical_count', 'writedown', 'damage', 'theft', 'obsolescence', 'other'])->default('physical_count');
            $table->string('warehouse_id', 50)->nullable()->comment('شناسه انبار (اختیاری)');
            $table->text('reason');
            $table->text('notes')->nullable();
            $table->decimal('total_adjustment_value', 20, 4)->default(0)->comment('مبلغ کل تعدیل');
            $table->enum('status', ['draft', 'approved', 'posted', 'cancelled'])->default('draft');
            $table->foreignId('accounting_document_id')->nullable()->constrained('accounting_documents')->onDelete('set null');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('posted_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('adjustment_number');
            $table->index('status');
            $table->index('adjustment_date');
            $table->index('adjustment_type');
        });

        // اقلام تعدیل موجودی
        Schema::create('inventory_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_adjustment_id')->constrained('inventory_adjustments')->onDelete('cascade');
            $table->integer('line_number');
            $table->string('product_id', 100)->nullable()->comment('شناسه محصول (polymorphic)');
            $table->string('product_type', 100)->nullable()->comment('نوع محصول (polymorphic)');
            $table->string('product_name', 255);
            $table->string('sku', 100)->nullable();
            $table->decimal('system_quantity', 12, 4)->default(0)->comment('موجودی سیستم');
            $table->decimal('actual_quantity', 12, 4)->default(0)->comment('موجودی واقعی');
            $table->decimal('difference_quantity', 12, 4)->default(0)->comment('تفاوت');
            $table->decimal('unit_cost', 20, 4)->default(0)->comment('بهای تمام شده واحد');
            $table->decimal('adjustment_value', 20, 4)->default(0)->comment('ارزش تعدیل');
            $table->text('reason')->nullable();
            $table->timestamps();
            
            $table->index('inventory_adjustment_id');
            $table->index(['product_id', 'product_type']);
            $table->unique(['inventory_adjustment_id', 'line_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustment_items');
        Schema::dropIfExists('inventory_adjustments');
    }
};
