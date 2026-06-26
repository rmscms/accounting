<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // اضافه کردن ستون‌های مالیاتی به customer_invoices
        if (Schema::hasTable('customer_invoices')) {
            Schema::table('customer_invoices', function (Blueprint $table) {
                if (!Schema::hasColumn('customer_invoices', 'tax_method')) {
                    $table->string('tax_method', 20)->default('exclusive')->after('payment_status')
                        ->comment('exclusive: بدون مالیات، inclusive: شامل مالیات');
                }
                if (!Schema::hasColumn('customer_invoices', 'tax_rate')) {
                    $table->decimal('tax_rate', 5, 2)->default(0)->after('tax_method')
                        ->comment('نرخ مالیات (درصد)');
                }
            });
        }
        
        // اضافه کردن ستون‌های مالیاتی به supplier_invoices
        if (Schema::hasTable('supplier_invoices')) {
            Schema::table('supplier_invoices', function (Blueprint $table) {
                if (!Schema::hasColumn('supplier_invoices', 'tax_method')) {
                    $table->string('tax_method', 20)->default('exclusive')->after('payment_status')
                        ->comment('exclusive: بدون مالیات، inclusive: شامل مالیات');
                }
                if (!Schema::hasColumn('supplier_invoices', 'tax_rate')) {
                    $table->decimal('tax_rate', 5, 2)->default(0)->after('tax_method')
                        ->comment('نرخ مالیات (درصد)');
                }
            });
        }
        
        // اضافه کردن tax_exempt به customers
        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                if (!Schema::hasColumn('customers', 'tax_exempt')) {
                    $table->boolean('tax_exempt')->default(false)->after('active')
                        ->comment('معاف از مالیات');
                }
            });
        }
        
        // اضافه کردن tax_exempt به suppliers
        if (Schema::hasTable('suppliers')) {
            Schema::table('suppliers', function (Blueprint $table) {
                if (!Schema::hasColumn('suppliers', 'tax_exempt')) {
                    $table->boolean('tax_exempt')->default(false)->after('active')
                        ->comment('معاف از مالیات');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // حذف ستون‌ها از customer_invoices
        if (Schema::hasTable('customer_invoices')) {
            Schema::table('customer_invoices', function (Blueprint $table) {
                $table->dropColumn(['tax_method', 'tax_rate']);
            });
        }
        
        // حذف ستون‌ها از supplier_invoices
        if (Schema::hasTable('supplier_invoices')) {
            Schema::table('supplier_invoices', function (Blueprint $table) {
                $table->dropColumn(['tax_method', 'tax_rate']);
            });
        }
        
        // حذف tax_exempt از customers
        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('tax_exempt');
            });
        }
        
        // حذف tax_exempt از suppliers
        if (Schema::hasTable('suppliers')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->dropColumn('tax_exempt');
            });
        }
    }
};
