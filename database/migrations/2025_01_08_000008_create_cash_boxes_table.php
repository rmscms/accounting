<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_boxes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('location', 255)->nullable();
            $table->foreignId('account_id')->constrained('accounts')->onDelete('restrict')->comment('حساب صندوق در دفاتر');
            $table->decimal('balance', 20, 4)->default(0);
            $table->string('currency_code', 3)->default('IRR');
            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->unsignedBigInteger('responsible_user_id')->nullable()->comment('مسئول صندوق');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_boxes');
    }
};
