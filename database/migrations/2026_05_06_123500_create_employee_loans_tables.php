<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_loans', function (Blueprint $table): void {
            $table->id();
            $table->string('loan_number', 50)->unique();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('disbursement_bank_id')->nullable()->constrained('banks')->nullOnDelete();
            $table->date('disbursement_date');
            $table->date('first_due_date');
            $table->decimal('principal_amount', 20, 4)->default(0);
            $table->decimal('annual_interest_rate', 8, 4)->default(0);
            $table->unsignedInteger('installments_count')->default(1);
            $table->decimal('installment_amount', 20, 4)->default(0);
            $table->decimal('total_interest_amount', 20, 4)->default(0);
            $table->decimal('total_amount', 20, 4)->default(0);
            $table->decimal('remaining_principal', 20, 4)->default(0);
            $table->decimal('remaining_interest', 20, 4)->default(0);
            $table->decimal('remaining_total', 20, 4)->default(0);
            $table->enum('status', ['draft', 'active', 'closed', 'cancelled'])->default('draft');
            $table->foreignId('disbursement_manual_journal_id')->nullable()->constrained('manual_journals')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });

        Schema::create('employee_loan_installments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_loan_id')->constrained('employee_loans')->cascadeOnDelete();
            $table->unsignedInteger('installment_number');
            $table->date('due_date');
            $table->decimal('opening_principal', 20, 4)->default(0);
            $table->decimal('principal_amount', 20, 4)->default(0);
            $table->decimal('interest_amount', 20, 4)->default(0);
            $table->decimal('installment_amount', 20, 4)->default(0);
            $table->decimal('paid_principal', 20, 4)->default(0);
            $table->decimal('paid_interest', 20, 4)->default(0);
            $table->decimal('paid_total', 20, 4)->default(0);
            $table->decimal('remaining_amount', 20, 4)->default(0);
            $table->enum('status', ['pending', 'partially_paid', 'paid'])->default('pending');
            $table->timestamps();

            $table->unique(['employee_loan_id', 'installment_number']);
            $table->index(['due_date', 'status']);
        });

        Schema::create('employee_loan_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_loan_id')->constrained('employee_loans')->cascadeOnDelete();
            $table->foreignId('employee_loan_installment_id')->nullable()->constrained('employee_loan_installments')->nullOnDelete();
            $table->foreignId('payroll_run_id')->nullable()->constrained('payroll_runs')->nullOnDelete();
            $table->date('payment_date');
            $table->enum('source', ['payroll', 'manual'])->default('payroll');
            $table->decimal('principal_amount', 20, 4)->default(0);
            $table->decimal('interest_amount', 20, 4)->default(0);
            $table->decimal('amount', 20, 4)->default(0);
            $table->foreignId('manual_journal_id')->nullable()->constrained('manual_journals')->nullOnDelete();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['employee_loan_id', 'payment_date']);
            $table->index(['source', 'payroll_run_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_loan_payments');
        Schema::dropIfExists('employee_loan_installments');
        Schema::dropIfExists('employee_loans');
    }
};
