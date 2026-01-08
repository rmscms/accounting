<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expense extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'expense_number', 'document_id', 'expense_category_id', 'expense_type',
        'amount', 'currency_code', 'fx_rate', 'amount_irr', 'expense_date',
        'payment_id', 'payment_status', 'paid_amount', 'payee_type', 'payee_id',
        'payee_name', 'description', 'receipt_number', 'receipt_image', 'tax_amount',
        'is_recurring', 'recurring_frequency', 'approved_by_user_id', 'approved_at',
        'requested_by_user_id', 'notes', 'status',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'fx_rate' => 'decimal:6',
        'amount_irr' => 'decimal:4',
        'expense_date' => 'date',
        'paid_amount' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'is_recurring' => 'boolean',
        'approved_at' => 'datetime',
    ];

    const TYPE_OPERATIONAL = 'operational';
    const TYPE_SALARY = 'salary';
    const TYPE_RENT = 'rent';
    const TYPE_UTILITIES = 'utilities';
    const TYPE_MARKETING = 'marketing';
    const TYPE_TRANSPORTATION = 'transportation';
    const TYPE_SUPPLIES = 'supplies';
    const TYPE_MAINTENANCE = 'maintenance';
    const TYPE_OTHER = 'other';

    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_PAID = 'paid';
    const STATUS_REJECTED = 'rejected';
    const STATUS_CANCELLED = 'cancelled';

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(AccountingDocument::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ExpenseItem::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }
}
