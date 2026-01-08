<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('کد حساب مثلاً: 1-100, 4-1001');
            $table->string('name', 255)->comment('نام حساب');
            $table->tinyInteger('level')->comment('1=کل, 2=معین, 3=تفصیلی');
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->onDelete('restrict');
            $table->enum('account_type', ['asset', 'liability', 'equity', 'income', 'expense'])->comment('نوع حساب');
            $table->boolean('is_system')->default(false)->comment('حساب سیستمی');
            $table->string('currency_code', 3)->nullable()->comment('ارز پیش‌فرض حساب');
            $table->boolean('active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('code');
            $table->index(['account_type', 'active']);
            $table->index('parent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
