<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_invoices', 'document_id')) {
                $table->unsignedBigInteger('document_id')->nullable()->after('balance_due');
                $table->index('document_id');
            }
            if (! Schema::hasColumn('customer_invoices', 'settlement_mode')) {
                $table->string('settlement_mode', 20)->default('credit')->after('document_id');
            }
            if (! Schema::hasColumn('customer_invoices', 'upfront_payment_amount')) {
                $table->decimal('upfront_payment_amount', 20, 4)->default(0)->after('settlement_mode');
            }
            if (! Schema::hasColumn('customer_invoices', 'paid_at_source_bank_id')) {
                $table->unsignedBigInteger('paid_at_source_bank_id')->nullable()->after('upfront_payment_amount');
            }
            if (! Schema::hasColumn('customer_invoices', 'paid_at_source_cash_box_id')) {
                $table->unsignedBigInteger('paid_at_source_cash_box_id')->nullable()->after('paid_at_source_bank_id');
            }
            if (! Schema::hasColumn('customer_invoices', 'paid_at_source_wallet_id')) {
                $table->unsignedBigInteger('paid_at_source_wallet_id')->nullable()->after('paid_at_source_cash_box_id');
            }
        });

        if (! Schema::hasTable('customer_invoice_items')) {
            Schema::create('customer_invoice_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_invoice_id');
                $table->unsignedBigInteger('product_id')->nullable();
                $table->string('product_sku', 100)->nullable();
                $table->string('product_name');
                $table->decimal('quantity', 20, 4)->default(1);
                $table->decimal('price', 20, 4)->default(0);
                $table->decimal('tax_rate', 8, 4)->default(0);
                $table->decimal('discount_amount', 20, 4)->default(0);
                $table->decimal('tax_amount', 20, 4)->default(0);
                $table->decimal('total', 20, 4)->default(0);
                $table->timestamps();

                $table->index(['customer_invoice_id', 'id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customer_invoice_items')) {
            Schema::drop('customer_invoice_items');
        }

        Schema::table('customer_invoices', function (Blueprint $table) {
            foreach ([
                'paid_at_source_wallet_id',
                'paid_at_source_cash_box_id',
                'paid_at_source_bank_id',
                'upfront_payment_amount',
                'settlement_mode',
                'document_id',
            ] as $col) {
                if (Schema::hasColumn('customer_invoices', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
