<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accruals', function (Blueprint $table) {
            $table->id();
            $table->string('accrual_number')->unique();
            $table->enum('accrual_type', ['accrued_revenue', 'accrued_expense', 'deferred_revenue', 'deferred_expense']);
            $table->date('accrual_date');
            $table->decimal('amount', 15, 2);
            $table->foreignId('account_id')->constrained('accounts')->onDelete('restrict');
            $table->string('description');
            $table->date('reversal_date')->nullable();
            $table->boolean('is_reversed')->default(false);
            $table->foreignId('reversal_document_id')->nullable()->constrained('accounting_documents')->onDelete('set null');
            $table->foreignId('accounting_document_id')->nullable()->constrained('accounting_documents')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('accrual_type'); $table->index('accrual_date'); $table->index('is_reversed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accruals');
    }
};
