<?php

declare(strict_types=1);

namespace RMS\Accounting\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use RMS\Accounting\Models\Account;
use RMS\Accounting\Models\Accrual;
use RMS\Accounting\Models\BadDebtWriteoff;
use RMS\Accounting\Models\Bank;
use RMS\Accounting\Models\CashBox;
use RMS\Accounting\Models\Cheque;
use RMS\Accounting\Models\Chequebook;
use RMS\Accounting\Models\CreditNote;
use RMS\Accounting\Models\Currency;
use RMS\Accounting\Models\Customer;
use RMS\Accounting\Models\CustomerAdvance;
use RMS\Accounting\Models\CustomerInvoice;
use RMS\Accounting\Models\CustomerPayment;
use RMS\Accounting\Models\CustomerRefund;
use RMS\Accounting\Models\DebitNote;
use RMS\Accounting\Models\DepreciationEntry;
use RMS\Accounting\Models\Employee;
use RMS\Accounting\Models\FixedAsset;
use RMS\Accounting\Models\Expense;
use RMS\Accounting\Models\ExpenseCategory;
use RMS\Accounting\Models\FixedAssetCategory;
use RMS\Accounting\Models\InventoryAdjustment;
use RMS\Accounting\Models\PaymentMethod;
use RMS\Accounting\Models\PayrollRun;
use RMS\Accounting\Models\Shareholder;
use RMS\Accounting\Models\Supplier;
use RMS\Accounting\Models\SupplierAdvance;
use RMS\Accounting\Models\SupplierInvoice;
use RMS\Accounting\Models\SupplierPayment;
use RMS\Accounting\Models\SupplierRefund;
use RMS\Accounting\Models\TaxRate;
use RMS\Accounting\Models\VatDeclaration;
use RMS\Accounting\Models\Wallet;
use RMS\Accounting\Models\VatRemittance;
use RMS\Accounting\Support\AccountingVatAccounts;
use RMS\Accounting\Services\Tax\TaxCalculator;
use RMS\Core\Models\Setting;

class ScenarioRunnerService
{
    public const SCENARIO_SALES_INVOICE = 'sales_invoice_credit';
    public const SCENARIO_PURCHASE_INVOICE = 'purchase_invoice_on_account';
    public const SCENARIO_CUSTOMER_RECEIPT_CASH = 'customer_receipt_cash';
    public const SCENARIO_CUSTOMER_RECEIPT_WALLET = 'customer_receipt_wallet';
    public const SCENARIO_SUPPLIER_PAYMENT_CASH = 'supplier_payment_cash';
    public const SCENARIO_SUPPLIER_PAYMENT_WALLET = 'supplier_payment_wallet';
    public const SCENARIO_RECEIVED_CHEQUE_CASH = 'received_cheque_cash';
    public const SCENARIO_ISSUED_CHEQUE_CASH = 'issued_cheque_cash';
    public const SCENARIO_EXPENSE_CASH = 'expense_paid_cash';
    public const SCENARIO_BANK_TRANSFER = 'bank_transfer_treasury';
    public const SCENARIO_BANK_TRANSFER_CASHBOX = 'bank_transfer_cashbox_to_bank';
    public const SCENARIO_CUSTOMER_ADVANCE_CASH = 'customer_advance_cash';
    public const SCENARIO_SUPPLIER_ADVANCE_CASH = 'supplier_advance_cash';
    public const SCENARIO_CREDIT_NOTE_ISSUE = 'credit_note_issue';
    public const SCENARIO_CREDIT_NOTE_APPLY = 'credit_note_apply';
    public const SCENARIO_DEBIT_NOTE_ISSUE = 'debit_note_issue';
    public const SCENARIO_DEBIT_NOTE_APPLY = 'debit_note_apply';
    public const SCENARIO_CUSTOMER_REFUND_CASH = 'customer_refund_cash';
    public const SCENARIO_SUPPLIER_REFUND_CASH = 'supplier_refund_cash';
    public const SCENARIO_CUSTOMER_ADVANCE_APPLY = 'customer_advance_apply';
    public const SCENARIO_SUPPLIER_ADVANCE_APPLY = 'supplier_advance_apply';
    public const SCENARIO_INVENTORY_ADJUSTMENT_POST = 'inventory_adjustment_post';
    public const SCENARIO_ACCRUAL_POST_REVERSE = 'accrual_post_reverse';
    public const SCENARIO_BAD_DEBT_WRITEOFF = 'bad_debt_writeoff';
    public const SCENARIO_FIXED_ASSET_PURCHASE = 'fixed_asset_purchase_cash';
    public const SCENARIO_FIXED_ASSET_DEPRECIATION = 'fixed_asset_depreciation';
    public const SCENARIO_FIXED_ASSET_DISPOSAL = 'fixed_asset_disposal';
    public const SCENARIO_SHAREHOLDER_CAPITAL = 'shareholder_capital_bank';
    public const SCENARIO_SHAREHOLDER_WITHDRAWAL = 'shareholder_withdrawal_cash';
    public const SCENARIO_PAYROLL_ACCRUAL_BASIC = 'payroll_accrual_basic';
    public const SCENARIO_PAYROLL_INSURANCE_REMITTANCE = 'payroll_insurance_remittance';
    public const SCENARIO_PAYROLL_LOAN_SETTLEMENT = 'payroll_loan_settlement';
    public const SCENARIO_VAT_DECLARATION_SUBMIT = 'vat_declaration_submit';
    public const SCENARIO_ISSUED_CHEQUE_BOUNCE = 'issued_cheque_bounce';
    public const SCENARIO_VAT_REMITTANCE = 'vat_remittance_bank';
    public const SCENARIO_MANUAL_JOURNAL = 'manual_journal_basic';

    public function __construct(
        private readonly LedgerSnapshotService $ledgerSnapshotService,
        private readonly ExpectedActualDiffService $expectedActualDiffService,
        private readonly CustomerInvoiceService $customerInvoiceService,
        private readonly CustomerInvoiceItemAdminService $customerInvoiceItemAdminService,
        private readonly SupplierInvoiceService $supplierInvoiceService,
        private readonly CustomerPaymentService $customerPaymentService,
        private readonly SupplierPaymentService $supplierPaymentService,
        private readonly BankTransferService $bankTransferService,
        private readonly AdvancePaymentService $advancePaymentService,
        private readonly CreditNoteService $creditNoteService,
        private readonly DebitNoteService $debitNoteService,
        private readonly RefundService $refundService,
        private readonly PartyService $partyService,
        private readonly ChequeLedgerService $chequeLedgerService,
        private readonly ChequeAutoCreationService $chequeAutoCreationService,
        private readonly ExpenseService $expenseService,
        private readonly FixedAssetService $fixedAssetService,
        private readonly InventoryAdjustmentService $inventoryAdjustmentService,
        private readonly AccrualService $accrualService,
        private readonly BadDebtService $badDebtService,
        private readonly ShareholderCapitalContributionService $shareholderCapitalContributionService,
        private readonly ShareholderWithdrawalService $shareholderWithdrawalService,
        private readonly PayrollJournalService $payrollJournalService,
        private readonly EmployeeLoanService $employeeLoanService,
        private readonly VatDeclarationService $vatDeclarationService,
        private readonly VatRemittanceService $vatRemittanceService,
        private readonly ManualJournalService $manualJournalService
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function defaultFormValues(): array
    {
        return [
            'scenario_key' => self::SCENARIO_SALES_INVOICE,
            'amount' => 1000000,
            'scenario_date' => now()->toDateString(),
            'notes' => '',
        ];
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function scenarioDefinitions(): array
    {
        $definitions = [
            self::SCENARIO_SALES_INVOICE => [
                'title' => (string) trans('accounting::accounting.scenario_runner.scenarios.sales_invoice_credit.title'),
                'module' => 'AR',
                'description' => (string) trans('accounting::accounting.scenario_runner.scenarios.sales_invoice_credit.description'),
            ],
            self::SCENARIO_PURCHASE_INVOICE => [
                'title' => (string) trans('accounting::accounting.scenario_runner.scenarios.purchase_invoice_on_account.title'),
                'module' => 'AP',
                'description' => (string) trans('accounting::accounting.scenario_runner.scenarios.purchase_invoice_on_account.description'),
            ],
            self::SCENARIO_CUSTOMER_RECEIPT_CASH => [
                'title' => (string) trans('accounting::accounting.scenario_runner.scenarios.customer_receipt_cash.title'),
                'module' => 'Treasury',
                'description' => (string) trans('accounting::accounting.scenario_runner.scenarios.customer_receipt_cash.description'),
            ],
            self::SCENARIO_CUSTOMER_RECEIPT_WALLET => [
                'title' => (string) trans('accounting::accounting.scenario_runner.scenarios.customer_receipt_wallet.title'),
                'module' => 'Treasury',
                'description' => (string) trans('accounting::accounting.scenario_runner.scenarios.customer_receipt_wallet.description'),
            ],
            self::SCENARIO_SUPPLIER_PAYMENT_CASH => [
                'title' => (string) trans('accounting::accounting.scenario_runner.scenarios.supplier_payment_cash.title'),
                'module' => 'Treasury',
                'description' => (string) trans('accounting::accounting.scenario_runner.scenarios.supplier_payment_cash.description'),
            ],
            self::SCENARIO_SUPPLIER_PAYMENT_WALLET => [
                'title' => (string) trans('accounting::accounting.scenario_runner.scenarios.supplier_payment_wallet.title'),
                'module' => 'Treasury',
                'description' => (string) trans('accounting::accounting.scenario_runner.scenarios.supplier_payment_wallet.description'),
            ],
            self::SCENARIO_CUSTOMER_ADVANCE_CASH => [
                'title' => (string) trans('accounting::accounting.scenario_runner.scenarios.customer_advance_cash.title'),
                'module' => 'Advance',
                'description' => (string) trans('accounting::accounting.scenario_runner.scenarios.customer_advance_cash.description'),
            ],
            self::SCENARIO_SUPPLIER_ADVANCE_CASH => [
                'title' => (string) trans('accounting::accounting.scenario_runner.scenarios.supplier_advance_cash.title'),
                'module' => 'Advance',
                'description' => (string) trans('accounting::accounting.scenario_runner.scenarios.supplier_advance_cash.description'),
            ],
            self::SCENARIO_RECEIVED_CHEQUE_CASH => [
                'title' => (string) trans('accounting::accounting.scenario_runner.scenarios.received_cheque_cash.title'),
                'module' => 'Cheques',
                'description' => (string) trans('accounting::accounting.scenario_runner.scenarios.received_cheque_cash.description'),
            ],
            self::SCENARIO_ISSUED_CHEQUE_CASH => [
                'title' => (string) trans('accounting::accounting.scenario_runner.scenarios.issued_cheque_cash.title'),
                'module' => 'Cheques',
                'description' => (string) trans('accounting::accounting.scenario_runner.scenarios.issued_cheque_cash.description'),
            ],
            self::SCENARIO_EXPENSE_CASH => [
                'title' => (string) trans('accounting::accounting.scenario_runner.scenarios.expense_paid_cash.title'),
                'module' => 'Expense',
                'description' => (string) trans('accounting::accounting.scenario_runner.scenarios.expense_paid_cash.description'),
            ],
            self::SCENARIO_BANK_TRANSFER => [
                'title' => (string) trans('accounting::accounting.scenario_runner.scenarios.bank_transfer_treasury.title'),
                'module' => 'Treasury',
                'description' => (string) trans('accounting::accounting.scenario_runner.scenarios.bank_transfer_treasury.description'),
            ],
            self::SCENARIO_BANK_TRANSFER_CASHBOX => [
                'title' => (string) trans('accounting::accounting.scenario_runner.scenarios.bank_transfer_cashbox_to_bank.title'),
                'module' => 'Treasury',
                'description' => (string) trans('accounting::accounting.scenario_runner.scenarios.bank_transfer_cashbox_to_bank.description'),
            ],
            self::SCENARIO_CREDIT_NOTE_ISSUE => [
                'title' => (string) trans('accounting::accounting.scenario_runner.scenarios.credit_note_issue.title'),
                'module' => 'AR',
                'description' => (string) trans('accounting::accounting.scenario_runner.scenarios.credit_note_issue.description'),
            ],
            self::SCENARIO_CREDIT_NOTE_APPLY => [
                'title' => (string) trans('accounting::accounting.scenario_runner.scenarios.credit_note_apply.title'),
                'module' => 'AR',
                'description' => (string) trans('accounting::accounting.scenario_runner.scenarios.credit_note_apply.description'),
            ],
            self::SCENARIO_DEBIT_NOTE_ISSUE => [
                'title' => (string) trans('accounting::accounting.scenario_runner.scenarios.debit_note_issue.title'),
                'module' => 'AP',
                'description' => (string) trans('accounting::accounting.scenario_runner.scenarios.debit_note_issue.description'),
            ],
            self::SCENARIO_DEBIT_NOTE_APPLY => [
                'title' => (string) trans('accounting::accounting.scenario_runner.scenarios.debit_note_apply.title'),
                'module' => 'AP',
                'description' => (string) trans('accounting::accounting.scenario_runner.scenarios.debit_note_apply.description'),
            ],
            self::SCENARIO_CUSTOMER_REFUND_CASH => [
                'title' => (string) trans('accounting::accounting.scenario_runner.scenarios.customer_refund_cash.title'),
                'module' => 'AR',
                'description' => (string) trans('accounting::accounting.scenario_runner.scenarios.customer_refund_cash.description'),
            ],
            self::SCENARIO_SUPPLIER_REFUND_CASH => [
                'title' => (string) trans('accounting::accounting.scenario_runner.scenarios.supplier_refund_cash.title'),
                'module' => 'AP',
                'description' => (string) trans('accounting::accounting.scenario_runner.scenarios.supplier_refund_cash.description'),
            ],
            self::SCENARIO_CUSTOMER_ADVANCE_APPLY => [
                'title' => (string) trans('accounting::accounting.scenario_runner.scenarios.customer_advance_apply.title'),
                'module' => 'AR',
                'description' => (string) trans('accounting::accounting.scenario_runner.scenarios.customer_advance_apply.description'),
            ],
            self::SCENARIO_SUPPLIER_ADVANCE_APPLY => [
                'title' => (string) trans('accounting::accounting.scenario_runner.scenarios.supplier_advance_apply.title'),
                'module' => 'AP',
                'description' => (string) trans('accounting::accounting.scenario_runner.scenarios.supplier_advance_apply.description'),
            ],
            self::SCENARIO_INVENTORY_ADJUSTMENT_POST => [
                'title' => (string) trans('accounting::accounting.scenario_runner.scenarios.inventory_adjustment_post.title'),
                'module' => 'Inventory',
                'description' => (string) trans('accounting::accounting.scenario_runner.scenarios.inventory_adjustment_post.description'),
            ],
            self::SCENARIO_ACCRUAL_POST_REVERSE => [
                'title' => (string) trans('accounting::accounting.scenario_runner.scenarios.accrual_post_reverse.title'),
                'module' => 'Accrual',
                'description' => (string) trans('accounting::accounting.scenario_runner.scenarios.accrual_post_reverse.description'),
            ],
            self::SCENARIO_BAD_DEBT_WRITEOFF => [
                'title' => (string) trans('accounting::accounting.scenario_runner.scenarios.bad_debt_writeoff.title'),
                'module' => 'AR',
                'description' => (string) trans('accounting::accounting.scenario_runner.scenarios.bad_debt_writeoff.description'),
            ],
            self::SCENARIO_FIXED_ASSET_PURCHASE => [
                'title' => (string) trans('accounting::accounting.scenario_runner.scenarios.fixed_asset_purchase_cash.title'),
                'module' => 'FixedAsset',
                'description' => (string) trans('accounting::accounting.scenario_runner.scenarios.fixed_asset_purchase_cash.description'),
            ],
            self::SCENARIO_FIXED_ASSET_DEPRECIATION => [
                'title' => (string) trans('accounting::accounting.scenario_runner.scenarios.fixed_asset_depreciation.title'),
                'module' => 'FixedAsset',
                'description' => (string) trans('accounting::accounting.scenario_runner.scenarios.fixed_asset_depreciation.description'),
            ],
            self::SCENARIO_FIXED_ASSET_DISPOSAL => [
                'title' => (string) trans('accounting::accounting.scenario_runner.scenarios.fixed_asset_disposal.title'),
                'module' => 'FixedAsset',
                'description' => (string) trans('accounting::accounting.scenario_runner.scenarios.fixed_asset_disposal.description'),
            ],
            self::SCENARIO_SHAREHOLDER_CAPITAL => [
                'title' => (string) trans('accounting::accounting.scenario_runner.scenarios.shareholder_capital_bank.title'),
                'module' => 'Equity',
                'description' => (string) trans('accounting::accounting.scenario_runner.scenarios.shareholder_capital_bank.description'),
            ],
            self::SCENARIO_SHAREHOLDER_WITHDRAWAL => [
                'title' => (string) trans('accounting::accounting.scenario_runner.scenarios.shareholder_withdrawal_cash.title'),
                'module' => 'Equity',
                'description' => (string) trans('accounting::accounting.scenario_runner.scenarios.shareholder_withdrawal_cash.description'),
            ],
            self::SCENARIO_PAYROLL_ACCRUAL_BASIC => [
                'title' => (string) trans('accounting::accounting.scenario_runner.scenarios.payroll_accrual_basic.title'),
                'module' => 'Payroll',
                'description' => (string) trans('accounting::accounting.scenario_runner.scenarios.payroll_accrual_basic.description'),
            ],
            self::SCENARIO_PAYROLL_INSURANCE_REMITTANCE => [
                'title' => (string) trans('accounting::accounting.scenario_runner.scenarios.payroll_insurance_remittance.title'),
                'module' => 'Payroll',
                'description' => (string) trans('accounting::accounting.scenario_runner.scenarios.payroll_insurance_remittance.description'),
            ],
            self::SCENARIO_PAYROLL_LOAN_SETTLEMENT => [
                'title' => (string) trans('accounting::accounting.scenario_runner.scenarios.payroll_loan_settlement.title'),
                'module' => 'Payroll',
                'description' => (string) trans('accounting::accounting.scenario_runner.scenarios.payroll_loan_settlement.description'),
            ],
            self::SCENARIO_VAT_DECLARATION_SUBMIT => [
                'title' => (string) trans('accounting::accounting.scenario_runner.scenarios.vat_declaration_submit.title'),
                'module' => 'Tax',
                'description' => (string) trans('accounting::accounting.scenario_runner.scenarios.vat_declaration_submit.description'),
            ],
            self::SCENARIO_ISSUED_CHEQUE_BOUNCE => [
                'title' => (string) trans('accounting::accounting.scenario_runner.scenarios.issued_cheque_bounce.title'),
                'module' => 'Cheques',
                'description' => (string) trans('accounting::accounting.scenario_runner.scenarios.issued_cheque_bounce.description'),
            ],
            self::SCENARIO_VAT_REMITTANCE => [
                'title' => (string) trans('accounting::accounting.scenario_runner.scenarios.vat_remittance_bank.title'),
                'module' => 'Tax',
                'description' => (string) trans('accounting::accounting.scenario_runner.scenarios.vat_remittance_bank.description'),
            ],
            self::SCENARIO_MANUAL_JOURNAL => [
                'title' => (string) trans('accounting::accounting.scenario_runner.scenarios.manual_journal_basic.title'),
                'module' => 'Journal',
                'description' => (string) trans('accounting::accounting.scenario_runner.scenarios.manual_journal_basic.description'),
            ],
        ];

        $profiles = $this->scenarioProfiles();
        foreach ($definitions as $scenarioKey => &$definition) {
            $profile = $profiles[$scenarioKey] ?? null;
            $definition['category_key'] = (string) ($profile['category_key'] ?? 'other');
            $definition['input_profile'] = (string) ($profile['input_profile'] ?? 'basic');
            $definition['required_fields'] = (array) ($profile['required_fields'] ?? []);
        }
        unset($definition);

        return $definitions;
    }

    /**
     * @return array<string,array{category_key:string,input_profile:string,required_fields:list<string>,required_requirements:list<string>}>
     */
    private function scenarioProfiles(): array
    {
        return [
            self::SCENARIO_SALES_INVOICE => [
                'category_key' => 'sales',
                'input_profile' => 'sales_invoice',
                'required_fields' => ['amount', 'scenario_date', 'customer_id'],
                'required_requirements' => ['customer'],
            ],
            self::SCENARIO_PURCHASE_INVOICE => [
                'category_key' => 'purchase',
                'input_profile' => 'purchase_invoice',
                'required_fields' => ['amount', 'scenario_date', 'supplier_id'],
                'required_requirements' => ['supplier'],
            ],
            self::SCENARIO_CUSTOMER_RECEIPT_CASH => [
                'category_key' => 'receivable',
                'input_profile' => 'customer_receipt_cash',
                'required_fields' => ['amount', 'scenario_date', 'customer_id', 'cash_box_id', 'payment_method_id'],
                'required_requirements' => ['customer', 'cash_box', 'cash_method'],
            ],
            self::SCENARIO_CUSTOMER_RECEIPT_WALLET => [
                'category_key' => 'receivable',
                'input_profile' => 'customer_receipt_wallet',
                'required_fields' => ['amount', 'scenario_date', 'customer_id', 'wallet_id', 'payment_method_id'],
                'required_requirements' => ['customer', 'wallet', 'cash_method'],
            ],
            self::SCENARIO_SUPPLIER_PAYMENT_CASH => [
                'category_key' => 'payable',
                'input_profile' => 'supplier_payment_cash',
                'required_fields' => ['amount', 'scenario_date', 'supplier_id', 'cash_box_id', 'payment_method_id'],
                'required_requirements' => ['supplier', 'cash_box', 'cash_method'],
            ],
            self::SCENARIO_SUPPLIER_PAYMENT_WALLET => [
                'category_key' => 'payable',
                'input_profile' => 'supplier_payment_wallet',
                'required_fields' => ['amount', 'scenario_date', 'supplier_id', 'wallet_id', 'payment_method_id'],
                'required_requirements' => ['supplier', 'wallet', 'cash_method'],
            ],
            self::SCENARIO_CUSTOMER_ADVANCE_CASH => [
                'category_key' => 'receivable',
                'input_profile' => 'customer_advance_cash',
                'required_fields' => ['amount', 'scenario_date', 'customer_id', 'cash_box_id'],
                'required_requirements' => ['customer', 'cash_box'],
            ],
            self::SCENARIO_SUPPLIER_ADVANCE_CASH => [
                'category_key' => 'payable',
                'input_profile' => 'supplier_advance_cash',
                'required_fields' => ['amount', 'scenario_date', 'supplier_id', 'cash_box_id'],
                'required_requirements' => ['supplier', 'cash_box'],
            ],
            self::SCENARIO_RECEIVED_CHEQUE_CASH => [
                'category_key' => 'treasury_cheque',
                'input_profile' => 'received_cheque',
                'required_fields' => ['amount', 'scenario_date', 'customer_id', 'bank_id', 'payment_method_id'],
                'required_requirements' => ['customer', 'bank', 'cheque_method'],
            ],
            self::SCENARIO_ISSUED_CHEQUE_CASH => [
                'category_key' => 'treasury_cheque',
                'input_profile' => 'issued_cheque',
                'required_fields' => ['amount', 'scenario_date', 'supplier_id', 'bank_id', 'payment_method_id', 'chequebook_id'],
                'required_requirements' => ['supplier', 'bank', 'cheque_method', 'chequebook'],
            ],
            self::SCENARIO_EXPENSE_CASH => [
                'category_key' => 'expense',
                'input_profile' => 'expense_cash',
                'required_fields' => ['amount', 'scenario_date', 'expense_category_id', 'cash_box_id'],
                'required_requirements' => ['expense_category', 'cash_box'],
            ],
            self::SCENARIO_BANK_TRANSFER => [
                'category_key' => 'treasury_transfer',
                'input_profile' => 'treasury_transfer_wallet_to_bank',
                'required_fields' => [
                    'amount',
                    'scenario_date',
                    'from_treasury_type',
                    'from_treasury_id',
                    'to_treasury_type',
                    'to_treasury_id',
                    'value_date',
                    'transfer_fee',
                ],
                'required_requirements' => [],
            ],
            self::SCENARIO_BANK_TRANSFER_CASHBOX => [
                'category_key' => 'treasury_transfer',
                'input_profile' => 'treasury_transfer_cashbox_to_bank',
                'required_fields' => [
                    'amount',
                    'scenario_date',
                    'from_treasury_type',
                    'from_treasury_id',
                    'to_treasury_type',
                    'to_treasury_id',
                    'value_date',
                    'transfer_fee',
                ],
                'required_requirements' => [],
            ],
            self::SCENARIO_CREDIT_NOTE_ISSUE => [
                'category_key' => 'sales',
                'input_profile' => 'credit_note_issue',
                'required_fields' => ['amount', 'scenario_date', 'customer_id'],
                'required_requirements' => ['customer'],
            ],
            self::SCENARIO_CREDIT_NOTE_APPLY => [
                'category_key' => 'sales',
                'input_profile' => 'credit_note_apply',
                'required_fields' => ['amount', 'scenario_date', 'customer_id'],
                'required_requirements' => ['customer'],
            ],
            self::SCENARIO_DEBIT_NOTE_ISSUE => [
                'category_key' => 'purchase',
                'input_profile' => 'debit_note_issue',
                'required_fields' => ['amount', 'scenario_date', 'supplier_id'],
                'required_requirements' => ['supplier'],
            ],
            self::SCENARIO_DEBIT_NOTE_APPLY => [
                'category_key' => 'purchase',
                'input_profile' => 'debit_note_apply',
                'required_fields' => ['amount', 'scenario_date', 'supplier_id'],
                'required_requirements' => ['supplier'],
            ],
            self::SCENARIO_CUSTOMER_REFUND_CASH => [
                'category_key' => 'receivable',
                'input_profile' => 'customer_refund_cash',
                'required_fields' => ['amount', 'scenario_date', 'customer_id', 'cash_box_id'],
                'required_requirements' => ['customer', 'cash_box'],
            ],
            self::SCENARIO_SUPPLIER_REFUND_CASH => [
                'category_key' => 'payable',
                'input_profile' => 'supplier_refund_cash',
                'required_fields' => ['amount', 'scenario_date', 'supplier_id', 'cash_box_id'],
                'required_requirements' => ['supplier', 'cash_box'],
            ],
            self::SCENARIO_CUSTOMER_ADVANCE_APPLY => [
                'category_key' => 'receivable',
                'input_profile' => 'customer_advance_apply',
                'required_fields' => ['amount', 'scenario_date', 'customer_id', 'payment_method_id'],
                'required_requirements' => ['customer'],
            ],
            self::SCENARIO_SUPPLIER_ADVANCE_APPLY => [
                'category_key' => 'payable',
                'input_profile' => 'supplier_advance_apply',
                'required_fields' => ['amount', 'scenario_date', 'supplier_id', 'cash_box_id'],
                'required_requirements' => ['supplier', 'cash_box'],
            ],
            self::SCENARIO_INVENTORY_ADJUSTMENT_POST => [
                'category_key' => 'inventory',
                'input_profile' => 'inventory_adjustment_post',
                'required_fields' => ['amount', 'scenario_date'],
                'required_requirements' => [],
            ],
            self::SCENARIO_ACCRUAL_POST_REVERSE => [
                'category_key' => 'accrual',
                'input_profile' => 'accrual_post_reverse',
                'required_fields' => ['amount', 'scenario_date'],
                'required_requirements' => [],
            ],
            self::SCENARIO_BAD_DEBT_WRITEOFF => [
                'category_key' => 'receivable',
                'input_profile' => 'bad_debt_writeoff',
                'required_fields' => ['amount', 'scenario_date', 'customer_id'],
                'required_requirements' => ['customer'],
            ],
            self::SCENARIO_FIXED_ASSET_PURCHASE => [
                'category_key' => 'fixed_asset',
                'input_profile' => 'fixed_asset_purchase_cash',
                'required_fields' => ['amount', 'scenario_date', 'fixed_asset_category_id', 'cash_box_id'],
                'required_requirements' => ['fixed_asset_category', 'cash_box'],
            ],
            self::SCENARIO_FIXED_ASSET_DEPRECIATION => [
                'category_key' => 'fixed_asset',
                'input_profile' => 'fixed_asset_depreciation',
                'required_fields' => ['amount', 'scenario_date', 'fixed_asset_category_id'],
                'required_requirements' => ['fixed_asset_category'],
            ],
            self::SCENARIO_FIXED_ASSET_DISPOSAL => [
                'category_key' => 'fixed_asset',
                'input_profile' => 'fixed_asset_disposal',
                'required_fields' => ['amount', 'scenario_date', 'fixed_asset_category_id', 'cash_box_id'],
                'required_requirements' => ['fixed_asset_category', 'cash_box'],
            ],
            self::SCENARIO_SHAREHOLDER_CAPITAL => [
                'category_key' => 'equity',
                'input_profile' => 'shareholder_capital_bank',
                'required_fields' => ['amount', 'scenario_date', 'shareholder_id', 'bank_id'],
                'required_requirements' => ['shareholder', 'bank'],
            ],
            self::SCENARIO_SHAREHOLDER_WITHDRAWAL => [
                'category_key' => 'equity',
                'input_profile' => 'shareholder_withdrawal_cash',
                'required_fields' => ['amount', 'scenario_date', 'shareholder_id', 'cash_box_id'],
                'required_requirements' => ['shareholder', 'cash_box'],
            ],
            self::SCENARIO_PAYROLL_ACCRUAL_BASIC => [
                'category_key' => 'payroll',
                'input_profile' => 'payroll_accrual_basic',
                'required_fields' => ['amount', 'scenario_date'],
                'required_requirements' => ['employee'],
            ],
            self::SCENARIO_PAYROLL_INSURANCE_REMITTANCE => [
                'category_key' => 'payroll',
                'input_profile' => 'payroll_insurance_remittance',
                'required_fields' => ['amount', 'scenario_date', 'bank_id'],
                'required_requirements' => ['employee', 'bank'],
            ],
            self::SCENARIO_PAYROLL_LOAN_SETTLEMENT => [
                'category_key' => 'payroll',
                'input_profile' => 'payroll_loan_settlement',
                'required_fields' => ['amount', 'scenario_date', 'bank_id'],
                'required_requirements' => ['employee', 'bank'],
            ],
            self::SCENARIO_VAT_DECLARATION_SUBMIT => [
                'category_key' => 'vat',
                'input_profile' => 'vat_declaration_submit',
                'required_fields' => ['scenario_date'],
                'required_requirements' => [],
            ],
            self::SCENARIO_ISSUED_CHEQUE_BOUNCE => [
                'category_key' => 'treasury_cheque',
                'input_profile' => 'issued_cheque_bounce',
                'required_fields' => ['amount', 'scenario_date', 'supplier_id', 'bank_id', 'payment_method_id', 'chequebook_id'],
                'required_requirements' => ['supplier', 'bank', 'cheque_method', 'chequebook'],
            ],
            self::SCENARIO_VAT_REMITTANCE => [
                'category_key' => 'vat',
                'input_profile' => 'vat_remittance_bank',
                'required_fields' => ['amount', 'scenario_date', 'bank_id'],
                'required_requirements' => ['bank', 'vat_payable_account'],
            ],
            self::SCENARIO_MANUAL_JOURNAL => [
                'category_key' => 'journal',
                'input_profile' => 'manual_journal_basic',
                'required_fields' => ['amount', 'scenario_date', 'cash_box_id'],
                'required_requirements' => ['cash_box'],
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $input
     * @return array<string,mixed>
     */
    public function preview(array $input): array
    {
        $payload = $this->normalizeInput($input);
        $scenarioKey = $payload['scenario_key'];
        $definitions = $this->scenarioDefinitions();
        if (! isset($definitions[$scenarioKey])) {
            throw new InvalidArgumentException('Invalid scenario key.');
        }

        $context = $this->buildScenarioContext($scenarioKey, $payload);
        $expected = $this->buildExpectedEntries($scenarioKey, $payload, $context);

        return [
            'input' => $payload,
            'scenario_key' => $scenarioKey,
            'scenario' => $definitions[$scenarioKey],
            'precheck' => $context['precheck'],
            'precheck_errors' => (array) ($context['errors'] ?? []),
            'can_execute' => ($context['errors'] ?? []) === [],
            'expected_entries' => $expected,
            'tracked_account_ids' => array_values(array_unique(array_map(static fn (array $entry): int => (int) ($entry['account_id'] ?? 0), $expected))),
        ];
    }

    /**
     * @param  array<string,mixed>  $input
     * @return array<string,mixed>
     */
    public function execute(array $input): array
    {
        $preview = $this->preview($input);
        if (! ($preview['can_execute'] ?? false)) {
            $messages = array_values(array_filter(array_map(
                static fn ($row): string => trim((string) $row),
                (array) ($preview['precheck_errors'] ?? [])
            )));
            if ($messages !== []) {
                throw new RuntimeException(
                    (string) trans('accounting::accounting.scenario_runner.errors.precheck_failed')
                    .' '.implode(' | ', $messages)
                );
            }
            throw new RuntimeException((string) trans('accounting::accounting.scenario_runner.errors.precheck_failed'));
        }

        $payload = $preview['input'];
        $scenarioKey = (string) $preview['scenario_key'];
        $expectedEntries = is_array($preview['expected_entries']) ? $preview['expected_entries'] : [];
        $trackedAccountIds = array_values(array_filter(array_map(static fn ($id): int => (int) $id, (array) ($preview['tracked_account_ids'] ?? []))));

        $beforeSnapshot = $this->ledgerSnapshotService->snapshotPostedBalances($trackedAccountIds);
        $execution = $this->executeScenario($scenarioKey, $payload, $this->buildScenarioContext($scenarioKey, $payload));
        $afterSnapshot = $this->ledgerSnapshotService->snapshotPostedBalances($trackedAccountIds);
        $diff = $this->expectedActualDiffService->compare($expectedEntries, $beforeSnapshot, $afterSnapshot);
        $postChecks = $this->runPostExecutionChecks($scenarioKey, $payload, $execution);
        $overallOk = (bool) ($diff['ok'] ?? false) && (bool) ($postChecks['ok'] ?? true);

        $documents = $this->ledgerSnapshotService->fetchDocumentsWithLines((array) ($execution['document_ids'] ?? []));

        return [
            'ok' => $overallOk,
            'scenario_key' => $scenarioKey,
            'scenario' => $this->scenarioDefinitions()[$scenarioKey] ?? [],
            'input' => $payload,
            'expected_entries' => $expectedEntries,
            'before' => $beforeSnapshot,
            'after' => $afterSnapshot,
            'diff' => $diff,
            'post_checks' => $postChecks,
            'execution' => $execution,
            'documents' => $documents,
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $execution
     * @return array{ok: bool, rows: array<int,array<string,mixed>>}
     */
    private function runPostExecutionChecks(string $scenarioKey, array $payload, array $execution): array
    {
        $rows = [];
        $rows = array_merge($rows, $this->postCheckDocumentsExist($execution));
        $rows = array_merge($rows, $this->postCheckDocumentsBalanced($execution));
        $rows = array_merge($rows, $this->postCheckDocumentsHaveLedgerEntries($execution));

        $scenarioRows = match ($scenarioKey) {
            self::SCENARIO_CUSTOMER_ADVANCE_CASH => $this->postCheckCustomerAdvanceInStatement($payload, $execution)['rows'],
            self::SCENARIO_CREDIT_NOTE_APPLY => $this->postCheckCreditNoteApplied($execution),
            self::SCENARIO_DEBIT_NOTE_APPLY => $this->postCheckDebitNoteApplied($execution),
            self::SCENARIO_CUSTOMER_ADVANCE_APPLY => array_merge(
                $this->postCheckCustomerAdvanceApplied($execution),
                $this->postCheckCustomerAdvanceInStatement($payload, $execution)['rows']
            ),
            self::SCENARIO_SUPPLIER_ADVANCE_APPLY => $this->postCheckSupplierAdvanceApplied($execution),
            self::SCENARIO_INVENTORY_ADJUSTMENT_POST => $this->postCheckInventoryAdjustmentPosted($execution),
            self::SCENARIO_ACCRUAL_POST_REVERSE => $this->postCheckAccrualReversed($execution),
            self::SCENARIO_BAD_DEBT_WRITEOFF => $this->postCheckBadDebtWriteoffPosted($execution),
            self::SCENARIO_FIXED_ASSET_DEPRECIATION => $this->postCheckFixedAssetDepreciationPosted($execution),
            self::SCENARIO_FIXED_ASSET_DISPOSAL => $this->postCheckFixedAssetDisposed($execution),
            self::SCENARIO_PAYROLL_ACCRUAL_BASIC => $this->postCheckPayrollAccrualPosted($execution),
            self::SCENARIO_PAYROLL_INSURANCE_REMITTANCE => $this->postCheckPayrollInsurancePosted($execution),
            self::SCENARIO_PAYROLL_LOAN_SETTLEMENT => $this->postCheckPayrollLoanSettlementPosted($execution),
            self::SCENARIO_VAT_DECLARATION_SUBMIT => array_merge(
                $this->postCheckVatDeclarationSubmitted($execution),
                $this->postCheckVatDeclarationSnapshotSynced($execution)
            ),
            self::SCENARIO_ISSUED_CHEQUE_BOUNCE => $this->postCheckIssuedChequeBounced($execution),
            default => [],
        };

        $rows = array_merge($rows, $scenarioRows);
        $ok = ! collect($rows)->contains(static fn (array $row): bool => ! (bool) ($row['ok'] ?? false));

        return ['ok' => $ok, 'rows' => $rows];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $execution
     * @return array{ok: bool, rows: array<int,array<string,mixed>>}
     */
    private function postCheckCustomerAdvanceInStatement(array $payload, array $execution): array
    {
        $advanceId = (int) ($execution['entity_id'] ?? 0);
        $customerId = (int) ($payload['customer_id'] ?? 0);
        $scenarioDate = (string) ($payload['scenario_date'] ?? now()->toDateString());

        $baseMessage = (string) trans('accounting::accounting.scenario_runner.post_checks.customer_statement_contains_advance');
        if ($advanceId <= 0 || $customerId <= 0) {
            return [
                'ok' => false,
                'rows' => [[
                    'key' => 'customer_statement_contains_advance',
                    'ok' => false,
                    'message' => $baseMessage,
                    'details' => (string) trans('accounting::accounting.scenario_runner.post_checks.missing_context'),
                ]],
            ];
        }

        $advance = CustomerAdvance::query()->find($advanceId);
        if (! $advance) {
            return [
                'ok' => false,
                'rows' => [[
                    'key' => 'customer_statement_contains_advance',
                    'ok' => false,
                    'message' => $baseMessage,
                    'details' => (string) trans('accounting::accounting.scenario_runner.post_checks.advance_not_found'),
                ]],
            ];
        }

        $fromDate = Carbon::parse($scenarioDate)->subDay()->toDateString();
        $toDate = Carbon::parse($scenarioDate)->addDay()->toDateString();
        $statement = app(ReportService::class)->getCustomerStatement([
            'customer_id' => $customerId,
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ]);
        $entries = (array) ($statement['entries'] ?? []);

        $matched = collect($entries)->first(static function ($entry) use ($advance): bool {
            return (string) data_get($entry, 'document_number', '') === (string) ($advance->advance_number ?? '')
                && (string) data_get($entry, 'description', '') === 'پیش‌دریافت مشتری';
        });

        $ok = is_array($matched);
        $details = $ok
            ? (string) trans('accounting::accounting.scenario_runner.post_checks.entry_found')
            : (string) trans('accounting::accounting.scenario_runner.post_checks.entry_missing');

        return [
            'ok' => $ok,
            'rows' => [[
                'key' => 'customer_statement_contains_advance',
                'ok' => $ok,
                'message' => $baseMessage,
                'details' => $details,
            ]],
        ];
    }

    /**
     * @param array<string,mixed> $execution
     * @return array<int,array<string,mixed>>
     */
    private function postCheckDocumentsExist(array $execution): array
    {
        $documentIds = array_values(array_filter(array_map(static fn ($id): int => (int) $id, (array) ($execution['document_ids'] ?? []))));
        if ($documentIds === []) {
            return [];
        }

        $existing = (int) DB::table('accounting_documents')->whereIn('id', $documentIds)->count();
        $ok = $existing === count($documentIds);

        return [[
            'key' => 'documents_exist',
            'ok' => $ok,
            'message' => (string) trans('accounting::accounting.scenario_runner.post_checks.documents_exist'),
            'details' => $ok
                ? (string) trans('accounting::accounting.scenario_runner.post_checks.entry_found')
                : (string) trans('accounting::accounting.scenario_runner.post_checks.entry_missing'),
        ]];
    }

    /**
     * @param array<string,mixed> $execution
     * @return array<int,array<string,mixed>>
     */
    private function postCheckDocumentsBalanced(array $execution): array
    {
        $documentIds = array_values(array_filter(array_map(static fn ($id): int => (int) $id, (array) ($execution['document_ids'] ?? []))));
        if ($documentIds === []) {
            return [];
        }

        $aggregate = DB::table('financial_ledgers')
            ->whereIn('accounting_document_id', $documentIds)
            ->selectRaw('COALESCE(SUM(debit_amount),0) AS total_debit')
            ->selectRaw('COALESCE(SUM(credit_amount),0) AS total_credit')
            ->first();

        $debit = round((float) ($aggregate->total_debit ?? 0), 4);
        $credit = round((float) ($aggregate->total_credit ?? 0), 4);
        $ok = abs($debit - $credit) < 0.01;

        return [[
            'key' => 'documents_balanced',
            'ok' => $ok,
            'message' => (string) trans('accounting::accounting.scenario_runner.post_checks.documents_balanced'),
            'details' => 'debit='.$debit.' | credit='.$credit,
        ]];
    }

    /**
     * @param array<string,mixed> $execution
     * @return array<int,array<string,mixed>>
     */
    private function postCheckDocumentsHaveLedgerEntries(array $execution): array
    {
        $documentIds = array_values(array_filter(array_map(static fn ($id): int => (int) $id, (array) ($execution['document_ids'] ?? []))));
        if ($documentIds === []) {
            return [];
        }

        $covered = (int) DB::table('financial_ledgers')
            ->whereIn('accounting_document_id', $documentIds)
            ->distinct()
            ->count('accounting_document_id');
        $ok = $covered === count($documentIds);

        return [[
            'key' => 'documents_have_entries',
            'ok' => $ok,
            'message' => (string) trans('accounting::accounting.scenario_runner.post_checks.documents_have_entries'),
            'details' => $covered.'/'.count($documentIds),
        ]];
    }

    /**
     * @param array<string,mixed> $execution
     * @return array<int,array<string,mixed>>
     */
    private function postCheckCreditNoteApplied(array $execution): array
    {
        $creditNoteId = (int) ($execution['entity_id'] ?? 0);
        $note = CreditNote::query()->find($creditNoteId);
        $ok = $note !== null && (string) $note->status === CreditNote::STATUS_APPLIED;

        return [[
            'key' => 'credit_note_applied',
            'ok' => $ok,
            'message' => (string) trans('accounting::accounting.scenario_runner.post_checks.credit_note_applied'),
            'details' => $ok ? (string) trans('accounting::accounting.scenario_runner.post_checks.entry_found') : (string) trans('accounting::accounting.scenario_runner.post_checks.entry_missing'),
        ]];
    }

    /**
     * @param array<string,mixed> $execution
     * @return array<int,array<string,mixed>>
     */
    private function postCheckDebitNoteApplied(array $execution): array
    {
        $debitNoteId = (int) ($execution['entity_id'] ?? 0);
        $note = DebitNote::query()->find($debitNoteId);
        $ok = $note !== null && (string) $note->status === DebitNote::STATUS_APPLIED;

        return [[
            'key' => 'debit_note_applied',
            'ok' => $ok,
            'message' => (string) trans('accounting::accounting.scenario_runner.post_checks.debit_note_applied'),
            'details' => $ok ? (string) trans('accounting::accounting.scenario_runner.post_checks.entry_found') : (string) trans('accounting::accounting.scenario_runner.post_checks.entry_missing'),
        ]];
    }

    /**
     * @param array<string,mixed> $execution
     * @return array<int,array<string,mixed>>
     */
    private function postCheckCustomerAdvanceApplied(array $execution): array
    {
        $advanceId = (int) ($execution['entity_id'] ?? 0);
        $advance = CustomerAdvance::query()->find($advanceId);
        $ok = $advance !== null && (float) ($advance->applied_amount ?? 0) > 0;

        return [[
            'key' => 'customer_advance_applied',
            'ok' => $ok,
            'message' => (string) trans('accounting::accounting.scenario_runner.post_checks.customer_advance_applied'),
            'details' => $ok ? (string) trans('accounting::accounting.scenario_runner.post_checks.entry_found') : (string) trans('accounting::accounting.scenario_runner.post_checks.entry_missing'),
        ]];
    }

    /**
     * @param array<string,mixed> $execution
     * @return array<int,array<string,mixed>>
     */
    private function postCheckSupplierAdvanceApplied(array $execution): array
    {
        $advanceId = (int) ($execution['entity_id'] ?? 0);
        $advance = SupplierAdvance::query()->find($advanceId);
        $ok = $advance !== null && (float) ($advance->applied_amount ?? 0) > 0;

        return [[
            'key' => 'supplier_advance_applied',
            'ok' => $ok,
            'message' => (string) trans('accounting::accounting.scenario_runner.post_checks.supplier_advance_applied'),
            'details' => $ok ? (string) trans('accounting::accounting.scenario_runner.post_checks.entry_found') : (string) trans('accounting::accounting.scenario_runner.post_checks.entry_missing'),
        ]];
    }

    /**
     * @param array<string,mixed> $execution
     * @return array<int,array<string,mixed>>
     */
    private function postCheckInventoryAdjustmentPosted(array $execution): array
    {
        $adjustmentId = (int) ($execution['entity_id'] ?? 0);
        $adjustment = InventoryAdjustment::query()->find($adjustmentId);
        $ok = $adjustment !== null && (string) ($adjustment->status ?? '') === 'posted';

        return [[
            'key' => 'inventory_adjustment_posted',
            'ok' => $ok,
            'message' => (string) trans('accounting::accounting.scenario_runner.post_checks.inventory_adjustment_posted'),
            'details' => $ok ? (string) trans('accounting::accounting.scenario_runner.post_checks.entry_found') : (string) trans('accounting::accounting.scenario_runner.post_checks.entry_missing'),
        ]];
    }

    /**
     * @param array<string,mixed> $execution
     * @return array<int,array<string,mixed>>
     */
    private function postCheckAccrualReversed(array $execution): array
    {
        $accrualId = (int) ($execution['entity_id'] ?? 0);
        $accrual = Accrual::query()->find($accrualId);
        $ok = $accrual !== null && (bool) ($accrual->is_reversed ?? false) && (int) ($accrual->reversal_document_id ?? 0) > 0;

        return [[
            'key' => 'accrual_reversed',
            'ok' => $ok,
            'message' => (string) trans('accounting::accounting.scenario_runner.post_checks.accrual_reversed'),
            'details' => $ok ? (string) trans('accounting::accounting.scenario_runner.post_checks.entry_found') : (string) trans('accounting::accounting.scenario_runner.post_checks.entry_missing'),
        ]];
    }

    /**
     * @param array<string,mixed> $execution
     * @return array<int,array<string,mixed>>
     */
    private function postCheckBadDebtWriteoffPosted(array $execution): array
    {
        $writeoffId = (int) ($execution['entity_id'] ?? 0);
        $writeoff = BadDebtWriteoff::query()->find($writeoffId);
        $ok = $writeoff !== null && (string) ($writeoff->status ?? '') === BadDebtWriteoff::STATUS_APPROVED;

        return [[
            'key' => 'bad_debt_writeoff_posted',
            'ok' => $ok,
            'message' => (string) trans('accounting::accounting.scenario_runner.post_checks.bad_debt_writeoff_posted'),
            'details' => $ok ? (string) trans('accounting::accounting.scenario_runner.post_checks.entry_found') : (string) trans('accounting::accounting.scenario_runner.post_checks.entry_missing'),
        ]];
    }

    /**
     * @param array<string,mixed> $execution
     * @return array<int,array<string,mixed>>
     */
    private function postCheckFixedAssetDepreciationPosted(array $execution): array
    {
        $entryId = (int) ($execution['entity_id'] ?? 0);
        $entry = DepreciationEntry::query()->find($entryId);
        $ok = $entry !== null && (int) ($entry->accounting_document_id ?? 0) > 0;

        return [[
            'key' => 'fixed_asset_depreciation_posted',
            'ok' => $ok,
            'message' => (string) trans('accounting::accounting.scenario_runner.post_checks.fixed_asset_depreciation_posted'),
            'details' => $ok ? (string) trans('accounting::accounting.scenario_runner.post_checks.entry_found') : (string) trans('accounting::accounting.scenario_runner.post_checks.entry_missing'),
        ]];
    }

    /**
     * @param array<string,mixed> $execution
     * @return array<int,array<string,mixed>>
     */
    private function postCheckFixedAssetDisposed(array $execution): array
    {
        $assetId = (int) ($execution['entity_id'] ?? 0);
        $asset = FixedAsset::query()->find($assetId);
        $ok = $asset !== null && (string) ($asset->status ?? '') === 'disposed';

        return [[
            'key' => 'fixed_asset_disposed',
            'ok' => $ok,
            'message' => (string) trans('accounting::accounting.scenario_runner.post_checks.fixed_asset_disposed'),
            'details' => $ok ? (string) trans('accounting::accounting.scenario_runner.post_checks.entry_found') : (string) trans('accounting::accounting.scenario_runner.post_checks.entry_missing'),
        ]];
    }

    /**
     * @param array<string,mixed> $execution
     * @return array<int,array<string,mixed>>
     */
    private function postCheckPayrollAccrualPosted(array $execution): array
    {
        $runId = (int) ($execution['entity_id'] ?? 0);
        $run = PayrollRun::query()->find($runId);
        $ok = $run !== null && (int) ($run->accrual_manual_journal_id ?? 0) > 0;

        return [[
            'key' => 'payroll_accrual_posted',
            'ok' => $ok,
            'message' => (string) trans('accounting::accounting.scenario_runner.post_checks.payroll_accrual_posted'),
            'details' => $ok ? (string) trans('accounting::accounting.scenario_runner.post_checks.entry_found') : (string) trans('accounting::accounting.scenario_runner.post_checks.entry_missing'),
        ]];
    }

    /**
     * @param array<string,mixed> $execution
     * @return array<int,array<string,mixed>>
     */
    private function postCheckPayrollInsurancePosted(array $execution): array
    {
        $runId = (int) ($execution['entity_id'] ?? 0);
        $run = PayrollRun::query()->find($runId);
        $ok = $run !== null && (int) ($run->insurance_remittance_manual_journal_id ?? 0) > 0;

        return [[
            'key' => 'payroll_insurance_posted',
            'ok' => $ok,
            'message' => (string) trans('accounting::accounting.scenario_runner.post_checks.payroll_insurance_posted'),
            'details' => $ok ? (string) trans('accounting::accounting.scenario_runner.post_checks.entry_found') : (string) trans('accounting::accounting.scenario_runner.post_checks.entry_missing'),
        ]];
    }

    /**
     * @param array<string,mixed> $execution
     * @return array<int,array<string,mixed>>
     */
    private function postCheckPayrollLoanSettlementPosted(array $execution): array
    {
        $runId = (int) ($execution['entity_id'] ?? 0);
        $run = PayrollRun::query()->find($runId);
        $ok = $run !== null && (int) ($run->loan_settlement_manual_journal_id ?? 0) > 0;

        return [[
            'key' => 'payroll_loan_settlement_posted',
            'ok' => $ok,
            'message' => (string) trans('accounting::accounting.scenario_runner.post_checks.payroll_loan_settlement_posted'),
            'details' => $ok ? (string) trans('accounting::accounting.scenario_runner.post_checks.entry_found') : (string) trans('accounting::accounting.scenario_runner.post_checks.entry_missing'),
        ]];
    }

    /**
     * @param array<string,mixed> $execution
     * @return array<int,array<string,mixed>>
     */
    private function postCheckVatDeclarationSubmitted(array $execution): array
    {
        $declarationId = (int) ($execution['entity_id'] ?? 0);
        $declaration = VatDeclaration::query()->find($declarationId);
        $ok = $declaration !== null && (string) ($declaration->status ?? '') === VatDeclaration::STATUS_SUBMITTED;

        return [[
            'key' => 'vat_declaration_submitted',
            'ok' => $ok,
            'message' => (string) trans('accounting::accounting.scenario_runner.post_checks.vat_declaration_submitted'),
            'details' => $ok ? (string) trans('accounting::accounting.scenario_runner.post_checks.entry_found') : (string) trans('accounting::accounting.scenario_runner.post_checks.entry_missing'),
        ]];
    }

    /**
     * @param array<string,mixed> $execution
     * @return array<int,array<string,mixed>>
     */
    private function postCheckVatDeclarationSnapshotSynced(array $execution): array
    {
        $declarationId = (int) ($execution['entity_id'] ?? 0);
        $declaration = VatDeclaration::query()->find($declarationId);
        $snapshot = is_array($declaration?->snapshot_json) ? $declaration->snapshot_json : [];
        $report = is_array($snapshot['vat_report'] ?? null) ? $snapshot['vat_report'] : [];
        $ok = $declaration !== null
            && $report !== []
            && array_key_exists('vat_payable', $report);

        return [[
            'key' => 'vat_declaration_snapshot_synced',
            'ok' => $ok,
            'message' => (string) trans('accounting::accounting.scenario_runner.post_checks.vat_declaration_snapshot_synced'),
            'details' => $ok ? (string) trans('accounting::accounting.scenario_runner.post_checks.entry_found') : (string) trans('accounting::accounting.scenario_runner.post_checks.entry_missing'),
        ]];
    }

    /**
     * @param array<string,mixed> $execution
     * @return array<int,array<string,mixed>>
     */
    private function postCheckIssuedChequeBounced(array $execution): array
    {
        $chequeId = (int) ($execution['entity_id'] ?? 0);
        $cheque = Cheque::query()->find($chequeId);
        $ok = $cheque !== null && (string) ($cheque->status ?? '') === Cheque::STATUS_BOUNCED;

        return [[
            'key' => 'issued_cheque_bounced',
            'ok' => $ok,
            'message' => (string) trans('accounting::accounting.scenario_runner.post_checks.issued_cheque_bounced'),
            'details' => $ok ? (string) trans('accounting::accounting.scenario_runner.post_checks.entry_found') : (string) trans('accounting::accounting.scenario_runner.post_checks.entry_missing'),
        ]];
    }

    /**
     * @param  array<string,mixed>  $input
     * @return array<string,mixed>
     */
    private function normalizeInput(array $input): array
    {
        $defaults = $this->defaultFormValues();
        $scenarioDate = Carbon::parse((string) ($input['scenario_date'] ?? $defaults['scenario_date']))->toDateString();
        $fromTreasuryType = strtolower(trim((string) ($input['from_treasury_type'] ?? '')));
        $toTreasuryType = strtolower(trim((string) ($input['to_treasury_type'] ?? '')));

        return [
            'scenario_key' => (string) ($input['scenario_key'] ?? $defaults['scenario_key']),
            'amount' => (float) ($input['amount'] ?? $defaults['amount']),
            'scenario_date' => $scenarioDate,
            'notes' => trim((string) ($input['notes'] ?? '')),
            'customer_id' => (int) ($input['customer_id'] ?? 0),
            'supplier_id' => (int) ($input['supplier_id'] ?? 0),
            'bank_id' => (int) ($input['bank_id'] ?? 0),
            'cash_box_id' => (int) ($input['cash_box_id'] ?? 0),
            'wallet_id' => (int) ($input['wallet_id'] ?? 0),
            'payment_method_id' => (int) ($input['payment_method_id'] ?? 0),
            'chequebook_id' => (int) ($input['chequebook_id'] ?? 0),
            'expense_category_id' => (int) ($input['expense_category_id'] ?? 0),
            'fixed_asset_category_id' => (int) ($input['fixed_asset_category_id'] ?? 0),
            'shareholder_id' => (int) ($input['shareholder_id'] ?? 0),
            'from_treasury_type' => in_array($fromTreasuryType, ['wallet', 'cashbox', 'bank'], true) ? $fromTreasuryType : null,
            'from_treasury_id' => (int) ($input['from_treasury_id'] ?? 0),
            'to_treasury_type' => in_array($toTreasuryType, ['wallet', 'cashbox', 'bank'], true) ? $toTreasuryType : null,
            'to_treasury_id' => (int) ($input['to_treasury_id'] ?? 0),
            'value_date' => trim((string) ($input['value_date'] ?? '')) !== ''
                ? Carbon::parse((string) $input['value_date'])->toDateString()
                : $scenarioDate,
            'transfer_fee' => max(0, (float) ($input['transfer_fee'] ?? 0)),
            'reference_number' => trim((string) ($input['reference_number'] ?? '')),
            'description' => trim((string) ($input['description'] ?? '')),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function buildScenarioContext(string $scenarioKey, array $payload = []): array
    {
        $precheck = [];
        $errors = [];

        $defaultCustomer = Customer::query()
            ->where('active', true)
            ->whereNotNull('account_id')
            ->where('account_id', '>', 0)
            ->whereNotNull('party_id')
            ->where('party_id', '>', 0)
            ->orderBy('id')
            ->first();
        $defaultSupplier = Supplier::query()
            ->where('active', true)
            ->whereNotNull('account_id')
            ->where('account_id', '>', 0)
            ->whereNotNull('party_id')
            ->where('party_id', '>', 0)
            ->orderBy('id')
            ->first();
        $defaultBank = Bank::query()->where('active', true)->whereNotNull('account_id')->where('account_id', '>', 0)->orderBy('id')->first();
        $defaultCashBox = CashBox::query()->where('active', true)->whereNotNull('account_id')->where('account_id', '>', 0)->orderBy('id')->first();
        $defaultWallet = Wallet::query()
            ->where('active', true)
            ->where('wallet_type', Wallet::TYPE_TREASURY)
            ->whereNotNull('account_id')
            ->where('account_id', '>', 0)
            ->orderBy('id')
            ->first();
        if (! $defaultWallet) {
            $defaultWallet = Wallet::query()
                ->where('active', true)
                ->whereNotNull('account_id')
                ->where('account_id', '>', 0)
                ->orderBy('id')
                ->first();
        }
        $defaultCashMethodId = (int) PaymentMethod::query()->where('active', true)->where('type', PaymentMethod::TYPE_CASH)->value('id');
        $defaultChequeMethodId = (int) PaymentMethod::query()->where('active', true)->where('type', PaymentMethod::TYPE_CHEQUE)->value('id');
        $defaultChequebook = Chequebook::query()->where('active', true)->orderBy('id')->first();
        $defaultExpenseCategory = ExpenseCategory::query()->active()->whereNotNull('account_id')->orderBy('id')->first();
        $defaultFixedAssetCategory = FixedAssetCategory::query()->where('active', true)->whereNotNull('asset_account_id')->orderBy('id')->first();
        $defaultShareholder = Shareholder::query()->where('active', true)->orderBy('id')->first();
        $defaultEmployee = Employee::query()
            ->where('active', true)
            ->whereNotNull('payroll_expense_account_id')
            ->where('payroll_expense_account_id', '>', 0)
            ->orderBy('id')
            ->first();
        if (! $defaultEmployee) {
            $defaultEmployee = Employee::query()->where('active', true)->orderBy('id')->first();
        }

        $customer = (int) ($payload['customer_id'] ?? 0) > 0
            ? Customer::query()->where('active', true)->whereKey((int) $payload['customer_id'])->first()
            : $defaultCustomer;
        $supplier = (int) ($payload['supplier_id'] ?? 0) > 0
            ? Supplier::query()->where('active', true)->whereKey((int) $payload['supplier_id'])->first()
            : $defaultSupplier;
        $bank = (int) ($payload['bank_id'] ?? 0) > 0
            ? Bank::query()->where('active', true)->whereKey((int) $payload['bank_id'])->first()
            : $defaultBank;
        $cashBox = (int) ($payload['cash_box_id'] ?? 0) > 0
            ? CashBox::query()->where('active', true)->whereKey((int) $payload['cash_box_id'])->first()
            : $defaultCashBox;
        $wallet = (int) ($payload['wallet_id'] ?? 0) > 0
            ? Wallet::query()->where('active', true)->whereKey((int) $payload['wallet_id'])->first()
            : $defaultWallet;
        $selectedPaymentMethodId = (int) ($payload['payment_method_id'] ?? 0);
        $selectedPaymentMethod = $selectedPaymentMethodId > 0
            ? PaymentMethod::query()->where('active', true)->whereKey($selectedPaymentMethodId)->first()
            : null;
        $cashMethodId = $selectedPaymentMethod && (string) $selectedPaymentMethod->type === PaymentMethod::TYPE_CASH
            ? (int) $selectedPaymentMethod->id
            : $defaultCashMethodId;
        $chequeMethodId = $selectedPaymentMethod && (string) $selectedPaymentMethod->type === PaymentMethod::TYPE_CHEQUE
            ? (int) $selectedPaymentMethod->id
            : $defaultChequeMethodId;
        $chequebook = (int) ($payload['chequebook_id'] ?? 0) > 0
            ? Chequebook::query()->where('active', true)->whereKey((int) $payload['chequebook_id'])->first()
            : $defaultChequebook;
        $expenseCategory = (int) ($payload['expense_category_id'] ?? 0) > 0
            ? ExpenseCategory::query()->active()->whereKey((int) $payload['expense_category_id'])->first()
            : $defaultExpenseCategory;
        $fixedAssetCategory = (int) ($payload['fixed_asset_category_id'] ?? 0) > 0
            ? FixedAssetCategory::query()->where('active', true)->whereKey((int) $payload['fixed_asset_category_id'])->first()
            : $defaultFixedAssetCategory;
        $shareholder = (int) ($payload['shareholder_id'] ?? 0) > 0
            ? Shareholder::query()->where('active', true)->whereKey((int) $payload['shareholder_id'])->first()
            : $defaultShareholder;
        $employee = $defaultEmployee;

        $context = [
            'customer' => $customer,
            'supplier' => $supplier,
            'bank' => $bank,
            'cash_box' => $cashBox,
            'wallet' => $wallet,
            'cash_method_id' => $cashMethodId,
            'cheque_method_id' => $chequeMethodId,
            'selected_payment_method' => $selectedPaymentMethod,
            'chequebook' => $chequebook,
            'expense_category' => $expenseCategory,
            'fixed_asset_category' => $fixedAssetCategory,
            'shareholder' => $shareholder,
            'employee' => $employee,
        ];

        $profiles = $this->scenarioProfiles();
        $requiredRequirements = (array) ($profiles[$scenarioKey]['required_requirements'] ?? []);
        foreach ($requiredRequirements as $requirement) {
            $ok = match ($requirement) {
                'customer' => $customer !== null,
                'supplier' => $supplier !== null,
                'bank' => $bank !== null,
                'cash_box' => $cashBox !== null,
                'wallet' => $wallet !== null,
                'cash_method' => $cashMethodId > 0,
                'cheque_method' => $chequeMethodId > 0,
                'chequebook' => $chequebook !== null,
                'expense_category' => $expenseCategory !== null,
                'fixed_asset_category' => $fixedAssetCategory !== null,
                'shareholder' => $shareholder !== null,
                'employee' => $employee !== null,
                'vat_payable_account' => (int) (AccountingVatAccounts::resolvePayableAccountId() ?? 0) > 0,
                default => true,
            };

            $label = (string) trans('accounting::accounting.scenario_runner.requirements.'.$requirement);
            $precheck[] = ['key' => $requirement, 'ok' => $ok, 'message' => $label];
            if (! $ok) {
                $errors[] = $label;
            }
        }

        $this->appendScenarioSpecificPrechecks($scenarioKey, $context, $precheck, $errors);

        $context['precheck'] = $precheck;
        $context['errors'] = $errors;

        return $context;
    }

    /**
     * @param  array<string,mixed>  $context
     * @param  array<int,array<string,mixed>>  $precheck
     * @param  array<int,string>  $errors
     */
    private function appendScenarioSpecificPrechecks(string $scenarioKey, array $context, array &$precheck, array &$errors): void
    {
        if ($scenarioKey === self::SCENARIO_EXPENSE_CASH) {
            /** @var ExpenseCategory|null $expenseCategory */
            $expenseCategory = $context['expense_category'] ?? null;
            $expenseAccountId = (int) ($expenseCategory?->account_id ?? 0);
            $accountExists = $expenseAccountId > 0 && Account::query()->whereKey($expenseAccountId)->exists();
            $ok = $expenseCategory !== null && $accountExists;

            if (! $ok) {
                $settingsUrl = $this->expenseCategorySettingsUrl();
                $categoryName = trim((string) ($expenseCategory?->name ?? ''));
                $message = (string) trans(
                    'accounting::accounting.scenario_runner.errors.expense_category_account_invalid',
                    [
                        'category' => $categoryName !== '' ? $categoryName : '-',
                        'account_id' => (string) $expenseAccountId,
                        'url' => $settingsUrl,
                    ]
                );
                if ($message === 'accounting::accounting.scenario_runner.errors.expense_category_account_invalid') {
                    $message = 'Expense category account is invalid. Please set a valid account in: '.$settingsUrl;
                }

                $precheck[] = [
                    'key' => 'expense_category_account_valid',
                    'ok' => false,
                    'message' => $message,
                    'action_url' => $settingsUrl,
                    'action_label' => (string) trans('accounting::accounting.scenario_runner.actions.open_expense_categories'),
                ];
                $errors[] = $message;
            }
        }

        if (in_array($scenarioKey, [self::SCENARIO_FIXED_ASSET_DEPRECIATION, self::SCENARIO_FIXED_ASSET_DISPOSAL], true)) {
            /** @var FixedAssetCategory|null $fixedAssetCategory */
            $fixedAssetCategory = $context['fixed_asset_category'] ?? null;
            $requiredAccounts = [
                (int) ($fixedAssetCategory?->asset_account_id ?? 0),
                (int) ($fixedAssetCategory?->depreciation_account_id ?? 0),
                (int) ($fixedAssetCategory?->accumulated_depreciation_account_id ?? 0),
            ];
            $ok = $fixedAssetCategory !== null
                && collect($requiredAccounts)->every(static fn (int $accountId): bool => $accountId > 0 && Account::query()->whereKey($accountId)->exists());

            if (! $ok) {
                $message = (string) trans('accounting::accounting.scenario_runner.errors.fixed_asset_accounts_invalid');
                $precheck[] = [
                    'key' => 'fixed_asset_accounts_valid',
                    'ok' => false,
                    'message' => $message,
                ];
                $errors[] = $message;
            }
        }

        if (in_array($scenarioKey, [self::SCENARIO_PAYROLL_ACCRUAL_BASIC, self::SCENARIO_PAYROLL_INSURANCE_REMITTANCE, self::SCENARIO_PAYROLL_LOAN_SETTLEMENT], true)) {
            /** @var Employee|null $employee */
            $employee = $context['employee'] ?? null;
            $expenseAccountId = (int) ($employee?->payroll_expense_account_id ?? 0);
            $ok = $employee !== null && $expenseAccountId > 0 && Account::query()->whereKey($expenseAccountId)->exists();
            if (! $ok) {
                $message = (string) trans('accounting::accounting.scenario_runner.errors.payroll_employee_account_invalid');
                $precheck[] = [
                    'key' => 'payroll_employee_account_valid',
                    'ok' => false,
                    'message' => $message,
                ];
                $errors[] = $message;
            }
        }
    }

    private function expenseCategorySettingsUrl(): string
    {
        try {
            return (string) route('admin.accounting.expense-categories.index');
        } catch (\Throwable) {
            return (string) url('/admin/accounting/expense-categories');
        }
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<int,array<string,mixed>>
     */
    private function buildExpectedEntries(string $scenarioKey, array $payload, array $context): array
    {
        $amount = round((float) $payload['amount'], 4);
        $vatCalc = $this->resolveScenarioVatAmounts($amount);
        $vat = $vatCalc['tax_amount'];
        $total = $vatCalc['total_amount'];
        $baseAmount = $vatCalc['base_amount'];

        return match ($scenarioKey) {
            self::SCENARIO_SALES_INVOICE => [
                $this->expectedEntry(
                    (int) $this->resolveCustomerReceivableAccountId($context),
                    $total,
                    0,
                    $this->expectedEntryNote('accounts_receivable'),
                    $this->resolveCounterpartyName($context, 'customer')
                ),
                $this->expectedEntry((int) $this->resolveCustomerRevenueAccountId($context), 0, $baseAmount, $this->expectedEntryNote('sales_revenue')),
                $this->expectedEntry((int) (AccountingVatAccounts::resolvePayableAccountId() ?? 0), 0, $vat, $this->expectedEntryNote('vat_payable')),
            ],
            self::SCENARIO_PURCHASE_INVOICE => [
                $this->expectedEntry((int) $this->resolveSupplierCostAccountId($context), $baseAmount, 0, $this->expectedEntryNote('inventory_cost')),
                $this->expectedEntry((int) (AccountingVatAccounts::resolveReceivableAccountId() ?? 0), $vat, 0, $this->expectedEntryNote('vat_receivable')),
                $this->expectedEntry((int) $this->resolveSupplierPayableAccountId($context), 0, $total, $this->expectedEntryNote('accounts_payable')),
            ],
            self::SCENARIO_CUSTOMER_RECEIPT_CASH => [
                $this->expectedEntry((int) ($context['cash_box']?->account_id ?? 0), $amount, 0, $this->expectedEntryNote('cashbox')),
                $this->expectedEntry(
                    (int) ($context['customer']?->account_id ?? 0),
                    0,
                    $amount,
                    $this->expectedEntryNote('accounts_receivable'),
                    $this->resolveCounterpartyName($context, 'customer')
                ),
            ],
            self::SCENARIO_CUSTOMER_RECEIPT_WALLET => [
                $this->expectedEntry((int) ($context['wallet']?->account_id ?? 0), $amount, 0, $this->expectedEntryNote('wallet')),
                $this->expectedEntry(
                    (int) ($context['customer']?->account_id ?? 0),
                    0,
                    $amount,
                    $this->expectedEntryNote('accounts_receivable'),
                    $this->resolveCounterpartyName($context, 'customer')
                ),
            ],
            self::SCENARIO_SUPPLIER_PAYMENT_CASH => [
                $this->expectedEntry((int) ($context['supplier']?->account_id ?? 0), $amount, 0, $this->expectedEntryNote('accounts_payable')),
                $this->expectedEntry((int) ($context['cash_box']?->account_id ?? 0), 0, $amount, $this->expectedEntryNote('cashbox')),
            ],
            self::SCENARIO_SUPPLIER_PAYMENT_WALLET => [
                $this->expectedEntry((int) ($context['supplier']?->account_id ?? 0), $amount, 0, $this->expectedEntryNote('accounts_payable')),
                $this->expectedEntry((int) ($context['wallet']?->account_id ?? 0), 0, $amount, $this->expectedEntryNote('wallet')),
            ],
            self::SCENARIO_CUSTOMER_ADVANCE_CASH => [
                $this->expectedEntry((int) ($context['cash_box']?->account_id ?? 0), $amount, 0, $this->expectedEntryNote('cashbox')),
                $this->expectedEntry((int) $this->resolveCustomerAdvanceAccountId($context), 0, $amount, $this->expectedEntryNote('customer_advance_liability')),
            ],
            self::SCENARIO_SUPPLIER_ADVANCE_CASH => [
                $this->expectedEntry((int) $this->resolveSupplierAdvanceAccountId($context), $amount, 0, $this->expectedEntryNote('supplier_advance_asset')),
                $this->expectedEntry((int) ($context['cash_box']?->account_id ?? 0), 0, $amount, $this->expectedEntryNote('cashbox')),
            ],
            self::SCENARIO_RECEIVED_CHEQUE_CASH => [
                $this->expectedEntry((int) ($context['bank']?->account_id ?? 0), $amount, 0, $this->expectedEntryNote('bank')),
                $this->expectedEntry(
                    (int) ($context['customer']?->account_id ?? 0),
                    0,
                    $amount,
                    $this->expectedEntryNote('customer_counterparty_account'),
                    $this->resolveCounterpartyName($context, 'customer')
                ),
            ],
            self::SCENARIO_ISSUED_CHEQUE_CASH => [
                $this->expectedEntry((int) ($context['supplier']?->account_id ?? 0), $amount, 0, $this->expectedEntryNote('supplier_counterparty_account')),
                $this->expectedEntry((int) ($context['bank']?->account_id ?? 0), 0, $amount, $this->expectedEntryNote('bank')),
            ],
            self::SCENARIO_EXPENSE_CASH => [
                $this->expectedEntry((int) ($context['expense_category']?->account_id ?? 0), $amount, 0, $this->expectedEntryNote('expense_account')),
                $this->expectedEntry((int) ($context['cash_box']?->account_id ?? 0), 0, $amount, $this->expectedEntryNote('cashbox')),
            ],
            self::SCENARIO_BANK_TRANSFER => $this->buildTransferExpectedEntries($payload, $context, 'wallet', 'bank'),
            self::SCENARIO_BANK_TRANSFER_CASHBOX => $this->buildTransferExpectedEntries($payload, $context, 'cashbox', 'bank'),
            self::SCENARIO_CREDIT_NOTE_ISSUE => [
                $this->expectedEntry((int) $this->resolveSalesAccountId(), $amount, 0, $this->expectedEntryNote('sales_return')),
                $this->expectedEntry(
                    (int) $this->resolveCustomerReceivableAccountId($context),
                    0,
                    $amount,
                    $this->expectedEntryNote('accounts_receivable_reduction'),
                    $this->resolveCounterpartyName($context, 'customer')
                ),
            ],
            self::SCENARIO_CREDIT_NOTE_APPLY => [
                $this->expectedEntry(
                    (int) $this->resolveCustomerReceivableAccountId($context),
                    0,
                    0,
                    $this->expectedEntryNote('accounts_receivable_reduction'),
                    $this->resolveCounterpartyName($context, 'customer')
                ),
            ],
            self::SCENARIO_DEBIT_NOTE_ISSUE => [
                $this->expectedEntry((int) $this->resolveSupplierPayableAccountId($context), $amount, 0, $this->expectedEntryNote('accounts_payable_reduction')),
                $this->expectedEntry((int) $this->resolveSupplierCostAccountId($context), 0, $amount, $this->expectedEntryNote('purchase_return')),
            ],
            self::SCENARIO_DEBIT_NOTE_APPLY => [
                $this->expectedEntry(
                    (int) $this->resolveSupplierPayableAccountId($context),
                    0,
                    0,
                    $this->expectedEntryNote('accounts_payable_reduction'),
                    $this->resolveCounterpartyName($context, 'supplier')
                ),
            ],
            self::SCENARIO_CUSTOMER_REFUND_CASH => [
                $this->expectedEntry(
                    (int) $this->resolveCustomerReceivableAccountId($context),
                    $amount,
                    0,
                    $this->expectedEntryNote('accounts_receivable'),
                    $this->resolveCounterpartyName($context, 'customer')
                ),
                $this->expectedEntry((int) ($context['cash_box']?->account_id ?? 0), 0, $amount, $this->expectedEntryNote('cashbox')),
            ],
            self::SCENARIO_SUPPLIER_REFUND_CASH => [
                $this->expectedEntry((int) ($context['cash_box']?->account_id ?? 0), $amount, 0, $this->expectedEntryNote('cashbox')),
                $this->expectedEntry((int) $this->resolveSupplierPayableAccountId($context), 0, $amount, $this->expectedEntryNote('accounts_payable')),
            ],
            self::SCENARIO_CUSTOMER_ADVANCE_APPLY => [
                $this->expectedEntry(
                    (int) $this->resolveCustomerReceivableAccountId($context),
                    $amount,
                    0,
                    $this->expectedEntryNote('accounts_receivable'),
                    $this->resolveCounterpartyName($context, 'customer')
                ),
                $this->expectedEntry(
                    (int) $this->resolveCustomerRevenueAccountId($context),
                    0,
                    $amount,
                    $this->expectedEntryNote('sales_revenue')
                ),
                $this->expectedEntry(
                    (int) $this->resolveCustomerAdvanceSourceAccountId($payload, $context),
                    $amount,
                    0,
                    $this->expectedEntryNote($this->resolveCustomerAdvanceSourceNoteKey($payload))
                ),
                $this->expectedEntry(
                    (int) $this->resolveCustomerAdvanceAccountId($context),
                    0,
                    $amount,
                    $this->expectedEntryNote('customer_advance_liability'),
                    $this->resolveCounterpartyName($context, 'customer')
                ),
            ],
            self::SCENARIO_SUPPLIER_ADVANCE_APPLY => [
                $this->expectedEntry(
                    (int) $this->resolveSupplierCostAccountId($context),
                    $amount,
                    0,
                    $this->expectedEntryNote('inventory_cost')
                ),
                $this->expectedEntry(
                    (int) $this->resolveSupplierPayableAccountId($context),
                    0,
                    $amount,
                    $this->expectedEntryNote('accounts_payable'),
                    $this->resolveCounterpartyName($context, 'supplier')
                ),
                $this->expectedEntry(
                    (int) $this->resolveSupplierAdvanceAccountId($context),
                    $amount,
                    0,
                    $this->expectedEntryNote('supplier_advance_asset'),
                    $this->resolveCounterpartyName($context, 'supplier')
                ),
                $this->expectedEntry(
                    (int) $this->resolveSupplierAdvanceSourceAccountId($payload, $context),
                    0,
                    $amount,
                    $this->expectedEntryNote($this->resolveSupplierAdvanceSourceNoteKey($payload))
                ),
            ],
            self::SCENARIO_INVENTORY_ADJUSTMENT_POST => [
                $this->expectedEntry((int) $this->resolveInventoryOrAssetAccountId(), $amount, 0, $this->expectedEntryNote('inventory_cost')),
                $this->expectedEntry((int) $this->resolveAnyIncomeAccountId(), 0, $amount, $this->expectedEntryNote('inventory_adjustment_gain')),
            ],
            self::SCENARIO_ACCRUAL_POST_REVERSE => [
                $this->expectedEntry((int) $this->resolveAccountsPayableId(), 0, 0, $this->expectedEntryNote('accounts_payable')),
            ],
            self::SCENARIO_BAD_DEBT_WRITEOFF => [
                $this->expectedEntry((int) $this->resolveAllowanceForDoubtfulAccountId(), $amount, 0, $this->expectedEntryNote('allowance_doubtful_accounts')),
                $this->expectedEntry((int) $this->resolveCustomerReceivableAccountId($context), 0, $amount, $this->expectedEntryNote('accounts_receivable_reduction')),
            ],
            self::SCENARIO_FIXED_ASSET_PURCHASE => [
                $this->expectedEntry((int) ($context['fixed_asset_category']?->asset_account_id ?? 0), $amount, 0, $this->expectedEntryNote('fixed_asset_account')),
                $this->expectedEntry((int) ($context['cash_box']?->account_id ?? 0), 0, $amount, $this->expectedEntryNote('cashbox')),
            ],
            self::SCENARIO_FIXED_ASSET_DEPRECIATION => [
                $this->expectedEntry((int) ($context['fixed_asset_category']?->depreciation_account_id ?? 0), $amount, 0, $this->expectedEntryNote('depreciation_expense')),
                $this->expectedEntry((int) ($context['fixed_asset_category']?->accumulated_depreciation_account_id ?? 0), 0, $amount, $this->expectedEntryNote('accumulated_depreciation')),
            ],
            self::SCENARIO_FIXED_ASSET_DISPOSAL => [
                $this->expectedEntry((int) ($context['cash_box']?->account_id ?? 0), $amount, 0, $this->expectedEntryNote('cashbox')),
                $this->expectedEntry((int) ($context['fixed_asset_category']?->asset_account_id ?? 0), 0, $amount, $this->expectedEntryNote('fixed_asset_account')),
            ],
            self::SCENARIO_SHAREHOLDER_CAPITAL => [
                $this->expectedEntry((int) ($context['bank']?->account_id ?? 0), $amount, 0, $this->expectedEntryNote('bank')),
                $this->expectedEntry((int) ($context['shareholder']?->capital_account_id ?? 0), 0, $amount, $this->expectedEntryNote('shareholder_capital')),
            ],
            self::SCENARIO_SHAREHOLDER_WITHDRAWAL => [
                $this->expectedEntry((int) ($context['shareholder']?->drawings_account_id ?? 0), $amount, 0, $this->expectedEntryNote('shareholder_drawings')),
                $this->expectedEntry((int) ($context['cash_box']?->account_id ?? 0), 0, $amount, $this->expectedEntryNote('cashbox')),
            ],
            self::SCENARIO_PAYROLL_ACCRUAL_BASIC => [
                $this->expectedEntry((int) ($context['employee']?->payroll_expense_account_id ?? 0), $amount, 0, $this->expectedEntryNote('expense_account')),
            ],
            self::SCENARIO_PAYROLL_INSURANCE_REMITTANCE => [
                $this->expectedEntry((int) ($context['employee']?->payroll_expense_account_id ?? 0), $amount, 0, $this->expectedEntryNote('expense_account')),
            ],
            self::SCENARIO_PAYROLL_LOAN_SETTLEMENT => [
                $this->expectedEntry((int) ($context['employee']?->payroll_expense_account_id ?? 0), $amount, 0, $this->expectedEntryNote('expense_account')),
            ],
            self::SCENARIO_VAT_DECLARATION_SUBMIT => [
                $this->expectedEntry((int) $this->resolveAnyIncomeAccountId(), 0, 0, $this->expectedEntryNote('income_account')),
            ],
            self::SCENARIO_ISSUED_CHEQUE_BOUNCE => [
                $this->expectedEntry((int) ($context['bank']?->account_id ?? 0), 0, 0, $this->expectedEntryNote('bank')),
            ],
            self::SCENARIO_VAT_REMITTANCE => [
                $this->expectedEntry((int) (AccountingVatAccounts::resolvePayableAccountId() ?? 0), $amount, 0, $this->expectedEntryNote('vat_payable')),
                $this->expectedEntry((int) ($context['bank']?->account_id ?? 0), 0, $amount, $this->expectedEntryNote('bank')),
            ],
            self::SCENARIO_MANUAL_JOURNAL => [
                $this->expectedEntry((int) ($context['cash_box']?->account_id ?? 0), $amount, 0, $this->expectedEntryNote('cashbox')),
                $this->expectedEntry((int) $this->resolveAnyIncomeAccountId(), 0, $amount, $this->expectedEntryNote('income_account')),
            ],
            default => [],
        };
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executeScenario(string $scenarioKey, array $payload, array $context): array
    {
        return match ($scenarioKey) {
            self::SCENARIO_SALES_INVOICE => $this->executeSalesInvoice($payload, $context),
            self::SCENARIO_PURCHASE_INVOICE => $this->executePurchaseInvoice($payload, $context),
            self::SCENARIO_CUSTOMER_RECEIPT_CASH => $this->executeCustomerReceiptCash($payload, $context),
            self::SCENARIO_CUSTOMER_RECEIPT_WALLET => $this->executeCustomerReceiptWallet($payload, $context),
            self::SCENARIO_SUPPLIER_PAYMENT_CASH => $this->executeSupplierPaymentCash($payload, $context),
            self::SCENARIO_SUPPLIER_PAYMENT_WALLET => $this->executeSupplierPaymentWallet($payload, $context),
            self::SCENARIO_CUSTOMER_ADVANCE_CASH => $this->executeCustomerAdvanceCash($payload, $context),
            self::SCENARIO_SUPPLIER_ADVANCE_CASH => $this->executeSupplierAdvanceCash($payload, $context),
            self::SCENARIO_RECEIVED_CHEQUE_CASH => $this->executeReceivedChequeCash($payload, $context),
            self::SCENARIO_ISSUED_CHEQUE_CASH => $this->executeIssuedChequeCash($payload, $context),
            self::SCENARIO_EXPENSE_CASH => $this->executeExpenseCash($payload, $context),
            self::SCENARIO_BANK_TRANSFER => $this->executeBankTransferTreasury($payload, $context),
            self::SCENARIO_BANK_TRANSFER_CASHBOX => $this->executeBankTransferCashboxToBank($payload, $context),
            self::SCENARIO_CREDIT_NOTE_ISSUE => $this->executeCreditNoteIssue($payload, $context),
            self::SCENARIO_CREDIT_NOTE_APPLY => $this->executeCreditNoteApply($payload, $context),
            self::SCENARIO_DEBIT_NOTE_ISSUE => $this->executeDebitNoteIssue($payload, $context),
            self::SCENARIO_DEBIT_NOTE_APPLY => $this->executeDebitNoteApply($payload, $context),
            self::SCENARIO_CUSTOMER_REFUND_CASH => $this->executeCustomerRefundCash($payload, $context),
            self::SCENARIO_SUPPLIER_REFUND_CASH => $this->executeSupplierRefundCash($payload, $context),
            self::SCENARIO_CUSTOMER_ADVANCE_APPLY => $this->executeCustomerAdvanceApply($payload, $context),
            self::SCENARIO_SUPPLIER_ADVANCE_APPLY => $this->executeSupplierAdvanceApply($payload, $context),
            self::SCENARIO_INVENTORY_ADJUSTMENT_POST => $this->executeInventoryAdjustmentPost($payload, $context),
            self::SCENARIO_ACCRUAL_POST_REVERSE => $this->executeAccrualPostReverse($payload, $context),
            self::SCENARIO_BAD_DEBT_WRITEOFF => $this->executeBadDebtWriteoff($payload, $context),
            self::SCENARIO_FIXED_ASSET_PURCHASE => $this->executeFixedAssetPurchase($payload, $context),
            self::SCENARIO_FIXED_ASSET_DEPRECIATION => $this->executeFixedAssetDepreciation($payload, $context),
            self::SCENARIO_FIXED_ASSET_DISPOSAL => $this->executeFixedAssetDisposal($payload, $context),
            self::SCENARIO_SHAREHOLDER_CAPITAL => $this->executeShareholderCapital($payload, $context),
            self::SCENARIO_SHAREHOLDER_WITHDRAWAL => $this->executeShareholderWithdrawal($payload, $context),
            self::SCENARIO_PAYROLL_ACCRUAL_BASIC => $this->executePayrollAccrualBasic($payload, $context),
            self::SCENARIO_PAYROLL_INSURANCE_REMITTANCE => $this->executePayrollInsuranceRemittance($payload, $context),
            self::SCENARIO_PAYROLL_LOAN_SETTLEMENT => $this->executePayrollLoanSettlement($payload, $context),
            self::SCENARIO_VAT_DECLARATION_SUBMIT => $this->executeVatDeclarationSubmit($payload, $context),
            self::SCENARIO_ISSUED_CHEQUE_BOUNCE => $this->executeIssuedChequeBounce($payload, $context),
            self::SCENARIO_VAT_REMITTANCE => $this->executeVatRemittance($payload, $context),
            self::SCENARIO_MANUAL_JOURNAL => $this->executeManualJournal($payload, $context),
            default => throw new InvalidArgumentException('Unsupported scenario'),
        };
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executeSalesInvoice(array $payload, array $context): array
    {
        /** @var Customer $customer */
        $customer = $context['customer'];
        $amount = round((float) $payload['amount'], 4);
        $vatCalc = $this->resolveScenarioVatAmounts($amount);
        $taxRate = $vatCalc['tax_rate'];
        $taxMethod = $vatCalc['tax_method'];
        $taxAmount = $vatCalc['tax_amount'];
        $baseAmount = $vatCalc['base_amount'];
        $totalAmount = $vatCalc['total_amount'];

        $invoice = $this->customerInvoiceService->createInvoice([
            'invoice_number' => 'SCN-CINV-'.now()->format('YmdHis').'-'.random_int(100, 999),
            'customer_id' => (int) $customer->id,
            'store_id' => 0,
            'invoice_date' => $payload['scenario_date'],
            'due_date' => Carbon::parse($payload['scenario_date'])->addDays(7)->toDateString(),
            'subtotal' => $baseAmount,
            'tax_amount' => $taxAmount,
            'discount_amount' => 0,
            'total_amount' => $totalAmount,
            'currency_code' => $this->scenarioCurrencyCode(),
            'fx_rate' => 1,
            'amount_base' => $totalAmount,
            'payment_status' => CustomerInvoice::STATUS_UNPAID,
            'paid_amount' => 0,
            'balance_due' => $totalAmount,
            'settlement_mode' => CustomerInvoice::SETTLEMENT_CREDIT,
            'status' => CustomerInvoice::STATUS_DRAFT,
            'tax_method' => $taxMethod,
            'notes' => trim('SCENARIO-RUNNER|'.($payload['notes'] ?? '')),
        ]);

        $this->customerInvoiceItemAdminService->createLine($invoice, [
            'product_name' => 'Scenario Sales Line',
            'quantity' => 1,
            'price' => $amount,
            'tax_rate' => $taxRate,
            'discount_amount' => 0,
        ]);
        $posted = $this->customerInvoiceService->postSalesAccountingDocument($invoice->fresh());

        return [
            'entity_type' => 'customer_invoice',
            'entity_id' => (int) $posted->id,
            'document_ids' => array_values(array_filter([(int) ($posted->document_id ?? 0)])),
            'notes' => 'Customer invoice posted from scenario runner',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executePurchaseInvoice(array $payload, array $context): array
    {
        /** @var Supplier $supplier */
        $supplier = $context['supplier'];
        $amount = round((float) $payload['amount'], 4);
        $vatCalc = $this->resolveScenarioVatAmounts($amount);
        $taxRate = $vatCalc['tax_rate'];
        $taxMethod = $vatCalc['tax_method'];
        $tax = $vatCalc['tax_amount'];
        $total = $vatCalc['total_amount'];
        $baseAmount = $vatCalc['base_amount'];

        $invoice = $this->supplierInvoiceService->createInvoice([
            'invoice_number' => 'SCN-SINV-'.now()->format('YmdHis').'-'.random_int(100, 999),
            'supplier_id' => (int) $supplier->id,
            'store_id' => 0,
            'invoice_date' => $payload['scenario_date'],
            'due_date' => Carbon::parse($payload['scenario_date'])->addDays(10)->toDateString(),
            'subtotal' => $baseAmount,
            'tax_amount' => $tax,
            'discount_amount' => 0,
            'total_amount' => $total,
            'currency_code' => $this->scenarioCurrencyCode(),
            'fx_rate_at_invoice' => 1,
            'amount_base_at_invoice' => $total,
            'tax_method' => $taxMethod,
            'payment_status' => SupplierInvoice::STATUS_UNPAID,
            'paid_amount' => 0,
            'balance_due' => $total,
            'settlement_mode' => SupplierInvoice::SETTLEMENT_ON_ACCOUNT,
            'notes' => trim('SCENARIO-RUNNER|'.($payload['notes'] ?? '')),
        ], [
            [
                'product_name' => 'Scenario Purchase Line',
                'quantity' => 1,
                'unit_price' => $amount,
                'tax_rate' => $taxRate,
                'discount_amount' => 0,
                'total_price' => $total,
                'tax_amount' => $tax,
            ],
        ]);

        return [
            'entity_type' => 'supplier_invoice',
            'entity_id' => (int) $invoice->id,
            'document_ids' => array_values(array_filter([(int) ($invoice->document_id ?? 0)])),
            'notes' => 'Supplier invoice posted from scenario runner',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executeCustomerReceiptCash(array $payload, array $context): array
    {
        /** @var Customer $customer */
        $customer = $context['customer'];
        /** @var CashBox $cashBox */
        $cashBox = $context['cash_box'];
        $payment = $this->customerPaymentService->createPayment([
            'payment_number' => 'SCN-CRP-'.now()->format('YmdHis').'-'.random_int(100, 999),
            'customer_id' => (int) $customer->id,
            'store_id' => 0,
            'payment_method_id' => (int) $context['cash_method_id'],
            'amount' => round((float) $payload['amount'], 4),
            'currency_code' => $this->scenarioCurrencyCode(),
            'payment_date' => $payload['scenario_date'],
            'status' => CustomerPayment::STATUS_COMPLETED,
            'cash_box_id' => (int) $cashBox->id,
            'notes' => trim('SCENARIO-RUNNER|'.($payload['notes'] ?? '')),
        ]);

        return [
            'entity_type' => 'customer_payment',
            'entity_id' => (int) $payment->id,
            'document_ids' => array_values(array_filter([(int) ($payment->document_id ?? 0)])),
            'notes' => 'Customer cash receipt posted from scenario runner',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executeSupplierPaymentCash(array $payload, array $context): array
    {
        /** @var Supplier $supplier */
        $supplier = $context['supplier'];
        /** @var CashBox $cashBox */
        $cashBox = $context['cash_box'];
        $amount = round((float) $payload['amount'], 4);

        $payment = $this->supplierPaymentService->recordPayment([
            'payment_number' => 'SCN-SPP-'.now()->format('YmdHis').'-'.random_int(100, 999),
            'supplier_id' => (int) $supplier->id,
            'payment_method_id' => (int) $context['cash_method_id'],
            'amount' => $amount,
            'currency_code' => $this->scenarioCurrencyCode(),
            'payment_date' => $payload['scenario_date'],
            'cash_box_id' => (int) $cashBox->id,
            'status' => SupplierPayment::STATUS_COMPLETED,
            'notes' => trim('SCENARIO-RUNNER|'.($payload['notes'] ?? '')),
            'store_id' => 0,
        ]);

        return [
            'entity_type' => 'supplier_payment',
            'entity_id' => (int) $payment->id,
            'document_ids' => array_values(array_filter([(int) ($payment->document_id ?? 0)])),
            'notes' => 'Supplier cash payment posted from scenario runner',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executeCustomerReceiptWallet(array $payload, array $context): array
    {
        /** @var Customer $customer */
        $customer = $context['customer'];
        /** @var Wallet $wallet */
        $wallet = $context['wallet'];
        $payment = $this->customerPaymentService->createPayment([
            'payment_number' => 'SCN-CWR-'.now()->format('YmdHis').'-'.random_int(100, 999),
            'customer_id' => (int) $customer->id,
            'store_id' => 0,
            'payment_method_id' => (int) $context['cash_method_id'],
            'amount' => round((float) $payload['amount'], 4),
            'currency_code' => $this->scenarioCurrencyCode(),
            'payment_date' => $payload['scenario_date'],
            'status' => CustomerPayment::STATUS_COMPLETED,
            'wallet_id' => (int) $wallet->id,
            'notes' => trim('SCENARIO-RUNNER|'.($payload['notes'] ?? '')),
        ]);

        return [
            'entity_type' => 'customer_payment',
            'entity_id' => (int) $payment->id,
            'document_ids' => array_values(array_filter([(int) ($payment->document_id ?? 0)])),
            'notes' => 'Customer wallet receipt posted from scenario runner',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executeSupplierPaymentWallet(array $payload, array $context): array
    {
        /** @var Supplier $supplier */
        $supplier = $context['supplier'];
        /** @var Wallet $wallet */
        $wallet = $context['wallet'];
        $amount = round((float) $payload['amount'], 4);

        $payment = $this->supplierPaymentService->recordPayment([
            'payment_number' => 'SCN-SPW-'.now()->format('YmdHis').'-'.random_int(100, 999),
            'supplier_id' => (int) $supplier->id,
            'payment_method_id' => (int) $context['cash_method_id'],
            'amount' => $amount,
            'currency_code' => $this->scenarioCurrencyCode(),
            'payment_date' => $payload['scenario_date'],
            'wallet_id' => (int) $wallet->id,
            'status' => SupplierPayment::STATUS_COMPLETED,
            'notes' => trim('SCENARIO-RUNNER|'.($payload['notes'] ?? '')),
            'store_id' => 0,
        ]);

        return [
            'entity_type' => 'supplier_payment',
            'entity_id' => (int) $payment->id,
            'document_ids' => array_values(array_filter([(int) ($payment->document_id ?? 0)])),
            'notes' => 'Supplier wallet payment posted from scenario runner',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executeCustomerAdvanceCash(array $payload, array $context): array
    {
        /** @var Customer $customer */
        $customer = $context['customer'];
        /** @var CashBox $cashBox */
        $cashBox = $context['cash_box'];
        $amount = round((float) $payload['amount'], 4);

        $advance = $this->advancePaymentService->receiveCustomerAdvance([
            'advance_number' => 'SCN-CADV-'.now()->format('YmdHis').'-'.random_int(100, 999),
            'customer_id' => (int) $customer->id,
            'store_id' => 0,
            'advance_date' => $payload['scenario_date'],
            'amount' => $amount,
            'currency_code' => $this->scenarioCurrencyCode(),
            'fx_rate' => 1,
            'payment_method' => 'cash',
            'cash_box_id' => (int) $cashBox->id,
            'notes' => trim('SCENARIO-RUNNER|'.($payload['notes'] ?? '')),
        ]);

        return [
            'entity_type' => 'customer_advance',
            'entity_id' => (int) $advance->id,
            'document_ids' => array_values(array_filter([(int) ($advance->accounting_document_id ?? 0)])),
            'notes' => 'Customer advance receipt posted from scenario runner',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executeSupplierAdvanceCash(array $payload, array $context): array
    {
        /** @var Supplier $supplier */
        $supplier = $context['supplier'];
        /** @var CashBox $cashBox */
        $cashBox = $context['cash_box'];
        $amount = round((float) $payload['amount'], 4);

        if ((float) $cashBox->balance < $amount) {
            $cashBox->increment('balance', $amount + 10000);
            $cashBox->refresh();
        }

        $advance = $this->advancePaymentService->paySupplierAdvance([
            'advance_number' => 'SCN-SADV-'.now()->format('YmdHis').'-'.random_int(100, 999),
            'supplier_id' => (int) $supplier->id,
            'store_id' => 0,
            'advance_date' => $payload['scenario_date'],
            'amount' => $amount,
            'currency_code' => $this->scenarioCurrencyCode(),
            'fx_rate' => 1,
            'payment_method' => 'cash',
            'cash_box_id' => (int) $cashBox->id,
            'notes' => trim('SCENARIO-RUNNER|'.($payload['notes'] ?? '')),
        ]);

        return [
            'entity_type' => 'supplier_advance',
            'entity_id' => (int) $advance->id,
            'document_ids' => array_values(array_filter([(int) ($advance->accounting_document_id ?? 0)])),
            'notes' => 'Supplier advance payment posted from scenario runner',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executeCreditNoteIssue(array $payload, array $context): array
    {
        /** @var Customer $customer */
        $customer = $context['customer'];
        $amount = round((float) $payload['amount'], 4);
        $this->ensureValidAccountSetting('accounting.sales_account_id', $this->resolveAnyIncomeAccountId());
        $this->ensureValidAccountSetting('accounting.ar_account_id', $this->resolveAccountsReceivableId());

        $creditNote = $this->creditNoteService->createCreditNote([
            'credit_note_number' => 'SCN-CN-'.now()->format('YmdHis').'-'.random_int(100, 999),
            'customer_id' => (int) $customer->id,
            'store_id' => 0,
            'credit_date' => $payload['scenario_date'],
            'currency_code' => $this->scenarioCurrencyCode(),
            'fx_rate' => 1,
            'subtotal' => $amount,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => $amount,
            'amount_base' => $amount,
            'notes' => trim('SCENARIO-RUNNER|'.($payload['notes'] ?? '')),
            'items' => [
                [
                    'product_name' => 'آیتم نمونه اعتبار برگشتی فروش',
                    'quantity' => 1,
                    'price' => $amount,
                    'tax_rate' => 0,
                    'discount_amount' => 0,
                ],
            ],
        ]);
        $creditNote = $this->creditNoteService->issueCreditNote($creditNote->fresh());

        return [
            'entity_type' => 'credit_note',
            'entity_id' => (int) $creditNote->id,
            'document_ids' => array_values(array_filter([(int) ($creditNote->accounting_document_id ?? 0)])),
            'notes' => 'اعتبار برگشتی فروش از سناریو‌رانر صادر شد',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executeDebitNoteIssue(array $payload, array $context): array
    {
        /** @var Supplier $supplier */
        $supplier = $context['supplier'];
        $amount = round((float) $payload['amount'], 4);
        $this->ensureValidAccountSetting('accounting.ap_account_id', $this->resolveAccountsPayableId());
        $this->ensureValidAccountSetting('accounting.purchase_account_id', $this->resolveInventoryOrAssetAccountId());

        $debitNote = $this->debitNoteService->createDebitNote([
            'debit_note_number' => 'SCN-DN-'.now()->format('YmdHis').'-'.random_int(100, 999),
            'supplier_id' => (int) $supplier->id,
            'store_id' => 0,
            'debit_date' => $payload['scenario_date'],
            'currency_code' => $this->scenarioCurrencyCode(),
            'fx_rate' => 1,
            'notes' => trim('SCENARIO-RUNNER|'.($payload['notes'] ?? '')),
            'items' => [
                [
                    'product_name' => 'آیتم نمونه یادداشت بدهکار خرید',
                    'quantity' => 1,
                    'price' => $amount,
                    'tax_rate' => 0,
                    'discount_amount' => 0,
                ],
            ],
        ]);
        $debitNote = $this->debitNoteService->issueDebitNote($debitNote->fresh());

        return [
            'entity_type' => 'debit_note',
            'entity_id' => (int) $debitNote->id,
            'document_ids' => array_values(array_filter([(int) ($debitNote->accounting_document_id ?? 0)])),
            'notes' => 'یادداشت بدهکار خرید از سناریو‌رانر صادر شد',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executeCreditNoteApply(array $payload, array $context): array
    {
        /** @var Customer $customer */
        $customer = $context['customer'];
        $amount = round((float) $payload['amount'], 4);
        $invoice = $this->createScenarioSalesInvoiceWithoutVat($customer, $payload, $amount);

        $creditAmount = max(1.0, min($amount, round((float) ($invoice->balance_due ?? $amount), 4)));
        $creditNote = $this->creditNoteService->createCreditNote([
            'credit_note_number' => 'SCN-CNA-'.now()->format('YmdHis').'-'.random_int(100, 999),
            'customer_id' => (int) $customer->id,
            'customer_invoice_id' => (int) $invoice->id,
            'store_id' => 0,
            'credit_date' => $payload['scenario_date'],
            'currency_code' => $this->scenarioCurrencyCode(),
            'fx_rate' => 1,
            'subtotal' => $creditAmount,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => $creditAmount,
            'amount_base' => $creditAmount,
            'notes' => trim('SCENARIO-RUNNER|'.($payload['notes'] ?? '')),
            'items' => [[
                'product_name' => 'آیتم نمونه اعمال اعتبار فروش',
                'quantity' => 1,
                'price' => $creditAmount,
                'tax_rate' => 0,
                'discount_amount' => 0,
            ]],
        ]);
        $issued = $this->creditNoteService->issueCreditNote($creditNote->fresh());
        $applied = $this->creditNoteService->applyToInvoice($issued->fresh(), (int) $invoice->id);
        $invoice->refresh();

        return [
            'entity_type' => 'credit_note',
            'entity_id' => (int) $applied->id,
            'document_ids' => array_values(array_filter([(int) ($applied->accounting_document_id ?? 0)])),
            'target_invoice_id' => (int) $invoice->id,
            'target_invoice_balance' => (float) ($invoice->balance_due ?? 0),
            'notes' => 'اعتبار برگشتی فروش صادر و روی فاکتور اعمال شد',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executeDebitNoteApply(array $payload, array $context): array
    {
        /** @var Supplier $supplier */
        $supplier = $context['supplier'];
        $amount = round((float) $payload['amount'], 4);
        $invoice = $this->createScenarioSupplierInvoiceWithoutVat($supplier, $payload, $amount);

        $debitAmount = max(1.0, min($amount, round((float) ($invoice->balance_due ?? $amount), 4)));
        $debitNote = $this->debitNoteService->createDebitNote([
            'debit_note_number' => 'SCN-DNA-'.now()->format('YmdHis').'-'.random_int(100, 999),
            'supplier_id' => (int) $supplier->id,
            'supplier_invoice_id' => (int) $invoice->id,
            'store_id' => 0,
            'debit_date' => $payload['scenario_date'],
            'currency_code' => $this->scenarioCurrencyCode(),
            'fx_rate' => 1,
            'notes' => trim('SCENARIO-RUNNER|'.($payload['notes'] ?? '')),
            'items' => [[
                'product_name' => 'آیتم نمونه اعمال یادداشت بدهکار',
                'quantity' => 1,
                'price' => $debitAmount,
                'tax_rate' => 0,
                'discount_amount' => 0,
            ]],
        ]);
        $issued = $this->debitNoteService->issueDebitNote($debitNote->fresh());
        $applied = $this->debitNoteService->applyToInvoice($issued->fresh(), (int) $invoice->id);
        $invoice->refresh();

        return [
            'entity_type' => 'debit_note',
            'entity_id' => (int) $applied->id,
            'document_ids' => array_values(array_filter([(int) ($applied->accounting_document_id ?? 0)])),
            'target_invoice_id' => (int) $invoice->id,
            'target_invoice_balance' => (float) ($invoice->balance_due ?? 0),
            'notes' => 'یادداشت بدهکار خرید صادر و روی فاکتور اعمال شد',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executeCustomerAdvanceApply(array $payload, array $context): array
    {
        /** @var Customer $customer */
        $customer = $context['customer'];
        $amount = round((float) $payload['amount'], 4);
        $bankId = (int) ($payload['bank_id'] ?? 0);
        $cashBoxId = (int) ($payload['cash_box_id'] ?? 0);

        $invoice = $this->createScenarioSalesInvoiceWithoutVat($customer, $payload, $amount);
        $advance = $this->advancePaymentService->receiveCustomerAdvance([
            'advance_number' => 'SCN-CAA-'.now()->format('YmdHis').'-'.random_int(100, 999),
            'customer_id' => (int) $customer->id,
            'store_id' => 0,
            'advance_date' => $payload['scenario_date'],
            'amount' => $amount,
            'currency_code' => $this->scenarioCurrencyCode(),
            'fx_rate' => 1,
            'payment_method' => $this->resolveCustomerAdvancePaymentMethod($context),
            'bank_id' => $bankId > 0 ? $bankId : null,
            'cash_box_id' => $cashBoxId > 0 ? $cashBoxId : null,
            'notes' => trim('SCENARIO-RUNNER|'.($payload['notes'] ?? '')),
        ]);

        $applyAmount = min((float) ($advance->remaining_amount ?? 0), (float) ($invoice->balance_due ?? 0));
        $this->advancePaymentService->applyCustomerAdvanceToInvoice((int) $advance->id, (int) $invoice->id, $applyAmount);
        $advance->refresh();
        $invoice->refresh();

        return [
            'entity_type' => 'customer_advance',
            'entity_id' => (int) $advance->id,
            'document_ids' => array_values(array_filter([(int) ($advance->accounting_document_id ?? 0)])),
            'target_invoice_id' => (int) $invoice->id,
            'target_invoice_balance' => (float) ($invoice->balance_due ?? 0),
            'notes' => 'پیش‌دریافت مشتری ایجاد و روی فاکتور اعمال شد',
        ];
    }

    /**
     * @param array<string,mixed> $context
     */
    private function resolveCustomerAdvancePaymentMethod(array $context): string
    {
        /** @var PaymentMethod|null $method */
        $method = $context['selected_payment_method'] ?? null;
        $type = strtolower(trim((string) ($method?->type ?? '')));

        return in_array($type, [PaymentMethod::TYPE_CASH, PaymentMethod::TYPE_CHEQUE, PaymentMethod::TYPE_ONLINE, PaymentMethod::TYPE_POS, PaymentMethod::TYPE_CARD_TRANSFER, PaymentMethod::TYPE_BANK_TRANSFER], true)
            ? $type
            : PaymentMethod::TYPE_CASH;
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executeSupplierAdvanceApply(array $payload, array $context): array
    {
        /** @var Supplier $supplier */
        $supplier = $context['supplier'];
        /** @var CashBox $cashBox */
        $cashBox = $context['cash_box'];
        $amount = round((float) $payload['amount'], 4);

        if ((float) $cashBox->balance < $amount) {
            $cashBox->increment('balance', $amount + 10000);
            $cashBox->refresh();
        }

        $invoice = $this->createScenarioSupplierInvoiceWithoutVat($supplier, $payload, $amount);
        $advance = $this->advancePaymentService->paySupplierAdvance([
            'advance_number' => 'SCN-SAA-'.now()->format('YmdHis').'-'.random_int(100, 999),
            'supplier_id' => (int) $supplier->id,
            'store_id' => 0,
            'advance_date' => $payload['scenario_date'],
            'amount' => $amount,
            'currency_code' => $this->scenarioCurrencyCode(),
            'fx_rate' => 1,
            'payment_method' => 'cash',
            'cash_box_id' => (int) $cashBox->id,
            'notes' => trim('SCENARIO-RUNNER|'.($payload['notes'] ?? '')),
        ]);

        $applyAmount = min((float) ($advance->remaining_amount ?? 0), (float) ($invoice->balance_due ?? 0));
        $this->advancePaymentService->applySupplierAdvanceToInvoice((int) $advance->id, (int) $invoice->id, $applyAmount);
        $advance->refresh();
        $invoice->refresh();

        return [
            'entity_type' => 'supplier_advance',
            'entity_id' => (int) $advance->id,
            'document_ids' => array_values(array_filter([(int) ($advance->accounting_document_id ?? 0)])),
            'target_invoice_id' => (int) $invoice->id,
            'target_invoice_balance' => (float) ($invoice->balance_due ?? 0),
            'notes' => 'پیش‌پرداخت تامین‌کننده ایجاد و روی فاکتور اعمال شد',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executeInventoryAdjustmentPost(array $payload, array $context): array
    {
        $amount = round((float) $payload['amount'], 4);
        $adjustment = $this->inventoryAdjustmentService->createAdjustment([
            'adjustment_number' => 'SCN-INV-'.now()->format('YmdHis').'-'.random_int(100, 999),
            'adjustment_date' => $payload['scenario_date'],
            'adjustment_type' => 'count',
            'reason' => 'Scenario inventory adjustment',
            'notes' => trim('SCENARIO-RUNNER|'.($payload['notes'] ?? '')),
            'items' => [[
                'product_name' => 'Scenario Inventory Item',
                'system_quantity' => 0,
                'actual_quantity' => 1,
                'unit_cost' => $amount,
                'reason' => 'Scenario count',
            ]],
        ]);
        $approved = $this->inventoryAdjustmentService->approveAdjustment((int) $adjustment->id);
        $posted = $this->inventoryAdjustmentService->postAdjustment((int) $approved->id, [
            'inventory_account_id' => (int) $this->resolveInventoryOrAssetAccountId(),
            'adjustment_gain_account_id' => (int) $this->resolveAnyIncomeAccountId(),
            'writedown_account_id' => (int) $this->resolveAnyExpenseAccountId(),
            'adjustment_loss_account_id' => (int) $this->resolveAnyExpenseAccountId(),
        ]);

        return [
            'entity_type' => 'inventory_adjustment',
            'entity_id' => (int) $posted->id,
            'document_ids' => array_values(array_filter([(int) ($posted->accounting_document_id ?? 0)])),
            'notes' => 'تعدیل موجودی ایجاد و در دفترکل ثبت شد',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executeAccrualPostReverse(array $payload, array $context): array
    {
        $amount = round((float) $payload['amount'], 4);
        $accrual = $this->accrualService->createAccrual([
            'accrual_number' => 'SCN-ACR-'.now()->format('YmdHis').'-'.random_int(100, 999),
            'accrual_type' => Accrual::TYPE_ACCRUED_EXPENSE,
            'accrual_date' => $payload['scenario_date'],
            'amount' => $amount,
            'account_id' => (int) $this->resolveAnyExpenseAccountId(),
            'description' => 'Scenario accrual post and reverse',
            'notes' => trim('SCENARIO-RUNNER|'.($payload['notes'] ?? '')),
        ]);
        $this->accrualService->reverseAccrual((int) $accrual->id);
        $accrual->refresh();

        return [
            'entity_type' => 'accrual',
            'entity_id' => (int) $accrual->id,
            'document_ids' => array_values(array_filter([
                (int) ($accrual->accounting_document_id ?? 0),
                (int) ($accrual->reversal_document_id ?? 0),
            ])),
            'notes' => 'تعهد ثبت و برگشت زده شد',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executeBadDebtWriteoff(array $payload, array $context): array
    {
        /** @var Customer $customer */
        $customer = $context['customer'];
        $amount = round((float) $payload['amount'], 4);
        $invoice = $this->createScenarioSalesInvoiceWithoutVat($customer, $payload, $amount);

        $this->ensureValidAccountSetting('accounting.bad_debt_expense_account_id', $this->resolveAnyExpenseAccountId());
        $this->ensureValidAccountSetting('accounting.allowance_doubtful_accounts_id', $this->resolveAllowanceForDoubtfulAccountId());
        $writeoff = $this->badDebtService->writeOffBadDebt([
            'writeoff_number' => 'SCN-BDW-'.now()->format('YmdHis').'-'.random_int(100, 999),
            'customer_id' => (int) $customer->id,
            'customer_invoice_id' => (int) $invoice->id,
            'writeoff_date' => $payload['scenario_date'],
            'writeoff_amount' => min($amount, (float) ($invoice->balance_due ?? $amount)),
            'reason' => 'Scenario bad debt writeoff',
            'notes' => trim('SCENARIO-RUNNER|'.($payload['notes'] ?? '')),
        ]);

        return [
            'entity_type' => 'bad_debt_writeoff',
            'entity_id' => (int) $writeoff->id,
            'document_ids' => array_values(array_filter([(int) ($writeoff->accounting_document_id ?? 0)])),
            'notes' => 'مطالبه مشکوک الوصول حذف شد',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executeFixedAssetDepreciation(array $payload, array $context): array
    {
        /** @var FixedAssetCategory $category */
        $category = $context['fixed_asset_category'];
        $amount = round((float) $payload['amount'], 4);

        $asset = $this->fixedAssetService->createAsset([
            'asset_code' => 'SCN-FAD-'.now()->format('YmdHis').'-'.random_int(100, 999),
            'name' => 'Scenario Fixed Asset Depreciation',
            'category_id' => (int) $category->id,
            'purchase_date' => Carbon::parse($payload['scenario_date'])->startOfMonth()->toDateString(),
            'purchase_price' => $amount,
            'useful_life_years' => 0,
            'useful_life_months' => 1,
            'depreciation_method' => 'straight_line',
            'salvage_value' => 0,
            'description' => trim('SCENARIO-RUNNER|'.($payload['notes'] ?? '')),
            'record_purchase' => false,
            'generate_schedule' => true,
        ]);

        $periodDate = Carbon::parse((string) $asset->purchase_date)->addMonth()->startOfMonth()->toDateString();
        $entry = $this->fixedAssetService->recordDepreciation((int) $asset->id, $periodDate);

        return [
            'entity_type' => 'depreciation_entry',
            'entity_id' => (int) $entry->id,
            'document_ids' => array_values(array_filter([(int) ($entry->accounting_document_id ?? 0)])),
            'asset_id' => (int) $asset->id,
            'notes' => 'استهلاک دارایی ثابت ثبت شد',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executeFixedAssetDisposal(array $payload, array $context): array
    {
        /** @var FixedAssetCategory $category */
        $category = $context['fixed_asset_category'];
        /** @var CashBox $cashBox */
        $cashBox = $context['cash_box'];
        $amount = round((float) $payload['amount'], 4);

        $asset = $this->fixedAssetService->createAsset([
            'asset_code' => 'SCN-FAX-'.now()->format('YmdHis').'-'.random_int(100, 999),
            'name' => 'Scenario Fixed Asset Disposal',
            'category_id' => (int) $category->id,
            'purchase_date' => $payload['scenario_date'],
            'purchase_price' => $amount,
            'useful_life_years' => 5,
            'useful_life_months' => 0,
            'depreciation_method' => 'straight_line',
            'salvage_value' => 0,
            'description' => trim('SCENARIO-RUNNER|'.($payload['notes'] ?? '')),
            'record_purchase' => false,
            'generate_schedule' => false,
        ]);

        $disposed = $this->fixedAssetService->disposeAsset((int) $asset->id, [
            'disposal_date' => $payload['scenario_date'],
            'disposal_value' => $amount,
            'cash_account_id' => (int) $cashBox->account_id,
            'gain_account_id' => (int) $this->resolveAnyIncomeAccountId(),
            'loss_account_id' => (int) $this->resolveAnyExpenseAccountId(),
        ]);

        $documentIds = DB::table('accounting_documents')
            ->where('reference_type', 'fixed_asset')
            ->where('reference_id', (int) $disposed->id)
            ->where('document_type', 'asset_disposal')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        return [
            'entity_type' => 'fixed_asset',
            'entity_id' => (int) $disposed->id,
            'document_ids' => $documentIds,
            'notes' => 'خروج دارایی ثابت ثبت شد',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executePayrollAccrualBasic(array $payload, array $context): array
    {
        $run = $this->createScenarioPayrollRun($payload, $context, false);
        $posted = $this->withAttendanceGateDisabled(fn () => $this->payrollJournalService->postAccrual((int) $run->id));
        $documentId = $this->manualJournalDocumentId((int) ($posted->accrual_manual_journal_id ?? 0));

        return [
            'entity_type' => 'payroll_run',
            'entity_id' => (int) $posted->id,
            'document_ids' => array_values(array_filter([$documentId])),
            'notes' => 'ثبت تعهد حقوق پایه انجام شد',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executePayrollInsuranceRemittance(array $payload, array $context): array
    {
        /** @var Bank $bank */
        $bank = $context['bank'];
        $run = $this->createScenarioPayrollRun($payload, $context, false);

        $postedAccrual = $this->withAttendanceGateDisabled(fn () => $this->payrollJournalService->postAccrual((int) $run->id));
        $postedInsurance = $this->withAttendanceGateDisabled(fn () => $this->payrollJournalService->postInsuranceRemittance((int) $postedAccrual->id, (int) $bank->id, $payload['scenario_date']));

        $documentIds = array_values(array_filter([
            $this->manualJournalDocumentId((int) ($postedInsurance->accrual_manual_journal_id ?? 0)),
            $this->manualJournalDocumentId((int) ($postedInsurance->insurance_remittance_manual_journal_id ?? 0)),
        ]));

        return [
            'entity_type' => 'payroll_run',
            'entity_id' => (int) $postedInsurance->id,
            'document_ids' => $documentIds,
            'notes' => 'ثبت تعهد حقوق و واریز بیمه انجام شد',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executePayrollLoanSettlement(array $payload, array $context): array
    {
        /** @var Bank $bank */
        $bank = $context['bank'];
        $run = $this->createScenarioPayrollRun($payload, $context, true, (int) $bank->id);

        $postedAccrual = $this->withAttendanceGateDisabled(fn () => $this->payrollJournalService->postAccrual((int) $run->id));
        $postedLoan = $this->withAttendanceGateDisabled(fn () => $this->payrollJournalService->postLoanSettlement((int) $postedAccrual->id));

        $documentIds = array_values(array_filter([
            $this->manualJournalDocumentId((int) ($postedLoan->accrual_manual_journal_id ?? 0)),
            $this->manualJournalDocumentId((int) ($postedLoan->loan_settlement_manual_journal_id ?? 0)),
        ]));

        return [
            'entity_type' => 'payroll_run',
            'entity_id' => (int) $postedLoan->id,
            'document_ids' => $documentIds,
            'notes' => 'ثبت تعهد حقوق و تسویه وام انجام شد',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executeVatDeclarationSubmit(array $payload, array $context): array
    {
        $start = Carbon::parse($payload['scenario_date'])->startOfMonth()->toDateString();
        $end = Carbon::parse($payload['scenario_date'])->endOfMonth()->toDateString();
        $draft = $this->vatDeclarationService->createDraft([
            'period_start' => $start,
            'period_end' => $end,
            'notes' => trim('SCENARIO-RUNNER|'.($payload['notes'] ?? '')),
        ]);
        $submitted = $this->vatDeclarationService->markSubmitted($draft->fresh());

        return [
            'entity_type' => 'vat_declaration',
            'entity_id' => (int) $submitted->id,
            'document_ids' => [],
            'notes' => 'اظهارنامه VAT ایجاد و ارسال شد',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executeIssuedChequeBounce(array $payload, array $context): array
    {
        /** @var Supplier $supplier */
        $supplier = $context['supplier'];
        /** @var Bank $bank */
        $bank = $context['bank'];
        /** @var Chequebook|null $chequebook */
        $chequebook = $context['chequebook'];
        $amount = round((float) $payload['amount'], 4);

        $cheque = $this->chequeAutoCreationService->ensureCheque([
            'context' => 'scenario_runner_issued_cheque_bounce',
            'source_short' => 'SCN',
            'payment_method_id' => (int) $context['cheque_method_id'],
            'cheque_type' => Cheque::TYPE_ISSUED,
            'party_id' => (int) ($supplier->party_id ?? 0),
            'amount' => $amount,
            'currency_code' => $this->scenarioCurrencyCode(),
            'bank_id' => (int) $bank->id,
            'chequebook_id' => $chequebook ? (int) $chequebook->id : null,
            'issue_date' => $payload['scenario_date'],
            'due_date' => Carbon::parse($payload['scenario_date'])->addDays(2)->toDateString(),
            'notes' => trim('SCENARIO-RUNNER|'.($payload['notes'] ?? '')),
        ]);
        if (! $cheque) {
            throw new RuntimeException('Could not create cheque.');
        }

        $this->chequeLedgerService->recordChequeCreated($cheque->fresh());
        $this->chequeLedgerService->recordChequeCashed($cheque->fresh());
        $this->chequeLedgerService->recordChequeBounced($cheque->fresh(), 'Scenario cheque bounced');
        $cheque->refresh();

        return [
            'entity_type' => 'cheque',
            'entity_id' => (int) $cheque->id,
            'document_ids' => array_values(array_filter([(int) ($cheque->accounting_document_id ?? 0)])),
            'notes' => 'چک پرداختی ایجاد، پاس و سپس برگشت شد',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executeCustomerRefundCash(array $payload, array $context): array
    {
        /** @var Customer $customer */
        $customer = $context['customer'];
        /** @var CashBox $cashBox */
        $cashBox = $context['cash_box'];
        $amount = round((float) $payload['amount'], 4);
        $this->ensureValidAccountSetting('accounting.ar_account_id', $this->resolveAccountsReceivableId());

        if ((float) $cashBox->balance < $amount) {
            $cashBox->increment('balance', $amount + 10000);
            $cashBox->refresh();
        }

        $refund = $this->refundService->processCustomerRefund([
            'refund_number' => 'SCN-CRF-'.now()->format('YmdHis').'-'.random_int(100, 999),
            'customer_id' => (int) $customer->id,
            'store_id' => 0,
            'refund_date' => $payload['scenario_date'],
            'amount' => $amount,
            'currency_code' => $this->scenarioCurrencyCode(),
            'fx_rate' => 1,
            'refund_method' => CustomerRefund::METHOD_CASH,
            'cash_box_id' => (int) $cashBox->id,
            'notes' => trim('SCENARIO-RUNNER|'.($payload['notes'] ?? '')),
        ]);

        return [
            'entity_type' => 'customer_refund',
            'entity_id' => (int) $refund->id,
            'document_ids' => array_values(array_filter([(int) ($refund->accounting_document_id ?? 0)])),
            'notes' => 'Customer refund posted from scenario runner',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executeSupplierRefundCash(array $payload, array $context): array
    {
        /** @var Supplier $supplier */
        $supplier = $context['supplier'];
        /** @var CashBox $cashBox */
        $cashBox = $context['cash_box'];
        $amount = round((float) $payload['amount'], 4);
        $this->ensureValidAccountSetting('accounting.ap_account_id', $this->resolveAccountsPayableId());

        $refund = $this->refundService->receiveSupplierRefund([
            'refund_number' => 'SCN-SRF-'.now()->format('YmdHis').'-'.random_int(100, 999),
            'supplier_id' => (int) $supplier->id,
            'store_id' => 0,
            'refund_date' => $payload['scenario_date'],
            'amount' => $amount,
            'currency_code' => $this->scenarioCurrencyCode(),
            'fx_rate' => 1,
            'refund_method' => SupplierRefund::METHOD_CASH,
            'cash_box_id' => (int) $cashBox->id,
            'notes' => trim('SCENARIO-RUNNER|'.($payload['notes'] ?? '')),
        ]);

        return [
            'entity_type' => 'supplier_refund',
            'entity_id' => (int) $refund->id,
            'document_ids' => array_values(array_filter([(int) ($refund->accounting_document_id ?? 0)])),
            'notes' => 'Supplier refund posted from scenario runner',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executeReceivedChequeCash(array $payload, array $context): array
    {
        /** @var Customer $customer */
        $customer = $context['customer'];
        /** @var Bank $bank */
        $bank = $context['bank'];
        $amount = round((float) $payload['amount'], 4);

        $cheque = $this->chequeAutoCreationService->ensureCheque([
            'context' => 'scenario_runner_received_cheque',
            'source_short' => 'SCN',
            'payment_method_id' => (int) $context['cheque_method_id'],
            'cheque_type' => Cheque::TYPE_RECEIVED,
            'party_id' => (int) ($customer->party_id ?? 0),
            'amount' => $amount,
            'currency_code' => $this->scenarioCurrencyCode(),
            'bank_id' => (int) $bank->id,
            'issue_date' => $payload['scenario_date'],
            'due_date' => Carbon::parse($payload['scenario_date'])->addDays(2)->toDateString(),
            'notes' => trim('SCENARIO-RUNNER|'.($payload['notes'] ?? '')),
        ]);
        if (! $cheque) {
            throw new RuntimeException('Could not create cheque.');
        }

        $this->chequeLedgerService->recordChequeCreated($cheque->fresh());
        $this->chequeLedgerService->recordChequeCashed($cheque->fresh());
        $cheque->refresh();

        return [
            'entity_type' => 'cheque',
            'entity_id' => (int) $cheque->id,
            'document_ids' => array_values(array_filter([(int) ($cheque->accounting_document_id ?? 0)])),
            'notes' => 'Received cheque created and cashed from scenario runner',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executeIssuedChequeCash(array $payload, array $context): array
    {
        /** @var Supplier $supplier */
        $supplier = $context['supplier'];
        /** @var Bank $bank */
        $bank = $context['bank'];
        /** @var Chequebook|null $chequebook */
        $chequebook = $context['chequebook'];
        $amount = round((float) $payload['amount'], 4);

        $cheque = $this->chequeAutoCreationService->ensureCheque([
            'context' => 'scenario_runner_issued_cheque',
            'source_short' => 'SCN',
            'payment_method_id' => (int) $context['cheque_method_id'],
            'cheque_type' => Cheque::TYPE_ISSUED,
            'party_id' => (int) ($supplier->party_id ?? 0),
            'amount' => $amount,
            'currency_code' => $this->scenarioCurrencyCode(),
            'bank_id' => (int) $bank->id,
            'chequebook_id' => $chequebook ? (int) $chequebook->id : null,
            'issue_date' => $payload['scenario_date'],
            'due_date' => Carbon::parse($payload['scenario_date'])->addDays(2)->toDateString(),
            'notes' => trim('SCENARIO-RUNNER|'.($payload['notes'] ?? '')),
        ]);
        if (! $cheque) {
            throw new RuntimeException('Could not create cheque.');
        }

        $this->chequeLedgerService->recordChequeCreated($cheque->fresh());
        $this->chequeLedgerService->recordChequeCashed($cheque->fresh());
        $cheque->refresh();

        return [
            'entity_type' => 'cheque',
            'entity_id' => (int) $cheque->id,
            'document_ids' => array_values(array_filter([(int) ($cheque->accounting_document_id ?? 0)])),
            'notes' => 'Issued cheque created and cashed from scenario runner',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executeExpenseCash(array $payload, array $context): array
    {
        /** @var ExpenseCategory $category */
        $category = $context['expense_category'];
        /** @var CashBox $cashBox */
        $cashBox = $context['cash_box'];
        $amount = round((float) $payload['amount'], 4);

        $expense = Expense::query()->create([
            'expense_number' => 'SCN-EXP-'.now()->format('YmdHis').'-'.random_int(100, 999),
            'expense_category_id' => (int) $category->id,
            'expense_type' => Expense::TYPE_OPERATIONAL,
            'amount' => $amount,
            'currency_code' => $this->scenarioCurrencyCode(),
            'fx_rate' => 1,
            'amount_base' => $amount,
            'expense_date' => $payload['scenario_date'],
            'payment_status' => 'paid',
            'paid_amount' => $amount,
            'payee_type' => 'other',
            'payee_name' => 'Scenario Expense Payee',
            'description' => trim('SCENARIO-RUNNER|'.($payload['notes'] ?? '')),
            'status' => Expense::STATUS_PAID,
            'cash_box_id' => (int) $cashBox->id,
        ]);
        $this->expenseService->ensureLedgerPosted($expense->fresh());
        $expense->refresh();

        return [
            'entity_type' => 'expense',
            'entity_id' => (int) $expense->id,
            'document_ids' => array_values(array_filter([(int) ($expense->document_id ?? 0)])),
            'notes' => 'Expense posted from scenario runner',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executeBankTransferTreasury(array $payload, array $context): array
    {
        return $this->executeBankTransferAdvanced($payload, $context, 'wallet', 'bank', 'Scenario bank transfer');
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executeBankTransferCashboxToBank(array $payload, array $context): array
    {
        return $this->executeBankTransferAdvanced($payload, $context, 'cashbox', 'bank', 'Scenario cashbox to bank transfer');
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executeFixedAssetPurchase(array $payload, array $context): array
    {
        /** @var FixedAssetCategory $category */
        $category = $context['fixed_asset_category'];
        /** @var CashBox $cashBox */
        $cashBox = $context['cash_box'];
        $amount = round((float) $payload['amount'], 4);

        $asset = $this->fixedAssetService->createAsset([
            'asset_code' => 'SCN-FA-'.now()->format('YmdHis').'-'.random_int(100, 999),
            'name' => 'Scenario Fixed Asset',
            'category_id' => (int) $category->id,
            'purchase_date' => $payload['scenario_date'],
            'purchase_price' => $amount,
            'useful_life_years' => 5,
            'useful_life_months' => 0,
            'depreciation_method' => 'straight_line',
            'salvage_value' => 0,
            'description' => trim('SCENARIO-RUNNER|'.($payload['notes'] ?? '')),
            'record_purchase' => true,
            'payment_account_id' => (int) $cashBox->account_id,
            'generate_schedule' => false,
        ]);

        $documentIds = DB::table('accounting_documents')
            ->where('reference_type', 'fixed_asset')
            ->where('reference_id', (int) $asset->id)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        return [
            'entity_type' => 'fixed_asset',
            'entity_id' => (int) $asset->id,
            'document_ids' => $documentIds,
            'notes' => 'Fixed asset purchase posted from scenario runner',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executeShareholderCapital(array $payload, array $context): array
    {
        /** @var Shareholder $shareholder */
        $shareholder = $context['shareholder'];
        /** @var Bank $bank */
        $bank = $context['bank'];
        $amount = round((float) $payload['amount'], 4);

        $contribution = $this->shareholderCapitalContributionService->record([
            'shareholder_id' => (int) $shareholder->id,
            'amount' => $amount,
            'journal_date' => $payload['scenario_date'],
            'source_type' => 'bank',
            'bank_id' => (int) $bank->id,
            'currency_code' => $this->scenarioCurrencyCode(),
            'description' => trim('SCENARIO-RUNNER|'.($payload['notes'] ?? '')),
        ]);

        $documentId = (int) DB::table('manual_journals')
            ->where('id', (int) $contribution->manual_journal_id)
            ->value('accounting_document_id');

        return [
            'entity_type' => 'shareholder_capital_contribution',
            'entity_id' => (int) $contribution->id,
            'document_ids' => array_values(array_filter([$documentId])),
            'notes' => 'Shareholder capital contribution posted from scenario runner',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executeShareholderWithdrawal(array $payload, array $context): array
    {
        /** @var Shareholder $shareholder */
        $shareholder = $context['shareholder'];
        /** @var CashBox $cashBox */
        $cashBox = $context['cash_box'];
        $amount = round((float) $payload['amount'], 4);

        $withdrawal = $this->shareholderWithdrawalService->record([
            'shareholder_id' => (int) $shareholder->id,
            'amount' => $amount,
            'journal_date' => $payload['scenario_date'],
            'source_type' => 'cash',
            'cash_box_id' => (int) $cashBox->id,
            'currency_code' => $this->scenarioCurrencyCode(),
            'description' => trim('SCENARIO-RUNNER|'.($payload['notes'] ?? '')),
            'post_journal' => true,
        ]);

        $documentId = (int) DB::table('manual_journals')
            ->where('id', (int) $withdrawal->manual_journal_id)
            ->value('accounting_document_id');

        return [
            'entity_type' => 'shareholder_withdrawal',
            'entity_id' => (int) $withdrawal->id,
            'document_ids' => array_values(array_filter([$documentId])),
            'notes' => 'Shareholder withdrawal posted from scenario runner',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executeVatRemittance(array $payload, array $context): array
    {
        /** @var Bank $bank */
        $bank = $context['bank'];
        $amount = round((float) $payload['amount'], 4);

        $remittance = $this->vatRemittanceService->createAndPost([
            'period_start' => Carbon::parse($payload['scenario_date'])->startOfMonth()->toDateString(),
            'period_end' => Carbon::parse($payload['scenario_date'])->endOfMonth()->toDateString(),
            'payment_date' => $payload['scenario_date'],
            'amount' => $amount,
            'bank_id' => (int) $bank->id,
            'currency_code' => $this->scenarioCurrencyCode(),
            'store_id' => 0,
            'notes' => trim('SCENARIO-RUNNER|'.($payload['notes'] ?? '')),
        ]);

        return [
            'entity_type' => 'vat_remittance',
            'entity_id' => (int) $remittance->id,
            'document_ids' => array_values(array_filter([(int) $remittance->accounting_document_id])),
            'notes' => 'VAT remittance posted from scenario runner',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executeManualJournal(array $payload, array $context): array
    {
        /** @var CashBox $cashBox */
        $cashBox = $context['cash_box'];
        $amount = round((float) $payload['amount'], 4);
        $incomeAccountId = $this->resolveAnyIncomeAccountId();
        if ($incomeAccountId <= 0) {
            throw new RuntimeException('No active income account found.');
        }

        $journal = $this->manualJournalService->createJournal([
            'journal_date' => $payload['scenario_date'],
            'description' => trim('SCENARIO-RUNNER|'.($payload['notes'] ?? '')),
            'lines' => [
                [
                    'account_id' => (int) $cashBox->account_id,
                    'debit_amount' => $amount,
                    'credit_amount' => 0,
                    'currency_code' => $this->scenarioCurrencyCode(),
                    'description' => 'Scenario manual journal debit',
                ],
                [
                    'account_id' => $incomeAccountId,
                    'debit_amount' => 0,
                    'credit_amount' => $amount,
                    'currency_code' => $this->scenarioCurrencyCode(),
                    'description' => 'Scenario manual journal credit',
                ],
            ],
        ]);
        $posted = $this->manualJournalService->postJournal((int) $journal->id);

        return [
            'entity_type' => 'manual_journal',
            'entity_id' => (int) $posted->id,
            'document_ids' => array_values(array_filter([(int) ($posted->accounting_document_id ?? 0)])),
            'notes' => 'Manual journal posted from scenario runner',
        ];
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function createScenarioSalesInvoiceWithoutVat(Customer $customer, array $payload, float $amount): CustomerInvoice
    {
        $invoice = $this->customerInvoiceService->createInvoice([
            'invoice_number' => 'SCN-CINV-'.now()->format('YmdHis').'-'.random_int(100, 999),
            'customer_id' => (int) $customer->id,
            'store_id' => 0,
            'invoice_date' => $payload['scenario_date'],
            'due_date' => Carbon::parse($payload['scenario_date'])->addDays(7)->toDateString(),
            'subtotal' => $amount,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => $amount,
            'currency_code' => $this->scenarioCurrencyCode(),
            'fx_rate' => 1,
            'amount_base' => $amount,
            'payment_status' => CustomerInvoice::STATUS_UNPAID,
            'paid_amount' => 0,
            'balance_due' => $amount,
            'settlement_mode' => CustomerInvoice::SETTLEMENT_CREDIT,
            'status' => CustomerInvoice::STATUS_DRAFT,
            'notes' => trim('SCENARIO-RUNNER|'.($payload['notes'] ?? '')),
        ]);
        $this->customerInvoiceItemAdminService->createLine($invoice, [
            'product_name' => 'Scenario AR invoice line',
            'quantity' => 1,
            'price' => $amount,
            'tax_rate' => 0,
            'discount_amount' => 0,
        ]);

        return $this->customerInvoiceService->postSalesAccountingDocument($invoice->fresh());
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function createScenarioSupplierInvoiceWithoutVat(Supplier $supplier, array $payload, float $amount): SupplierInvoice
    {
        return $this->supplierInvoiceService->createInvoice([
            'invoice_number' => 'SCN-SINV-'.now()->format('YmdHis').'-'.random_int(100, 999),
            'supplier_id' => (int) $supplier->id,
            'store_id' => 0,
            'invoice_date' => $payload['scenario_date'],
            'due_date' => Carbon::parse($payload['scenario_date'])->addDays(10)->toDateString(),
            'subtotal' => $amount,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => $amount,
            'currency_code' => $this->scenarioCurrencyCode(),
            'fx_rate_at_invoice' => 1,
            'amount_base_at_invoice' => $amount,
            'tax_method' => 'exclusive',
            'payment_status' => SupplierInvoice::STATUS_UNPAID,
            'paid_amount' => 0,
            'balance_due' => $amount,
            'settlement_mode' => SupplierInvoice::SETTLEMENT_ON_ACCOUNT,
            'notes' => trim('SCENARIO-RUNNER|'.($payload['notes'] ?? '')),
        ], [[
            'product_name' => 'Scenario AP invoice line',
            'quantity' => 1,
            'unit_price' => $amount,
            'tax_rate' => 0,
            'discount_amount' => 0,
            'total_price' => $amount,
            'tax_amount' => 0,
        ]]);
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $context
     */
    private function createScenarioPayrollRun(array $payload, array $context, bool $seedLoan = false, ?int $disbursementBankId = null): PayrollRun
    {
        /** @var Employee|null $employee */
        $employee = $context['employee'] ?? null;
        if (! $employee instanceof Employee) {
            throw new RuntimeException('No active employee is available for payroll scenario.');
        }

        $expenseAccountId = (int) ($employee->payroll_expense_account_id ?? 0);
        if ($expenseAccountId <= 0) {
            $expenseAccountId = $this->resolveAnyExpenseAccountId();
            $employee->forceFill(['payroll_expense_account_id' => $expenseAccountId])->save();
            $employee->refresh();
        }

        $scenarioDate = Carbon::parse($payload['scenario_date']);
        $periodStart = $scenarioDate->copy()->startOfMonth()->toDateString();
        $periodEnd = $scenarioDate->copy()->endOfMonth()->toDateString();
        $amount = round((float) $payload['amount'], 4);

        if ($seedLoan) {
            $bankId = (int) ($disbursementBankId ?? 0);
            if ($bankId <= 0) {
                throw new RuntimeException('A disbursement bank is required for payroll loan settlement scenario.');
            }

            $this->employeeLoanService->createLoanWithDisbursement([
                'loan_number' => 'SCN-ELN-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'employee_id' => (int) $employee->id,
                'disbursement_bank_id' => $bankId,
                'disbursement_date' => $periodStart,
                'first_due_date' => $periodEnd,
                'principal_amount' => round($amount * 0.5, 4),
                'annual_interest_rate' => 12,
                'installments_count' => 1,
                'notes' => 'SCENARIO-RUNNER|Payroll loan seed',
            ]);
        }

        return $this->withAttendanceGateDisabled(function () use ($employee, $periodStart, $periodEnd, $payload, $amount): PayrollRun {
            return $this->payrollJournalService->createRun([
                'run_number' => 'SCN-PR-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'title' => 'Scenario Payroll Run',
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'journal_date' => $payload['scenario_date'],
                'currency_code' => $this->scenarioCurrencyCode(),
                'lines' => [[
                    'employee_id' => (int) $employee->id,
                    'base_salary' => $amount,
                    'benefits' => 0,
                    'description' => 'Scenario payroll line',
                ]],
            ]);
        });
    }

    private function manualJournalDocumentId(int $manualJournalId): int
    {
        if ($manualJournalId <= 0) {
            return 0;
        }

        return (int) DB::table('manual_journals')
            ->where('id', $manualJournalId)
            ->value('accounting_document_id');
    }

    /**
     * @template TReturn
     * @param callable():TReturn $callback
     * @return TReturn
     */
    private function withAttendanceGateDisabled(callable $callback)
    {
        $settingKey = 'accounting.payroll.attendance.feature_enabled';
        $previous = Setting::get($settingKey, true);
        Setting::set($settingKey, false);

        try {
            return $callback();
        } finally {
            Setting::set($settingKey, $previous);
        }
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<int,array<string,mixed>>
     */
    private function buildTransferExpectedEntries(array $payload, array $context, string $defaultFromType, string $defaultToType): array
    {
        $amount = round((float) ($payload['amount'] ?? 0), 4);
        $fromAccountId = $this->resolveTreasuryAccountForTransfer('from', $payload, $context, $defaultFromType);
        $toAccountId = $this->resolveTreasuryAccountForTransfer('to', $payload, $context, $defaultToType);

        return [
            $this->expectedEntry($toAccountId, $amount, 0, $this->expectedEntryNote('transfer_destination')),
            $this->expectedEntry($fromAccountId, 0, $amount, $this->expectedEntryNote('transfer_source')),
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function executeBankTransferAdvanced(array $payload, array $context, string $defaultFromType, string $defaultToType, string $defaultNotes): array
    {
        $amount = round((float) $payload['amount'], 4);
        $fromType = (string) ($payload['from_treasury_type'] ?: $defaultFromType);
        $toType = (string) ($payload['to_treasury_type'] ?: $defaultToType);
        $fromId = (int) ($payload['from_treasury_id'] ?? 0);
        $toId = (int) ($payload['to_treasury_id'] ?? 0);

        if ($fromId <= 0) {
            $fromId = $this->resolveTreasuryIdForType($fromType, $context);
        }
        if ($toId <= 0) {
            $toId = $this->resolveTreasuryIdForType($toType, $context);
        }
        if ($fromId <= 0 || $toId <= 0) {
            throw new RuntimeException('Treasury source/destination is not resolved.');
        }

        if ($fromType === 'wallet') {
            $wallet = Wallet::query()->find($fromId);
            if ($wallet && (float) $wallet->balance < $amount) {
                $wallet->increment('balance', $amount + 10000);
            }
        } elseif ($fromType === 'cashbox') {
            $cashbox = CashBox::query()->find($fromId);
            if ($cashbox && (float) $cashbox->balance < $amount) {
                $cashbox->increment('balance', $amount + 10000);
            }
        }

        $transfer = $this->bankTransferService->createTransfer([
            'transfer_number' => 'SCN-BTR-'.now()->format('YmdHis').'-'.random_int(100, 999),
            'from_treasury_type' => $fromType,
            'from_treasury_id' => $fromId,
            'to_treasury_type' => $toType,
            'to_treasury_id' => $toId,
            'amount' => $amount,
            'currency_code' => $this->scenarioCurrencyCode(),
            'fx_rate' => 1,
            'transfer_date' => $payload['scenario_date'],
            'value_date' => $payload['value_date'] ?? $payload['scenario_date'],
            'transfer_fee' => round(max(0, (float) ($payload['transfer_fee'] ?? 0)), 4),
            'reference_number' => trim((string) ($payload['reference_number'] ?? '')),
            'description' => trim((string) ($payload['description'] ?? '')) !== ''
                ? trim((string) $payload['description'])
                : trim('SCENARIO-RUNNER|'.($payload['notes'] ?? '')),
            'notes' => trim((string) ($payload['notes'] ?? '')) !== '' ? trim((string) $payload['notes']) : $defaultNotes,
            'auto_process' => true,
        ]);

        return [
            'entity_type' => 'bank_transfer',
            'entity_id' => (int) $transfer->id,
            'document_ids' => array_values(array_filter([(int) ($transfer->accounting_document_id ?? 0)])),
            'notes' => 'Treasury bank transfer posted from scenario runner',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     */
    private function resolveTreasuryAccountForTransfer(string $direction, array $payload, array $context, string $defaultType): int
    {
        $typeKey = $direction.'_treasury_type';
        $idKey = $direction.'_treasury_id';
        $type = (string) ($payload[$typeKey] ?: $defaultType);
        $id = (int) ($payload[$idKey] ?? 0);
        if ($id <= 0) {
            $id = $this->resolveTreasuryIdForType($type, $context);
        }
        if ($id <= 0) {
            return 0;
        }

        return match ($type) {
            'wallet' => (int) (Wallet::query()->whereKey($id)->value('account_id') ?? 0),
            'cashbox' => (int) (CashBox::query()->whereKey($id)->value('account_id') ?? 0),
            'bank' => (int) (Bank::query()->whereKey($id)->value('account_id') ?? 0),
            default => 0,
        };
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function resolveTreasuryIdForType(string $type, array $context): int
    {
        return match ($type) {
            'wallet' => (int) ($context['wallet']?->id ?? 0),
            'cashbox' => (int) ($context['cash_box']?->id ?? 0),
            'bank' => (int) ($context['bank']?->id ?? 0),
            default => 0,
        };
    }

    /**
     * @return array<string,mixed>
     */
    private function expectedEntry(int $accountId, float $debit, float $credit, string $note, string $counterpartyName = ''): array
    {
        $account = $accountId > 0 ? Account::query()->find($accountId) : null;

        return [
            'account_id' => $accountId,
            'account_code' => (string) ($account?->code ?? ''),
            'account_name' => (string) ($account?->name ?? ''),
            'counterparty_name' => trim($counterpartyName),
            'debit' => round($debit, 4),
            'credit' => round($credit, 4),
            'expected_delta' => round($debit - $credit, 4),
            'note' => $note,
        ];
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function resolveCounterpartyName(array $context, string $entityKey): string
    {
        return trim((string) ($context[$entityKey]?->name ?? ''));
    }

    private function expectedEntryNote(string $key): string
    {
        $translationKey = 'accounting::accounting.scenario_runner.expected_entry_notes.'.$key;
        $translated = (string) trans($translationKey);

        return $translated !== $translationKey ? $translated : $key;
    }

    private function scenarioCurrencyCode(): string
    {
        $configured = strtoupper(trim((string) Setting::get('accounting.base_currency', '')));
        if ($configured !== '') {
            return $configured;
        }

        return Currency::resolveBaseCurrencyCode('IRR');
    }

    private function resolveAnyIncomeAccountId(): int
    {
        $preferredCode = trim((string) config('accounting.accounts.sales_revenue', ''));
        if ($preferredCode !== '') {
            $preferred = (int) Account::query()->where('code', $preferredCode)->value('id');
            if ($preferred > 0) {
                return $preferred;
            }
        }

        return (int) Account::query()
            ->where('active', true)
            ->where('account_type', Account::TYPE_INCOME)
            ->orderBy('id')
            ->value('id');
    }

    private function resolveAnyExpenseAccountId(): int
    {
        return (int) Account::query()
            ->where('active', true)
            ->where('account_type', Account::TYPE_EXPENSE)
            ->orderBy('id')
            ->value('id');
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     */
    private function resolveCustomerAdvanceSourceAccountId(array $payload, array $context): int
    {
        $bankId = (int) ($payload['bank_id'] ?? 0);
        if ($bankId > 0) {
            return (int) (Bank::query()->whereKey($bankId)->value('account_id') ?? 0);
        }

        $cashBoxId = (int) ($payload['cash_box_id'] ?? 0);
        if ($cashBoxId > 0) {
            return (int) (CashBox::query()->whereKey($cashBoxId)->value('account_id') ?? 0);
        }

        return (int) ($context['cash_box']?->account_id ?? 0);
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $context
     */
    private function resolveSupplierAdvanceSourceAccountId(array $payload, array $context): int
    {
        $cashBoxId = (int) ($payload['cash_box_id'] ?? 0);
        if ($cashBoxId > 0) {
            return (int) (CashBox::query()->whereKey($cashBoxId)->value('account_id') ?? 0);
        }

        $bankId = (int) ($payload['bank_id'] ?? 0);
        if ($bankId > 0) {
            return (int) (Bank::query()->whereKey($bankId)->value('account_id') ?? 0);
        }

        $walletId = (int) ($payload['wallet_id'] ?? 0);
        if ($walletId > 0) {
            return (int) (Wallet::query()->whereKey($walletId)->value('account_id') ?? 0);
        }

        return (int) ($context['cash_box']?->account_id ?? 0);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function resolveCustomerAdvanceSourceNoteKey(array $payload): string
    {
        return (int) ($payload['bank_id'] ?? 0) > 0 ? 'bank' : 'cashbox';
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function resolveSupplierAdvanceSourceNoteKey(array $payload): string
    {
        if ((int) ($payload['bank_id'] ?? 0) > 0) {
            return 'bank';
        }
        if ((int) ($payload['wallet_id'] ?? 0) > 0) {
            return 'wallet';
        }

        return 'cashbox';
    }

    private function resolveAllowanceForDoubtfulAccountId(): int
    {
        $configured = (int) Setting::get('accounting.allowance_doubtful_accounts_id', 0);
        if ($configured > 0) {
            return $configured;
        }

        $byCode = (int) Account::query()
            ->where('active', true)
            ->where('code', 'LIKE', '129%')
            ->orderBy('id')
            ->value('id');
        if ($byCode > 0) {
            return $byCode;
        }

        return $this->resolveAccountsReceivableId();
    }

    private function resolveSalesAccountId(): int
    {
        $configured = (int) Setting::get('accounting.sales_account_id', 0);
        if ($configured > 0) {
            return $configured;
        }

        return $this->resolveAnyIncomeAccountId();
    }

    private function resolveAccountsReceivableId(): int
    {
        $configured = (int) Setting::get('accounting.ar_account_id', 0);
        if ($configured > 0) {
            return $configured;
        }

        return (int) Account::query()
            ->where('active', true)
            ->where('account_type', Account::TYPE_ASSET)
            ->orderBy('id')
            ->value('id');
    }

    private function resolveAccountsPayableId(): int
    {
        $configured = (int) Setting::get('accounting.ap_account_id', 0);
        if ($configured > 0) {
            return $configured;
        }

        return (int) Account::query()
            ->where('active', true)
            ->where('account_type', Account::TYPE_LIABILITY)
            ->orderBy('id')
            ->value('id');
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function resolveCustomerAdvanceAccountId(array $context = []): int
    {
        /** @var Customer|null $customer */
        $customer = $context['customer'] ?? null;
        if (! $customer) {
            throw new RuntimeException('مشتری سناریو برای resolve حساب تفصیلی پیش‌دریافت موجود نیست.');
        }

        $this->partyService->ensurePartyForCustomer($customer);
        $customer->refresh();

        if ((int) ($customer->account_id ?? 0) <= 0) {
            $partyId = (int) ($customer->party_id ?? 0);
            if ($partyId <= 0) {
                throw new RuntimeException("برای مشتری {$customer->id} party_id تعریف نشده است.");
            }
            $customerAccount = $this->partyService->getOrCreateCustomerAccount($partyId);
            $customer->account_id = (int) $customerAccount->id;
            $customer->save();
            $customer->refresh();
        }

        $accountId = (int) ($customer->account_id ?? 0);
        if ($accountId <= 0) {
            throw new RuntimeException("حساب تفصیلی مشتری {$customer->id} ایجاد/یافت نشد.");
        }

        return $accountId;
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function resolveSupplierAdvanceAccountId(array $context = []): int
    {
        /** @var Supplier|null $supplier */
        $supplier = $context['supplier'] ?? null;
        if (! $supplier) {
            throw new RuntimeException('تامین‌کننده سناریو برای resolve حساب تفصیلی پیش‌پرداخت موجود نیست.');
        }

        $this->partyService->ensurePartyForSupplier($supplier);
        $supplier->refresh();

        if ((int) ($supplier->account_id ?? 0) <= 0) {
            $partyId = (int) ($supplier->party_id ?? 0);
            if ($partyId <= 0) {
                throw new RuntimeException("برای تامین‌کننده {$supplier->id} party_id تعریف نشده است.");
            }
            $supplierAccount = $this->partyService->getOrCreateSupplierAccount($partyId);
            $supplier->account_id = (int) $supplierAccount->id;
            $supplier->save();
            $supplier->refresh();
        }

        $accountId = (int) ($supplier->account_id ?? 0);
        if ($accountId <= 0) {
            throw new RuntimeException("حساب تفصیلی تامین‌کننده {$supplier->id} ایجاد/یافت نشد.");
        }

        return $accountId;
    }

    private function ensureValidAccountSetting(string $key, int $fallbackAccountId): int
    {
        $configured = (int) Setting::get($key, 0);
        if ($configured > 0 && Account::query()->whereKey($configured)->exists()) {
            return $configured;
        }

        if ($fallbackAccountId > 0 && Account::query()->whereKey($fallbackAccountId)->exists()) {
            Setting::set($key, $fallbackAccountId);

            return $fallbackAccountId;
        }

        $firstActive = (int) Account::query()
            ->where('active', true)
            ->orderBy('id')
            ->value('id');

        if ($firstActive > 0) {
            Setting::set($key, $firstActive);
        }

        return $firstActive;
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function resolveSupplierPayableAccountId(array $context): int
    {
        /** @var Supplier|null $supplier */
        $supplier = $context['supplier'] ?? null;
        if ($supplier && (int) ($supplier->party_id ?? 0) > 0) {
            try {
                $account = $this->partyService->getOrCreateSupplierAccount((int) $supplier->party_id);
                if ($account && (int) ($account->id ?? 0) > 0) {
                    return (int) $account->id;
                }
            } catch (\Throwable) {
                // fallback below
            }
        }
        if ($supplier && (int) ($supplier->account_id ?? 0) > 0) {
            return (int) $supplier->account_id;
        }

        return $this->resolveAccountsPayableId();
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function resolveSupplierCostAccountId(array $context): int
    {
        /** @var Supplier|null $supplier */
        $supplier = $context['supplier'] ?? null;
        if ($supplier && (int) ($supplier->party_id ?? 0) > 0) {
            try {
                $account = $this->partyService->getOrCreateSupplierCostAccount((int) $supplier->party_id);
                if ($account && (int) ($account->id ?? 0) > 0) {
                    return (int) $account->id;
                }
            } catch (\Throwable) {
                // fallback below
            }
        }

        return $this->resolveInventoryOrAssetAccountId();
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function resolveCustomerReceivableAccountId(array $context): int
    {
        /** @var Customer|null $customer */
        $customer = $context['customer'] ?? null;
        if ($customer && (int) ($customer->party_id ?? 0) > 0) {
            try {
                $account = $this->partyService->getOrCreateCustomerAccount((int) $customer->party_id);
                if ($account && (int) ($account->id ?? 0) > 0) {
                    return (int) $account->id;
                }
            } catch (\Throwable) {
                // fallback below
            }
        }
        if ($customer && (int) ($customer->account_id ?? 0) > 0) {
            return (int) $customer->account_id;
        }

        return (int) config('accounting.accounts.accounts_receivable');
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function resolveCustomerRevenueAccountId(array $context): int
    {
        /** @var Customer|null $customer */
        $customer = $context['customer'] ?? null;
        if ($customer && (int) ($customer->party_id ?? 0) > 0) {
            try {
                $account = $this->partyService->getOrCreateCustomerRevenueAccount((int) $customer->party_id);
                if ($account && (int) ($account->id ?? 0) > 0) {
                    return (int) $account->id;
                }
            } catch (\Throwable) {
                // fallback below
            }
        }

        return (int) config('accounting.accounts.sales_revenue');
    }

    private function resolveInventoryOrAssetAccountId(): int
    {
        $configuredCode = trim((string) Setting::get('accounting.system_accounts.assets.inventory', ''));
        if ($configuredCode === '') {
            throw new RuntimeException((string) trans('accounting::accounting.sample_data.preflight.inventory', [
                'code' => '—',
            ]));
        }

        $inventoryId = (int) (Account::query()
            ->where('active', true)
            ->where('account_type', Account::TYPE_ASSET)
            ->where('code', $configuredCode)
            ->value('id') ?? 0);
        if ($inventoryId <= 0) {
            throw new RuntimeException((string) trans('accounting::accounting.sample_data.preflight.inventory', [
                'code' => $configuredCode,
            ]));
        }

        return $inventoryId;
    }

    private function vatRate(): float
    {
        if (function_exists('is_vat_enabled') && ! is_vat_enabled()) {
            return 0.0;
        }

        $today = now()->toDateString();
        $defaultVatRate = TaxRate::query()
            ->where('tax_type', TaxRate::TYPE_VAT)
            ->where('active', true)
            ->where('is_default', true)
            ->where(function ($query) use ($today): void {
                $query->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', $today);
            })
            ->where(function ($query) use ($today): void {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $today);
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->value('rate');
        if (is_numeric($defaultVatRate)) {
            return (float) $defaultVatRate;
        }

        return (float) TaxCalculator::getVATRate('standard');
    }

    /**
     * @return array{tax_rate: float, tax_method: string, base_amount: float, tax_amount: float, total_amount: float}
     */
    private function resolveScenarioVatAmounts(float $amount): array
    {
        $taxRate = $this->vatRate();
        $taxMethod = function_exists('tax_calculation_method') ? (string) tax_calculation_method() : 'exclusive';
        if (! in_array($taxMethod, ['inclusive', 'exclusive'], true)) {
            $taxMethod = 'exclusive';
        }

        $result = TaxCalculator::calculateVAT(round($amount, 4), $taxRate, $taxMethod);

        return [
            'tax_rate' => $taxRate,
            'tax_method' => $taxMethod,
            'base_amount' => round((float) ($result['base_amount'] ?? $amount), 4),
            'tax_amount' => round((float) ($result['tax_amount'] ?? 0), 4),
            'total_amount' => round((float) ($result['total_amount'] ?? $amount), 4),
        ];
    }
}

