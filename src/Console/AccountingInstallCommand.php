<?php

namespace RMS\Accounting\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use RMS\Accounting\Database\Seeders\AccountingDatabaseSeeder;
use RMS\Accounting\Services\AccountingInstallService;

/**
 * دستور نصب پکیج Accounting — پیش‌فرض: همان منطق ویزارد (سید + نگاشت).
 * گزینهٔ --bootstrap: publish، migrate و منوی قدیمی (فقط در صورت نیاز صریح).
 */
class AccountingInstallCommand extends Command
{
    protected $signature = 'accounting:install {--bootstrap : Publish configs/migrations/views/lang, migrate, seed AccountingDatabaseSeeder, patch sidebar}';

    protected $description = 'Run accounting initial setup (wizard logic). Use --bootstrap for full vendor publish + migrate + legacy sidebar.';

    public function handle(AccountingInstallService $installService): int
    {
        if ($this->option('bootstrap')) {
            return $this->runBootstrap();
        }

        $this->info('Running accounting install wizard (seed + map settings)...');
        $result = $installService->runAll();

        foreach ($result['steps'] as $row) {
            $status = $row['status'] ?? '';
            $line = sprintf(
                '[%s] %s (%s): %s',
                $status,
                $row['label'] ?? ($row['key'] ?? ''),
                $row['type'] ?? '',
                $row['detail'] ?? ''
            );
            if ($status === 'error') {
                $this->error($line);
            } elseif ($status === 'skipped') {
                $this->comment($line);
            } else {
                $this->line($line);
            }
        }

        if ($result['success']) {
            $this->info('Accounting install completed successfully.');

            return self::SUCCESS;
        }

        $this->warn('Accounting install finished with errors or incomplete mapping.');

        return self::FAILURE;
    }

    protected function runBootstrap(): int
    {
        $this->info('Bootstrap mode: publishing, migrating, seeding (legacy behaviour)...');

        $this->info('Publishing config...');
        Artisan::call('vendor:publish', [
            '--tag' => 'accounting-config',
            '--force' => true,
        ]);

        $this->info('Publishing migrations...');
        Artisan::call('vendor:publish', [
            '--tag' => 'accounting-migrations',
            '--force' => true,
        ]);

        $this->info('Publishing views...');
        Artisan::call('vendor:publish', [
            '--tag' => 'accounting-views',
            '--force' => true,
        ]);

        $this->info('Publishing translations...');
        Artisan::call('vendor:publish', [
            '--tag' => 'accounting-lang',
            '--force' => true,
        ]);

        $this->info('Running migrations...');
        Artisan::call('migrate');

        $this->info('Running AccountingDatabaseSeeder...');
        Artisan::call('db:seed', ['--class' => AccountingDatabaseSeeder::class]);

        $this->info('Updating admin sidebar (if stub exists)...');
        $this->updateSidebar();

        $this->info('Bootstrap finished. For chart/settings alignment run: php artisan accounting:install (without --bootstrap)');

        return self::SUCCESS;
    }

    /**
     * به‌روزرسانی sidebar ادمین
     */
    protected function updateSidebar(): void
    {
        $sidebarPath = resource_path('views/vendor/cms/admin/layout/sidebar.blade.php');

        if (! File::exists($sidebarPath)) {
            $this->error('Sidebar file not found. Publish CMS views first.');

            return;
        }

        $content = File::get($sidebarPath);

        if (strpos($content, '{{-- Accounting Management --}}') !== false) {
            $this->comment('Accounting menu already present in sidebar.');

            return;
        }

        $stubPath = __DIR__.'/../../resources/stubs/accounting-menu.blade.stub';
        if (! File::exists($stubPath)) {
            $this->error('Accounting menu stub not found.');

            return;
        }

        $accountingMenu = "\n".File::get($stubPath)."\n";
        $content = preg_replace('/(\\s*<\\/ul>)/', $accountingMenu.'$1', $content, 1);
        File::put($sidebarPath, $content);
        $this->info('Accounting menu appended to sidebar.');
    }
}
