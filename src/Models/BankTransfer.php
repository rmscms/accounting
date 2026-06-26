<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RMS\Accounting\Models\CashBox;
use RMS\Accounting\Models\Wallet;

class BankTransfer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transfer_number',
        'from_bank_id',
        'to_bank_id',
        'from_treasury_type',
        'from_treasury_id',
        'to_treasury_type',
        'to_treasury_id',
        'amount',
        'currency_code',
        'fx_rate',
        'transfer_date',
        'value_date',
        'transfer_fee',
        'transfer_fee_account_id',
        'reference_number',
        'status',
        'description',
        'notes',
        'accounting_document_id',
        'created_by_admin_id',
        'created_by_user_id',
        'processed_by_admin_id',
        'processed_by_user_id',
        'processed_at',
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'value_date' => 'date',
        'amount' => 'decimal:4',
        'fx_rate' => 'decimal:6',
        'transfer_fee' => 'decimal:4',
        'processed_at' => 'datetime',
    ];

    public const TREASURY_TYPE_BANK = 'bank';
    public const TREASURY_TYPE_CASHBOX = 'cashbox';
    public const TREASURY_TYPE_WALLET = 'wallet';

    /**
     * بانک مبدا
     */
    public function fromBank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'from_bank_id');
    }

    /**
     * بانک مقصد
     */
    public function toBank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'to_bank_id');
    }

    public function fromCashBox(): BelongsTo
    {
        return $this->belongsTo(CashBox::class, 'from_treasury_id');
    }

    public function toCashBox(): BelongsTo
    {
        return $this->belongsTo(CashBox::class, 'to_treasury_id');
    }

    public function fromWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'from_treasury_id');
    }

    public function toWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'to_treasury_id');
    }

    /**
     * حساب کارمزد
     */
    public function feeAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'transfer_fee_account_id');
    }

    /**
     * سند حسابداری
     */
    public function accountingDocument(): BelongsTo
    {
        return $this->belongsTo(AccountingDocument::class);
    }

    /**
     * ارز
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    /**
     * کاربر ایجادکننده
     */
    public function creator(): BelongsTo
    {
        return $this->createdByUser();
    }

    /**
     * کاربر پردازش‌کننده
     */
    public function processor(): BelongsTo
    {
        return $this->processedByUser();
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

    public function processedByAdmin(): BelongsTo
    {
        $adminModel = config('auth.providers.admins.model', \App\Models\Admin::class);

        return $this->belongsTo($adminModel, 'processed_by_admin_id');
    }

    public function processedByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'processed_by_user_id');
    }

    /**
     * Scope: Pending
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Completed
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Generate unique transfer number
     */
    public static function generateTransferNumber(): string
    {
        $lastTransfer = self::orderBy('id', 'desc')->first();
        $nextNumber = $lastTransfer ? ($lastTransfer->id + 1) : 1;
        return 'BT-' . date('Ymd') . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
