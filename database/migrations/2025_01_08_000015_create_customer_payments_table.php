<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number', 50)->unique();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('store_id')->nullable();
            $table->foreignId('customer_invoice_id')->nullable()->constrained('customer_invoices')->onDelete('set null');
            $table->foreignId('payment_method_id')->constrained('payment_methods');
            $table->decimal('amount', 20, 4);
            $table->string('currency_code', 3)->default('IRR');
            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->decimal('fx_rate', 12, 6)->default(1);
            $table->decimal('amount_base', 20, 4);
            $table->date('payment_date');
            $table->foreignId('bank_id')->nullable()->constrained('banks');
            $table->foreignId('cash_box_id')->nullable()->constrained('cash_boxes');
            $table->foreignId('pos_terminal_id')->nullable()->constrained('pos_terminals');
            $table->foreignId('wallet_id')->nullable()->constrained('wallets');
            $table->foreignId('cheque_id')->nullable()->constrained('cheques');
            $table->string('gateway_transaction_id', 100)->nullable();
            $table->enum('status', ['pending', 'completed', 'failed', 'reversed', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->string('receipt_image', 500)->nullable();
            $table->foreignId('document_id')->nullable()->constrained('accounting_documents');
            $table->unsignedBigInteger('processed_by_user_id')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['payment_date', 'status']);
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_payments');
    }
};
