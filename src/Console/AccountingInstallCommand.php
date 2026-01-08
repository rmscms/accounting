<?php

namespace RMS\Accounting\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use RMS\Accounting\Database\Seeders\AccountingDatabaseSeeder;

/**
 * دستور نصب پکیج Accounting
 */
class AccountingInstallCommand extends Command
{
    protected $signature = 'accounting:install {--seed : اجرای Seeders}';

    protected $description = 'نصب پکیج Accounting و ایجاد ساختار اولیه';

    public function handle()
    {
        $this->info('🚀 شروع نصب پکیج Accounting...');

        // 1. Publish configs
        $this->info('📝 انتشار فایل‌های تنظیمات...');
        Artisan::call('vendor:publish', [
            '--tag' => 'accounting-config',
            '--force' => true,
        ]);

        // 2. Publish migrations
        $this->info('📝 انتشار Migrations...');
        Artisan::call('vendor:publish', [
            '--tag' => 'accounting-migrations',
            '--force' => true,
        ]);

        // 3. Publish views
        $this->info('📝 انتشار Views...');
        Artisan::call('vendor:publish', [
            '--tag' => 'accounting-views',
            '--force' => true,
        ]);

        // 4. Publish translations
        $this->info('📝 انتشار ترجمه‌ها...');
        Artisan::call('vendor:publish', [
            '--tag' => 'accounting-lang',
            '--force' => true,
        ]);

        // 5. Run migrations
        $this->info('📦 اجرای Migrations...');
        Artisan::call('migrate');

        // 6. Update admin sidebar
        $this->info('📋 به‌روزرسانی منوی مدیریت...');
        $this->updateSidebar();

        // 7. Run seeders if requested
        if ($this->option('seed')) {
            $this->info('🌱 اجرای Seeders...');
            Artisan::call('db:seed', ['--class' => AccountingDatabaseSeeder::class]);
        }

        $this->info('✅ پکیج Accounting با موفقیت نصب شد!');
        $this->line('');
        $this->line('📋 مراحل بعدی:');
        $this->line('1. فایل config/accounting.php را بررسی و تنظیم کنید');
        $this->line('2. سال مالی فعلی را تعیین کنید');
        $this->line('3. حساب‌های اضافی مورد نیاز را ایجاد کنید');
        $this->line('4. از دستور accounting:install --seed برای ایجاد داده‌های اولیه استفاده کنید');

        return 0;
    }

    /**
     * به‌روزرسانی sidebar ادمین
     */
    protected function updateSidebar()
    {
        $sidebarPath = resource_path('views/vendor/cms/admin/layout/sidebar.blade.php');

        if (!File::exists($sidebarPath)) {
            $this->error('❌ فایل sidebar یافت نشد! ابتدا views پکیج CMS را publish کنید.');
            return;
        }

        $content = File::get($sidebarPath);

        // بررسی تکراری نبودن
        if (strpos($content, '{{-- Accounting Management --}}') !== false) {
            $this->info('ℹ️ منوی حسابداری قبلاً در sidebar وجود دارد.');
            return;
        }

        // بارگذاری از stub
        $stubPath = __DIR__ . '/../../resources/stubs/accounting-menu.blade.stub';
        if (!File::exists($stubPath)) {
            $this->error('❌ فایل stub منوی حسابداری یافت نشد!');
            return;
        }

        $accountingMenu = "\n" . File::get($stubPath) . "\n";

        // درج قبل از اولین </ul>
        $content = preg_replace('/(\\s*<\\/ul>)/', $accountingMenu . '$1', $content, 1);
        File::put($sidebarPath, $content);
        
        $this->info('✅ منوی حسابداری به sidebar اضافه شد.');
    }
}
