<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_advances', function (Blueprint $table) {
            if (! Schema::hasColumn('supplier_advances', 'payment_method_id')) {
                $table->foreignId('payment_method_id')->nullable()->after('payment_method')->constrained('payment_methods')->nullOnDelete();
            }
            if (! Schema::hasColumn('supplier_advances', 'cheque_id')) {
                $table->foreignId('cheque_id')->nullable()->after('cash_box_id')->constrained('cheques')->nullOnDelete();
            }
            if (! Schema::hasColumn('supplier_advances', 'pos_terminal_id')) {
                $table->foreignId('pos_terminal_id')->nullable()->after('cheque_id')->constrained('pos_terminals')->nullOnDelete();
            }
            if (! Schema::hasColumn('supplier_advances', 'wallet_id')) {
                $table->foreignId('wallet_id')->nullable()->after('pos_terminal_id')->constrained('wallets')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('supplier_advances', function (Blueprint $table) {
            if (Schema::hasColumn('supplier_advances', 'wallet_id')) {
                $table->dropConstrainedForeignId('wallet_id');
            }
            if (Schema::hasColumn('supplier_advances', 'pos_terminal_id')) {
                $table->dropConstrainedForeignId('pos_terminal_id');
            }
            if (Schema::hasColumn('supplier_advances', 'cheque_id')) {
                $table->dropConstrainedForeignId('cheque_id');
            }
            if (Schema::hasColumn('supplier_advances', 'payment_method_id')) {
                $table->dropConstrainedForeignId('payment_method_id');
            }
        });
    }
};
