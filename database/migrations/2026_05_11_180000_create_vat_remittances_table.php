<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vat_remittances', function (Blueprint $table): void {
            $table->id();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->date('payment_date');
            $table->decimal('amount', 18, 4);
            $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();
            $table->foreignId('cash_box_id')->nullable()->constrained('cash_boxes')->nullOnDelete();
            $table->foreignId('wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
            $table->foreignId('accounting_document_id')->nullable()->constrained('accounting_documents')->nullOnDelete();
            $table->string('status', 32)->default('posted');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['payment_date', 'status']);
            $table->index(['period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vat_remittances');
    }
};
