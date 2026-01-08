<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_documents', function (Blueprint $table) {
            $table->id();
            $table->string('document_number', 50)->unique()->comment('شماره سند مثلاً: ACC-2025-00001');
            $table->enum('document_type', [
                'SALE', 'PURCHASE', 'PAYMENT', 'RECEIPT', 'TAX', 
                'FX_ADJUST', 'CORRECTION', 'OPENING', 'CLOSING', 'EXPENSE'
            ])->comment('نوع سند');
            $table->unsignedBigInteger('store_id')->nullable()->comment('شناسه فروشگاه');
            $table->foreignId('fiscal_year_id')->nullable()->constrained('fiscal_years')->onDelete('set null');
            $table->enum('reference_type', ['event', 'manual', 'system'])->default('manual');
            $table->unsignedBigInteger('reference_id')->nullable()->comment('شناسه مرجع خارجی');
            $table->text('description');
            $table->decimal('total_debit', 20, 4)->default(0)->comment('جمع بدهکار');
            $table->decimal('total_credit', 20, 4)->default(0)->comment('جمع بستانکار');
            $table->enum('status', ['draft', 'posted', 'reversed'])->default('draft');
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('reversed_by_document_id')->nullable()->constrained('accounting_documents')->onDelete('set null');
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('document_number');
            $table->index(['document_type', 'store_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_documents');
    }
};
