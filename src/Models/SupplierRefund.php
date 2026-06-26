<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Supplier Refund (دریافت بازگشت وجه از تامین‌کننده)
 *
 * شامل: دریافت نقد به خزانه، کسر از بدهی نسیه (offset_payable)،
 * و اعتبار روی حساب طرف پس از پرداخت (supplier_credit_on_account).
 */
class SupplierRefund extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'refund_number',
        'supplier_id',
        'supplier_invoice_id',
        'debit_note_id',
        'supplier_payment_id',
        'store_id',
        'refund_date',
        'reason',
        'amount',
        'currency_code',
        'fx_rate',
        'amount_base',
        'refund_method',
        'payment_method_id',
        'bank_id',
        'cash_box_id',
        'cheque_id',
        'wallet_id',
        'pos_terminal_id',
        'status',
        'received_at',
        'reference_number',
        'accounting_document_id',
        'notes',
        'created_by_admin_id',
        'created_by_user_id',
        'approved_by_admin_id',
        'approved_by_user_id',
        'approved_at',
    ];

    protected $casts = [
        'refund_date' => 'date',
        'amount' => 'decimal:2',
        'fx_rate' => 'decimal:6',
        'amount_base' => 'decimal:2',
        'received_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_RECEIVED = 'received';
    const STATUS_CANCELLED = 'cancelled';

    // Method constants
    const METHOD_CASH = 'cash';
    const METHOD_BANK_TRANSFER = 'bank_transfer';
    const METHOD_CHEQUE = 'cheque';
    const METHOD_ONLINE = 'online';
    const METHOD_DEDUCT = 'deduct_from_next_purchase';

    /** تسویهٔ بدهی پرداختنی بدون ورود نقد (فاکتور نسیه / ماندهٔ AP) */
    const METHOD_OFFSET_PAYABLE = 'offset_payable';

    /** اعتبار روی حساب تأمین‌کننده پس از پرداخت نقد (بدون برگشت نقد به خزانه) */
    const METHOD_SUPPLIER_CREDIT_ON_ACCOUNT = 'supplier_credit_on_account';

    /**
     * استردادهایی که سند آن‌ها بانک/صندوق ندارد (خزانه ثبت نشود).
     */
    public function isNonCashTreasuryRefund(): bool
    {
        return in_array((string) $this->refund_method, [
            self::METHOD_OFFSET_PAYABLE,
            self::METHOD_SUPPLIER_CREDIT_ON_ACCOUNT,
            self::METHOD_DEDUCT,
        ], true);
    }

    // Relations

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    public function debitNote(): BelongsTo
    {
        return $this->belongsTo(DebitNote::class);
    }

    public function originalPayment(): BelongsTo
    {
        return $this->belongsTo(SupplierPayment::class, 'supplier_payment_id');
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function cashBox(): BelongsTo
    {
        return $this->belongsTo(CashBox::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function cheque(): BelongsTo
    {
        return $this->belongsTo(Cheque::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function posTerminal(): BelongsTo
    {
        return $this->belongsTo(POSTerminal::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function accountingDocument(): BelongsTo
    {
        return $this->belongsTo(AccountingDocument::class);
    }

    public function createdByAdmin(): BelongsTo
    {
        $adminModel = config('auth.providers.admins.model', \App\Models\Admin::class);

        return $this->belongsTo($adminModel, 'created_by_admin_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_user_id');
    }

    public function approvedByAdmin(): BelongsTo
    {
        $adminModel = config('auth.providers.admins.model', \App\Models\Admin::class);

        return $this->belongsTo($adminModel, 'approved_by_admin_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by_user_id');
    }

    // Scopes

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeReceived($query)
    {
        return $query->where('status', self::STATUS_RECEIVED);
    }

    // Helper Methods

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isReceived(): bool
    {
        return $this->status === self::STATUS_RECEIVED;
    }

    public function canBeReceived(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
