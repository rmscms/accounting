<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_invoices')) {
            Schema::table('customer_invoices', function (Blueprint $table): void {
                $drops = [];
                if (Schema::hasColumn('customer_invoices', 'tax_method')) {
                    $drops[] = 'tax_rate';
                }
                if ($drops !== []) {
                    $table->dropColumn($drops);
                }
            });
        }

        if (Schema::hasTable('supplier_invoices')) {
            Schema::table('supplier_invoices', function (Blueprint $table): void {
                $drops = [];
                if (Schema::hasColumn('supplier_invoices', 'tax_method')) {
                    $drops[] = 'tax_rate';
                }
                if ($drops !== []) {
                    $table->dropColumn($drops);
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customer_invoices')) {
            Schema::table('customer_invoices', function (Blueprint $table): void {
                if (! Schema::hasColumn('customer_invoices', 'tax_rate')) {
                    $table->decimal('tax_rate', 5, 2)->default(0)->after('tax_method');
                }
            });
        }

        if (Schema::hasTable('supplier_invoices')) {
            Schema::table('supplier_invoices', function (Blueprint $table): void {
                if (! Schema::hasColumn('supplier_invoices', 'tax_rate')) {
                    $table->decimal('tax_rate', 5, 2)->default(0)->after('tax_method');
                }
            });
        }
    }
};
