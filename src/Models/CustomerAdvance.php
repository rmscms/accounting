<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\{Model, SoftDeletes, Relations\BelongsTo};

class CustomerAdvance extends Model
{
    use SoftDeletes;

    protected $fillable = ['advance_number', 'customer_id', 'store_id', 'advance_date', 'amount', 'currency_code', 'fx_rate', 'amount_base', 'applied_amount', 'remaining_amount', 'payment_method', 'bank_id', 'cash_box_id', 'reference_number', 'status', 'accounting_document_id', 'notes', 'created_by_admin_id', 'created_by_user_id'];

    protected $casts = ['advance_date' => 'date', 'amount' => 'decimal:2', 'fx_rate' => 'decimal:6', 'amount_base' => 'decimal:2', 'applied_amount' => 'decimal:2', 'remaining_amount' => 'decimal:2'];

    const STATUS_ACTIVE = 'active';
    const STATUS_FULLY_APPLIED = 'fully_applied';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_CANCELLED = 'cancelled';

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function bank(): BelongsTo { return $this->belongsTo(Bank::class); }
    public function cashBox(): BelongsTo { return $this->belongsTo(CashBox::class); }
    public function currency(): BelongsTo { return $this->belongsTo(Currency::class, 'currency_code', 'code'); }
    public function accountingDocument(): BelongsTo { return $this->belongsTo(AccountingDocument::class); }
    public function createdByAdmin(): BelongsTo {
        $adminModel = config('auth.providers.admins.model', \App\Models\Admin::class);
        return $this->belongsTo($adminModel, 'created_by_admin_id');
    }
    public function createdByUser(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'created_by_user_id'); }
    
    public function scopeActive($query) { return $query->where('status', self::STATUS_ACTIVE)->where('remaining_amount', '>', 0); }
    public function isActive(): bool { return $this->status === self::STATUS_ACTIVE && $this->remaining_amount > 0; }
}
