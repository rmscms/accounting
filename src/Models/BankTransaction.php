<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transaction_number',
        'bank_id',
        'transaction_type',
        'transaction_date',
        'amount',
        'currency_code',
        'fx_rate',
        'charge_type_account_id',
        'reference_number',
        'description',
        'notes',
        'accounting_document_id',
        'status',
        'created_by_admin_id',
        'created_by_user_id',
        'posted_by_admin_id',
        'posted_by_user_id',
        'posted_at',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:4',
        'fx_rate' => 'decimal:6',
        'posted_at' => 'datetime',
    ];

    /**
     * بانک
     */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    /**
     * حساب نوع کارمزد/سود
     */
    public function chargeTypeAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'charge_type_account_id');
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
     * کاربر ثبت‌کننده
     */
    public function poster(): BelongsTo
    {
        return $this->postedByUser();
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

    public function postedByAdmin(): BelongsTo
    {
        $adminModel = config('auth.providers.admins.model', \App\Models\Admin::class);

        return $this->belongsTo($adminModel, 'posted_by_admin_id');
    }

    public function postedByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'posted_by_user_id');
    }

    /**
     * Scope: Draft
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope: Posted
     */
    public function scopePosted($query)
    {
        return $query->where('status', 'posted');
    }

    /**
     * Generate unique transaction number
     */
    public static function generateTransactionNumber(): string
    {
        $lastTransaction = self::orderBy('id', 'desc')->first();
        $nextNumber = $lastTransaction ? ($lastTransaction->id + 1) : 1;
        return 'BTX-' . date('Ymd') . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
