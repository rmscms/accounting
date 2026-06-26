<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_run_lines', function (Blueprint $table): void {
            if (! Schema::hasColumn('payroll_run_lines', 'skip_loan_deduction')) {
                $table->boolean('skip_loan_deduction')
                    ->default(false)
                    ->after('other_deductions');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_run_lines', function (Blueprint $table): void {
            if (Schema::hasColumn('payroll_run_lines', 'skip_loan_deduction')) {
                $table->dropColumn('skip_loan_deduction');
            }
        });
    }
};
