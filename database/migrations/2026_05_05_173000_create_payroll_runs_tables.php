<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_number', 50)->unique();
            $table->string('title', 255);
            $table->date('period_start');
            $table->date('period_end');
            $table->date('journal_date');
            $table->string('currency_code', 3)->default('IRR');
            $table->enum('status', ['draft', 'accrued', 'paid', 'insurance_remitted', 'tax_remitted', 'closed'])->default('draft');

            $table->decimal('total_base_salary', 20, 4)->default(0);
            $table->decimal('total_benefits', 20, 4)->default(0);
            $table->decimal('total_gross', 20, 4)->default(0);
            $table->decimal('total_employee_insurance', 20, 4)->default(0);
            $table->decimal('total_employer_insurance', 20, 4)->default(0);
            $table->decimal('total_tax', 20, 4)->default(0);
            $table->decimal('total_other_deductions', 20, 4)->default(0);
            $table->decimal('total_net', 20, 4)->default(0);

            $table->foreignId('accrual_manual_journal_id')->nullable()->constrained('manual_journals')->nullOnDelete();
            $table->foreignId('net_payment_manual_journal_id')->nullable()->constrained('manual_journals')->nullOnDelete();
            $table->foreignId('insurance_remittance_manual_journal_id')->nullable()->constrained('manual_journals')->nullOnDelete();
            $table->foreignId('tax_remittance_manual_journal_id')->nullable()->constrained('manual_journals')->nullOnDelete();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['period_start', 'period_end']);
            $table->index('status');
        });

        Schema::create('payroll_run_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->unsignedInteger('line_number')->default(1);
            $table->decimal('base_salary', 20, 4)->default(0);
            $table->decimal('benefits', 20, 4)->default(0);
            $table->decimal('gross_salary', 20, 4)->default(0);
            $table->decimal('employee_insurance', 20, 4)->default(0);
            $table->decimal('employer_insurance', 20, 4)->default(0);
            $table->decimal('tax', 20, 4)->default(0);
            $table->decimal('other_deductions', 20, 4)->default(0);
            $table->decimal('net_salary', 20, 4)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
            $table->unique(['payroll_run_id', 'line_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_run_lines');
        Schema::dropIfExists('payroll_runs');
    }
};
