<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cheques', function (Blueprint $table) {
            $table->id();
            $table->string('cheque_number', 50)->unique();
            $table->foreignId('bank_id')->constrained('banks')->onDelete('restrict');
            $table->enum('cheque_type', ['received', 'issued']);
            $table->decimal('amount', 20, 4);
            $table->string('currency_code', 3)->default('IRR');
            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->date('issue_date');
            $table->date('due_date');
            $table->string('payer_name', 255);
            $table->string('payer_account', 50)->nullable();
            $table->string('payee_name', 255);
            $table->string('payee_account', 50)->nullable();
            $table->enum('status', ['issued', 'pending', 'cashed', 'bounced', 'cancelled'])->default('pending');
            $table->timestamp('cashed_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->text('bounce_reason')->nullable();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->text('notes')->nullable();
            $table->string('image', 500)->nullable();
            $table->timestamps();
            
            $table->index(['due_date', 'status']);
            $table->index('cheque_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cheques');
    }
};
