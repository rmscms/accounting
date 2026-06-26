<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shareholders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('national_id', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('capital_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('drawings_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('national_id', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('payroll_expense_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('wages_payable_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('shareholder_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shareholder_id')->constrained('shareholders')->cascadeOnDelete();
            $table->decimal('amount', 20, 4);
            $table->string('currency_code', 3)->default('IRR');
            $table->date('journal_date');
            $table->string('source_type', 16);
            $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();
            $table->foreignId('cash_box_id')->nullable()->constrained('cash_boxes')->nullOnDelete();
            $table->text('description')->nullable();
            $table->foreignId('manual_journal_id')->nullable()->constrained('manual_journals')->nullOnDelete();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('shareholder_capital_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shareholder_id')->constrained('shareholders')->cascadeOnDelete();
            $table->decimal('amount', 20, 4);
            $table->string('currency_code', 3)->default('IRR');
            $table->date('journal_date');
            $table->string('source_type', 16);
            $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();
            $table->foreignId('cash_box_id')->nullable()->constrained('cash_boxes')->nullOnDelete();
            $table->text('description')->nullable();
            $table->foreignId('manual_journal_id')->nullable()->constrained('manual_journals')->nullOnDelete();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shareholder_capital_contributions');
        Schema::dropIfExists('shareholder_withdrawals');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('shareholders');
    }
};
