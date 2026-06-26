<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Party extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'national_code',
        'tax_number',
        'phone',
        'email',
        'address',
        'contact_person',
        'type',
        'active',
        'notes',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * Customer relationship
     */
    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    /**
     * Supplier relationship
     */
    public function supplier(): HasOne
    {
        return $this->hasOne(Supplier::class);
    }

    /**
     * Check if party is a customer
     */
    public function isCustomer(): bool
    {
        return $this->customer !== null;
    }

    /**
     * Check if party is a supplier
     */
    public function isSupplier(): bool
    {
        return $this->supplier !== null;
    }

    /**
     * Check if party is both customer and supplier
     */
    public function isBoth(): bool
    {
        return $this->isCustomer() && $this->isSupplier();
    }

    /**
     * Scope: Active parties
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope: Parties that are customers
     */
    public function scopeCustomers($query)
    {
        return $query->whereHas('customer');
    }

    /**
     * Scope: Parties that are suppliers
     */
    public function scopeSuppliers($query)
    {
        return $query->whereHas('supplier');
    }

    /**
     * Scope: Parties that are both customer and supplier
     */
    public function scopeBoth($query)
    {
        return $query->whereHas('customer')->whereHas('supplier');
    }
}
