<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * اعتبار برگشتی (Credit Note) - IFRS 15
     * 
     * زمانی که مشتری کالا را برمی‌گرداند یا تخفیف می‌گیرد
     */
    public function up(): void
    {
        // جدول اصلی Credit Note
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->string('credit_note_number')->unique();
            
            // مشتری
            $table->foreignId('customer_id')->constrained('customers')->onDelete('restrict');
            
            // لینک به فاکتور اصلی
            $table->foreignId('customer_invoice_id')->nullable()->constrained('customer_invoices')->onDelete('restrict');
            
            // اطلاعات اولیه
            $table->foreignId('store_id')->default(0);
            $table->date('credit_date');
            $table->string('reason')->nullable(); // دلیل برگشت
            $table->enum('credit_type', ['return', 'discount', 'correction'])->default('return');
            
            // مبالغ
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            
            // ارز
            $table->string('currency_code', 3)->default('IRR');
            $table->decimal('fx_rate', 12, 6)->default(1);
            $table->decimal('amount_base', 15, 2)->default(0);
            
            // وضعیت
            $table->enum('status', ['draft', 'issued', 'applied', 'void'])->default('draft');
            
            // اعمال شده به کدام فاکتور؟
            $table->foreignId('applied_to_invoice_id')->nullable()->constrained('customer_invoices')->onDelete('set null');
            $table->timestamp('applied_at')->nullable();
            
            // سند حسابداری
            $table->foreignId('accounting_document_id')->nullable()->constrained('accounting_documents')->onDelete('set null');
            
            // یادداشت‌ها
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            
            // مدیریت
            $table->foreignId('created_by_user_id')->nullable();
            $table->foreignId('approved_by_user_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('credit_date');
            $table->index('status');
            $table->index(['customer_id', 'credit_date']);
        });

        // جدول آیتم‌های Credit Note
        Schema::create('credit_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_note_id')->constrained('credit_notes')->onDelete('cascade');
            
            // محصول
            $table->foreignId('product_id')->nullable();
            $table->string('product_sku')->nullable();
            $table->string('product_name');
            
            // مقادیر
            $table->decimal('quantity', 12, 2);
            $table->decimal('price', 15, 2); // قیمت واحد
            
            // مالیات
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            
            // تخفیف
            $table->decimal('discount_amount', 15, 2)->default(0);
            
            // جمع
            $table->decimal('total', 15, 2)->default(0);
            
            // دلیل برگشت
            $table->string('return_reason')->nullable();
            
            // یادداشت
            $table->text('notes')->nullable();
            
            $table->timestamp('created_at');
            
            // Indexes
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_note_items');
        Schema::dropIfExists('credit_notes');
    }
};
