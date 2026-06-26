<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chequebooks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_id')->constrained('banks')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('title', 255);
            $table->string('book_number', 100)->nullable();
            $table->unsignedBigInteger('serial_from')->nullable();
            $table->unsignedBigInteger('serial_to')->nullable();
            $table->unsignedBigInteger('next_serial')->nullable();
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['bank_id', 'active'], 'chequebooks_bank_active_idx');
            $table->unique(['bank_id', 'book_number'], 'chequebooks_bank_book_number_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chequebooks');
    }
};

