<?php

namespace RMS\Accounting\Adapters;

use RMS\Accounting\Services\{
    CustomerInvoiceService,
    CustomerPaymentService,
    SupplierInvoiceService,
    SupplierPaymentService,
    COGSService,
    TaxService,
    CurrencyService,
    CreditNoteService,
    DebitNoteService,
    RefundService,
    AdvancePaymentService,
    FixedAssetService,
    BankTransferService,
    BankTransactionService,
    ManualJournalService,
    InventoryAdjustmentService,
    PartyService,
    PartyBalanceService
};
use RMS\Accounting\Models\{Customer, Supplier};
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Accounting Adapter
 * 
 * پل ارتباطی بین Shop/Inventory و هسته حسابداری
 * 
 * دو حالت کاری:
 * 1. Local Mode: استفاده مستقیم از Services (پکیج نصب شده)
 * 2. Remote Mode: استفاده از API (سرور جدا)
 * 
 * تنظیمات در config/accounting.php
 */
class AccountingAdapter
{
    protected ?CustomerInvoiceService $invoiceService = null;
    protected ?CustomerPaymentService $paymentService = null;
    protected ?SupplierInvoiceService $supplierInvoiceService = null;
    protected ?SupplierPaymentService $supplierPaymentService = null;
    protected ?COGSService $cogsService = null;
    protected ?TaxService $taxService = null;
    protected ?CurrencyService $currencyService = null;
    protected ?CreditNoteService $creditNoteService = null;
    protected ?DebitNoteService $debitNoteService = null;
    protected ?RefundService $refundService = null;
    protected ?AdvancePaymentService $advanceService = null;
    protected ?FixedAssetService $fixedAssetService = null;
    protected ?BankTransferService $bankTransferService = null;
    protected ?BankTransactionService $bankTransactionService = null;
    protected ?ManualJournalService $manualJournalService = null;
    protected ?InventoryAdjustmentService $inventoryAdjustmentService = null;
    protected ?PartyService $partyService = null;
    protected ?PartyBalanceService $partyBalanceService = null;
    
    protected bool $useRemoteApi;
    protected ?string $apiBaseUrl;
    protected ?string $apiKey;

    public function __construct()
    {
        $this->useRemoteApi = config('accounting.use_remote_api', false);
        
        if ($this->useRemoteApi) {
            // Remote Mode: API configuration
            $this->apiBaseUrl = config('accounting.api_base_url');
            $this->apiKey = config('accounting.api_key');
            
            if (!$this->apiBaseUrl || !$this->apiKey) {
                throw new \Exception('Accounting API configuration is missing');
            }
        } else {
            // Local Mode: Direct service injection
            $this->invoiceService = app(CustomerInvoiceService::class);
            $this->paymentService = app(CustomerPaymentService::class);
            $this->supplierInvoiceService = app(SupplierInvoiceService::class);
            $this->supplierPaymentService = app(SupplierPaymentService::class);
            $this->cogsService = app(COGSService::class);
            $this->taxService = app(TaxService::class);
            $this->currencyService = app(CurrencyService::class);
            $this->creditNoteService = app(CreditNoteService::class);
            $this->debitNoteService = app(DebitNoteService::class);
            $this->refundService = app(RefundService::class);
            $this->advanceService = app(AdvancePaymentService::class);
            $this->fixedAssetService = app(FixedAssetService::class);
            $this->bankTransferService = app(BankTransferService::class);
            $this->bankTransactionService = app(BankTransactionService::class);
            $this->manualJournalService = app(ManualJournalService::class);
            $this->inventoryAdjustmentService = app(InventoryAdjustmentService::class);
            $this->partyService = app(PartyService::class);
            $this->partyBalanceService = app(PartyBalanceService::class);
        }
    }

    // ========================================
    // Sales / Customer Invoices
    // ========================================

    /**
     * ثبت فاکتور فروش
     * 
     * @param array $data
     * @return array|object
     */
    public function recordSalesInvoice(array $data)
    {
        if ($this->useRemoteApi) {
            return $this->callRemoteApi('POST', '/sales/record-invoice', $data);
        }
        
        $invoice = $this->invoiceService->createInvoice($data);
        
        // اعمال مالیات
        if (isset($data['apply_tax']) && $data['apply_tax']) {
            $invoice = $this->taxService->applyVATToCustomerInvoice($invoice);
            $invoice->save();
        }
        
        return $invoice;
    }

    /**
     * ثبت دریافت از مشتری
     * 
     * @param array $data
     * @return array|object
     */
    public function recordCustomerPayment(array $data)
    {
        if ($this->useRemoteApi) {
            return $this->callRemoteApi('POST', '/sales/record-payment', $data);
        }
        
        return $this->paymentService->recordPayment($data);
    }

    // ========================================
    // Purchases / Supplier Invoices
    // ========================================

    /**
     * ثبت فاکتور خرید
     * 
     * @param array $data
     * @return array|object
     */
    public function recordPurchaseInvoice(array $data)
    {
        if ($this->useRemoteApi) {
            return $this->callRemoteApi('POST', '/purchases/record-invoice', $data);
        }
        
        $invoice = $this->supplierInvoiceService->createInvoice($data);
        
        // اعمال مالیات
        if (isset($data['apply_tax']) && $data['apply_tax']) {
            $invoice = $this->taxService->applyVATToSupplierInvoice($invoice);
            $invoice->save();
        }
        
        return $invoice;
    }

    /**
     * ثبت پرداخت به تامین‌کننده
     * 
     * @param array $data
     * @return array|object
     */
    public function recordSupplierPayment(array $data)
    {
        if ($this->useRemoteApi) {
            return $this->callRemoteApi('POST', '/purchases/record-payment', $data);
        }
        
        return $this->supplierPaymentService->recordPayment($data);
    }

    // ========================================
    // Inventory / COGS
    // ========================================

    /**
     * ثبت بهای تمام شده (COGS)
     * 
     * @param array $data
     * @return array|object
     */
    public function recordCOGS(array $data)
    {
        if ($this->useRemoteApi) {
            return $this->callRemoteApi('POST', '/inventory/record-cogs', $data);
        }
        
        return $this->cogsService->recordCOGS($data);
    }

    // ========================================
    // Party-Based Methods
    // ========================================

    /**
     * دریافت مانده کلی یک party
     * 
     * @param int $partyId
     * @return array
     */
    public function getPartyBalance(int $partyId): array
    {
        if ($this->useRemoteApi) {
            return $this->callRemoteApi('GET', "/parties/{$partyId}/balance");
        }
        
        if (!$this->partyBalanceService) {
            $this->partyBalanceService = app(PartyBalanceService::class);
        }
        
        $balance = $this->partyBalanceService->getPartyTotalBalance($partyId);
        $receivable = $this->partyBalanceService->getPartyReceivable($partyId);
        $payable = $this->partyBalanceService->getPartyPayable($partyId);
        
        return [
            'party_id' => $partyId,
            'receivable' => $receivable,
            'payable' => $payable,
            'net_balance' => $balance,
        ];
    }

    /**
     * دریافت گردش حساب یک party
     * 
     * @param int $partyId
     * @param array $filters
     * @return array
     */
    public function getPartyStatement(int $partyId, array $filters = []): array
    {
        if ($this->useRemoteApi) {
            return $this->callRemoteApi('GET', "/parties/{$partyId}/statement", $filters);
        }
        
        if (!$this->partyBalanceService) {
            $this->partyBalanceService = app(PartyBalanceService::class);
        }
        
        return $this->partyBalanceService->getPartyStatement($partyId, $filters);
    }

    /**
     * به‌روزرسانی getCustomerBalance برای استفاده از party
     */
    public function getCustomerBalance(int $customerId): array
    {
        if ($this->useRemoteApi) {
            return $this->callRemoteApi('GET', "/customers/{$customerId}/balance");
        }
        
        $customer = Customer::with(['invoices', 'party'])->find($customerId);
        
        if (!$customer) {
            return [
                'customer_id' => $customerId,
                'balance' => 0,
                'currency' => 'IRR',
                'exists' => false,
            ];
        }
        
        // اگر customer به party لینک شده، از party balance استفاده می‌کنیم
        if ($customer->party_id && $this->partyBalanceService) {
            $partyBalance = $this->partyBalanceService->getPartyReceivable($customer->party_id);
            return [
                'customer_id' => $customerId,
                'party_id' => $customer->party_id,
                'balance' => $partyBalance,
                'currency' => 'IRR',
                'exists' => true,
            ];
        }
        
        // Fallback به روش قدیمی
        $balance = $customer->invoices->sum('balance_due');
        
        return [
            'customer_id' => $customerId,
            'balance' => $balance,
            'currency' => 'IRR',
            'invoices_count' => $customer->invoices->count(),
            'unpaid_invoices' => $customer->invoices->where('payment_status', '!=', 'paid')->count(),
            'exists' => true,
        ];
    }

    /**
     * به‌روزرسانی getSupplierBalance برای استفاده از party
     */
    public function getSupplierBalance(int $supplierId): array
    {
        if ($this->useRemoteApi) {
            return $this->callRemoteApi('GET', "/suppliers/{$supplierId}/balance");
        }
        
        $supplier = Supplier::with(['invoices', 'party'])->find($supplierId);
        
        if (!$supplier) {
            return [
                'supplier_id' => $supplierId,
                'balance' => 0,
                'currency' => 'IRR',
                'exists' => false,
            ];
        }
        
        // اگر supplier به party لینک شده، از party balance استفاده می‌کنیم
        if ($supplier->party_id && $this->partyBalanceService) {
            $partyBalance = $this->partyBalanceService->getPartyPayable($supplier->party_id);
            return [
                'supplier_id' => $supplierId,
                'party_id' => $supplier->party_id,
                'balance' => $partyBalance,
                'currency' => 'IRR',
                'exists' => true,
            ];
        }
        
        // Fallback به روش قدیمی
        $balance = $supplier->invoices->sum('balance_due');
        
        return [
            'supplier_id' => $supplierId,
            'balance' => $balance,
            'currency' => 'IRR',
            'invoices_count' => $supplier->invoices->count(),
            'unpaid_invoices' => $supplier->invoices->where('payment_status', '!=', 'paid')->count(),
            'exists' => true,
        ];
    }

    // ========================================
    // Currency
    // ========================================

    /**
     * دریافت نرخ ارز فعلی
     * 
     * @param string $currencyCode
     * @return array
     */
    public function getCurrencyRate(string $currencyCode): array
    {
        if ($this->useRemoteApi) {
            return $this->callRemoteApi('GET', "/currencies/{$currencyCode}/rate");
        }
        
        $rate = $this->currencyService->getCurrentRate($currencyCode);
        
        return [
            'currency' => $currencyCode,
            'rate_to_irr' => $rate,
            'updated_at' => now()->toDateTimeString(),
        ];
    }

    // ========================================
    // Tax Calculations
    // ========================================

    /**
     * محاسبه مالیات (VAT)
     * 
     * @param float $amount
     * @param float|null $taxRate
     * @param string $method
     * @return array
     */
    public function calculateVAT(float $amount, ?float $taxRate = null, string $method = 'exclusive'): array
    {
        if ($this->useRemoteApi) {
            return $this->callRemoteApi('POST', '/tax/calculate-vat', [
                'amount' => $amount,
                'tax_rate' => $taxRate,
                'method' => $method,
            ]);
        }
        
        return \RMS\Accounting\Services\Tax\TaxCalculator::calculateVAT($amount, $taxRate, $method);
    }

    // ========================================
    // Remote API Helper
    // ========================================

    /**
     * فراخوانی Remote API
     * 
     * @param string $method
     * @param string $endpoint
     * @param array $data
     * @return array
     */
    protected function callRemoteApi(string $method, string $endpoint, array $data = []): array
    {
        try {
            $url = rtrim($this->apiBaseUrl, '/') . '/api/service/accounting' . $endpoint;
            
            $response = Http::withToken($this->apiKey)
                ->timeout(30)
                ->{strtolower($method)}($url, $data);
            
            if ($response->failed()) {
                Log::error('Accounting API Error', [
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                
                throw new \Exception('Accounting API Error: ' . $response->body());
            }
            
            return $response->json();
            
        } catch (\Exception $e) {
            Log::error('Accounting API Exception', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    // ========================================
    // Health Check
    // ========================================

    /**
     * بررسی وضعیت اتصال
     * 
     * @return array
     */
    public function healthCheck(): array
    {
        if ($this->useRemoteApi) {
            try {
                $response = $this->callRemoteApi('GET', '/health');
                return [
                    'status' => 'ok',
                    'mode' => 'remote',
                    'api_url' => $this->apiBaseUrl,
                    'response' => $response,
                ];
            } catch (\Exception $e) {
                return [
                    'status' => 'error',
                    'mode' => 'remote',
                    'api_url' => $this->apiBaseUrl,
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return [
            'status' => 'ok',
            'mode' => 'local',
            'services_loaded' => [
                'invoice' => $this->invoiceService !== null,
                'payment' => $this->paymentService !== null,
                'cogs' => $this->cogsService !== null,
                'tax' => $this->taxService !== null,
                'currency' => $this->currencyService !== null,
            ],
        ];
    }

    // ========================================
    // Credit Notes & Returns
    // ========================================

    public function createCreditNote(array $data)
    {
        if ($this->useRemoteApi) {
            return $this->callRemoteApi('POST', '/sales/credit-note', $data);
        }
        return $this->creditNoteService->createCreditNote($data);
    }

    public function issueCreditNote($creditNote)
    {
        if ($this->useRemoteApi) {
            return $this->callRemoteApi('POST', "/sales/credit-note/{$creditNote}/issue");
        }
        return $this->creditNoteService->issueCreditNote(is_object($creditNote) ? $creditNote : \RMS\Accounting\Models\CreditNote::findOrFail($creditNote));
    }

    public function applyCreditNoteToInvoice($creditNoteId, $invoiceId)
    {
        if ($this->useRemoteApi) {
            return $this->callRemoteApi('POST', "/sales/credit-note/{$creditNoteId}/apply", ['invoice_id' => $invoiceId]);
        }
        $creditNote = \RMS\Accounting\Models\CreditNote::findOrFail($creditNoteId);
        return $this->creditNoteService->applyToInvoice($creditNote, $invoiceId);
    }

    public function createDebitNote(array $data)
    {
        if ($this->useRemoteApi) {
            return $this->callRemoteApi('POST', '/purchases/debit-note', $data);
        }
        return $this->debitNoteService->createDebitNote($data);
    }

    // ========================================
    // Refunds
    // ========================================

    public function processCustomerRefund(array $data)
    {
        if ($this->useRemoteApi) {
            return $this->callRemoteApi('POST', '/sales/refund', $data);
        }
        return $this->refundService->processCustomerRefund($data);
    }

    public function receiveSupplierRefund(array $data)
    {
        if ($this->useRemoteApi) {
            return $this->callRemoteApi('POST', '/purchases/refund', $data);
        }
        return $this->refundService->receiveSupplierRefund($data);
    }

    // ========================================
    // Advance Payments
    // ========================================

    public function receiveCustomerAdvance(array $data)
    {
        if ($this->useRemoteApi) {
            return $this->callRemoteApi('POST', '/sales/advance', $data);
        }
        return $this->advanceService->receiveCustomerAdvance($data);
    }

    public function paySupplierAdvance(array $data)
    {
        if ($this->useRemoteApi) {
            return $this->callRemoteApi('POST', '/purchases/advance', $data);
        }
        return $this->advanceService->paySupplierAdvance($data);
    }

    public function applyCustomerAdvanceToInvoice(int $advanceId, int $invoiceId, float $amount)
    {
        if ($this->useRemoteApi) {
            return $this->callRemoteApi('POST', "/sales/advance/{$advanceId}/apply", ['invoice_id' => $invoiceId, 'amount' => $amount]);
        }
        return $this->advanceService->applyCustomerAdvanceToInvoice($advanceId, $invoiceId, $amount);
    }

    // ========================================
    // Fixed Assets
    // ========================================

    public function createFixedAsset(array $data)
    {
        if ($this->useRemoteApi) {
            return $this->callRemoteApi('POST', '/fixed-assets', $data);
        }
        return $this->fixedAssetService->createAsset($data);
    }

    public function recordDepreciation(int $assetId, string $periodDate)
    {
        if ($this->useRemoteApi) {
            return $this->callRemoteApi('POST', "/fixed-assets/{$assetId}/depreciation", ['period_date' => $periodDate]);
        }
        return $this->fixedAssetService->recordDepreciation($assetId, $periodDate);
    }

    // ========================================
    // Bank Transfers
    // ========================================

    public function createBankTransfer(array $data)
    {
        if ($this->useRemoteApi) {
            return $this->callRemoteApi('POST', '/bank-transfers', $data);
        }
        return $this->bankTransferService->createTransfer($data);
    }

    // ========================================
    // Bank Transactions (Charges/Interest)
    // ========================================

    public function recordBankCharge(array $data)
    {
        if ($this->useRemoteApi) {
            return $this->callRemoteApi('POST', '/bank-transactions/charge', $data);
        }
        return $this->bankTransactionService->recordBankCharge($data);
    }

    public function recordBankInterest(array $data)
    {
        if ($this->useRemoteApi) {
            return $this->callRemoteApi('POST', '/bank-transactions/interest', $data);
        }
        return $this->bankTransactionService->recordInterestIncome($data);
    }

    // ========================================
    // Manual Journals
    // ========================================

    public function createManualJournal(array $data)
    {
        if ($this->useRemoteApi) {
            return $this->callRemoteApi('POST', '/manual-journals', $data);
        }
        return $this->manualJournalService->createJournal($data);
    }

    // ========================================
    // Inventory Adjustments
    // ========================================

    public function createInventoryAdjustment(array $data)
    {
        if ($this->useRemoteApi) {
            return $this->callRemoteApi('POST', '/inventory-adjustments', $data);
        }
        return $this->inventoryAdjustmentService->createAdjustment($data);
    }

    public function recordInventoryWritedown(array $data)
    {
        if ($this->useRemoteApi) {
            return $this->callRemoteApi('POST', '/inventory-adjustments/writedown', $data);
        }
        $data['adjustment_type'] = 'writedown';
        return $this->inventoryAdjustmentService->createAdjustment($data);
    }
}
