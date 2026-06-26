# 📘 Controller Standards Reference

## ✅ استاندارد کامل Controller

```php
<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use RMS\Accounting\Models\Example;
use RMS\Accounting\Services\ExampleService; // اگر نیاز به service داری
use Illuminate\Http\Request;
use Illuminate\Filesystem\Filesystem; // ⚠️ الزامی
use Illuminate\Database\Query\Builder as QueryBuilder; // اگر query() داری
use RMS\Core\Data\Field;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\Actions\ChangeBoolField; // اگر boolean field داری

/**
 * مدیریت Examples
 * 
 * این controller برای مدیریت... استفاده می‌شود
 */
class ExamplesController extends AccountingAdminController implements
    HasList,
    HasForm,
    ShouldFilter,
    ChangeBoolField // اختیاری
{
    protected ExampleService $exampleService; // اگر service داری
    
    /**
     * Constructor
     * 
     * ⚠️ همیشه Filesystem را inject کن
     */
    public function __construct(Filesystem $filesystem, ExampleService $exampleService = null)
    {
        parent::__construct($filesystem);
        $this->exampleService = $exampleService;
    }
    
    // ================== متدهای الزامی Interface ==================
    
    /**
     * نام جدول
     */
    public function table(): string
    {
        return 'examples';
    }
    
    /**
     * نام کامل Model
     */
    public function modelName(): string
    {
        return Example::class;
    }
    
    /**
     * Route prefix
     */
    public function baseRoute(): string
    {
        return 'accounting.examples';
    }
    
    /**
     * نام parameter در route
     */
    public function routeParameter(): string
    {
        return 'example';
    }
    
    /**
     * Query customization (اختیاری)
     * 
     * برای join ها یا select های خاص
     */
    public function query(QueryBuilder $sql): void
    {
        $sql->leftJoin('related_table as rt', 'rt.example_id', '=', 'a.id')
            ->addSelect('a.*', 'rt.extra_field')
            ->orderBy('a.created_at', 'desc');
    }
    
    // ================== Form Fields ==================
    
    /**
     * فیلدهای فرم (Create/Edit)
     * 
     * @return Field[]
     */
    public function getFieldsForm(): array
    {
        return [
            // String
            Field::string('name', 'نام')
                ->required()
                ->setPlaceholder('نام را وارد کنید'),
            
            // Select با options
            Field::select('status', 'وضعیت')
                ->setOptions([
                    'draft' => 'پیش‌نویس',
                    'active' => 'فعال',
                    'inactive' => 'غیرفعال',
                ])
                ->withDefaultValue('draft')
                ->required(),
            
            // Number
            Field::number('amount', 'مبلغ')
                ->withDefaultValue(0)
                ->optional(),
            
            // Date
            Field::date('date', 'تاریخ')
                ->withDefaultValue(now())
                ->required(),
            
            // Textarea (ارتفاع با آرگومان سوم rows — متد setRows در Field وجود ندارد)
            Field::textarea('description', 'توضیحات', 4)
                ->optional(),
            
            // Boolean
            Field::boolean('active', 'فعال')
                ->withDefaultValue(true),
            
            // Select با dynamic options
            Field::select('customer_id', 'مشتری')
                ->setOptions($this->getCustomerOptions())
                ->required(),
        ];
    }
    
    // ================== List Fields ==================
    
    /**
     * فیلدهای لیست (Index)
     * 
     * @return Field[]
     */
    public function getListFields(): array
    {
        return [
            // ID (همیشه)
            Field::make('id')
                ->withTitle('شناسه')
                ->sortable()
                ->width('80px'),
            
            // String با جستجو
            Field::make('name')
                ->withTitle('نام')
                ->searchable()
                ->sortable(),
            
            // Relation
            Field::make('customer.name')
                ->withTitle('مشتری')
                ->searchable(),
            
            // با custom method برای نمایش
            Field::make('status')
                ->withTitle('وضعیت')
                ->customMethod('renderStatus')
                ->width('120px'),
            
            // Number
            Field::number('amount')
                ->withTitle('مبلغ')
                ->customMethod('renderAmount')
                ->sortable()
                ->width('150px'),
            
            // Boolean
            Field::boolean('active')
                ->withTitle('فعال')
                ->sortable()
                ->width('100px'),
            
            // Date
            Field::date('created_at')
                ->withTitle('تاریخ ایجاد')
                ->sortable()
                ->width('150px'),
        ];
    }
    
    // ================== Validation Rules ==================
    
    /**
     * قوانین اعتبارسنجی
     */
    public function rules(): array
    {
        $id = request()->route('example'); // برای unique validation
        
        return [
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:draft,active,inactive'],
            'amount' => ['required', 'numeric', 'min:0'],
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'active' => ['boolean'],
            'customer_id' => ['required', 'exists:customers,id'],
        ];
    }
    
    // ================== Boolean Fields (اگر ChangeBoolField داری) ==================
    
    /**
     * فیلدهای boolean قابل تغییر
     */
    public function boolFields(): array
    {
        return ['active'];
    }
    
    // ================== Custom Actions ==================
    
    /**
     * اکشن اختصاصی
     * 
     * ⚠️ این متدها را در routes هم باید تعریف کنی
     */
    public function customAction(Request $request, $id)
    {
        $example = Example::findOrFail($id);
        
        // استفاده از service
        $this->exampleService->doSomething($example);
        
        return redirect()->back()->with('success', 'عملیات با موفقیت انجام شد');
    }
    
    // ================== Lifecycle Hooks (اختیاری) ==================
    
    /**
     * قبل از حذف
     */
    public function beforeDelete(Request &$request, string|int $id): void
    {
        $example = Example::findOrFail($id);
        
        // چک کردن وابستگی‌ها
        if ($example->relatedItems()->exists()) {
            throw new \Exception('این آیتم دارای وابستگی است و نمی‌توان آن را حذف کرد');
        }
    }
    
    // ================== Helper Methods ==================
    
    /**
     * گرفتن options برای select
     */
    protected function getCustomerOptions(): array
    {
        return \RMS\Accounting\Models\Customer::where('active', true)
            ->pluck('name', 'id')
            ->toArray();
    }
    
    // ================== Render Methods ==================
    
    /**
     * نمایش مبلغ فرمت شده
     */
    public function renderAmount($row): string
    {
        if (empty($row->amount) || $row->amount == 0) {
            return '<span class="text-muted">-</span>';
        }
        
        return '<span class="text-primary font-weight-bold">' 
             . number_format($row->amount) 
             . ' تومان</span>';
    }
    
    /**
     * نمایش وضعیت با badge
     */
    public function renderStatus($row): string
    {
        $badges = [
            'draft' => '<span class="badge badge-secondary">پیش‌نویس</span>',
            'active' => '<span class="badge badge-success">فعال</span>',
            'inactive' => '<span class="badge badge-danger">غیرفعال</span>',
        ];
        
        return $badges[$row->status] ?? $row->status;
    }
}
```

---

## ❌ اشتباهات رایج

### 1. Override کردن index() و store()
```php
// ❌ غلط
public function index()
{
    $items = Example::all();
    return view('examples.index', compact('items'));
}

// ✅ درست - اصلا override نکن! خودکار است
```

### 2. فراموش کردن Filesystem
```php
// ❌ غلط
public function __construct(ExampleService $service)
{
    $this->service = $service;
}

// ✅ درست
public function __construct(Filesystem $filesystem, ExampleService $service)
{
    parent::__construct($filesystem);
    $this->service = $service;
}
```

### 3. استفاده از trait به جای interface
```php
// ❌ غلط
use HasList, HasForm, ShouldFilter;

// ✅ درست
implements HasList, HasForm, ShouldFilter
```

### 4. قرار دادن logic در controller
```php
// ❌ غلط
public function store(Request $request)
{
    DB::beginTransaction();
    $example = Example::create($request->all());
    // ... logic
    DB::commit();
}

// ✅ درست - logic در service
public function store(Request $request)
{
    // این متد خودکار است، override نکن
    // اگر logic نیاز داری، در service بنویس
}
```

---

## 📝 چک‌لیست Controller

- [ ] extends `AccountingAdminController`
- [ ] implements `HasList`, `HasForm`, `ShouldFilter`
- [ ] `Filesystem` در constructor
- [ ] متدهای الزامی: `table()`, `modelName()`, `baseRoute()`, `routeParameter()`
- [ ] `getFieldsForm()` با Field های کامل
- [ ] `getListFields()` با Field های مناسب
- [ ] `rules()` برای validation
- [ ] `render*()` methods برای نمایش custom
- [ ] **هیچ override ای برای index/store نیست**
- [ ] کامنت PHPDoc برای متدهای public
- [ ] helper methods به صورت `protected`

---

## 🎨 Custom Pages (صفحات اختصاصی)

اگر نیاز به صفحه اختصاصی داری (مثل dashboard، reports):

```php
class DashboardController extends AccountingAdminController
{
    // ⚠️ این controller نیاز به HasList/HasForm ندارد
    
    public function __construct(Filesystem $filesystem)
    {
        parent::__construct($filesystem);
    }
    
    public function index(Request $request = null)
    {
        // داده‌های dashboard
        $stats = [
            'total_customers' => Customer::count(),
            'total_invoices' => CustomerInvoice::count(),
            // ...
        ];
        
        return view('accounting::admin.dashboard.index', compact('stats'));
    }
    
    // متدهای الزامی (برای سازگاری)
    public function table(): string { return ''; }
    public function modelName(): string { return ''; }
}
```

---

## 📚 نمونه‌های واقعی

- ✅ **CustomersController** - استاندارد کامل با query()
- ✅ **BanksController** - با ChangeBoolField
- ✅ **CustomerInvoicesController** - با relations پیچیده
- ✅ **ReportsController** - custom page بدون CRUD

