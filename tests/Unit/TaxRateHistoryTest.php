<?php

namespace RMS\Accounting\Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RMS\Accounting\Services\TaxService;
use RMS\Accounting\Models\{CustomerInvoice, SupplierInvoice};
use RMS\Core\Models\Setting;

/**
 * تست برای Tax Rate History و Immutability
 * اطمینان از اینکه تغییر نرخ مالیات روی Invoice های قدیمی تأثیر نداره
 */
class TaxRateHistoryTest extends TestCase
{
    use RefreshDatabase;
    
    protected TaxService $taxService;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->taxService = app(TaxService::class);
        
        // تنظیمات پیش‌فرض
        Setting::set('accounting.vat.enabled', true);
        Setting::set('accounting.vat.rate', 9); // نرخ اولیه 9%
        Setting::set('accounting.tax.calculation_method', 'exclusive');
    }
    
    /** @test */
    public function invoice_stores_tax_rate_at_creation_time()
    {
        // نرخ فعلی: 9%
        $invoice = CustomerInvoice::create([
            'customer_id' => 1,
            'invoice_number' => 'INV-001',
            'invoice_date' => now(),
            'subtotal' => 100000,
        ]);
        
        // باید نرخ 9% ذخیره شده باشه
        $this->assertEquals(9, $invoice->fresh()->tax_rate);
        $this->assertEquals(9000, $invoice->fresh()->tax_amount);
    }
    
    /** @test */
    public function old_invoice_keeps_original_tax_rate_after_settings_change()
    {
        // مرحله 1: ایجاد Invoice با نرخ 9%
        Setting::set('accounting.vat.rate', 9);
        
        $invoice = CustomerInvoice::create([
            'customer_id' => 1,
            'invoice_number' => 'INV-001',
            'invoice_date' => now(),
            'subtotal' => 100000,
        ]);
        
        $this->assertEquals(9, $invoice->tax_rate);
        $this->assertEquals(9000, $invoice->tax_amount);
        
        // مرحله 2: تغییر نرخ در Settings به 12%
        Setting::set('accounting.vat.rate', 12);
        Setting::clearCache(); // مطمئن شو cache پاک شد
        
        // مرحله 3: Edit کردن Invoice قدیمی
        $invoice = CustomerInvoice::find($invoice->id);
        $invoice->subtotal = 120000;
        $invoice->save();
        
        // ⭐ باید از نرخ قدیم (9%) استفاده کنه، نه نرخ جدید (12%)
        $invoice = $invoice->fresh();
        $this->assertEquals(9, $invoice->tax_rate); // ✅ همان 9%
        $this->assertEquals(10800, $invoice->tax_amount); // 120000 * 0.09 = 10800
        $this->assertNotEquals(14400, $invoice->tax_amount); // نباید 120000 * 0.12 باشه
    }
    
    /** @test */
    public function new_invoice_uses_new_tax_rate_after_settings_change()
    {
        // مرحله 1: ایجاد Invoice با نرخ 9%
        Setting::set('accounting.vat.rate', 9);
        
        $oldInvoice = CustomerInvoice::create([
            'customer_id' => 1,
            'invoice_number' => 'INV-001',
            'invoice_date' => now(),
            'subtotal' => 100000,
        ]);
        
        $this->assertEquals(9, $oldInvoice->tax_rate);
        
        // مرحله 2: تغییر نرخ به 12%
        Setting::set('accounting.vat.rate', 12);
        Setting::clearCache();
        
        // مرحله 3: ایجاد Invoice جدید
        $newInvoice = CustomerInvoice::create([
            'customer_id' => 2,
            'invoice_number' => 'INV-002',
            'invoice_date' => now(),
            'subtotal' => 100000,
        ]);
        
        // ⭐ Invoice جدید باید نرخ جدید (12%) رو استفاده کنه
        $this->assertEquals(12, $newInvoice->fresh()->tax_rate);
        $this->assertEquals(12000, $newInvoice->fresh()->tax_amount);
    }
    
    /** @test */
    public function multiple_rate_changes_are_tracked_correctly()
    {
        // دوره 1: نرخ 9%
        Setting::set('accounting.vat.rate', 9);
        $invoice1 = CustomerInvoice::create([
            'invoice_number' => 'INV-001',
            'invoice_date' => now()->subMonths(3),
            'subtotal' => 100000,
        ]);
        
        // دوره 2: نرخ 12%
        Setting::set('accounting.vat.rate', 12);
        Setting::clearCache();
        $invoice2 = CustomerInvoice::create([
            'invoice_number' => 'INV-002',
            'invoice_date' => now()->subMonths(1),
            'subtotal' => 100000,
        ]);
        
        // دوره 3: نرخ 15%
        Setting::set('accounting.vat.rate', 15);
        Setting::clearCache();
        $invoice3 = CustomerInvoice::create([
            'invoice_number' => 'INV-003',
            'invoice_date' => now(),
            'subtotal' => 100000,
        ]);
        
        // بررسی اینکه هر Invoice نرخ خودش رو داره
        $this->assertEquals(9, $invoice1->fresh()->tax_rate);
        $this->assertEquals(9000, $invoice1->fresh()->tax_amount);
        
        $this->assertEquals(12, $invoice2->fresh()->tax_rate);
        $this->assertEquals(12000, $invoice2->fresh()->tax_amount);
        
        $this->assertEquals(15, $invoice3->fresh()->tax_rate);
        $this->assertEquals(15000, $invoice3->fresh()->tax_amount);
        
        // Edit کردن Invoice 1
        $invoice1->subtotal = 150000;
        $invoice1->save();
        
        // باید همچنان نرخ 9% باشه
        $this->assertEquals(9, $invoice1->fresh()->tax_rate);
        $this->assertEquals(13500, $invoice1->fresh()->tax_amount); // 150000 * 0.09
    }
    
    /** @test */
    public function supplier_invoice_also_preserves_tax_rate()
    {
        // ایجاد با نرخ 9%
        Setting::set('accounting.vat.rate', 9);
        $invoice = SupplierInvoice::create([
            'supplier_id' => 1,
            'invoice_number' => 'SUPP-001',
            'invoice_date' => now(),
            'subtotal' => 200000,
        ]);
        
        $this->assertEquals(9, $invoice->fresh()->tax_rate);
        $this->assertEquals(18000, $invoice->fresh()->tax_amount);
        
        // تغییر نرخ به 12%
        Setting::set('accounting.vat.rate', 12);
        Setting::clearCache();
        
        // Edit
        $invoice->subtotal = 250000;
        $invoice->save();
        
        // باید از نرخ قدیم استفاده کنه
        $this->assertEquals(9, $invoice->fresh()->tax_rate);
        $this->assertEquals(22500, $invoice->fresh()->tax_amount); // 250000 * 0.09
    }
    
    /** @test */
    public function tax_rate_can_be_manually_overridden()
    {
        // نرخ پیش‌فرض: 9%
        Setting::set('accounting.vat.rate', 9);
        
        // اما کاربر می‌تونه نرخ سفارشی تعیین کنه
        $invoice = CustomerInvoice::create([
            'invoice_number' => 'INV-001',
            'subtotal' => 100000,
            'tax_rate' => 5, // نرخ سفارشی
        ]);
        
        // باید از نرخ سفارشی استفاده کنه
        $this->assertEquals(5, $invoice->fresh()->tax_rate);
        $this->assertEquals(5000, $invoice->fresh()->tax_amount); // 100000 * 0.05
    }
    
    /** @test */
    public function reports_can_show_invoices_by_tax_rate()
    {
        // ایجاد Invoice ها با نرخ‌های مختلف
        Setting::set('accounting.vat.rate', 9);
        CustomerInvoice::factory()->count(3)->create(['subtotal' => 100000]);
        
        Setting::set('accounting.vat.rate', 12);
        Setting::clearCache();
        CustomerInvoice::factory()->count(2)->create(['subtotal' => 100000]);
        
        // گروه‌بندی بر اساس نرخ
        $grouped = CustomerInvoice::query()
            ->selectRaw('tax_rate, COUNT(*) as count, SUM(tax_amount) as total_tax')
            ->groupBy('tax_rate')
            ->get();
        
        $this->assertCount(2, $grouped); // 2 نرخ مختلف
        
        $rate9 = $grouped->firstWhere('tax_rate', 9);
        $this->assertEquals(3, $rate9->count);
        $this->assertEquals(27000, $rate9->total_tax); // 3 * 9000
        
        $rate12 = $grouped->firstWhere('tax_rate', 12);
        $this->assertEquals(2, $rate12->count);
        $this->assertEquals(24000, $rate12->total_tax); // 2 * 12000
    }
}
