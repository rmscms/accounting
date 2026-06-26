<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * بازگشت وجه به مشتری (Customer Refund)
     * 
     * زمانی که پول را به مشتری پس می‌دهیم
     */
    public function up(): void
    {
        Schema::create('customer_refunds', function (Blueprint $table) {
            $table->id();
            $table->string('refund_number')->unique();
            
            // مشتری
            $table->foreignId('customer_id')->constrained('customers')->onDelete('restrict');
            
            // لینک به Credit Note (اختیاری)
            $table->foreignId('credit_note_id')->nullable()->constrained('credit_notes')->onDelete('set null');
            
            // لینک به پرداخت اصلی (اختیاری)
            $table->foreignId('customer_payment_id')->nullable()->constrained('customer_payments')->onDelete('set null');
            
            // اطلاعات اولیه
            $table->foreignId('store_id')->default(0);
            $table->date('refund_date');
            $table->string('reason')->nullable();
            
            // مبلغ
            $table->decimal('amount', 15, 2);
            $table->string('currency_code', 3)->default('IRR');
            $table->decimal('fx_rate', 12, 6)->default(1);
            $table->decimal('amount_base', 15, 2);
            
            // روش بازگشت وجه
            $table->enum('refund_method', ['cash', 'bank_transfer', 'cheque', 'online', 'deduct_from_next_invoice'])->default('cash');
            $table->foreignId('bank_id')->nullable()->constrained('banks')->onDelete('set null');
            $table->foreignId('cash_box_id')->nullable()->constrained('cash_boxes')->onDelete('set null');
            
            // وضعیت
            $table->enum('status', ['pending', 'processed', 'cancelled'])->default('pending');
            $table->timestamp('processed_at')->nullable();
            
            // شماره مرجع (مثل شماره تراکنش)
            $table->string('reference_number')->nullable();
            
            // سند حسابداری
            $table->foreignId('accounting_document_id')->nullable()->constrained('accounting_documents')->onDelete('set null');
            
            // یادداشت‌ها
            $table->text('notes')->nullable();
            
            // مدیریت
            $table->foreignId('created_by_user_id')->nullable();
            $table->foreignId('approved_by_user_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('refund_date');
            $table->index('status');
            $table->index(['customer_id', 'refund_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_refunds');
    }
};
