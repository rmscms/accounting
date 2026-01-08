<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_terminals', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('serial_number', 100)->unique();
            $table->string('terminal_id', 50);
            $table->foreignId('bank_id')->constrained('banks')->onDelete('restrict');
            $table->string('merchant_id', 100)->nullable();
            $table->string('location', 255)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_terminals');
    }
};
