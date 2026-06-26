<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * پیش دریافت/پرداخت (Advance Payments)
     * 
     * استاندارد: IFRS 15 - Contract Liabilities/Assets
     */
    public function up(): void
    {
        // پیش دریافت از مشتری (Contract Liability)
        Schema::create('customer_advances', function (Blueprint $table) {
            $table->id();
            $table->string('advance_number')->unique();
            
            // مشتری
            $table->foreignId('customer_id')->constrained('customers')->onDelete('restrict');
            $table->foreignId('store_id')->default(0);
            
            // اطلاعات پرداخت
            $table->date('advance_date');
            $table->decimal('amount', 15, 2);
            $table->string('currency_code', 3)->default('IRR');
            $table->decimal('fx_rate', 12, 6)->default(1);
            $table->decimal('amount_base', 15, 2);
            
            // مانده باقیمانده (بعد از اعمال به فاکتورها)
            $table->decimal('applied_amount', 15, 2)->default(0);
            $table->decimal('remaining_amount', 15, 2)->default(0);
            
            // روش دریافت
            $table->enum('payment_method', ['cash', 'bank_transfer', 'card_transfer', 'cheque', 'online', 'pos'])->default('cash');
            $table->foreignId('bank_id')->nullable()->constrained('banks')->onDelete('set null');
            $table->foreignId('cash_box_id')->nullable()->constrained('cash_boxes')->onDelete('set null');
            $table->string('reference_number')->nullable();
            
            // وضعیت
            $table->enum('status', ['active', 'fully_applied', 'refunded', 'cancelled'])->default('active');
            
            // سند حسابداری
            $table->foreignId('accounting_document_id')->nullable()->constrained('accounting_documents')->onDelete('set null');
            
            // یادداشت
            $table->text('notes')->nullable();
            
            // مدیریت
            $table->foreignId('created_by_user_id')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('advance_date');
            $table->index('status');
            $table->index(['customer_id', 'status']);
        });

        // پیش پرداخت به تامین‌کننده (Contract Asset)
        Schema::create('supplier_advances', function (Blueprint $table) {
            $table->id();
            $table->string('advance_number')->unique();
            
            // تامین‌کننده
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('restrict');
            $table->foreignId('store_id')->default(0);
            
            // اطلاعات پرداخت
            $table->date('advance_date');
            $table->decimal('amount', 15, 2);
            $table->string('currency_code', 3)->default('IRR');
            $table->decimal('fx_rate', 12, 6)->default(1);
            $table->decimal('amount_base', 15, 2);
            
            // مانده باقیمانده
            $table->decimal('applied_amount', 15, 2)->default(0);
            $table->decimal('remaining_amount', 15, 2)->default(0);
            
            // روش پرداخت
            $table->enum('payment_method', ['cash', 'bank_transfer', 'card_transfer', 'cheque', 'online'])->default('bank_transfer');
            $table->foreignId('bank_id')->nullable()->constrained('banks')->onDelete('set null');
            $table->foreignId('cash_box_id')->nullable()->constrained('cash_boxes')->onDelete('set null');
            $table->string('reference_number')->nullable();
            
            // وضعیت
            $table->enum('status', ['active', 'fully_applied', 'refunded', 'cancelled'])->default('active');
            
            // سند حسابداری
            $table->foreignId('accounting_document_id')->nullable()->constrained('accounting_documents')->onDelete('set null');
            
            // یادداشت
            $table->text('notes')->nullable();
            
            // مدیریت
            $table->foreignId('created_by_user_id')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('advance_date');
            $table->index('status');
            $table->index(['supplier_id', 'status']);
        });

        // جدول اعمال Advances به فاکتورها
        Schema::create('advance_applications', function (Blueprint $table) {
            $table->id();
            
            // نوع (customer یا supplier)
            $table->enum('advance_type', ['customer', 'supplier']);
            $table->unsignedBigInteger('advance_id');
            
            // فاکتور
            $table->string('invoice_type'); // CustomerInvoice, SupplierInvoice
            $table->unsignedBigInteger('invoice_id');
            
            // مبلغ اعمال شده
            $table->decimal('applied_amount', 15, 2);
            $table->date('application_date');
            
            // یادداشت
            $table->text('notes')->nullable();
            
            $table->timestamp('created_at');
            
            // Indexes
            $table->index(['advance_type', 'advance_id']);
            $table->index(['invoice_type', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advance_applications');
        Schema::dropIfExists('supplier_advances');
        Schema::dropIfExists('customer_advances');
    }
};
