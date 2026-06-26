<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('employee_contracts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('contract_number', 80)->unique();
            $table->string('status', 20)->default('draft')->index();
            $table->date('effective_from')->index();
            $table->date('effective_to')->nullable()->index();
            $table->date('signed_at')->nullable();
            $table->decimal('base_salary', 20, 4)->default(0);
            $table->string('salary_cycle', 20)->default('monthly');
            $table->decimal('employee_insurance_rate', 8, 4)->nullable();
            $table->decimal('employer_insurance_rate', 8, 4)->nullable();
            $table->decimal('tax_rate', 8, 4)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'effective_from']);
            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_contracts');
    }
};
