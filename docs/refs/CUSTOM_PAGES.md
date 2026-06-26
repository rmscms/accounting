# 📄 Custom Pages Guide

## صفحات اختصاصی (بدون CRUD)

صفحات اختصاصی مانند Dashboard، Reports، Settings که نیاز به CRUD ندارند.

---

## ✅ استاندارد Custom Page Controller

```php
<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Filesystem\Filesystem;

/**
 * Custom Page Controller
 * 
 * برای صفحاتی که CRUD ندارند (Dashboard, Reports, Settings, etc.)
 */
class CustomPageController extends AccountingAdminController
{
    /**
     * Constructor
     * 
     * ⚠️ فقط Filesystem نیاز است
     */
    public function __construct(Filesystem $filesystem)
    {
        parent::__construct($filesystem);
    }
    
    /**
     * نمایش صفحه
     */
    public function index(Request $request = null)
    {
        // دریافت داده‌ها
        $data = $this->getData();
        
        // استفاده از view builder
        $this->view
            ->usePackageNamespace('accounting')
            ->setTpl('custom-page.index')
            ->withCss('vendor/accounting/admin/css/custom-page.css', true)
            ->withJs('vendor/accounting/admin/js/custom-page.js', true)
            ->withVariables([
                'data' => $data,
            ]);
        
        return $this->view();
    }
    
    /**
     * پردازش فرم (اگر نیاز باشد)
     */
    public function process(Request $request)
    {
        $validated = $request->validate([
            'field' => 'required',
        ]);
        
        // انجام عملیات
        
        return redirect()
            ->route('admin.accounting.custom-page.index')
            ->with('success', 'عملیات با موفقیت انجام شد');
    }
    
    // ==================== متدهای الزامی ====================
    
    /**
     * ⚠️ برای custom pages، این متدها خالی برمی‌گردند
     */
    public function table(): string
    {
        return '';
    }
    
    public function modelName(): string
    {
        return '';
    }
    
    // ==================== Helper Methods ====================
    
    protected function getData(): array
    {
        // logic دریافت داده
        return [];
    }
}
```

---

## 📊 نمونه: Dashboard Controller

```php
<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Filesystem\Filesystem;
use RMS\Accounting\Services\LedgerService;
use RMS\Accounting\Models\FiscalYear;

class DashboardController extends AccountingAdminController
{
    protected LedgerService $ledgerService;
    
    public function __construct(Filesystem $filesystem, LedgerService $ledgerService)
    {
        parent::__construct($filesystem);
        $this->ledgerService = $ledgerService;
    }
    
    public function index(Request $request = null)
    {
        $fiscalYear = FiscalYear::where('is_current', true)->first();
        
        $stats = [
            'total_revenue' => $this->ledgerService->getTotalRevenue($fiscalYear),
            'total_expenses' => $this->ledgerService->getTotalExpenses($fiscalYear),
            'net_profit' => 0, // محاسبه
        ];
        
        $chartData = $this->getChartData();
        
        $this->view
            ->usePackageNamespace('accounting')
            ->setTpl('dashboard')
            ->withPlugins(['chart-js'])
            ->withCss('vendor/accounting/admin/css/dashboard.css', true)
            ->withJs('vendor/accounting/admin/js/dashboard.js', true)
            ->withVariables([
                'stats' => $stats,
                'chartData' => $chartData,
                'fiscalYear' => $fiscalYear,
            ]);
        
        return $this->view();
    }
    
    protected function getChartData(): array
    {
        // دریافت داده‌های نمودار
        return [
            'labels' => ['فروردین', 'اردیبهشت', '...'],
            'data' => [100, 200, 300],
        ];
    }
    
    public function table(): string { return ''; }
    public function modelName(): string { return ''; }
}
```

---

## 📝 نمونه: Settings Controller

```php
<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Filesystem\Filesystem;
use RMS\Core\Models\Setting;

class SettingsController extends AccountingAdminController
{
    public function __construct(Filesystem $filesystem)
    {
        parent::__construct($filesystem);
    }
    
    /**
     * نمایش صفحه تنظیمات
     */
    public function showSettings(Request $request = null)
    {
        $settings = $this->getAllSettings();
        
        $this->view
            ->usePackageNamespace('accounting')
            ->setTpl('settings.index')
            ->withVariables([
                'settings' => $settings,
            ]);
        
        return $this->view();
    }
    
    /**
     * ذخیره تنظیمات
     */
    public function saveSettings(Request $request)
    {
        $validated = $request->validate([
            'vat_rate' => 'required|numeric|min:0|max:100',
            'income_tax_rate' => 'required|numeric|min:0|max:100',
        ]);
        
        Setting::setMany([
            'accounting.vat.rate' => $validated['vat_rate'],
            'accounting.income_tax.rate' => $validated['income_tax_rate'],
        ]);
        
        return redirect()
            ->route('admin.accounting.settings.index')
            ->with('success', 'تنظیمات ذخیره شد');
    }
    
    protected function getAllSettings(): array
    {
        return [
            'vat_rate' => Setting::get('accounting.vat.rate', 9),
            'income_tax_rate' => Setting::get('accounting.income_tax.rate', 25),
        ];
    }
    
    public function table(): string { return ''; }
    public function modelName(): string { return ''; }
}
```

---

## 📑 نمونه: Reports Controller

```php
<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Filesystem\Filesystem;
use RMS\Accounting\Services\ReportService;

class ReportsController extends AccountingAdminController
{
    protected ReportService $reportService;
    
    public function __construct(Filesystem $filesystem, ReportService $reportService)
    {
        parent::__construct($filesystem);
        $this->reportService = $reportService;
    }
    
    /**
     * لیست گزارش‌ها
     */
    public function index(Request $request = null)
    {
        $reports = [
            ['name' => 'ترازنامه', 'route' => 'balance-sheet'],
            ['name' => 'سود و زیان', 'route' => 'income-statement'],
            ['name' => 'گردش حساب', 'route' => 'ledger'],
        ];
        
        $this->view
            ->usePackageNamespace('accounting')
            ->setTpl('reports.index')
            ->withVariables(['reports' => $reports]);
        
        return $this->view();
    }
    
    /**
     * ترازنامه
     */
    public function balanceSheet(Request $request)
    {
        $data = $this->reportService->getBalanceSheet(
            $request->input('start_date'),
            $request->input('end_date')
        );
        
        $this->view
            ->usePackageNamespace('accounting')
            ->setTpl('reports.balance-sheet')
            ->withPlugins(['datatables'])
            ->withVariables(['data' => $data]);
        
        return $this->view();
    }
    
    /**
     * صورت سود و زیان
     */
    public function incomeStatement(Request $request)
    {
        $data = $this->reportService->getIncomeStatement(
            $request->input('start_date'),
            $request->input('end_date')
        );
        
        $this->view
            ->usePackageNamespace('accounting')
            ->setTpl('reports.income-statement')
            ->withVariables(['data' => $data]);
        
        return $this->view();
    }
    
    public function table(): string { return ''; }
    public function modelName(): string { return ''; }
}
```

---

## 🎯 تفاوت با CRUD Controllers

| ویژگی | CRUD Controller | Custom Page |
|------|----------------|-------------|
| Interfaces | `HasList`, `HasForm`, `ShouldFilter` | هیچ |
| `table()` | نام جدول | `''` |
| `modelName()` | کلاس Model | `''` |
| `getFieldsForm()` | ✅ | ❌ |
| `getListFields()` | ✅ | ❌ |
| `rules()` | ✅ | ❌ |
| `index()` متد | خودکار | دستی |
| `store()` متد | خودکار | دستی (اگر نیاز باشد) |

---

## 📋 Route ها برای Custom Pages

```php
// routes/admin.php

// Dashboard
Route::get('dashboard', [DashboardController::class, 'index'])
    ->name('accounting.dashboard');

// Settings
Route::get('settings', [SettingsController::class, 'showSettings'])
    ->name('accounting.settings.index');
Route::post('settings', [SettingsController::class, 'saveSettings'])
    ->name('accounting.settings.save');

// Reports
Route::get('reports', [ReportsController::class, 'index'])
    ->name('accounting.reports.index');
Route::get('reports/balance-sheet', [ReportsController::class, 'balanceSheet'])
    ->name('accounting.reports.balance-sheet');
Route::get('reports/income-statement', [ReportsController::class, 'incomeStatement'])
    ->name('accounting.reports.income-statement');
```

---

## ✅ چک‌لیست Custom Page

- [ ] extends `AccountingAdminController`
- [ ] **بدون** Interface (`HasList`, `HasForm`, ...)
- [ ] `Filesystem` در constructor
- [ ] `table()` و `modelName()` خالی (`''`)
- [ ] `index()` دستی با `$this->view`
- [ ] routes دستی (نه resource)
- [ ] views در `resources/views/admin/`
- [ ] assets (CSS/JS) در `resources/assets/admin/`

---

## 🎨 View Builder API

```php
$this->view
    ->usePackageNamespace('accounting')  // namespace پکیج
    ->setTpl('dashboard')                 // template name
    ->setTheme('admin')                   // theme (optional)
    ->withPlugins(['chart-js'])           // plugins
    ->withCss('path/to/style.css', true)  // true = versioned
    ->withJs('path/to/script.js', true)
    ->withVariables([                     // متغیرهای view
        'data' => $data,
    ]);

return $this->view();
```

---

## 📚 نمونه‌های واقعی

- ✅ **DashboardController** - صفحه اصلی با نمودارها
- ✅ **SettingsController** - تنظیمات با فرم
- ✅ **ReportsController** - گزارش‌های مختلف

