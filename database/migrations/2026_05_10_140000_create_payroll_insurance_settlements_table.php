<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_insurance_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('manual_journal_id')->nullable()->constrained('manual_journals')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();
            $table->decimal('amount', 18, 4)->default(0);
            $table->date('journal_date')->nullable();
            $table->string('status', 32)->default('open');
            $table->text('description')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->unsignedBigInteger('settled_by_user_id')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['status', 'journal_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_insurance_settlements');
    }
};

