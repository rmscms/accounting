<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number', 50)->unique();
            $table->foreignId('from_bank_id')->constrained('banks')->onDelete('restrict')->comment('بانک مبدا');
            $table->foreignId('to_bank_id')->constrained('banks')->onDelete('restrict')->comment('بانک مقصد');
            $table->decimal('amount', 20, 4);
            $table->string('currency_code', 3)->default('IRR');
            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->decimal('fx_rate', 12, 6)->default(1)->comment('نرخ تبدیل ارز');
            $table->date('transfer_date');
            $table->date('value_date')->nullable()->comment('تاریخ ارزش');
            $table->decimal('transfer_fee', 20, 4)->default(0)->comment('کارمزد انتقال');
            $table->foreignId('transfer_fee_account_id')->nullable()->constrained('accounts')->onDelete('restrict')->comment('حساب کارمزد');
            $table->string('reference_number', 100)->nullable()->comment('شماره پیگیری بانک');
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('accounting_document_id')->nullable()->constrained('accounting_documents')->onDelete('set null');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('processed_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('transfer_number');
            $table->index('from_bank_id');
            $table->index('to_bank_id');
            $table->index('status');
            $table->index('transfer_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transfers');
    }
};
