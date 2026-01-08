<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_ledgers', function (Blueprint $table) {
            $table->id();
            $table->enum('event_type', [
                'SALE', 'PURCHASE', 'PAYMENT', 'RECEIPT', 'FX_DIFF', 
                'TAX', 'COST', 'ADJUSTMENT', 'REVERSAL', 'EXPENSE'
            ])->comment('نوع رویداد');
            $table->enum('event_source', ['sales', 'inventory', 'system', 'manual'])->comment('منبع رویداد');
            $table->string('source_reference_type', 50)->nullable()->comment('نوع مرجع: order, invoice, payment, purchase');
            $table->unsignedBigInteger('source_reference_id')->nullable();
            $table->unsignedBigInteger('store_id')->index()->comment('شناسه فروشگاه');
            $table->foreignId('account_id')->constrained('accounts')->onDelete('restrict');
            $table->string('currency_code', 3)->default('IRR')->comment('کد ارز');
            $table->decimal('debit_amount', 20, 4)->default(0)->comment('مبلغ بدهکار');
            $table->decimal('credit_amount', 20, 4)->default(0)->comment('مبلغ بستانکار');
            $table->decimal('fx_rate_to_irr', 12, 6)->default(1)->comment('نرخ تبدیل به ریال');
            $table->decimal('amount_irr', 20, 4)->comment('مبلغ ریالی برای گزارش');
            $table->foreignId('accounting_document_id')->constrained('accounting_documents')->onDelete('restrict');
            $table->text('description')->nullable();
            $table->timestamp('created_at')->useCurrent()->comment('فقط INSERT - هیچ UPDATE نداریم');
            
            // Indexes
            $table->index(['accounting_document_id', 'account_id']);
            $table->index(['created_at']);
            $table->index(['source_reference_type', 'source_reference_id']);
            $table->index(['event_type', 'store_id']);
            $table->index('account_id');
            
            // Unique constraint برای جلوگیری از duplicate
            $table->unique(['accounting_document_id', 'account_id', 'debit_amount', 'credit_amount'], 'unique_ledger_entry');
        });
        
        // هشدار: این جدول IMMUTABLE است - هیچ UPDATE یا DELETE نداریم
        // برای اصلاح، باید سند اصلاحی جدید ثبت شود
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_ledgers');
    }
};
