<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currency_rates', function (Blueprint $table) {
            $table->id();
            $table->string('currency_code', 3);
            $table->foreign('currency_code')->references('code')->on('currencies')->onDelete('cascade');
            $table->decimal('rate_to_irr', 12, 6)->comment('نرخ تبدیل به ریال');
            $table->date('rate_date')->comment('تاریخ نرخ');
            $table->enum('source', ['manual', 'api', 'system'])->default('manual');
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->unique(['currency_code', 'rate_date']);
            $table->index('rate_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_rates');
    }
};
