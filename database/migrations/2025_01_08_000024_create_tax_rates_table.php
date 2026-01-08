<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->decimal('rate', 5, 2)->comment('درصد مالیات');
            $table->enum('tax_type', ['vat', 'income_tax', 'withholding_tax', 'other']);
            $table->foreignId('account_receivable_id')->nullable()->constrained('accounts')->comment('حساب مالیات دریافتنی');
            $table->foreignId('account_payable_id')->nullable()->constrained('accounts')->comment('حساب مالیات پرداختنی');
            $table->boolean('is_default')->default(false);
            $table->boolean('active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();
            
            $table->index(['active', 'is_default']);
            $table->index('tax_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};
