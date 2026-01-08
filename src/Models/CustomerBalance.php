<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerBalance extends Model
{
    protected $primaryKey = ['customer_id', 'store_id'];
    public $incrementing = false;
    const UPDATED_AT = 'updated_at';
    const CREATED_AT = null;

    protected $fillable = [
        'customer_id', 'store_id', 'balance_irr', 'total_invoices', 'total_payments',
        'last_invoice_at', 'last_payment_at', 'last_transaction_at', 'credit_limit',
    ];

    protected $casts = [
        'balance_irr' => 'decimal:4',
        'total_invoices' => 'decimal:4',
        'total_payments' => 'decimal:4',
        'credit_limit' => 'decimal:4',
        'last_invoice_at' => 'datetime',
        'last_payment_at' => 'datetime',
        'last_transaction_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function scopeDebtors($query)
    {
        return $query->where('balance_irr', '>', 0);
    }
}
