<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_run_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_line_id')->constrained('payroll_run_lines')->cascadeOnDelete();
            $table->enum('type', ['earning', 'deduction', 'employer_contribution']);
            $table->string('code', 80);
            $table->string('title', 255);
            $table->decimal('amount', 20, 4)->default(0);
            $table->unsignedInteger('sort_order')->default(1);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_auto_calculated')->default(false);
            $table->boolean('is_manual_override')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['payroll_run_line_id', 'type']);
            $table->index(['payroll_run_line_id', 'code']);
            $table->index(['payroll_run_line_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_run_line_items');
    }
};
