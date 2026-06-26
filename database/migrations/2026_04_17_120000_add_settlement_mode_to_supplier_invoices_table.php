<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->string('settlement_mode', 32)->default('on_account')->after('payment_status');
            $table->foreignId('paid_at_source_bank_id')->nullable()->after('settlement_mode')->constrained('banks')->nullOnDelete();
            $table->foreignId('paid_at_source_cash_box_id')->nullable()->after('paid_at_source_bank_id')->constrained('cash_boxes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->dropForeign(['paid_at_source_bank_id']);
            $table->dropForeign(['paid_at_source_cash_box_id']);
            $table->dropColumn(['settlement_mode', 'paid_at_source_bank_id', 'paid_at_source_cash_box_id']);
        });
    }
};
