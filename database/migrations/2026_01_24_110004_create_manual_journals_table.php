<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // اسناد دستی
        Schema::create('manual_journals', function (Blueprint $table) {
            $table->id();
            $table->string('journal_number', 50)->unique();
            $table->date('journal_date');
            $table->date('posting_date')->nullable();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->onDelete('restrict');
            $table->text('description');
            $table->text('notes')->nullable();
            $table->decimal('total_debit', 20, 4)->default(0);
            $table->decimal('total_credit', 20, 4)->default(0);
            $table->enum('status', ['draft', 'posted', 'reversed'])->default('draft');
            $table->foreignId('accounting_document_id')->nullable()->constrained('accounting_documents')->onDelete('set null');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('posted_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('reversed_journal_id')->nullable()->constrained('manual_journals')->onDelete('set null')->comment('اگر برگشت خورده');
            $table->text('reversal_reason')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('journal_number');
            $table->index('fiscal_year_id');
            $table->index('status');
            $table->index('journal_date');
        });

        // سطرهای سند دستی
        Schema::create('manual_journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manual_journal_id')->constrained('manual_journals')->onDelete('cascade');
            $table->integer('line_number');
            $table->foreignId('account_id')->constrained('accounts')->onDelete('restrict');
            $table->decimal('debit_amount', 20, 4)->default(0);
            $table->decimal('credit_amount', 20, 4)->default(0);
            $table->string('currency_code', 3)->default('IRR');
            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->decimal('fx_rate', 12, 6)->default(1)->comment('نرخ تبدیل ارز');
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->index('manual_journal_id');
            $table->index('account_id');
            $table->unique(['manual_journal_id', 'line_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_journal_lines');
        Schema::dropIfExists('manual_journals');
    }
};
