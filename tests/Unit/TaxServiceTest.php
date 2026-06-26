<?php

namespace RMS\Accounting\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RMS\Accounting\Services\TaxService;
use RMS\Accounting\Models\{CustomerInvoice, SupplierInvoice, VatRemittance};
use RMS\Core\Models\Setting;
use Tests\TestCase;

/**
 * تست‌های واحد برای TaxService
 */
class TaxServiceTest extends TestCase
{
    use RefreshDatabase;
    
    protected TaxService $taxService;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->taxService = app(TaxService::class);
        
        // تنظیمات پیش‌فرض مالیاتی
        Setting::set('accounting.vat.enabled', true);
        Setting::set('accounting.vat.rate', 9);
        Setting::set('accounting.tax.calculation_method', 'exclusive');
        Setting::set('accounting.vat.account_payable_id', 1);
        Setting::set('accounting.vat.account_receivable_id', 2);
    }
    
    /** @test */
    public function it_applies_vat_to_customer_invoice_without_items()
    {
        $invoiceId = $this->insertCustomerInvoice([
            'subtotal' => 100000,
            'tax_amount' => 0,
            'total_amount' => 100000,
        ]);
        $invoice = CustomerInvoice::query()->findOrFail($invoiceId);

        $invoice = $this->taxService->applyVATToCustomerInvoice($invoice);

        $this->assertEquals(100000, $invoice->subtotal);
        $this->assertEquals(9000, $invoice->tax_amount);
        $this->assertEquals(109000, $invoice->total_amount);
    }

    /** @test */
    public function it_handles_customer_invoice_without_items_and_without_subtotal_or_total()
    {
        $invoiceId = $this->insertCustomerInvoice([
            'subtotal' => null,
            'tax_amount' => 0,
            'total_amount' => null,
        ]);
        $invoice = CustomerInvoice::query()->findOrFail($invoiceId);

        $invoice = $this->taxService->applyVATToCustomerInvoice($invoice);

        $this->assertEquals(0.0, (float) $invoice->subtotal);
        $this->assertEquals(0.0, (float) $invoice->tax_amount);
        $this->assertEquals(0.0, (float) $invoice->total_amount);
    }

    /** @test */
    public function it_applies_vat_to_supplier_invoice_without_items()
    {
        $invoiceId = $this->insertSupplierInvoice([
            'subtotal' => 50000,
            'tax_amount' => 0,
            'total_amount' => 50000,
        ]);
        $invoice = SupplierInvoice::query()->findOrFail($invoiceId);

        $invoice = $this->taxService->applyVATToSupplierInvoice($invoice);

        $this->assertEquals(50000, $invoice->subtotal);
        $this->assertEquals(4500, $invoice->tax_amount);
        $this->assertEquals(54500, $invoice->total_amount);
    }

    /** @test */
    public function it_handles_supplier_invoice_without_items_and_without_subtotal_or_total()
    {
        $invoiceId = $this->insertSupplierInvoice([
            'subtotal' => null,
            'tax_amount' => 0,
            'total_amount' => null,
        ]);
        $invoice = SupplierInvoice::query()->findOrFail($invoiceId);

        $invoice = $this->taxService->applyVATToSupplierInvoice($invoice);

        $this->assertEquals(0.0, (float) $invoice->subtotal);
        $this->assertEquals(0.0, (float) $invoice->tax_amount);
        $this->assertEquals(0.0, (float) $invoice->total_amount);
    }

    /** @test */
    public function it_respects_tax_exemption_for_customer()
    {
        $customer = (object) ['tax_exempt' => true];
        $isExempt = $this->taxService->isExemptFromTax($customer);

        $this->assertTrue($isExempt);
    }

    /** @test */
    public function it_respects_tax_exemption_for_supplier()
    {
        $supplier = (object) ['tax_exempt' => true];
        $isExempt = $this->taxService->isExemptFromTax($supplier);

        $this->assertTrue($isExempt);
    }

    /** @test */
    public function it_calculates_vat_payable_correctly()
    {
        $this->insertCustomerInvoice([
            'invoice_date' => now(),
            'tax_amount' => 9000,
        ]);

        $this->insertSupplierInvoice([
            'invoice_date' => now(),
            'tax_amount' => 4500,
        ]);

        $vatPayable = $this->taxService->calculateVATPayable(
            now()->startOfMonth()->format('Y-m-d'),
            now()->endOfMonth()->format('Y-m-d')
        );

        $this->assertEquals(4500, $vatPayable);
    }

    /** @test */
    public function it_subtracts_vat_remittance_from_net_vat_payable()
    {
        $this->insertCustomerInvoice([
            'invoice_date' => now(),
            'tax_amount' => 200000,
        ]);

        $this->insertSupplierInvoice([
            'invoice_date' => now(),
            'tax_amount' => 20000,
        ]);

        VatRemittance::query()->create([
            'payment_date' => now()->format('Y-m-d'),
            'amount' => 180000,
            'status' => VatRemittance::STATUS_POSTED,
        ]);

        $vatPayable = $this->taxService->calculateVATPayable(
            now()->startOfMonth()->format('Y-m-d'),
            now()->endOfMonth()->format('Y-m-d')
        );

        $this->assertEquals(0, $vatPayable);
    }
    
    /** @test */
    public function it_gets_tax_settings()
    {
        $settings = $this->taxService->getTaxSettings();

        $this->assertTrue((bool) $settings['vat']['enabled']);
        $this->assertEquals(9, $settings['vat']['rate']);
        $this->assertEquals('exclusive', $settings['vat']['method']);
    }

    /** @test */
    public function it_handles_disabled_vat()
    {
        Setting::set('accounting.vat.enabled', false);
        
        // اگر VAT غیرفعال باشد، Observer نباید مالیات محاسبه کند
        $this->assertFalse(is_vat_enabled());
    }
    
    /** @test */
    public function it_applies_vat_to_invoice_with_multiple_items()
    {
        $invoiceId = $this->insertCustomerInvoice([
            'subtotal' => 0,
            'tax_amount' => 0,
        ]);
        $invoice = CustomerInvoice::query()->findOrFail($invoiceId);

        $invoice->items()->create([
            'product_id' => 1,
            'product_name' => 'A',
            'quantity' => 1,
            'price' => 50000,
            'tax_rate' => 9,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total' => 50000,
        ]);

        $invoice->items()->create([
            'product_id' => 2,
            'product_name' => 'B',
            'quantity' => 1,
            'price' => 30000,
            'tax_rate' => 9,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total' => 30000,
        ]);

        $invoice = $this->taxService->applyVATToCustomerInvoice($invoice);

        $this->assertEquals(80000, $invoice->subtotal);
        $this->assertEquals(7200, $invoice->tax_amount);
        $this->assertEquals(87200, $invoice->total_amount);
    }

    /** @test */
    public function it_applies_customer_item_vat_on_net_amount_after_discount()
    {
        $invoiceId = $this->insertCustomerInvoice([
            'subtotal' => 0,
            'tax_amount' => 0,
        ]);
        $invoice = CustomerInvoice::query()->findOrFail($invoiceId);

        $invoice->items()->create([
            'product_id' => 1,
            'product_name' => 'C',
            'quantity' => 2,
            'price' => 100000,
            'discount_amount' => 50000,
            'tax_rate' => 9,
            'tax_amount' => 0,
            'total' => 150000,
        ]);

        $invoice = $this->taxService->applyVATToCustomerInvoice($invoice);

        $this->assertEquals(150000, $invoice->subtotal); // 200,000 - 50,000
        $this->assertEquals(13500, $invoice->tax_amount); // 9%
        $this->assertEquals(163500, $invoice->total_amount);
    }

    protected function insertCustomerInvoice(array $overrides = []): int
    {
        $now = now();
        $currency = $this->ensureCurrency();
        $customerId = (int) DB::table('customers')->insertGetId([
            'name' => 'Customer Test '.Str::upper(Str::random(4)),
            'type' => 'Regular',
            'national_code' => null,
            'phone' => null,
            'address' => null,
            'credit_limit' => 0,
            'active' => 1,
            'tax_exempt' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) DB::table('customer_invoices')->insertGetId(array_merge([
            'invoice_number' => 'CINV-'.Str::upper(Str::random(8)),
            'customer_id' => $customerId,
            'store_id' => null,
            'invoice_date' => $now->format('Y-m-d'),
            'due_date' => $now->copy()->addDays(30)->format('Y-m-d'),
            'subtotal' => 100000,
            'tax_amount' => 9000,
            'discount_amount' => 0,
            'total_amount' => 109000,
            'currency_code' => $currency,
            'fx_rate' => 1,
            'amount_base' => 109000,
            'status' => CustomerInvoice::STATUS_ISSUED,
            'payment_status' => CustomerInvoice::STATUS_UNPAID,
            'paid_amount' => 0,
            'balance_due' => 109000,
            'tax_method' => 'exclusive',
            'tax_rate' => 9,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides));
    }

    protected function insertSupplierInvoice(array $overrides = []): int
    {
        $now = now();
        $currency = $this->ensureCurrency();

        $accountId = (int) DB::table('accounts')->insertGetId([
            'code' => 'AP-'.Str::upper(Str::random(6)),
            'name' => 'AP Test',
            'level' => 3,
            'parent_id' => null,
            'account_type' => 'liability',
            'is_system' => 0,
            'currency_code' => $currency,
            'active' => 1,
            'description' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $supplierId = (int) DB::table('suppliers')->insertGetId([
            'code' => 'SUP-'.Str::upper(Str::random(6)),
            'name' => 'Supplier Test',
            'contact_person' => null,
            'phone' => null,
            'email' => null,
            'address' => null,
            'tax_number' => null,
            'account_id' => $accountId,
            'currency_code' => $currency,
            'payment_terms_days' => 30,
            'credit_limit' => 0,
            'active' => 1,
            'notes' => null,
            'tax_exempt' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) DB::table('supplier_invoices')->insertGetId(array_merge([
            'invoice_number' => 'SINV-'.Str::upper(Str::random(8)),
            'supplier_invoice_number' => null,
            'supplier_id' => $supplierId,
            'purchase_order_id' => null,
            'store_id' => null,
            'invoice_date' => $now->format('Y-m-d'),
            'due_date' => $now->copy()->addDays(30)->format('Y-m-d'),
            'subtotal' => 50000,
            'tax_amount' => 4500,
            'discount_amount' => 0,
            'shipping_amount' => 0,
            'total_amount' => 54500,
            'currency_code' => $currency,
            'fx_rate_at_invoice' => 1,
            'amount_base_at_invoice' => 54500,
            'payment_status' => 'unpaid',
            'paid_amount' => 0,
            'balance_due' => 54500,
            'tax_method' => 'exclusive',
            'tax_rate' => 9,
            'settlement_mode' => 'on_account',
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides));
    }

    protected function ensureCurrency(): string
    {
        $now = now();
        $currency = (string) (DB::table('currencies')->value('code') ?? 'IRR');
        if (! DB::table('currencies')->where('code', $currency)->exists()) {
            DB::table('currencies')->insert([
                'code' => $currency,
                'name' => 'Iran Rial',
                'symbol' => 'IRR',
                'decimals' => 0,
                'is_base' => 1,
                'active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $currency;
    }
}
