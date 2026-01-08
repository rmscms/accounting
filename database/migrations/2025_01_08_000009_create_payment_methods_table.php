<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('CASH, POS, ONLINE, CHEQUE, WALLET');
            $table->string('name', 255);
            $table->enum('type', ['cash', 'pos', 'online', 'cheque', 'card_transfer', 'wallet']);
            $table->boolean('requires_bank')->default(false);
            $table->boolean('requires_pos')->default(false);
            $table->boolean('requires_gateway')->default(false);
            $table->foreignId('account_id')->nullable()->constrained('accounts')->comment('حساب پیش‌فرض');
            $table->boolean('active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index(['active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
