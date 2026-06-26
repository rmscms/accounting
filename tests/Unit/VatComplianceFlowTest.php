<?php

namespace RMS\Accounting\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RMS\Accounting\Models\VatDeclaration;
use RMS\Accounting\Models\VatRemittance;
use RMS\Accounting\Services\ReportService;
use RMS\Accounting\Services\VatDeclarationService;
use Tests\TestCase;

class VatComplianceFlowTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_reports_remaining_vat_after_remittance_payment()
    {
        $documentId = $this->insertAccountingDocument();
        $this->insertCustomerInvoice([
            'subtotal' => 2_000_000,
            'tax_amount' => 200_000,
        ]);

        $this->insertSupplierInvoice([
            'subtotal' => 200_000,
            'tax_amount' => 20_000,
            'document_id' => $documentId,
        ]);

        VatRemittance::query()->create([
            'payment_date' => now()->format('Y-m-d'),
            'amount' => 180_000,
            'status' => VatRemittance::STATUS_POSTED,
        ]);

        $report = app(ReportService::class)->getVATReport([
            'from_date' => now()->startOfMonth()->format('Y-m-d'),
            'to_date' => now()->endOfMonth()->format('Y-m-d'),
        ]);

        $this->assertSame(180000.0, (float) $report['vat_payable']);
        $this->assertSame(180000.0, (float) $report['remitted_vat']);
        $this->assertSame(0.0, (float) $report['net_payable_remaining']);
    }

    /** @test */
    public function it_supports_exempt_and_variable_rate_rows_in_vat_detailed_report()
    {
        $this->insertCustomerInvoice([
            'subtotal' => 100_000,
            'tax_rate' => 0,
            'tax_amount' => 0,
        ]);

        $this->insertSupplierInvoice([
            'subtotal' => 300_000,
            'tax_rate' => 3,
            'tax_amount' => 9_000,
        ]);

        $report = app(ReportService::class)->getVATReport([
            'from_date' => now()->startOfMonth()->format('Y-m-d'),
            'to_date' => now()->endOfMonth()->format('Y-m-d'),
            'transaction_type' => 'all',
        ]);

        $this->assertNotEmpty($report['rows']);
        $this->assertCount(8, $report['rows'][0]);
    }

    /** @test */
    public function it_creates_declaration_and_amendment_and_generates_official_export()
    {
        $this->insertCustomerInvoice([
            'subtotal' => 100_000,
            'tax_amount' => 9_000,
        ]);
        $this->insertSupplierInvoice([
            'subtotal' => 10_000,
            'tax_amount' => 900,
        ]);

        /** @var VatDeclarationService $service */
        $service = app(VatDeclarationService::class);
        $declaration = $service->createDraft([
            'period_start' => now()->startOfQuarter()->format('Y-m-d'),
            'period_end' => now()->endOfQuarter()->format('Y-m-d'),
        ]);

        $this->assertInstanceOf(VatDeclaration::class, $declaration);
        $this->assertSame(VatDeclaration::STATUS_DRAFT, $declaration->status);

        $amendment = $service->createDraft([
            'period_start' => now()->startOfQuarter()->format('Y-m-d'),
            'period_end' => now()->endOfQuarter()->format('Y-m-d'),
            'parent_declaration_id' => $declaration->id,
        ]);

        $this->assertSame(VatDeclaration::STATUS_AMENDED, $amendment->status);
        $this->assertSame($declaration->id, $amendment->parent_declaration_id);

        $csv = $service->exportCsv($declaration);
        $this->assertStringContainsString('"Field","Value"', $csv);
        $this->assertStringContainsString('VAT خروجی', $csv);
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
            'status' => 'issued',
            'payment_status' => 'unpaid',
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

    protected function insertAccountingDocument(): int
    {
        $now = now();
        return (int) DB::table('accounting_documents')->insertGetId([
            'document_number' => 'DOC-'.Str::upper(Str::random(8)),
            'document_type' => 'PURCHASE',
            'store_id' => null,
            'fiscal_year_id' => null,
            'reference_type' => 'manual',
            'reference_id' => null,
            'description' => 'test document',
            'total_debit' => 0,
            'total_credit' => 0,
            'status' => 'posted',
            'posted_at' => $now,
            'created_by_user_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
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
