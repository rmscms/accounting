<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('customer_invoices', 'paid_at_source_cheque_id')) {
                $table->foreignId('paid_at_source_cheque_id')->nullable()->after('paid_at_source_cash_box_id')
                    ->constrained('cheques')->cascadeOnUpdate()->nullOnDelete();
            }
        });

        Schema::table('supplier_invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('supplier_invoices', 'paid_at_source_cheque_id')) {
                $table->foreignId('paid_at_source_cheque_id')->nullable()->after('paid_at_source_cash_box_id')
                    ->constrained('cheques')->cascadeOnUpdate()->nullOnDelete();
            }
        });

        Schema::table('supplier_refunds', function (Blueprint $table): void {
            if (! Schema::hasColumn('supplier_refunds', 'payment_method_id')) {
                $table->foreignId('payment_method_id')->nullable()->after('refund_method')
                    ->constrained('payment_methods')->cascadeOnUpdate()->nullOnDelete();
            }
            if (! Schema::hasColumn('supplier_refunds', 'cheque_id')) {
                $table->foreignId('cheque_id')->nullable()->after('cash_box_id')
                    ->constrained('cheques')->cascadeOnUpdate()->nullOnDelete();
            }
            if (! Schema::hasColumn('supplier_refunds', 'wallet_id')) {
                $table->foreignId('wallet_id')->nullable()->after('cheque_id')
                    ->constrained('wallets')->cascadeOnUpdate()->nullOnDelete();
            }
            if (! Schema::hasColumn('supplier_refunds', 'pos_terminal_id')) {
                $table->foreignId('pos_terminal_id')->nullable()->after('wallet_id')
                    ->constrained('pos_terminals')->cascadeOnUpdate()->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('supplier_refunds', function (Blueprint $table): void {
            if (Schema::hasColumn('supplier_refunds', 'pos_terminal_id')) {
                $table->dropConstrainedForeignId('pos_terminal_id');
            }
            if (Schema::hasColumn('supplier_refunds', 'wallet_id')) {
                $table->dropConstrainedForeignId('wallet_id');
            }
            if (Schema::hasColumn('supplier_refunds', 'cheque_id')) {
                $table->dropConstrainedForeignId('cheque_id');
            }
            if (Schema::hasColumn('supplier_refunds', 'payment_method_id')) {
                $table->dropConstrainedForeignId('payment_method_id');
            }
        });

        Schema::table('supplier_invoices', function (Blueprint $table): void {
            if (Schema::hasColumn('supplier_invoices', 'paid_at_source_cheque_id')) {
                $table->dropConstrainedForeignId('paid_at_source_cheque_id');
            }
        });

        Schema::table('customer_invoices', function (Blueprint $table): void {
            if (Schema::hasColumn('customer_invoices', 'paid_at_source_cheque_id')) {
                $table->dropConstrainedForeignId('paid_at_source_cheque_id');
            }
        });
    }
};

