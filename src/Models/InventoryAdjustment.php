<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryAdjustment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'adjustment_number',
        'adjustment_date',
        'adjustment_type',
        'warehouse_id',
        'reason',
        'notes',
        'total_adjustment_value',
        'status',
        'accounting_document_id',
        'created_by_admin_id',
        'created_by_user_id',
        'approved_by_admin_id',
        'approved_by_user_id',
        'approved_at',
        'posted_by_admin_id',
        'posted_by_user_id',
        'posted_at',
    ];

    protected $casts = [
        'adjustment_date' => 'date',
        'total_adjustment_value' => 'decimal:4',
        'approved_at' => 'datetime',
        'posted_at' => 'datetime',
    ];

    /**
     * اقلام تعدیل
     */
    public function items(): HasMany
    {
        return $this->hasMany(InventoryAdjustmentItem::class);
    }

    /**
     * سند حسابداری
     */
    public function accountingDocument(): BelongsTo
    {
        return $this->belongsTo(AccountingDocument::class);
    }

    /**
     * کاربر ایجادکننده
     */
    public function creator(): BelongsTo
    {
        return $this->createdByUser();
    }

    /**
     * کاربر تاییدکننده
     */
    public function approver(): BelongsTo
    {
        return $this->approvedByUser();
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

    public function approvedByAdmin(): BelongsTo
    {
        $adminModel = config('auth.providers.admins.model', \App\Models\Admin::class);

        return $this->belongsTo($adminModel, 'approved_by_admin_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by_user_id');
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
     * Scope: Approved
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope: Posted
     */
    public function scopePosted($query)
    {
        return $query->where('status', 'posted');
    }

    /**
     * Generate unique adjustment number
     */
    public static function generateAdjustmentNumber(): string
    {
        $lastAdjustment = self::orderBy('id', 'desc')->first();
        $nextNumber = $lastAdjustment ? ($lastAdjustment->id + 1) : 1;
        return 'IA-' . date('Ymd') . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
