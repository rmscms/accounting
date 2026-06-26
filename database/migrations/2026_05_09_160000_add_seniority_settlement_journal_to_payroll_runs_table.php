<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table): void {
            if (! Schema::hasColumn('payroll_runs', 'seniority_settlement_manual_journal_id')) {
                $table->foreignId('seniority_settlement_manual_journal_id')
                    ->nullable()
                    ->after('loan_settlement_manual_journal_id')
                    ->constrained('manual_journals')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table): void {
            if (Schema::hasColumn('payroll_runs', 'seniority_settlement_manual_journal_id')) {
                $table->dropConstrainedForeignId('seniority_settlement_manual_journal_id');
            }
        });
    }
};
