<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('employee_contracts', function (Blueprint $table): void {
            if (! Schema::hasColumn('employee_contracts', 'seniority_monthly_default')) {
                $table->decimal('seniority_monthly_default', 20, 4)->nullable()->after('base_salary');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employee_contracts', function (Blueprint $table): void {
            if (Schema::hasColumn('employee_contracts', 'seniority_monthly_default')) {
                $table->dropColumn('seniority_monthly_default');
            }
        });
    }
};
