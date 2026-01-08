<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->string('reconciliation_number', 50)->unique();
            $table->unsignedBigInteger('payment_id')->nullable()->comment('customer or supplier payment');
            $table->enum('reconciliation_type', ['bank', 'cash_box', 'pos', 'general']);
            $table->foreignId('bank_id')->nullable()->constrained('banks');
            $table->foreignId('cash_box_id')->nullable()->constrained('cash_boxes');
            $table->foreignId('pos_terminal_id')->nullable()->constrained('pos_terminals');
            $table->decimal('expected_amount', 20, 4)->comment('مبلغ مورد انتظار از سیستم');
            $table->decimal('actual_amount', 20, 4)->comment('مبلغ واقعی از رسید/statement');
            $table->decimal('discrepancy_amount', 20, 4)->comment('اختلاف');
            $table->date('reconciliation_date');
            $table->boolean('is_reconciled')->default(false);
            $table->unsignedBigInteger('reconciled_by_user_id')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->string('bank_statement_reference', 100)->nullable();
            $table->string('receipt_image', 500)->nullable();
            $table->text('discrepancy_notes')->nullable();
            $table->enum('status', ['pending', 'matched', 'discrepancy', 'resolved'])->default('pending');
            $table->timestamps();
            
            $table->index(['payment_id', 'is_reconciled']);
            $table->index(['reconciliation_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reconciliations');
    }
};
