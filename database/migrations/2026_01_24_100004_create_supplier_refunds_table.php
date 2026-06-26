<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * دریافت بازگشت وجه از تامین‌کننده (Supplier Refund)
     * 
     * زمانی که از تامین‌کننده پول پس می‌گیریم
     */
    public function up(): void
    {
        Schema::create('supplier_refunds', function (Blueprint $table) {
            $table->id();
            $table->string('refund_number')->unique();
            
            // تامین‌کننده
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('restrict');
            
            // لینک به Debit Note (اختیاری)
            $table->foreignId('debit_note_id')->nullable()->constrained('debit_notes')->onDelete('set null');
            
            // لینک به پرداخت اصلی (اختیاری)
            $table->foreignId('supplier_payment_id')->nullable()->constrained('supplier_payments')->onDelete('set null');
            
            // اطلاعات اولیه
            $table->foreignId('store_id')->default(0);
            $table->date('refund_date');
            $table->string('reason')->nullable();
            
            // مبلغ
            $table->decimal('amount', 15, 2);
            $table->string('currency_code', 3)->default('IRR');
            $table->decimal('fx_rate', 12, 6)->default(1);
            $table->decimal('amount_base', 15, 2);
            
            // روش دریافت بازگشت
            $table->enum('refund_method', ['cash', 'bank_transfer', 'cheque', 'online', 'deduct_from_next_purchase'])->default('bank_transfer');
            $table->foreignId('bank_id')->nullable()->constrained('banks')->onDelete('set null');
            $table->foreignId('cash_box_id')->nullable()->constrained('cash_boxes')->onDelete('set null');
            
            // وضعیت
            $table->enum('status', ['pending', 'received', 'cancelled'])->default('pending');
            $table->timestamp('received_at')->nullable();
            
            // شماره مرجع
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
            $table->index(['supplier_id', 'refund_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_refunds');
    }
};
