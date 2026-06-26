<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * یادداشت بدهکار (Debit Note) - IAS 2
     * 
     * زمانی که ما کالا را به تامین‌کننده برمی‌گردانیم
     */
    public function up(): void
    {
        // جدول اصلی Debit Note
        Schema::create('debit_notes', function (Blueprint $table) {
            $table->id();
            $table->string('debit_note_number')->unique();
            
            // تامین‌کننده
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('restrict');
            
            // لینک به فاکتور خرید اصلی
            $table->foreignId('supplier_invoice_id')->nullable()->constrained('supplier_invoices')->onDelete('restrict');
            
            // اطلاعات اولیه
            $table->foreignId('store_id')->default(0);
            $table->date('debit_date');
            $table->string('reason')->nullable(); // دلیل برگشت
            $table->enum('debit_type', ['return', 'discount', 'correction'])->default('return');
            
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
            $table->foreignId('applied_to_invoice_id')->nullable()->constrained('supplier_invoices')->onDelete('set null');
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
            $table->index('debit_date');
            $table->index('status');
            $table->index(['supplier_id', 'debit_date']);
        });

        // جدول آیتم‌های Debit Note
        Schema::create('debit_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debit_note_id')->constrained('debit_notes')->onDelete('cascade');
            
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
        Schema::dropIfExists('debit_note_items');
        Schema::dropIfExists('debit_notes');
    }
};
