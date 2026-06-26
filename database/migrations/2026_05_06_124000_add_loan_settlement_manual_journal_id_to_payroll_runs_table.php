<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table): void {
            if (! Schema::hasColumn('payroll_runs', 'loan_settlement_manual_journal_id')) {
                $table->foreignId('loan_settlement_manual_journal_id')
                    ->nullable()
                    ->after('tax_remittance_manual_journal_id')
                    ->constrained('manual_journals')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table): void {
            if (Schema::hasColumn('payroll_runs', 'loan_settlement_manual_journal_id')) {
                $table->dropConstrainedForeignId('loan_settlement_manual_journal_id');
            }
        });
    }
};
