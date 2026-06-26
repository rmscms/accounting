<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bad_debt_provisions', function (Blueprint $table) {
            $table->id();
            $table->string('provision_number')->unique();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->date('provision_date');
            $table->string('provision_amount', 32);
            $table->enum('calculation_method', ['percentage_sales', 'aging_analysis', 'specific_identification'])->default('aging_analysis');
            $table->decimal('percentage_used', 5, 2)->nullable();
            $table->enum('status', ['active', 'written_off', 'recovered', 'cancelled'])->default('active');
            $table->foreignId('accounting_document_id')->nullable()->constrained('accounting_documents')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('provision_date'); $table->index('status');
        });

        Schema::create('bad_debt_writeoffs', function (Blueprint $table) {
            $table->id();
            $table->string('writeoff_number')->unique();
            $table->foreignId('bad_debt_provision_id')->nullable()->constrained('bad_debt_provisions')->onDelete('set null');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('restrict');
            $table->foreignId('customer_invoice_id')->nullable()->constrained('customer_invoices')->onDelete('set null');
            $table->date('writeoff_date');
            $table->decimal('writeoff_amount', 15, 2);
            $table->string('reason');
            $table->enum('status', ['pending', 'approved', 'cancelled'])->default('pending');
            $table->foreignId('accounting_document_id')->nullable()->constrained('accounting_documents')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable();
            $table->foreignId('approved_by_user_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bad_debt_writeoffs');
        Schema::dropIfExists('bad_debt_provisions');
    }
};
