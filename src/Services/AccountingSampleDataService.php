<?php

declare(strict_types=1);

namespace RMS\Accounting\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use RMS\Accounting\Models\Account;
use RMS\Accounting\Models\AccountingDocument;
use RMS\Accounting\Models\Bank;
use RMS\Accounting\Models\CashBox;
use RMS\Accounting\Models\Cheque;
use RMS\Accounting\Models\Chequebook;
use RMS\Accounting\Models\Customer;
use RMS\Accounting\Models\CustomerInvoice;
use RMS\Accounting\Models\CustomerInvoiceItem;
use RMS\Accounting\Models\CustomerPayment;
use RMS\Accounting\Models\Expense;
use RMS\Accounting\Models\ExpenseCategory;
use RMS\Accounting\Models\ExpenseStatusHistory;
use RMS\Accounting\Models\FixedAsset;
use RMS\Accounting\Models\FixedAssetCategory;
use RMS\Accounting\Models\FinancialLedger;
use RMS\Accounting\Models\FiscalYear;
use RMS\Accounting\Models\Party;
use RMS\Accounting\Models\PaymentMethod;
use RMS\Accounting\Models\PurchaseOrder;
use RMS\Accounting\Models\ManualJournal;
use RMS\Accounting\Models\ManualJournalLine;
use RMS\Accounting\Models\Currency;
use RMS\Accounting\Models\Shareholder;
use RMS\Accounting\Models\ShareholderCapitalContribution;
use RMS\Accounting\Models\ShareholderWithdrawal;
use RMS\Accounting\Models\Supplier;
use RMS\Accounting\Models\SupplierInvoice;
use RMS\Accounting\Models\SupplierInvoiceItem;
use RMS\Accounting\Models\SupplierPayment;
use RMS\Accounting\Services\CustomerInvoiceItemAdminService;
use RMS\Accounting\Services\FixedAssetService;
use RMS\Core\Models\Setting;
use RuntimeException;

class AccountingSampleDataService
{
    public const MODE_REBUILD = 'rebuild';
    public const MODE_APPEND = 'append';
    public const MAX_CUSTOMERS = 200;
    public const MARKER = 'ACC-SAMPLE-2026';
    private const PARTY_NATIONAL_PREFIX = '99066';
    private const CUSTOMER_PHONE_PREFIX = '091299';
    private const SUPPLIER_CODE_PREFIX = 'SMP-SUP-';
    private const PO_PREFIX = 'SMP-PO-';
    private const INV_PREFIX = 'SMP-SINV-';
    private const SALES_INV_PREFIX = 'SMP-CINV-';
    private const CUSTOMER_PAY_PREFIX = 'SMP-CRP-';
    private const SUPPLIER_PAY_PREFIX = 'SMP-SPP-';
    private const CHEQUE_PREFIX = 'SMP-CHQ-';
    private const EXPENSE_PREFIX = 'SMP-EXP-';
    private const SAMPLE_EXPENSE_CATEGORY_CODE = 'SMP-EXP-CAT';
    private const SHAREHOLDER_MARKER = self::MARKER.'|shareholder';
    /** @var non-empty-string */
    private const FIXED_ASSET_CATEGORY_CODE_PREFIX = 'SMP-FA-CAT-';
    /** @var non-empty-string */
    private const FIXED_ASSET_CODE_PREFIX = 'SMP-FA-';
    private const OLD_CRM_CUSTOMER_SNAPSHOT_PATH = 'database/sample_data/old_crm_customers.json';
    private const SAMPLE_CUSTOMER_NAMES = [
        'منوچهر کارگر',
        'پوریا پاکدل',
        'مصطفی زنگیان',
        'امین ابراهیم پور',
        'مرتضی افضل',
        'مهدی امینی راد',
        'محمد طاهریان',
        'محسن طالب زاده',
        'حامد رضازاده',
        'پوریا عزتی',
        'Amir Mansouri',
        'رضا ثانی زاده',
        'ابوالفضل مقصودلو',
        'مرتضی الماسی',
        'مهرداد حسین زاده',
        'امیر قربانی',
        'سیدامیرحسین فاطمی',
        'اسماعیل گودرزی',
        'حامد ولی نژاد ترکمانی',
        'سعیده محمدی',
    ];

    public function __construct(
        private readonly PartyService $partyService,
        private readonly PurchaseOrderService $purchaseOrderService,
        private readonly SupplierInvoiceService $supplierInvoiceService,
        private readonly CustomerInvoiceService $customerInvoiceService,
        private readonly CustomerInvoiceItemAdminService $customerInvoiceItemAdminService,
        private readonly CustomerPaymentService $customerPaymentService,
        private readonly SupplierPaymentService $supplierPaymentService,
        private readonly ChequeAutoCreationService $chequeAutoCreationService,
        private readonly ChequeLedgerService $chequeLedgerService,
        private readonly ShareholderWithdrawalService $shareholderWithdrawalService,
        private readonly ShareholderCapitalContributionService $shareholderCapitalContributionService,
        private readonly AccountingDataWipeService $accountingDataWipeService,
        private readonly ExpenseService $expenseService,
        private readonly FixedAssetService $fixedAssetService,
        private readonly TreasuryBalanceCacheSyncService $treasuryBalanceCacheSyncService
    ) {}

    /**
     * @return array<string,int>
     */
    public function defaultGenerationOptions(): array
    {
        return [
            'customers_count' => 20,
            'shared_suppliers_count' => 7,
            'purchase_orders_count' => 6,
            'supplier_direct_invoices_count' => 8,
            'sales_invoices_count' => 12,
            'customer_payments_count' => 8,
            'customer_cheque_payments_count' => 4,
            'supplier_payments_count' => 7,
            'supplier_cheque_payments_count' => 4,
            'expenses_count' => 8,
            'fixed_assets_count' => 6,
            'shareholders_count' => 2,
            'capital_contributions_min' => 3,
            'capital_contributions_max' => 5,
            'withdrawals_min' => 2,
            'withdrawals_max' => 4,
        ];
    }

    /**
     * @param  array<string,mixed>  $options
     * @return array<string,int>
     */
    public function normalizeGenerationOptions(array $options): array
    {
        $defaults = $this->defaultGenerationOptions();
        $normalized = $defaults;

        foreach ($defaults as $key => $value) {
            $candidate = array_key_exists($key, $options) ? (int) $options[$key] : $value;
            $normalized[$key] = max(0, $candidate);
        }

        $normalized['customers_count'] = min(self::MAX_CUSTOMERS, max(0, $normalized['customers_count']));
        $normalized['shared_suppliers_count'] = min($normalized['customers_count'], $normalized['shared_suppliers_count']);
        $normalized['customer_cheque_payments_count'] = min($normalized['customer_payments_count'], $normalized['customer_cheque_payments_count']);
        $normalized['supplier_cheque_payments_count'] = min($normalized['supplier_payments_count'], $normalized['supplier_cheque_payments_count']);

        if ($normalized['shareholders_count'] < 1) {
            $normalized['shareholders_count'] = $defaults['shareholders_count'];
        }

        if ($normalized['capital_contributions_min'] > $normalized['capital_contributions_max']) {
            $normalized['capital_contributions_max'] = $normalized['capital_contributions_min'];
        }
        if ($normalized['withdrawals_min'] > $normalized['withdrawals_max']) {
            $normalized['withdrawals_max'] = $normalized['withdrawals_min'];
        }

        return $normalized;
    }

    /**
     * @return array{ok:bool,checks:array<int,array<string,mixed>>,errors:array<int,string>,warnings:array<int,string>}
     */
    public function preflight(): array
    {
        $checks = [];

        $checks[] = $this->requiredCheck(
            'fiscal_year',
            FiscalYear::query()->where('is_current', true)->where('status', FiscalYear::STATUS_OPEN)->exists(),
            'sample_data.preflight.fiscal_year',
            'admin.accounting.fiscal_years.index'
        );

        $checks[] = $this->requiredCheck(
            'bank',
            Bank::query()->where('active', true)->exists(),
            'sample_data.preflight.bank',
            'admin.accounting.banks.index'
        );

        $checks[] = $this->requiredCheck(
            'chequebook',
            Chequebook::query()->where('active', true)->exists(),
            'sample_data.preflight.chequebook',
            'admin.accounting.chequebooks.index'
        );

        $checks[] = $this->requiredCheck(
            'payment_method_cash',
            PaymentMethod::query()->where('active', true)->where('type', PaymentMethod::TYPE_CASH)->exists(),
            'sample_data.preflight.payment_method_cash',
            'admin.accounting.payment-methods.index'
        );

        $checks[] = $this->requiredCheck(
            'payment_method_cheque',
            PaymentMethod::query()->where('active', true)->where('type', PaymentMethod::TYPE_CHEQUE)->exists(),
            'sample_data.preflight.payment_method_cheque',
            'admin.accounting.payment-methods.index'
        );

        $checks[] = $this->requiredSystemAccountCheck(
            'accounts_receivable',
            'accounting.system_accounts.assets.accounts_receivable',
            (string) config('accounting.system_accounts.assets.accounts_receivable', ''),
            'sample_data.preflight.accounts_receivable',
            [
                'settings_tab' => 'general-tab',
                'settings_focus_tags' => 'assets.accounts_receivable',
            ]
        );
        $checks[] = $this->requiredSystemAccountCheck(
            'accounts_payable',
            'accounting.system_accounts.liabilities.accounts_payable',
            (string) config('accounting.system_accounts.liabilities.accounts_payable', ''),
            'sample_data.preflight.accounts_payable',
            [
                'settings_tab' => 'general-tab',
                'settings_focus_tags' => 'liabilities.accounts_payable',
            ]
        );
        $checks[] = $this->requiredSystemAccountCheck(
            'inventory',
            'accounting.system_accounts.assets.inventory',
            (string) config('accounting.system_accounts.assets.inventory', ''),
            'sample_data.preflight.inventory'
        );
        $checks[] = $this->requiredSystemAccountCheck(
            'cheques_receivable_clearing',
            'accounting.system_accounts.assets.cheques_receivable_clearing',
            (string) config('accounting.system_accounts.assets.cheques_receivable_clearing', ''),
            'sample_data.preflight.cheques_receivable_clearing',
            [
                'settings_tab' => 'general-tab',
                'settings_focus_tags' => 'assets.cheques_receivable_clearing',
            ]
        );
        $checks[] = $this->requiredSystemAccountCheck(
            'cheques_payable_clearing',
            'accounting.system_accounts.liabilities.cheques_payable_clearing',
            (string) config('accounting.system_accounts.liabilities.cheques_payable_clearing', ''),
            'sample_data.preflight.cheques_payable_clearing',
            [
                'settings_tab' => 'general-tab',
                'settings_focus_tags' => 'liabilities.cheques_payable_clearing',
            ]
        );
        $checks[] = $this->requiredSystemAccountCheck(
            'bank_charges',
            'accounting.system_accounts.expenses.bank_charges',
            (string) config('accounting.system_accounts.expenses.bank_charges', ''),
            'sample_data.preflight.bank_charges',
            [
                'settings_tab' => 'bank-reconciliation-tab',
                'settings_focus_tags' => 'expenses.bank_charges',
            ]
        );

        $vatReceivableId = (int) Setting::get('accounting.vat.account_receivable_id', 0);
        $checks[] = $this->requiredCheck(
            'vat_receivable_account',
            $vatReceivableId > 0 && Account::query()->whereKey($vatReceivableId)->exists(),
            'sample_data.preflight.vat_receivable',
            'admin.accounting.settings.index',
            [
                'settings_tab' => 'tax-tab',
                'settings_focus_tags' => 'vat.account_receivable_id',
            ]
        );

        $errors = [];
        $warnings = [];
        foreach ($checks as $check) {
            if (($check['level'] ?? 'error') === 'error' && ! ($check['ok'] ?? false)) {
                $errors[] = (string) $check['message'];
            }
            if (($check['level'] ?? '') === 'warning' && ! ($check['ok'] ?? false)) {
                $warnings[] = (string) $check['message'];
            }
        }

        return [
            'ok' => $errors === [],
            'checks' => $checks,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  callable(string):void|null  $logger
     * @return array<string,int>
     */
    public function runFreshRebuild(
        ?callable $logger = null,
        bool $wipeAllBeforeGenerate = false,
        array $options = [],
        string $mode = self::MODE_REBUILD
    ): array
    {
        $this->log($logger, trans('accounting::accounting.sample_data.logs.preflight_start'));
        $preflight = $this->preflight();
        if (! $preflight['ok']) {
            foreach ($preflight['errors'] as $error) {
                $this->log($logger, ' - '.$error);
            }
            throw new RuntimeException((string) trans('accounting::accounting.sample_data.errors.preflight_failed'));
        }

        $mode = in_array($mode, [self::MODE_APPEND, self::MODE_REBUILD], true) ? $mode : self::MODE_REBUILD;
        $normalizedOptions = $this->normalizeGenerationOptions($options);
        $isAppend = $mode === self::MODE_APPEND;

        mt_srand(240513);

        if (! $isAppend) {
            if ($wipeAllBeforeGenerate) {
                $this->log($logger, trans('accounting::accounting.sample_data.logs.full_wipe_start'));
                $this->accountingDataWipeService->run(\RMS\Accounting\Services\AccountingWipe\WipeOptions::allTables(
                    dryRun: false,
                    confirmedReset: true
                ));
                $this->log($logger, trans('accounting::accounting.sample_data.logs.full_wipe_done'));
            } else {
                $this->log($logger, trans('accounting::accounting.sample_data.logs.cleanup_start'));
                $this->cleanupOldSampleData();
                $this->log($logger, trans('accounting::accounting.sample_data.logs.cleanup_done'));
            }
        }

        $parties = $this->seedCustomersAndSuppliers($normalizedOptions, $isAppend);
        $this->seedPurchases($parties['supplier_ids'], $normalizedOptions, $isAppend);
        $this->seedSales($parties['customer_ids'], $normalizedOptions, $isAppend);
        $this->seedPaymentsAndCheques(
            $parties['customer_ids'],
            $parties['supplier_ids'],
            $parties['customer_party_map'],
            $parties['supplier_party_map'],
            $normalizedOptions,
            $isAppend
        );
        $this->seedSampleExpenses($normalizedOptions, $isAppend);
        $this->seedSampleFixedAssets($normalizedOptions, $isAppend);
        $this->seedShareholdersAndWithdrawals($normalizedOptions, $isAppend);
        $this->treasuryBalanceCacheSyncService->syncAllTreasuryCaches();

        $summary = $this->summary();
        $this->log($logger, trans('accounting::accounting.sample_data.logs.complete'));

        return $summary;
    }

    /**
     * @return array{customer_ids:array<int,int>,supplier_ids:array<int,int>,customer_party_map:array<int,int>,supplier_party_map:array<int,int>}
     */
    private function seedCustomersAndSuppliers(array $options, bool $appendMode): array
    {
        $baseCurrencyCode = Currency::resolveBaseCurrencyCode('IRR');
        $customerIds = [];
        $supplierIds = [];
        $customerPartyMap = [];
        $supplierPartyMap = [];

        $existingCustomerCount = (int) Customer::query()
            ->where('phone', 'like', self::CUSTOMER_PHONE_PREFIX.'%')
            ->count();
        $startSequence = $appendMode ? ($existingCustomerCount + 1) : 1;
        $requestedCustomers = (int) ($options['customers_count'] ?? 0);
        $customerLimit = $appendMode
            ? max(0, min($requestedCustomers, self::MAX_CUSTOMERS - $existingCustomerCount))
            : $requestedCustomers;

        $sharedSuppliersCount = min($customerLimit, (int) ($options['shared_suppliers_count'] ?? 0));
        $customerSnapshot = $this->loadOldCrmCustomerSnapshot();

        for ($offset = 0; $offset < $customerLimit; $offset++) {
            $sequence = $startSequence + $offset;
            $code = str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $isSharedParty = $offset < $sharedSuppliersCount;
            $displayName = $this->resolveCustomerDisplayName($sequence, $customerSnapshot);

            $customer = $this->partyService->createOrLinkCustomer([
                'name' => $displayName,
                'type' => 'Regular',
                'phone' => self::CUSTOMER_PHONE_PREFIX.$code,
                'national_code' => substr(self::PARTY_NATIONAL_PREFIX.$code.'0', 0, 10),
                'active' => true,
                'default_currency_code' => $baseCurrencyCode,
            ]);

            $customerIds[] = (int) $customer->id;
            $customerPartyMap[(int) $customer->id] = (int) $customer->party_id;

            if ($isSharedParty) {
                $supplier = $this->partyService->linkAsSupplier((int) $customer->party_id, [
                    'code' => self::SUPPLIER_CODE_PREFIX.$code,
                    // Shared-party rows should look obviously connected in UI.
                    'name' => $displayName,
                    'phone' => self::CUSTOMER_PHONE_PREFIX.$code,
                    'currency_code' => $baseCurrencyCode,
                    'payment_terms_days' => 30,
                    'active' => true,
                    'notes' => self::MARKER.'|supplier|shared_party',
                ]);

                $supplierIds[] = (int) $supplier->id;
                $supplierPartyMap[(int) $supplier->id] = (int) $supplier->party_id;
            }
        }

        $existingCustomers = Customer::query()
            ->where('phone', 'like', self::CUSTOMER_PHONE_PREFIX.'%')
            ->get(['id', 'party_id']);
        foreach ($existingCustomers as $customer) {
            $customerIds[] = (int) $customer->id;
            $customerPartyMap[(int) $customer->id] = (int) $customer->party_id;
        }

        $existingSuppliers = Supplier::query()
            ->where('code', 'like', self::SUPPLIER_CODE_PREFIX.'%')
            ->get(['id', 'party_id']);
        foreach ($existingSuppliers as $supplier) {
            $supplierIds[] = (int) $supplier->id;
            $supplierPartyMap[(int) $supplier->id] = (int) $supplier->party_id;
        }

        return [
            'customer_ids' => array_values(array_unique($customerIds)),
            'supplier_ids' => array_values(array_unique($supplierIds)),
            'customer_party_map' => $customerPartyMap,
            'supplier_party_map' => $supplierPartyMap,
        ];
    }

    /**
     * @param  array<int,int>  $supplierIds
     */
    private function seedPurchases(array $supplierIds, array $options, bool $appendMode): void
    {
        if ($supplierIds === []) {
            return;
        }
        $defaultVatRate = (float) Setting::get('accounting.vat.rate', 9);
        $baseCurrencyCode = Currency::resolveBaseCurrencyCode('IRR');
        $poCount = (int) ($options['purchase_orders_count'] ?? 0);
        $directInvoiceCount = (int) ($options['supplier_direct_invoices_count'] ?? 0);
        $poSequence = $this->nextSequenceByPrefix('purchase_orders', 'po_number', self::PO_PREFIX);
        $supplierPoInvoiceSequence = $this->nextSequenceByPrefix('supplier_invoices', 'invoice_number', self::INV_PREFIX.'PO');
        $supplierDirectInvoiceSequence = $this->nextSequenceByPrefix('supplier_invoices', 'invoice_number', self::INV_PREFIX.'DR');

        // PO-based invoices
        for ($i = 0; $i < $poCount; $i++) {
            $seq = $poSequence + $i;
            $poInvoiceSeq = $supplierPoInvoiceSequence + $i;
            $dayIndex = $i + 1;
            $supplierId = $supplierIds[array_rand($supplierIds)];
            $base = $this->roundedAmount(10_000_000, 30_000_000);
            $po = PurchaseOrder::query()->create([
                'po_number' => self::PO_PREFIX.str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
                'supplier_id' => $supplierId,
                'store_id' => 0,
                'order_date' => Carbon::now()->subDays(40 - $dayIndex)->toDateString(),
                'expected_delivery_date' => Carbon::now()->subDays(35 - $dayIndex)->toDateString(),
                'subtotal' => $base,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => $base,
                'currency_code' => $baseCurrencyCode,
                'fx_rate_at_order' => 1,
                'amount_base_at_order' => $base,
                'status' => PurchaseOrder::STATUS_CONFIRMED,
                'notes' => self::MARKER.'|po',
            ]);

            $po->items()->create([
                'product_name' => 'Sample Raw Material '.$seq,
                'quantity' => 1,
                'unit_price' => $base,
                'tax_rate' => 0,
                'discount_amount' => 0,
                'total_price' => $base,
                'notes' => self::MARKER,
            ]);

            $invoice = $this->purchaseOrderService->convertToSupplierInvoice($po, [
                'invoice_number' => self::INV_PREFIX.'PO'.str_pad((string) $poInvoiceSeq, 4, '0', STR_PAD_LEFT),
                'invoice_date' => Carbon::now()->subDays(30 - $dayIndex)->toDateString(),
                'notes' => self::MARKER.'|from_po',
            ]);
            if (! $invoice->items()->exists()) {
                $invoice->items()->create([
                    'product_name' => 'PO Converted Item '.$seq,
                    'quantity' => 1,
                    'unit_price' => $base,
                    'tax_rate' => 0,
                    'discount_amount' => 0,
                    'total_price' => $base,
                    'tax_amount' => 0,
                    'shipping_amount' => 0,
                ]);
            }
            $this->supplierInvoiceService->postPurchaseAccountingDocument($invoice);
        }

        // Direct invoices (no PO / warehouse-free examples)
        for ($i = 0; $i < $directInvoiceCount; $i++) {
            $seq = $supplierDirectInvoiceSequence + $i;
            $dayIndex = $i + 1;
            $supplierId = $supplierIds[array_rand($supplierIds)];
            $lineOneBase = $this->roundedAmount(6_000_000, 18_000_000);
            $lineTwoBase = $this->roundedAmount(3_000_000, 12_000_000);
            $lineOneDiscount = $dayIndex % 2 === 0 ? 100_000 : 0;
            $lineTwoDiscount = $dayIndex % 3 === 0 ? 100_000 : 0;
            $lineOneRate = $defaultVatRate;
            $lineTwoRate = $dayIndex % 3 === 0 ? 0.0 : $defaultVatRate;
            $lineOneNet = max(0, $lineOneBase - $lineOneDiscount);
            $lineTwoNet = max(0, $lineTwoBase - $lineTwoDiscount);
            $subtotal = $lineOneNet + $lineTwoNet;
            $tax = (float) round(($lineOneNet * ($lineOneRate / 100)) + ($lineTwoNet * ($lineTwoRate / 100)), 4);
            $total = $subtotal + $tax;
            $this->supplierInvoiceService->createInvoice([
                'invoice_number' => self::INV_PREFIX.'DR'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
                'supplier_id' => $supplierId,
                'purchase_order_id' => null,
                'store_id' => 0,
                'invoice_date' => Carbon::now()->subDays(20 - $dayIndex)->toDateString(),
                'due_date' => Carbon::now()->addDays(15 + $dayIndex)->toDateString(),
                'subtotal' => $subtotal,
                'tax_amount' => $tax,
                'discount_amount' => 0,
                'total_amount' => $total,
                'currency_code' => $baseCurrencyCode,
                'fx_rate_at_invoice' => 1,
                'amount_base_at_invoice' => $total,
                'payment_status' => SupplierInvoice::STATUS_UNPAID,
                'paid_amount' => 0,
                'balance_due' => $total,
                'settlement_mode' => SupplierInvoice::SETTLEMENT_ON_ACCOUNT,
                'notes' => self::MARKER.'|direct_no_warehouse',
            ], [
                [
                    'product_name' => 'Sample Service Item '.$seq.'-A',
                    'quantity' => 1,
                    'unit_price' => $lineOneBase,
                    'tax_rate' => $lineOneRate,
                    'discount_amount' => $lineOneDiscount,
                    'total_price' => $lineOneNet,
                    'tax_amount' => round($lineOneNet * ($lineOneRate / 100), 4),
                    'shipping_amount' => 0,
                ],
                [
                    'product_name' => 'Sample Service Item '.$seq.'-B',
                    'quantity' => 1,
                    'unit_price' => $lineTwoBase,
                    'tax_rate' => $lineTwoRate,
                    'discount_amount' => $lineTwoDiscount,
                    'total_price' => $lineTwoNet,
                    'tax_amount' => round($lineTwoNet * ($lineTwoRate / 100), 4),
                    'shipping_amount' => 0,
                ],
            ]);
        }
    }

    /**
     * @param  array<int,int>  $customerIds
     */
    private function seedSales(array $customerIds, array $options, bool $appendMode): void
    {
        if ($customerIds === []) {
            return;
        }
        $defaultVatRate = (float) Setting::get('accounting.vat.rate', 9);
        $baseCurrencyCode = Currency::resolveBaseCurrencyCode('IRR');
        $salesInvoiceCount = (int) ($options['sales_invoices_count'] ?? 0);
        $salesInvoiceSequence = $this->nextSequenceByPrefix('customer_invoices', 'invoice_number', self::SALES_INV_PREFIX);

        for ($i = 0; $i < $salesInvoiceCount; $i++) {
            $seq = $salesInvoiceSequence + $i;
            $dayIndex = $i + 1;
            $customerId = $customerIds[array_rand($customerIds)];
            $lineOneBase = $this->roundedAmount(7_000_000, 16_000_000);
            $lineTwoBase = $this->roundedAmount(3_000_000, 12_000_000);
            $lineOneDiscount = $dayIndex % 2 === 0 ? 100_000 : 0;
            $lineTwoDiscount = $dayIndex % 4 === 0 ? 100_000 : 0;
            $lineOneRate = $defaultVatRate;
            $lineTwoRate = $dayIndex % 4 === 0 ? 0.0 : ($dayIndex % 5 === 0 ? max(0.0, $defaultVatRate + 1.0) : $defaultVatRate);
            $lineOneNet = max(0, $lineOneBase - $lineOneDiscount);
            $lineTwoNet = max(0, $lineTwoBase - $lineTwoDiscount);

            $invoiceDate = Carbon::now()->subDays(16 - $dayIndex)->toDateString();
            $invoice = $this->customerInvoiceService->createInvoice([
                'invoice_number' => self::SALES_INV_PREFIX.str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
                'customer_id' => $customerId,
                'store_id' => 0,
                'invoice_date' => $invoiceDate,
                'due_date' => Carbon::now()->addDays(15 + $dayIndex)->toDateString(),
                'currency_code' => $baseCurrencyCode,
                'fx_rate' => 1.0,
                'settlement_mode' => CustomerInvoice::SETTLEMENT_CREDIT,
                'status' => CustomerInvoice::STATUS_DRAFT,
                'tax_method' => function_exists('tax_calculation_method') ? tax_calculation_method() : 'exclusive',
                'notes' => self::MARKER.'|sales_vat',
            ]);

            $this->customerInvoiceItemAdminService->createLine($invoice, [
                'product_name' => 'Sample Sales Item '.$seq.'-A',
                'quantity' => 1,
                'price' => $lineOneBase,
                'tax_rate' => $lineOneRate,
                'discount_amount' => $lineOneDiscount,
                'tax_amount' => round($lineOneNet * ($lineOneRate / 100), 4),
            ]);
            $this->customerInvoiceItemAdminService->createLine($invoice->fresh(), [
                'product_name' => 'Sample Sales Item '.$seq.'-B',
                'quantity' => 1,
                'price' => $lineTwoBase,
                'tax_rate' => $lineTwoRate,
                'discount_amount' => $lineTwoDiscount,
                'tax_amount' => round($lineTwoNet * ($lineTwoRate / 100), 4),
            ]);

            $this->customerInvoiceService->postSalesAccountingDocument($invoice->fresh());
        }
    }

    /**
     * @param  array<int,int>  $customerIds
     * @param  array<int,int>  $supplierIds
     * @param  array<int,int>  $customerPartyMap
     * @param  array<int,int>  $supplierPartyMap
     */
    private function seedPaymentsAndCheques(
        array $customerIds,
        array $supplierIds,
        array $customerPartyMap,
        array $supplierPartyMap,
        array $options,
        bool $appendMode
    ): void {
        if ($customerIds === []) {
            return;
        }
        $cashMethodId = (int) PaymentMethod::query()->where('active', true)->where('type', PaymentMethod::TYPE_CASH)->value('id');
        $chequeMethodId = (int) PaymentMethod::query()->where('active', true)->where('type', PaymentMethod::TYPE_CHEQUE)->value('id');
        $baseCurrencyCode = Currency::resolveBaseCurrencyCode('IRR');
        $banks = $this->preferredActiveBanks();
        $cashBoxes = $this->preferredActiveCashBoxes();
        $fallbackChequebookId = (int) Chequebook::query()->where('active', true)->value('id');
        $customerPaymentsCount = (int) ($options['customer_payments_count'] ?? 0);
        $customerChequeCount = min($customerPaymentsCount, (int) ($options['customer_cheque_payments_count'] ?? 0));
        $supplierPaymentsCount = (int) ($options['supplier_payments_count'] ?? 0);
        $supplierChequeCount = min($supplierPaymentsCount, (int) ($options['supplier_cheque_payments_count'] ?? 0));
        $customerPaymentSequence = $this->nextSequenceByPrefix('customer_payments', 'payment_number', self::CUSTOMER_PAY_PREFIX);
        $supplierPaymentSequence = $this->nextSequenceByPrefix('supplier_payments', 'payment_number', self::SUPPLIER_PAY_PREFIX);
        $receivedChequeSequence = $this->nextSequenceByPrefix('cheques', 'cheque_number', self::CHEQUE_PREFIX.'RCV');
        $issuedChequeSequence = $this->nextSequenceByPrefix('cheques', 'cheque_number', self::CHEQUE_PREFIX.'ISS');

        // Customer-side payments: cash + cheque
        for ($i = 0; $i < $customerPaymentsCount; $i++) {
            $seq = $customerPaymentSequence + $i;
            $dayIndex = $i + 1;
            $customerId = $customerIds[array_rand($customerIds)];
            $amount = $this->roundedAmount(1_000_000, 7_000_000);
            $isCheque = $i >= ($customerPaymentsCount - $customerChequeCount);
            $chequeId = null;
            $bankId = $this->bankIdAtIndex($banks, $i);
            $cashBoxId = $this->cashBoxIdAtIndex($cashBoxes, $i);

            if ($isCheque) {
                $receivedChequeNumberSeq = $receivedChequeSequence++;
                $cheque = $this->chequeAutoCreationService->ensureCheque([
                    'context' => 'sample_customer_payment',
                    'source_short' => 'SCP',
                    'payment_method_id' => $chequeMethodId,
                    'cheque_type' => Cheque::TYPE_RECEIVED,
                    'party_id' => $customerPartyMap[$customerId] ?? null,
                    'amount' => $amount,
                    'currency_code' => $baseCurrencyCode,
                    'bank_id' => $bankId,
                    'issue_date' => Carbon::now()->subDays(8 - $dayIndex)->toDateString(),
                    'due_date' => Carbon::now()->addDays($dayIndex)->toDateString(),
                    'notes' => self::MARKER.'|customer_cheque',
                ]);
                if ($cheque) {
                    $cheque->update([
                        'cheque_number' => self::CHEQUE_PREFIX.'RCV'.str_pad((string) $receivedChequeNumberSeq, 4, '0', STR_PAD_LEFT),
                    ]);
                    if ($dayIndex % 2 === 0 && $this->chequeLedgerService->canCashCheque($cheque->fresh())) {
                        $this->chequeLedgerService->recordChequeCashed($cheque->fresh());
                    }
                    $chequeId = (int) $cheque->id;
                }
            }

            $payment = $this->customerPaymentService->createPayment([
                'payment_number' => self::CUSTOMER_PAY_PREFIX.str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
                'customer_id' => $customerId,
                'store_id' => 0,
                'payment_method_id' => $isCheque ? $chequeMethodId : $cashMethodId,
                'amount' => $amount,
                'currency_code' => $baseCurrencyCode,
                'payment_date' => Carbon::now()->subDays(8 - $dayIndex)->toDateString(),
                'status' => CustomerPayment::STATUS_COMPLETED,
                'bank_id' => $isCheque ? $bankId : null,
                'cash_box_id' => $isCheque ? null : ($cashBoxId > 0 ? $cashBoxId : null),
                'cheque_id' => $chequeId,
                'notes' => self::MARKER.'|customer_payment',
            ]);

            if ($chequeId) {
                $cheque = Cheque::query()->find($chequeId);
                if ($cheque) {
                    $this->chequeAutoCreationService->attachSource($cheque, CustomerPayment::class, (int) $payment->id);
                }
            }
        }

        // Supplier-side payments: cash + cheque + unpaid invoices (credit)
        $unpaidInvoices = SupplierInvoice::query()
            ->where('invoice_number', 'like', self::INV_PREFIX.'%')
            ->orderBy('id')
            ->get();
        $supplierPaymentRows = $unpaidInvoices->take($supplierPaymentsCount)->values();

        foreach ($supplierPaymentRows as $idx => $invoice) {
            $paymentIndex = $idx + 1;
            $seq = $supplierPaymentSequence + $idx;

            $amount = min((float) $invoice->balance_due, $this->roundedAmount(1_000_000, 8_000_000));
            if ($amount <= 0) {
                continue;
            }

            $bankId = $this->bankIdAtIndex($banks, $idx);
            $cashBoxId = $this->cashBoxIdAtIndex($cashBoxes, $idx);
            $chequebookIdForBank = $this->resolveChequebookIdForBank($bankId, $fallbackChequebookId);

            $isCheque = $idx >= ($supplierPaymentsCount - $supplierChequeCount);
            $chequeId = null;
            if ($isCheque) {
                $issuedChequeNumberSeq = $issuedChequeSequence++;
                $supplierId = (int) $invoice->supplier_id;
                $cheque = $this->chequeAutoCreationService->ensureCheque([
                    'context' => 'sample_supplier_payment',
                    'source_short' => 'SSP',
                    'payment_method_id' => $chequeMethodId,
                    'cheque_type' => Cheque::TYPE_ISSUED,
                    'party_id' => $supplierPartyMap[$supplierId] ?? null,
                    'amount' => $amount,
                    'currency_code' => $baseCurrencyCode,
                    'bank_id' => $bankId,
                    'chequebook_id' => $chequebookIdForBank > 0 ? $chequebookIdForBank : null,
                    'issue_date' => Carbon::now()->subDays($paymentIndex)->toDateString(),
                    'due_date' => Carbon::now()->addDays($paymentIndex + 5)->toDateString(),
                    'notes' => self::MARKER.'|supplier_cheque',
                ]);
                if ($cheque) {
                    $cheque->update([
                        'cheque_number' => self::CHEQUE_PREFIX.'ISS'.str_pad((string) $issuedChequeNumberSeq, 4, '0', STR_PAD_LEFT),
                    ]);
                    if ($paymentIndex % 2 === 1 && $this->chequeLedgerService->canCashCheque($cheque->fresh())) {
                        $this->chequeLedgerService->recordChequeCashed($cheque->fresh());
                    }
                    $chequeId = (int) $cheque->id;
                }
            }

            $payment = $this->supplierPaymentService->recordPayment([
                'payment_number' => self::SUPPLIER_PAY_PREFIX.str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
                'supplier_id' => (int) $invoice->supplier_id,
                'supplier_invoice_id' => (int) $invoice->id,
                'payment_method_id' => $isCheque ? $chequeMethodId : $cashMethodId,
                'amount' => $amount,
                'currency_code' => $baseCurrencyCode,
                'payment_date' => Carbon::now()->subDays($paymentIndex)->toDateString(),
                'bank_id' => $isCheque ? $bankId : null,
                'cash_box_id' => $isCheque ? null : ($cashBoxId > 0 ? $cashBoxId : null),
                'cheque_id' => $chequeId,
                'status' => SupplierPayment::STATUS_COMPLETED,
                'notes' => self::MARKER.'|supplier_payment',
                'store_id' => 0,
            ]);

            if ($chequeId) {
                $cheque = Cheque::query()->find($chequeId);
                if ($cheque) {
                    $this->chequeAutoCreationService->attachSource($cheque, SupplierPayment::class, (int) $payment->id);
                }
            }
        }
    }

    private function cleanupOldSampleData(): void
    {
        $this->cleanupSampleExpenseRecords();
        $this->cleanupSampleFixedAssets();
        $this->cleanupSampleShareholderCapitalContributions();

        ShareholderWithdrawal::query()
            ->where('description', 'like', self::SHAREHOLDER_MARKER.'%')
            ->delete();
        Shareholder::withTrashed()
            ->where('notes', 'like', self::SHAREHOLDER_MARKER.'%')
            ->forceDelete();

        $supplierPaymentIds = SupplierPayment::withTrashed()
            ->where('payment_number', 'like', self::SUPPLIER_PAY_PREFIX.'%')
            ->pluck('id');
        if ($supplierPaymentIds->isNotEmpty()) {
            SupplierPayment::withTrashed()->whereIn('id', $supplierPaymentIds)->forceDelete();
        }

        $customerPaymentIds = CustomerPayment::withTrashed()
            ->where('payment_number', 'like', self::CUSTOMER_PAY_PREFIX.'%')
            ->pluck('id');
        if ($customerPaymentIds->isNotEmpty()) {
            CustomerPayment::withTrashed()->whereIn('id', $customerPaymentIds)->forceDelete();
        }

        Cheque::query()->where('cheque_number', 'like', self::CHEQUE_PREFIX.'%')->delete();

        $customerInvoiceIds = CustomerInvoice::withTrashed()
            ->where('invoice_number', 'like', self::SALES_INV_PREFIX.'%')
            ->pluck('id');
        if ($customerInvoiceIds->isNotEmpty()) {
            CustomerInvoiceItem::query()->whereIn('customer_invoice_id', $customerInvoiceIds)->delete();
            CustomerInvoice::withTrashed()->whereIn('id', $customerInvoiceIds)->forceDelete();
        }

        $supplierInvoiceIds = SupplierInvoice::withTrashed()
            ->where('invoice_number', 'like', self::INV_PREFIX.'%')
            ->pluck('id');
        if ($supplierInvoiceIds->isNotEmpty()) {
            SupplierInvoiceItem::query()->whereIn('supplier_invoice_id', $supplierInvoiceIds)->delete();
            SupplierInvoice::withTrashed()->whereIn('id', $supplierInvoiceIds)->forceDelete();
        }

        PurchaseOrder::withTrashed()->where('po_number', 'like', self::PO_PREFIX.'%')->forceDelete();

        Supplier::withTrashed()->where('code', 'like', self::SUPPLIER_CODE_PREFIX.'%')->forceDelete();
        Customer::query()->where('phone', 'like', self::CUSTOMER_PHONE_PREFIX.'%')->delete();

        $partyCleanup = function ($query): void {
            $query->where(function ($q) {
                $q->where('national_code', 'like', self::PARTY_NATIONAL_PREFIX.'%')
                    ->orWhere('phone', 'like', self::CUSTOMER_PHONE_PREFIX.'%');
            });
        };
        if (in_array(SoftDeletes::class, class_uses_recursive(Party::class), true)) {
            $q = Party::withTrashed();
            $partyCleanup($q);
            $q->forceDelete();
        } else {
            $q = Party::query();
            $partyCleanup($q);
            $q->delete();
        }
    }

    /**
     * @return array<string,int>
     */
    private function summary(): array
    {
        return [
            'customers' => Customer::query()->where('national_code', 'like', self::PARTY_NATIONAL_PREFIX.'%')->count(),
            'suppliers' => Supplier::query()->where('code', 'like', self::SUPPLIER_CODE_PREFIX.'%')->count(),
            'purchase_orders' => PurchaseOrder::query()->where('po_number', 'like', self::PO_PREFIX.'%')->count(),
            'supplier_invoices' => SupplierInvoice::query()->where('invoice_number', 'like', self::INV_PREFIX.'%')->count(),
            'customer_invoices' => CustomerInvoice::query()->where('invoice_number', 'like', self::SALES_INV_PREFIX.'%')->count(),
            'customer_payments' => CustomerPayment::query()->where('payment_number', 'like', self::CUSTOMER_PAY_PREFIX.'%')->count(),
            'supplier_payments' => SupplierPayment::query()->where('payment_number', 'like', self::SUPPLIER_PAY_PREFIX.'%')->count(),
            'cheques' => Cheque::query()->where('cheque_number', 'like', self::CHEQUE_PREFIX.'%')->count(),
            'shareholders' => Shareholder::query()->where('notes', 'like', self::SHAREHOLDER_MARKER.'%')->count(),
            'shareholder_withdrawals' => ShareholderWithdrawal::query()->where('description', 'like', self::SHAREHOLDER_MARKER.'%')->count(),
            'shareholder_capital_contributions' => ShareholderCapitalContribution::query()->where('description', 'like', self::SHAREHOLDER_MARKER.'|capital|%')->count(),
            'fixed_asset_categories' => FixedAssetCategory::query()->where('code', 'like', self::FIXED_ASSET_CATEGORY_CODE_PREFIX.'%')->count(),
            'fixed_assets' => FixedAsset::query()->where('asset_code', 'like', self::FIXED_ASSET_CODE_PREFIX.'%')->count(),
            'expenses' => Expense::query()->where('expense_number', 'like', self::EXPENSE_PREFIX.'%')->count(),
        ];
    }

    private function seedSampleExpenses(array $options, bool $appendMode): void
    {
        $categoryId = $this->resolveSampleExpenseCategoryId();
        if ($categoryId < 1) {
            return;
        }
        $baseCurrencyCode = Currency::resolveBaseCurrencyCode('IRT');

        $banks = $this->preferredActiveBanks();
        $cashBoxes = $this->preferredActiveCashBoxes();
        if ($banks->isEmpty() && $cashBoxes->isEmpty()) {
            return;
        }

        $types = [
            Expense::TYPE_OPERATIONAL,
            Expense::TYPE_UTILITIES,
            Expense::TYPE_SUPPLIES,
            Expense::TYPE_TRANSPORTATION,
            Expense::TYPE_MAINTENANCE,
            Expense::TYPE_MARKETING,
            Expense::TYPE_OTHER,
        ];
        $expensesCount = (int) ($options['expenses_count'] ?? 0);
        $expenseSequence = $this->nextSequenceByPrefix('expenses', 'expense_number', self::EXPENSE_PREFIX);

        for ($i = 0; $i < $expensesCount; $i++) {
            $seq = $expenseSequence + $i;
            $dayIndex = $i + 1;
            $amount = $this->roundedExpenseAmount(500_000, 2_000_000);
            $preferBank = $banks->isNotEmpty() && ($cashBoxes->isEmpty() || $i % 2 === 1);
            $bankId = $preferBank ? $this->bankIdAtIndex($banks, $i) : null;
            $cashBoxId = ! $preferBank ? $this->cashBoxIdAtIndex($cashBoxes, $i) : null;
            if ($preferBank && $bankId < 1) {
                $bankId = null;
                $cashBoxId = $this->cashBoxIdAtIndex($cashBoxes, $i);
            }
            if ($bankId === null && $cashBoxId < 1) {
                continue;
            }

            $expense = Expense::query()->create([
                'expense_number' => self::EXPENSE_PREFIX.str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
                'expense_category_id' => $categoryId,
                'expense_type' => $types[$i % count($types)],
                'amount' => $amount,
                'currency_code' => $baseCurrencyCode,
                'fx_rate' => 1,
                'amount_base' => $amount,
                'expense_date' => Carbon::now()->subDays(28 - $dayIndex)->toDateString(),
                'payment_status' => 'paid',
                'paid_amount' => $amount,
                'payee_type' => 'other',
                'payee_name' => 'هزینه نمونه '.$seq,
                'description' => self::MARKER.'|expense|'.$seq,
                'status' => Expense::STATUS_PAID,
                'bank_id' => $bankId !== null && $bankId > 0 ? $bankId : null,
                'cash_box_id' => ($bankId !== null && $bankId > 0) ? null : (! empty($cashBoxId) && $cashBoxId > 0 ? $cashBoxId : null),
                'notes' => self::MARKER.'|expense',
            ]);

            $this->expenseService->ensureLedgerPosted($expense);
        }
    }

    private function resolveSampleExpenseCategoryId(): int
    {
        $existingValidCategoryId = (int) ExpenseCategory::query()
            ->active()
            ->whereHas('account', static function ($query): void {
                $query->where('active', true)->where('account_type', Account::TYPE_EXPENSE);
            })
            ->orderBy('id')
            ->value('id');
        if ($existingValidCategoryId > 0) {
            return $existingValidCategoryId;
        }

        $expenseAccountId = $this->resolveSampleExpenseAccountId();
        if ($expenseAccountId < 1) {
            return 0;
        }

        $category = ExpenseCategory::query()->updateOrCreate(
            ['code' => self::SAMPLE_EXPENSE_CATEGORY_CODE],
            [
                'name' => 'هزینه‌های عملیاتی نمونه',
                'parent_id' => null,
                'account_id' => $expenseAccountId,
                'description' => self::MARKER.'|expense_category',
                'active' => true,
            ]
        );

        return (int) $category->id;
    }

    private function resolveSampleExpenseAccountId(): int
    {
        $configuredCode = trim((string) Setting::get(
            'accounting.system_accounts.expenses.bank_charges',
            (string) config('accounting.system_accounts.expenses.bank_charges', '')
        ));
        if ($configuredCode !== '') {
            $configuredId = (int) Account::query()
                ->where('code', $configuredCode)
                ->where('active', true)
                ->where('account_type', Account::TYPE_EXPENSE)
                ->value('id');
            if ($configuredId > 0) {
                return $configuredId;
            }
        }

        return (int) Account::query()
            ->where('active', true)
            ->where('account_type', Account::TYPE_EXPENSE)
            ->orderBy('id')
            ->value('id');
    }

    private function seedSampleFixedAssets(array $options, bool $appendMode): void
    {
        $assetAcc = $this->resolveAccountByCode('1200');
        $accDepAcc = $this->resolveAccountByCode('1201');
        $depExpAcc = $this->resolveAccountByCode('5205');
        if ($assetAcc === null || $accDepAcc === null || $depExpAcc === null) {
            return;
        }

        $catDesc = self::MARKER.'|fixed_asset_category';

        $catEquip = FixedAssetCategory::query()->updateOrCreate(
            ['code' => self::FIXED_ASSET_CATEGORY_CODE_PREFIX.'EQUIP'],
            [
                'name' => 'تجهیزات و ماشین‌آلات اداری (نمونه)',
                'description' => $catDesc,
                'asset_account_id' => $assetAcc,
                'depreciation_account_id' => $depExpAcc,
                'accumulated_depreciation_account_id' => $accDepAcc,
                'active' => true,
            ]
        );

        $catVehicle = FixedAssetCategory::query()->updateOrCreate(
            ['code' => self::FIXED_ASSET_CATEGORY_CODE_PREFIX.'VEHICLE'],
            [
                'name' => 'وسایل نقلیه (نمونه)',
                'description' => $catDesc,
                'asset_account_id' => $assetAcc,
                'depreciation_account_id' => $depExpAcc,
                'accumulated_depreciation_account_id' => $accDepAcc,
                'active' => true,
            ]
        );

        $catLand = FixedAssetCategory::query()->updateOrCreate(
            ['code' => self::FIXED_ASSET_CATEGORY_CODE_PREFIX.'LAND'],
            [
                'name' => 'املاک و زمین (نمونه)',
                'description' => $catDesc,
                'asset_account_id' => $assetAcc,
                'depreciation_account_id' => $depExpAcc,
                'accumulated_depreciation_account_id' => $accDepAcc,
                'active' => true,
            ]
        );

        $categoryIds = [
            'equip' => (int) $catEquip->id,
            'vehicle' => (int) $catVehicle->id,
            'land' => (int) $catLand->id,
        ];

        $samples = [
            ['code' => '0001', 'name' => 'کامپیوتر All-in-One اداری', 'cat' => 'equip', 'years' => 4, 'price_min' => 22_000_000, 'price_max' => 48_000_000, 'loc' => 'اتاق مالی', 'sn' => 'SN-FA-DELL-AIO-001'],
            ['code' => '0002', 'name' => 'لپ‌تاپ کاری لنوو', 'cat' => 'equip', 'years' => 4, 'price_min' => 18_000_000, 'price_max' => 42_000_000, 'loc' => 'واحد فروش', 'sn' => 'SN-FA-TP-X1-002'],
            ['code' => '0003', 'name' => 'میز و صندلی اداری چوبی', 'cat' => 'equip', 'years' => 8, 'price_min' => 8_000_000, 'price_max' => 28_000_000, 'loc' => 'سالن جلسات', 'sn' => null],
            ['code' => '0004', 'name' => 'خودروی سازمانی (نمونه)', 'cat' => 'vehicle', 'years' => 5, 'price_min' => 180_000_000, 'price_max' => 420_000_000, 'loc' => 'پارکینگ', 'sn' => 'VIN-SMP-1399-004'],
            ['code' => '0005', 'name' => 'زمین تجاری نمونه', 'cat' => 'land', 'years' => 30, 'price_min' => 320_000_000, 'price_max' => 920_000_000, 'loc' => 'منطقه ۵ — نمونه', 'sn' => 'سند ۱۳۹۹/نمونه-۰۰۵'],
            ['code' => '0006', 'name' => 'پرینتر لیزری رنگی اچ‌پی', 'cat' => 'equip', 'years' => 5, 'price_min' => 12_000_000, 'price_max' => 35_000_000, 'loc' => 'دفتر فنی', 'sn' => 'SN-FA-HP-CLR-006'],
        ];
        $assetsCount = max(0, (int) ($options['fixed_assets_count'] ?? 0));
        $assetSequence = $this->nextSequenceByPrefix('fixed_assets', 'asset_code', self::FIXED_ASSET_CODE_PREFIX);

        $baseDate = Carbon::now()->subMonths(14);
        for ($idx = 0; $idx < $assetsCount; $idx++) {
            $row = $samples[$idx % count($samples)];
            $sequence = $assetSequence + $idx;
            $catKey = $row['cat'];
            if (! isset($categoryIds[$catKey])) {
                continue;
            }
            $price = $this->roundedAmount((int) $row['price_min'], (int) $row['price_max']);
            $purchaseDate = $baseDate->copy()->addMonths(($idx + 1) * 2)->toDateString();
            $this->fixedAssetService->createAsset([
                'asset_code' => self::FIXED_ASSET_CODE_PREFIX.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
                'name' => (string) $row['name'].' #'.$sequence,
                'category_id' => $categoryIds[$catKey],
                'purchase_date' => $purchaseDate,
                'purchase_price' => $price,
                'useful_life_years' => (int) $row['years'],
                'useful_life_months' => 0,
                'depreciation_method' => 'straight_line',
                'salvage_value' => 0,
                'location' => (string) $row['loc'],
                'serial_number' => isset($row['sn']) && $row['sn'] !== null ? (string) $row['sn'].'-'.$sequence : null,
                'description' => self::MARKER.'|fixed_asset|'.$sequence,
                'notes' => self::MARKER.'|fixed_asset',
                'generate_schedule' => true,
                'record_purchase' => false,
            ]);
        }
    }

    private function cleanupSampleShareholderCapitalContributions(): void
    {
        $pattern = self::SHAREHOLDER_MARKER.'|capital|%';
        $journalIds = ShareholderCapitalContribution::query()
            ->where('description', 'like', $pattern)
            ->pluck('manual_journal_id')
            ->filter(fn ($id) => (int) $id > 0)
            ->unique()
            ->values()
            ->all();

        ShareholderCapitalContribution::query()->where('description', 'like', $pattern)->delete();

        foreach ($journalIds as $journalId) {
            $journal = ManualJournal::withTrashed()->find((int) $journalId);
            if (! $journal) {
                continue;
            }
            $docId = (int) ($journal->accounting_document_id ?? 0);
            ManualJournalLine::query()->where('manual_journal_id', (int) $journalId)->delete();
            if ($docId > 0) {
                FinancialLedger::query()->where('accounting_document_id', $docId)->delete();
            }
            $journal->forceDelete();
            if ($docId > 0) {
                AccountingDocument::query()->whereKey($docId)->delete();
            }
        }
    }

    private function cleanupSampleExpenseRecords(): void
    {
        $expenseIds = Expense::withTrashed()
            ->where('expense_number', 'like', self::EXPENSE_PREFIX.'%')
            ->pluck('id');
        if ($expenseIds->isEmpty()) {
            return;
        }

        $documentIds = Expense::withTrashed()
            ->whereIn('id', $expenseIds)
            ->pluck('document_id')
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        // SQLite/NoAction FK: ابتدا اتصال expenses -> accounting_documents را آزاد کن.
        Expense::withTrashed()
            ->whereIn('id', $expenseIds)
            ->whereNotNull('document_id')
            ->update(['document_id' => null]);

        foreach ($documentIds as $docId) {
            FinancialLedger::query()->where('accounting_document_id', $docId)->delete();
            AccountingDocument::query()->whereKey($docId)->delete();
        }

        FinancialLedger::query()
            ->where('source_reference_type', Expense::class)
            ->whereIn('source_reference_id', $expenseIds)
            ->delete();

        ExpenseStatusHistory::query()->whereIn('expense_id', $expenseIds)->delete();
        Expense::withTrashed()->whereIn('id', $expenseIds)->forceDelete();
    }

    private function cleanupSampleFixedAssets(): void
    {
        FixedAsset::withTrashed()
            ->where('asset_code', 'like', self::FIXED_ASSET_CODE_PREFIX.'%')
            ->forceDelete();

        FixedAssetCategory::withTrashed()
            ->where('code', 'like', self::FIXED_ASSET_CATEGORY_CODE_PREFIX.'%')
            ->forceDelete();
    }

    private function seedShareholdersAndWithdrawals(array $options, bool $appendMode): void
    {
        $banks = $this->preferredActiveBanks();
        $cashBoxes = $this->preferredActiveCashBoxes();
        $baseCurrencyCode = Currency::resolveBaseCurrencyCode('IRR');
        $bankIdFallback = $this->bankIdAtIndex($banks, 0);
        $cashBoxIdFallback = $this->cashBoxIdAtIndex($cashBoxes, 0);
        if ($bankIdFallback < 1 && $cashBoxIdFallback < 1) {
            return;
        }

        $shareholderNames = $this->generateShareholderNames((int) ($options['shareholders_count'] ?? 2));
        $capitalMin = max(1, (int) ($options['capital_contributions_min'] ?? 3));
        $capitalMax = max($capitalMin, (int) ($options['capital_contributions_max'] ?? 5));
        $withdrawalsMin = max(1, (int) ($options['withdrawals_min'] ?? 2));
        $withdrawalsMax = max($withdrawalsMin, (int) ($options['withdrawals_max'] ?? 4));
        $shareholderSeq = 0;
        foreach ($shareholderNames as $name) {
            $shareholderSeq++;
            $shareholder = Shareholder::query()->updateOrCreate(
                ['name' => $name, 'notes' => self::SHAREHOLDER_MARKER],
                [
                    'active' => true,
                    'notes' => self::SHAREHOLDER_MARKER,
                ]
            );

            $capitalCount = mt_rand($capitalMin, $capitalMax);
            $existingCapitalCount = (int) ShareholderCapitalContribution::query()
                ->where('description', 'like', self::SHAREHOLDER_MARKER.'|capital|'.$name.'|%')
                ->count();
            for ($c = 1; $c <= $capitalCount; $c++) {
                $cSeq = (($shareholderSeq - 1) * 100) + $c;
                $bankId = $this->bankIdAtIndex($banks, $cSeq - 1);
                $cashBoxId = $this->cashBoxIdAtIndex($cashBoxes, $cSeq - 1);
                $useBankCap = $bankId > 0 && ($cashBoxId < 1 || ($c % 2 === 1));
                $sourceTypeCap = $useBankCap ? ShareholderCapitalContribution::SOURCE_BANK : ShareholderCapitalContribution::SOURCE_CASH;
                $amountCap = $this->roundedAmount(51_000_000, 220_000_000);
                $dateCap = Carbon::now()->subDays(mt_rand(25, 90))->toDateString();
                $descCap = self::SHAREHOLDER_MARKER.'|capital|'.$name.'|'.($existingCapitalCount + $c);

                $this->shareholderCapitalContributionService->record([
                    'shareholder_id' => (int) $shareholder->id,
                    'amount' => $amountCap,
                    'journal_date' => $dateCap,
                    'source_type' => $sourceTypeCap,
                    'bank_id' => $sourceTypeCap === ShareholderCapitalContribution::SOURCE_BANK ? $bankId : null,
                    'cash_box_id' => $sourceTypeCap === ShareholderCapitalContribution::SOURCE_CASH ? $cashBoxId : null,
                    'currency_code' => $baseCurrencyCode,
                    'description' => $descCap,
                ]);
            }

            $withdrawCount = mt_rand($withdrawalsMin, $withdrawalsMax);
            $existingWithdrawCount = (int) ShareholderWithdrawal::query()
                ->where('description', 'like', self::SHAREHOLDER_MARKER.'|'.$name.'|wd-%')
                ->count();
            for ($i = 1; $i <= $withdrawCount; $i++) {
                $seq = (($shareholderSeq - 1) * 10) + $i;
                $bankId = $this->bankIdAtIndex($banks, $seq - 1);
                $cashBoxId = $this->cashBoxIdAtIndex($cashBoxes, $seq - 1);
                $useBank = $bankId > 0 && ($cashBoxId < 1 || ($i % 2 === 1));
                $sourceType = $useBank ? ShareholderWithdrawal::SOURCE_BANK : ShareholderWithdrawal::SOURCE_CASH;
                $amount = $this->roundedAmount(1_000_000, 5_000_000);
                $date = Carbon::now()->subDays(mt_rand(1, 20))->toDateString();
                $desc = self::SHAREHOLDER_MARKER.'|'.$name.'|wd-'.($existingWithdrawCount + $i);

                $this->shareholderWithdrawalService->record([
                    'shareholder_id' => (int) $shareholder->id,
                    'amount' => $amount,
                    'journal_date' => $date,
                    'source_type' => $sourceType,
                    'bank_id' => $sourceType === ShareholderWithdrawal::SOURCE_BANK ? $bankId : null,
                    'cash_box_id' => $sourceType === ShareholderWithdrawal::SOURCE_CASH ? $cashBoxId : null,
                    'currency_code' => $baseCurrencyCode,
                    'description' => $desc,
                    'post_journal' => true,
                ]);
            }
        }
    }

    /**
     * اگر زیر حساب معین/تفصیلی زیر «بانک» در چارت وجود دارد، فقط بانک‌هایی که به آن زیرحساب‌ها وصل‌اند
     * (نه حساب کل/معین والد خالی) برای نمونه استفاده می‌شوند؛ وگرنه همهٔ بانک‌های فعال.
     *
     * @return EloquentCollection<int, Bank>
     */
    private function preferredActiveBanks(): EloquentCollection
    {
        $rootId = $this->resolveTreasuryRootAccountId('bank');
        if ($rootId === null || $rootId < 1) {
            return Bank::query()->where('active', true)->orderBy('id')->get();
        }

        if ($this->hasActiveChildAccountsUnder($rootId)) {
            $descendantIds = $this->collectDescendantAccountIds($rootId);
            if ($descendantIds !== []) {
                $linked = Bank::query()
                    ->where('active', true)
                    ->whereIn('account_id', $descendantIds)
                    ->orderBy('id')
                    ->get();
                if ($linked->isNotEmpty()) {
                    return $linked;
                }
            }
        }

        return Bank::query()->where('active', true)->orderBy('id')->get();
    }

    /**
     * همان منطق «بانک» برای صندوق‌ها زیر ریشهٔ «صندوق/نقد» در چارت.
     *
     * @return EloquentCollection<int, CashBox>
     */
    private function preferredActiveCashBoxes(): EloquentCollection
    {
        $rootId = $this->resolveTreasuryRootAccountId('cash');
        if ($rootId === null || $rootId < 1) {
            return CashBox::query()->where('active', true)->orderBy('id')->get();
        }

        if ($this->hasActiveChildAccountsUnder($rootId)) {
            $descendantIds = $this->collectDescendantAccountIds($rootId);
            if ($descendantIds !== []) {
                $linked = CashBox::query()
                    ->where('active', true)
                    ->whereIn('account_id', $descendantIds)
                    ->orderBy('id')
                    ->get();
                if ($linked->isNotEmpty()) {
                    return $linked;
                }
            }
        }

        return CashBox::query()->where('active', true)->orderBy('id')->get();
    }

    private function resolveTreasuryRootAccountId(string $assetsKey): ?int
    {
        $settingKey = 'accounting.system_accounts.assets.'.$assetsKey;
        $code = trim((string) Setting::get($settingKey, (string) config('accounting.system_accounts.assets.'.$assetsKey, '')));

        return $this->resolveAccountByCode($code);
    }

    private function hasActiveChildAccountsUnder(int $rootAccountId): bool
    {
        return Account::query()
            ->where('active', true)
            ->where('parent_id', $rootAccountId)
            ->exists();
    }

    /**
     * همهٔ شناسه‌های حساب‌های فعال در زیردرخت زیر `$rootAccountId` (خود ریشه نیست).
     *
     * @return array<int,int>
     */
    private function collectDescendantAccountIds(int $rootAccountId): array
    {
        $all = [];
        $frontier = [$rootAccountId];

        while ($frontier !== []) {
            $children = Account::query()
                ->where('active', true)
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();
            if ($children === []) {
                break;
            }
            $all = array_merge($all, $children);
            $frontier = $children;
        }

        return array_values(array_unique($all));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function loadOldCrmCustomerSnapshot(): array
    {
        $path = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.self::OLD_CRM_CUSTOMER_SNAPSHOT_PATH;
        if (! is_file($path)) {
            return [];
        }

        $raw = @file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        $rows = [];
        foreach ($decoded as $item) {
            if (is_array($item)) {
                $rows[] = $item;
            }
            if (count($rows) >= self::MAX_CUSTOMERS) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @param  array<int,array<string,mixed>>  $snapshot
     */
    private function resolveCustomerDisplayName(int $sequence, array $snapshot): string
    {
        $snapshotIndex = $sequence - 1;
        if (isset($snapshot[$snapshotIndex]) && is_array($snapshot[$snapshotIndex])) {
            $row = $snapshot[$snapshotIndex];
            $fullName = trim((string) ($row['full_name'] ?? ''));
            if ($fullName !== '') {
                return $fullName;
            }
            $firstName = trim((string) ($row['first_name'] ?? ''));
            $lastName = trim((string) ($row['last_name'] ?? ''));
            $composed = trim($firstName.' '.$lastName);
            if ($composed !== '') {
                return $composed;
            }
        }

        return self::SAMPLE_CUSTOMER_NAMES[($sequence - 1) % count(self::SAMPLE_CUSTOMER_NAMES)]
            ?? ('Sample Customer '.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT));
    }

    /**
     * @return array<int,string>
     */
    private function generateShareholderNames(int $count): array
    {
        $count = max(1, $count);
        $base = ['علی', 'شریف'];
        $names = [];
        for ($i = 0; $i < $count; $i++) {
            $names[] = $base[$i] ?? ('سهامدار نمونه '.($i + 1));
        }

        return $names;
    }

    private function nextSequenceByPrefix(string $table, string $column, string $prefix): int
    {
        if (! DB::getSchemaBuilder()->hasTable($table)) {
            return 1;
        }

        $last = (string) DB::table($table)
            ->where($column, 'like', $prefix.'%')
            ->orderByDesc($column)
            ->value($column);

        if ($last === '' || ! str_starts_with($last, $prefix)) {
            return 1;
        }

        $tail = substr($last, strlen($prefix));
        if ($tail === false || $tail === '') {
            return 1;
        }

        $digits = preg_replace('/\D+/', '', $tail) ?? '';
        if ($digits === '') {
            return 1;
        }

        return ((int) $digits) + 1;
    }

    private function bankIdAtIndex(EloquentCollection $banks, int $index): int
    {
        if ($banks->isEmpty()) {
            return 0;
        }

        return (int) $banks->get($index % $banks->count())->id;
    }

    private function cashBoxIdAtIndex(EloquentCollection $cashBoxes, int $index): int
    {
        if ($cashBoxes->isEmpty()) {
            return 0;
        }

        return (int) $cashBoxes->get($index % $cashBoxes->count())->id;
    }

    private function resolveChequebookIdForBank(int $bankId, int $fallbackChequebookId): int
    {
        if ($bankId < 1) {
            return $fallbackChequebookId;
        }

        $id = (int) Chequebook::query()
            ->where('active', true)
            ->where('bank_id', $bankId)
            ->orderBy('id')
            ->value('id');

        return $id > 0 ? $id : $fallbackChequebookId;
    }

    private function roundedAmount(int $min, int $max): int
    {
        $range = max(0, $max - $min);
        $raw = $min + ($range > 0 ? mt_rand(0, $range) : 0);
        return (int) (round($raw / 100_000) * 100_000);
    }

    /** گرد به پله‌های ۱۰٬۰۰۰ تومن (ریال در دیتابیس بر حسب واحد پروژه). */
    private function roundedExpenseAmount(int $min, int $max): int
    {
        $range = max(0, $max - $min);
        $raw = $min + ($range > 0 ? mt_rand(0, $range) : 0);
        $step = 10_000;
        $rounded = (int) (round($raw / $step) * $step);

        return max($min, min($max, $rounded));
    }

    /**
     * @return array<string,mixed>
     */
    private function requiredCheck(
        string $key,
        bool $ok,
        string $messageKey,
        ?string $actionRoute = null,
        array $actionQuery = []
    ): array
    {
        return [
            'key' => $key,
            'ok' => $ok,
            'level' => 'error',
            'message' => (string) trans('accounting::accounting.'.$messageKey),
            'action_route' => $actionRoute,
            'action_query' => $actionQuery,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function requiredSystemAccountCheck(
        string $key,
        string $settingKey,
        string $configFallback,
        string $messageKey,
        array $actionQuery = []
    ): array {
        $configured = trim((string) Setting::get($settingKey, $configFallback));
        $accountId = $this->resolveAccountByCode($configured);

        return [
            'key' => $key,
            'ok' => $configured !== '' && $accountId !== null,
            'level' => 'error',
            'message' => (string) trans('accounting::accounting.'.$messageKey, ['code' => $configured ?: '—']),
            'action_route' => 'admin.accounting.settings.index',
            'action_query' => $actionQuery,
        ];
    }

    private function resolveAccountByCode(string $code): ?int
    {
        if ($code === '') {
            return null;
        }

        $exact = (int) Account::query()->where('code', $code)->value('id');
        if ($exact > 0) {
            return $exact;
        }

        $normalized = str_replace('-', '', $code);
        if ($normalized !== $code) {
            $fallback = (int) Account::query()->where('code', $normalized)->value('id');
            if ($fallback > 0) {
                return $fallback;
            }
        }

        return null;
    }

    /**
     * @return array{ok:bool,issues:array<int,string>,stats:array<string,int>}
     */
    public function sampleConsistencyReport(): array
    {
        $issues = [];
        $sampleInvoiceQuery = CustomerInvoice::query()
            ->where('invoice_number', 'like', self::SALES_INV_PREFIX.'%');
        $sampleChequeQuery = Cheque::query()
            ->where('cheque_number', 'like', self::CHEQUE_PREFIX.'%');
        $sampleCustomerPaymentQuery = CustomerPayment::query()
            ->where('payment_number', 'like', self::CUSTOMER_PAY_PREFIX.'%');
        $sampleSupplierPaymentQuery = SupplierPayment::query()
            ->where('payment_number', 'like', self::SUPPLIER_PAY_PREFIX.'%');

        $issuedWithoutDocument = (int) (clone $sampleInvoiceQuery)
            ->where('status', CustomerInvoice::STATUS_ISSUED)
            ->whereNull('document_id')
            ->count();
        if ($issuedWithoutDocument > 0) {
            $issues[] = 'Issued sample sales invoices without accounting document: '.$issuedWithoutDocument;
        }

        $issuedWithoutItems = (int) (clone $sampleInvoiceQuery)
            ->where('status', CustomerInvoice::STATUS_ISSUED)
            ->whereDoesntHave('items')
            ->count();
        if ($issuedWithoutItems > 0) {
            $issues[] = 'Issued sample sales invoices without items: '.$issuedWithoutItems;
        }

        $cashedWithoutDate = (int) (clone $sampleChequeQuery)
            ->where('status', Cheque::STATUS_CASHED)
            ->whereNull('cashed_at')
            ->count();
        if ($cashedWithoutDate > 0) {
            $issues[] = 'Cashed sample cheques without cashed_at timestamp: '.$cashedWithoutDate;
        }

        $chequeCustomerPaymentsWithoutCheque = (int) (clone $sampleCustomerPaymentQuery)
            ->whereNotNull('payment_method_id')
            ->whereIn('payment_method_id', PaymentMethod::query()
                ->where('type', PaymentMethod::TYPE_CHEQUE)
                ->pluck('id')
                ->all())
            ->whereNull('cheque_id')
            ->count();
        if ($chequeCustomerPaymentsWithoutCheque > 0) {
            $issues[] = 'Sample customer cheque payments without linked cheque: '.$chequeCustomerPaymentsWithoutCheque;
        }

        $chequeSupplierPaymentsWithoutCheque = (int) (clone $sampleSupplierPaymentQuery)
            ->whereNotNull('payment_method_id')
            ->whereIn('payment_method_id', PaymentMethod::query()
                ->where('type', PaymentMethod::TYPE_CHEQUE)
                ->pluck('id')
                ->all())
            ->whereNull('cheque_id')
            ->count();
        if ($chequeSupplierPaymentsWithoutCheque > 0) {
            $issues[] = 'Sample supplier cheque payments without linked cheque: '.$chequeSupplierPaymentsWithoutCheque;
        }

        return [
            'ok' => $issues === [],
            'issues' => $issues,
            'stats' => [
                'sample_customer_invoices' => (int) (clone $sampleInvoiceQuery)->count(),
                'sample_cheques' => (int) (clone $sampleChequeQuery)->count(),
                'sample_customer_payments' => (int) (clone $sampleCustomerPaymentQuery)->count(),
                'sample_supplier_payments' => (int) (clone $sampleSupplierPaymentQuery)->count(),
                'issued_without_document' => $issuedWithoutDocument,
                'issued_without_items' => $issuedWithoutItems,
                'cashed_without_date' => $cashedWithoutDate,
                'customer_cheque_payments_without_cheque' => $chequeCustomerPaymentsWithoutCheque,
                'supplier_cheque_payments_without_cheque' => $chequeSupplierPaymentsWithoutCheque,
            ],
        ];
    }

    /**
     * @param  callable(string):void|null  $logger
     */
    private function log(?callable $logger, string $message): void
    {
        if ($logger !== null) {
            $logger($message);
        }
    }
}

