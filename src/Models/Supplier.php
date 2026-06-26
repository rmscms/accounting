<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    private const PAYABLE_ACCOUNT_PREFIX_FA = 'حساب‌های پرداختنی - ';

    private const PAYABLE_ACCOUNT_PREFIX_EN = 'Accounts Payable - ';

    protected static function booted(): void
    {
        static::saved(static function (Supplier $supplier): void {
            $supplier->syncLinkedPartyProfile();
            $supplier->syncLinkedPayableAccountName();
        });
    }

    protected $fillable = [
        'code', 'name', 'contact_person', 'phone', 'email', 'address', 'tax_number',
        'account_id', 'currency_code', 'payment_terms_days', 'credit_limit', 'active', 'notes',
        'tax_exempt', // معاف از مالیات
        'party_id',
    ];

    protected $casts = [
        'payment_terms_days' => 'integer',
        'credit_limit' => 'decimal:4',
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
     * Get party (helper method)
     */
    public function getParty(): ?Party
    {
        return $this->party;
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function syncLinkedPartyProfile(): void
    {
        if (! $this->party_id) {
            return;
        }

        $party = $this->relationLoaded('party')
            ? $this->party
            : Party::query()->find((int) $this->party_id);

        if (! $party) {
            return;
        }

        $payload = [];
        $name = trim((string) ($this->name ?? ''));
        if ($name !== '') {
            $payload['name'] = $name;
        }

        foreach (['phone', 'email', 'tax_number', 'address', 'contact_person'] as $field) {
            $value = trim((string) ($this->{$field} ?? ''));
            if ($value !== '') {
                $payload[$field] = $value;
            }
        }

        if ($payload === []) {
            return;
        }

        $party->fill($payload);
        if ($party->isDirty()) {
            $party->saveQuietly();
        }
    }

    public function syncLinkedPayableAccountName(): void
    {
        $supplierName = trim((string) ($this->name ?? ''));
        if ($supplierName === '' || ! $this->account_id) {
            return;
        }

        $account = Account::query()->find((int) $this->account_id);
        if (! $account) {
            return;
        }

        $currentName = trim((string) ($account->name ?? ''));
        if (
            $currentName !== ''
            && ! str_starts_with($currentName, self::PAYABLE_ACCOUNT_PREFIX_FA)
            && ! str_starts_with($currentName, self::PAYABLE_ACCOUNT_PREFIX_EN)
        ) {
            return;
        }

        $nextName = self::PAYABLE_ACCOUNT_PREFIX_FA.$supplierName;
        $maxLength = 255;
        if (mb_strlen($nextName) > $maxLength) {
            $nextName = mb_substr($nextName, 0, $maxLength);
        }

        if ((string) $account->name !== $nextName) {
            $account->name = $nextName;
            $account->saveQuietly();
        }
    }
}
