<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number', 50)->unique();
            $table->foreignId('document_id')->nullable()->constrained('accounting_documents');
            $table->foreignId('expense_category_id')->constrained('expense_categories')->onDelete('restrict');
            $table->enum('expense_type', ['operational', 'salary', 'rent', 'utilities', 'marketing', 'transportation', 'supplies', 'maintenance', 'other']);
            $table->decimal('amount', 20, 4);
            $table->string('currency_code', 3)->default('IRR');
            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->decimal('fx_rate', 12, 6)->default(1);
            $table->decimal('amount_base', 20, 4);
            $table->date('expense_date');
            $table->unsignedBigInteger('payment_id')->nullable()->comment('اگر پرداخت شده');
            $table->enum('payment_status', ['unpaid', 'paid', 'partially_paid'])->default('unpaid');
            $table->decimal('paid_amount', 20, 4)->default(0);
            $table->enum('payee_type', ['employee', 'supplier', 'service_provider', 'government', 'other']);
            $table->unsignedBigInteger('payee_id')->nullable();
            $table->string('payee_name', 255);
            $table->text('description')->comment('شرح برداشت: برای چه مصرفی');
            $table->string('receipt_number', 100)->nullable();
            $table->string('receipt_image', 500)->nullable();
            $table->decimal('tax_amount', 20, 4)->default(0);
            $table->boolean('is_recurring')->default(false);
            $table->enum('recurring_frequency', ['daily', 'weekly', 'monthly', 'yearly'])->nullable();
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'pending', 'approved', 'paid', 'rejected', 'cancelled'])->default('draft');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['expense_date', 'status']);
            $table->index(['expense_category_id', 'expense_date']);
            $table->index('payment_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
