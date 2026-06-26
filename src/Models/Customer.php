<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'phone',
        'email',
        'national_code',
        'credit_limit',
        'active',
        'tax_exempt', // معاف از مالیات
        'party_id',
        'account_id',
        'default_currency_code',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'active' => 'boolean',
        'tax_exempt' => 'boolean',
    ];

    /**
     * Party relationship
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * Account relationship
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * ارز مرجع عملیاتی (صورتحساب / پیش‌فرض فاکتور و پرداخت).
     */
    public function defaultCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'default_currency_code', 'code');
    }

    /**
     * Get party (helper method)
     */
    public function getParty(): ?Party
    {
        return $this->party;
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(CustomerInvoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(CustomerBalance::class);
    }
}
