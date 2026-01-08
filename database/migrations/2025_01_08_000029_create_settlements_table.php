<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->string('settlement_number', 50)->unique();
            $table->enum('settlement_type', ['customer', 'supplier']);
            $table->enum('party_type', ['customer', 'supplier']);
            $table->unsignedBigInteger('party_id')->comment('customer_id or supplier_id');
            $table->unsignedBigInteger('store_id')->nullable();
            $table->decimal('total_invoices', 20, 4);
            $table->decimal('total_payments', 20, 4);
            $table->decimal('settlement_amount', 20, 4);
            $table->date('settlement_date');
            $table->string('currency_code', 3)->default('IRR');
            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('document_id')->nullable()->constrained('accounting_documents');
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            
            $table->index(['party_type', 'party_id']);
            $table->index(['settlement_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
