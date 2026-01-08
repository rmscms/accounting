<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_balances', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('store_id');
            $table->decimal('balance_irr', 20, 4)->default(0);
            $table->decimal('total_invoices', 20, 4)->default(0);
            $table->decimal('total_payments', 20, 4)->default(0);
            $table->timestamp('last_invoice_at')->nullable();
            $table->timestamp('last_payment_at')->nullable();
            $table->timestamp('last_transaction_at')->nullable();
            $table->decimal('credit_limit', 20, 4)->nullable();
            $table->timestamp('updated_at')->useCurrent();
            
            $table->primary(['customer_id', 'store_id']);
            $table->index('balance_irr');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_balances');
    }
};
