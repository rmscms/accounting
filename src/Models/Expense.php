<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Expense extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'expense_number', 'document_id', 'expense_category_id', 'expense_type',
        'amount', 'currency_code', 'fx_rate', 'amount_base', 'expense_date',
        'payment_id', 'payment_status', 'paid_amount', 'payee_type', 'payee_id',
        'payee_name', 'description', 'receipt_number', 'receipt_image', 'tax_amount',
        'is_recurring', 'recurring_frequency', 'approved_by_user_id', 'approved_at',
        'requested_by_user_id', 'notes', 'status',
        'bank_id', 'cash_box_id', 'pos_terminal_id',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'fx_rate' => 'decimal:6',
        'amount_base' => 'decimal:4',
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

    /**
     * پیوست‌های فایلی (رسید / PDF) — مستقل از سند دفترکل AccountingDocument
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(AccountingAttachment::class, 'attachable');
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function cashBox(): BelongsTo
    {
        return $this->belongsTo(CashBox::class);
    }

    public function posTerminal(): BelongsTo
    {
        return $this->belongsTo(POSTerminal::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ExpenseStatusHistory::class)->orderBy('created_at');
    }

    /**
     * برچسب کوتاه منبع پرداخت برای لیست/نمایش.
     */
    /**
     * سازگاری با گزارش‌ها و ویجت‌هایی که فیلد را total_amount می‌نامند؛ در دیتابیس ستون `amount` است.
     */
    public function getTotalAmountAttribute(): mixed
    {
        return $this->attributes['amount'] ?? null;
    }

    /**
     * نرخ ارز برای ثبت در دفترکل (ExpenseService) — در absence نام ستون اختصاصی از fx_rate استفاده می‌شود.
     */
    public function getFxRateAtExpenseAttribute(): string
    {
        return (string) ($this->attributes['fx_rate'] ?? '1');
    }

    public function paymentSourceLabel(): string
    {
        if ($this->cash_box_id && $this->relationLoaded('cashBox') && $this->cashBox) {
            return $this->cashBox->name;
        }
        if ($this->bank_id && $this->relationLoaded('bank') && $this->bank) {
            return $this->bank->name;
        }
        if ($this->pos_terminal_id && $this->relationLoaded('posTerminal') && $this->posTerminal) {
            return $this->posTerminal->name ?? (string) $this->pos_terminal_id;
        }

        return '';
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
