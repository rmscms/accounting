<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number', 50)->unique();
            $table->foreignId('bank_id')->constrained('banks')->onDelete('restrict');
            $table->enum('transaction_type', ['charge', 'interest_income', 'interest_expense', 'fee', 'other'])->default('charge');
            $table->date('transaction_date');
            $table->decimal('amount', 20, 4);
            $table->string('currency_code', 3)->default('IRR');
            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->decimal('fx_rate', 12, 6)->default(1)->comment('نرخ تبدیل ارز');
            $table->foreignId('charge_type_account_id')->constrained('accounts')->onDelete('restrict')->comment('حساب نوع کارمزد/سود');
            $table->string('reference_number', 100)->nullable()->comment('شماره مرجع');
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('accounting_document_id')->nullable()->constrained('accounting_documents')->onDelete('set null');
            $table->enum('status', ['draft', 'posted'])->default('draft');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('posted_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('transaction_number');
            $table->index('bank_id');
            $table->index('transaction_type');
            $table->index('status');
            $table->index('transaction_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
