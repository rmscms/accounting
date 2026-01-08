<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->enum('wallet_type', ['customer', 'supplier', 'employee']);
            $table->decimal('balance', 20, 4)->default(0);
            $table->string('currency_code', 3)->default('IRR');
            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->foreignId('account_id')->nullable()->constrained('accounts');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['user_id', 'wallet_type']);
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
