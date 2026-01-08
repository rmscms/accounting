<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->string('code', 3)->primary()->comment('ISO 4217: IRR, USD, EUR, CNY');
            $table->string('name', 100);
            $table->string('symbol', 10)->nullable();
            $table->boolean('is_base')->default(false)->comment('فقط IRR = true');
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
