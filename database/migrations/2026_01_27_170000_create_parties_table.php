<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('parties')) {
            Schema::create('parties', function (Blueprint $table) {
                $table->id();
                $table->string('name', 255);
                $table->string('national_code', 50)->unique()->nullable()->comment('کد ملی/شناسه ملی');
                $table->string('tax_number', 50)->nullable()->comment('شماره مالیاتی');
                $table->string('phone', 20)->nullable();
                $table->string('email', 100)->nullable();
                $table->text('address')->nullable();
                $table->string('contact_person', 255)->nullable()->comment('شخص تماس (برای شرکت‌ها)');
                $table->enum('type', ['individual', 'company'])->default('individual');
                $table->boolean('active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
                
                $table->index('active');
                $table->index('national_code');
                $table->index('type');
            });
        } else {
            // جدول وجود دارد، ستون‌های مورد نیاز را اضافه می‌کنیم
            Schema::table('parties', function (Blueprint $table) {
                if (!Schema::hasColumn('parties', 'name')) {
                    $table->string('name', 255)->after('id');
                }
                if (!Schema::hasColumn('parties', 'national_code')) {
                    $table->string('national_code', 50)->unique()->nullable()->after('name')->comment('کد ملی/شناسه ملی');
                }
                if (!Schema::hasColumn('parties', 'tax_number')) {
                    $table->string('tax_number', 50)->nullable()->after('national_code')->comment('شماره مالیاتی');
                }
                if (!Schema::hasColumn('parties', 'phone')) {
                    $table->string('phone', 20)->nullable()->after('tax_number');
                }
                if (!Schema::hasColumn('parties', 'email')) {
                    $table->string('email', 100)->nullable()->after('phone');
                }
                if (!Schema::hasColumn('parties', 'address')) {
                    $table->text('address')->nullable()->after('email');
                }
                if (!Schema::hasColumn('parties', 'contact_person')) {
                    $table->string('contact_person', 255)->nullable()->after('address')->comment('شخص تماس (برای شرکت‌ها)');
                }
                if (!Schema::hasColumn('parties', 'type')) {
                    $table->enum('type', ['individual', 'company'])->default('individual')->after('contact_person');
                }
                if (!Schema::hasColumn('parties', 'active')) {
                    $table->boolean('active')->default(true)->after('type');
                }
                if (!Schema::hasColumn('parties', 'notes')) {
                    $table->text('notes')->nullable()->after('active');
                }
                if (!Schema::hasColumn('parties', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
            
            // اضافه کردن index ها (اگر وجود ندارند)
            try {
                Schema::table('parties', function (Blueprint $table) {
                    $table->index('active', 'parties_active_index');
                });
            } catch (\Exception $e) {
                // Index ممکن است وجود داشته باشد
            }
            
            try {
                Schema::table('parties', function (Blueprint $table) {
                    $table->index('national_code', 'parties_national_code_index');
                });
            } catch (\Exception $e) {
                // Index ممکن است وجود داشته باشد
            }
            
            try {
                Schema::table('parties', function (Blueprint $table) {
                    $table->index('type', 'parties_type_index');
                });
            } catch (\Exception $e) {
                // Index ممکن است وجود داشته باشد
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('parties');
    }
};
