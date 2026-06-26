<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table): void {
            if (! Schema::hasColumn('payroll_runs', 'total_seniority')) {
                $table->decimal('total_seniority', 20, 4)->default(0)->after('total_benefits');
            }
        });

        Schema::table('payroll_run_lines', function (Blueprint $table): void {
            if (! Schema::hasColumn('payroll_run_lines', 'seniority')) {
                $table->decimal('seniority', 20, 4)->default(0)->after('benefits');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_run_lines', function (Blueprint $table): void {
            if (Schema::hasColumn('payroll_run_lines', 'seniority')) {
                $table->dropColumn('seniority');
            }
        });

        Schema::table('payroll_runs', function (Blueprint $table): void {
            if (Schema::hasColumn('payroll_runs', 'total_seniority')) {
                $table->dropColumn('total_seniority');
            }
        });
    }
};
