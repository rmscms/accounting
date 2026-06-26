# قوانین و استانداردهای توسعه پکیج Accounting

> **⚠️ این فایل برای Cursor AI است!**
> 
> Cursor: اگر یادت رفت چطور Controller، Service، یا چیز دیگه‌ای بسازی، این فایل رو بخون!

---

## 🔴 قانون شماره یک (برای Cursor)

> **Cursor: قبل از شروع هر کاری، حتما `docs/refs/` را بخوان!**

این پوشه شامل:
- `CONTROLLER_STANDARDS.md` - ساختار استاندارد Controllers
- `CUSTOM_PAGES.md` - راهنمای صفحات Custom
- نحوه پیاده‌سازی Interfaces و Contracts
- الگوهای صحیح برای Models، Services، و Routes
- مثال‌های کامل از کدهای استاندارد

**⚠️ Cursor: این خیلی مهمه! هر بار Controller می‌سازی، اول این فایل‌ها رو بخون!**

---

## 📋 قوانین کلی

### 1. ساختار Controller ها

**✅ همیشه:**
```php
class ExampleController extends AccountingAdminController implements
    HasList,
    HasForm,
    ShouldFilter
{
    // اگر service نیاز داری
    protected ExampleService $service;
    
    public function __construct(Filesystem $filesystem, ExampleService $service)
    {
        parent::__construct($filesystem);
        $this->service = $service;
    }
    
    // متدهای الزامی
    public function table(): string { return 'table_name'; }
    public function modelName(): string { return Model::class; }
    public function baseRoute(): string { return 'accounting.resource'; }
    public function routeParameter(): string { return 'resource'; }
    
    // متدهای Interface
    public function getFieldsForm(): array { /* ... */ }
    public function getListFields(): array { /* ... */ }
    public function rules(): array { /* ... */ }
    
    // متدهای render برای نمایش custom
    public function renderColumnName($row): string { /* ... */ }
}
```

**❌ هرگز:**
- `index()` و `store()` را override نکن (خودکار هستند)
- چند Controller در یک فایل نذار
- بدون `Filesystem` در constructor نساز
- از trait استفاده نکن، فقط implement Interface

### 2. استفاده از Services

**✅ درست:**
```php
// در Service
class ExampleService
{
    public function createExample(array $data): Example
    {
        DB::beginTransaction();
        try {
            $example = Example::create($data);
            // Logic here
            DB::commit();
            return $example;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}

// در Controller
$result = $this->exampleService->createExample($validated);
```

**❌ غلط:**
- Business Logic در Controller ننویس
- مستقیم Model::create در Controller استفاده نکن

### 3. Routes

**✅ درست:**
```php
// برای CRUD کامل
RouteHelper::adminResource('examples', ExamplesController::class);

// برای custom actions
Route::post('examples/{id}/action', [ExamplesController::class, 'action'])
    ->name('accounting.examples.action');
```

### 4. Migrations

**✅ همیشه:**
- نام جداول جمع باشد (customers, invoices)
- Foreign keys با `constrained()` تعریف کن
- Index برای فیلدهای پرجستجو بذار
- SoftDeletes برای جداول مهم

```php
$table->foreignId('customer_id')->constrained('customers')->onDelete('restrict');
$table->index('invoice_date');
$table->index(['customer_id', 'status']);
```

### 5. Models

**✅ استاندارد:**
```php
class Example extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $fillable = ['field1', 'field2'];
    protected $casts = [
        'date_field' => 'date',
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];
    
    // Relations
    public function relatedModel(): BelongsTo
    {
        return $this->belongsTo(RelatedModel::class);
    }
    
    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    // Constants
    const STATUS_DRAFT = 'draft';
    const STATUS_ACTIVE = 'active';
}
```

### 6. Validation Rules

**✅ در Controller:**
```php
public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['nullable', 'email'],
        'amount' => ['required', 'numeric', 'min:0'],
        'status' => ['required', 'in:draft,active,cancelled'],
    ];
}
```

### 7. Menu (Sidebar)

**✅ همیشه:**
- بعد از ساخت Controller، منو را آپدیت کن
- در هر دو فایل: `stub` و `backend/resources/views/vendor/cms/admin/layout/`

### 8. استانداردهای حسابداری

**✅ مهم:**
- دوبل‌اینتری همیشه رعایت شود
- هر تراکنش باید سند حسابداری داشته باشد
- نرخ مالیات immutable در فاکتور ذخیره شود
- تاریخ‌ها همیشه `Carbon` باشند
- مبالغ همیشه `decimal:2` باشند

### 9. Testing

**✅ قبل از commit:**
```bash
php artisan route:list  # چک کردن routes
php artisan migrate     # چک کردن migrations
```

### 10. Documentation

**✅ کامنت‌ها:**
- برای متدهای public کامنت بنویس
- استاندارد IFRS/GAAP را ذکر کن
- مثال استفاده بنویس

```php
/**
 * ایجاد اعتبار برگشتی (Credit Note)
 * 
 * استاندارد: IFRS 15 - Revenue from Contracts with Customers
 * 
 * @param array $data
 * @return CreditNote
 */
public function createCreditNote(array $data): CreditNote
```

---

## 🎯 چک‌لیست قبل از Commit

- [ ] `docs/refs` را خواندم
- [ ] Controller استاندارد است (با Interface)
- [ ] Service برای logic استفاده شده
- [ ] Route صحیح تعریف شده
- [ ] Menu آپدیت شده (stub + backend)
- [ ] Migration بدون خطا اجرا می‌شود
- [ ] Route list بدون خطا کار می‌کند
- [ ] کامنت‌ها و documentation نوشته شده

---

## 📚 منابع

- `docs/refs/` - مثال‌های کامل
- `CustomersController.php` - Controller نمونه
- `BanksController.php` - Controller با ChangeBoolField
- `CreditNotesController.php` - Controller جدید
- `CustomerInvoiceService.php` - Service نمونه

---

**یادت باشه: قبل از هر کاری، `docs/refs` را مطالعه کن! 📖**
