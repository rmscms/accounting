<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('branch_name', 255)->nullable();
            $table->string('account_number', 50)->nullable();
            $table->string('iban', 50)->nullable();
            $table->string('swift_code', 20)->nullable();
            $table->foreignId('account_id')->constrained('accounts')->onDelete('restrict')->comment('حساب معین بانک در دفاتر');
            $table->decimal('balance', 20, 4)->default(0);
            $table->string('currency_code', 3)->default('IRR');
            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banks');
    }
};
