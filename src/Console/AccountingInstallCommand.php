<?php

namespace RMS\Accounting\Console;

use Illuminate\Console\Command;
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

        // Publish configs
        $this->call('vendor:publish', [
            '--tag' => 'accounting-config',
            '--force' => true,
        ]);

        // Publish migrations
        $this->call('vendor:publish', [
            '--tag' => 'accounting-migrations',
            '--force' => true,
        ]);

        // Run migrations
        $this->info('📦 اجرای Migrations...');
        $this->call('migrate');

        // Run seeders if requested
        if ($this->option('seed')) {
            $this->info('🌱 اجرای Seeders...');
            $this->call('db:seed', ['--class' => AccountingDatabaseSeeder::class]);
        }

        $this->info('✅ پکیج Accounting با موفقیت نصب شد!');
        $this->line('');
        $this->line('📋 مراحل بعدی:');
        $this->line('1. فایل config/accounting.php را بررسی و تنظیم کنید');
        $this->line('2. سال مالی فعلی را تعیین کنید');
        $this->line('3. حساب‌های اضافی مورد نیاز را ایجاد کنید');
        $this->line('4. از دستور accounting:seed برای ایجاد داده‌های اولیه استفاده کنید');

        return 0;
    }
}
