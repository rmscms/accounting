<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->string('year_code', 20)->unique()->comment('کد سال مالی مثلاً: 1403, FY-2024');
            $table->date('start_date')->comment('تاریخ شروع سال مالی');
            $table->date('end_date')->comment('تاریخ پایان سال مالی');
            $table->enum('status', ['open', 'locked', 'closed'])->default('open');
            $table->boolean('is_current')->default(false)->comment('فقط یک سال current است');
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by_user_id')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('year_code');
            $table->index(['status', 'is_current']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_years');
    }
};
