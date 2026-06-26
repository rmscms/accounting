<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // دسته‌بندی دارایی‌های ثابت
        Schema::create('fixed_asset_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('code', 50)->unique();
            $table->text('description')->nullable();
            $table->foreignId('asset_account_id')->nullable()->constrained('accounts')->onDelete('restrict')->comment('حساب دارایی');
            $table->foreignId('depreciation_account_id')->nullable()->constrained('accounts')->onDelete('restrict')->comment('حساب هزینه استهلاک');
            $table->foreignId('accumulated_depreciation_account_id')->nullable()->constrained('accounts')->onDelete('restrict')->comment('حساب استهلاک انباشته');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('active');
            $table->index('code');
        });

        // دارایی‌های ثابت
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code', 50)->unique();
            $table->string('name', 255);
            $table->foreignId('category_id')->constrained('fixed_asset_categories')->onDelete('restrict');
            $table->date('purchase_date');
            $table->decimal('purchase_price', 20, 4);
            $table->integer('useful_life_years');
            $table->integer('useful_life_months')->default(0);
            $table->enum('depreciation_method', ['straight_line', 'declining_balance', 'units_of_production'])->default('straight_line');
            $table->decimal('declining_balance_rate', 5, 2)->nullable()->comment('درصد استهلاک (برای declining_balance)');
            $table->integer('total_units')->nullable()->comment('واحدهای کل (برای units_of_production)');
            $table->decimal('salvage_value', 20, 4)->default(0)->comment('ارزش اسقاط');
            $table->decimal('accumulated_depreciation', 20, 4)->default(0)->comment('استهلاک انباشته');
            $table->decimal('book_value', 20, 4)->comment('ارزش دفتری');
            $table->foreignId('asset_account_id')->nullable()->constrained('accounts')->onDelete('restrict')->comment('حساب دارایی (override از category)');
            $table->foreignId('depreciation_account_id')->nullable()->constrained('accounts')->onDelete('restrict')->comment('حساب هزینه استهلاک');
            $table->foreignId('accumulated_depreciation_account_id')->nullable()->constrained('accounts')->onDelete('restrict')->comment('حساب استهلاک انباشته');
            $table->enum('status', ['active', 'disposed', 'fully_depreciated'])->default('active');
            $table->date('disposal_date')->nullable();
            $table->decimal('disposal_value', 20, 4)->nullable();
            $table->string('location', 255)->nullable();
            $table->string('serial_number', 255)->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('asset_code');
            $table->index('category_id');
            $table->index('status');
            $table->index('purchase_date');
        });

        // برنامه استهلاک
        Schema::create('depreciation_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->onDelete('cascade');
            $table->date('period_date')->comment('تاریخ دوره (ماه/سال)');
            $table->decimal('opening_book_value', 20, 4)->comment('ارزش دفتری ابتدای دوره');
            $table->decimal('depreciation_amount', 20, 4)->comment('مبلغ استهلاک');
            $table->decimal('closing_book_value', 20, 4)->comment('ارزش دفتری انتهای دوره');
            $table->integer('units_produced')->nullable()->comment('واحدهای تولید شده (برای units_of_production)');
            $table->boolean('posted')->default(false)->comment('ثبت شده در دفاتر');
            $table->foreignId('accounting_document_id')->nullable()->constrained('accounting_documents')->onDelete('set null');
            $table->timestamps();
            
            $table->index('fixed_asset_id');
            $table->index('period_date');
            $table->index('posted');
            $table->unique(['fixed_asset_id', 'period_date']);
        });

        // ثبت استهلاک دوره‌ای
        Schema::create('depreciation_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->onDelete('cascade');
            $table->foreignId('depreciation_schedule_id')->nullable()->constrained('depreciation_schedules')->onDelete('set null');
            $table->date('entry_date');
            $table->decimal('depreciation_amount', 20, 4);
            $table->foreignId('accounting_document_id')->nullable()->constrained('accounting_documents')->onDelete('set null');
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->index('fixed_asset_id');
            $table->index('entry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depreciation_entries');
        Schema::dropIfExists('depreciation_schedules');
        Schema::dropIfExists('fixed_assets');
        Schema::dropIfExists('fixed_asset_categories');
    }
};
