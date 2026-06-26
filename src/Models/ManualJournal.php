<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManualJournal extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'journal_number',
        'journal_date',
        'posting_date',
        'fiscal_year_id',
        'description',
        'notes',
        'total_debit',
        'total_credit',
        'status',
        'accounting_document_id',
        'created_by_admin_id',
        'created_by_user_id',
        'posted_by_admin_id',
        'posted_by_user_id',
        'posted_at',
        'reversed_journal_id',
        'reversal_reason',
        'reversed_at',
    ];

    protected $casts = [
        'journal_date' => 'date',
        'posting_date' => 'date',
        'total_debit' => 'decimal:4',
        'total_credit' => 'decimal:4',
        'posted_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    /**
     * سطرهای سند
     */
    public function lines(): HasMany
    {
        return $this->hasMany(ManualJournalLine::class);
    }

    /**
     * سال مالی
     */
    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    /**
     * سند حسابداری
     */
    public function accountingDocument(): BelongsTo
    {
        return $this->belongsTo(AccountingDocument::class);
    }

    /**
     * سند برگشتی
     */
    public function reversedJournal(): BelongsTo
    {
        return $this->belongsTo(ManualJournal::class, 'reversed_journal_id');
    }

    /**
     * کاربر ایجادکننده
     */
    public function creator(): BelongsTo
    {
        return $this->createdByAdmin();
    }

    /**
     * کاربر ثبت‌کننده
     */
    public function poster(): BelongsTo
    {
        return $this->postedByAdmin();
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
     * Scope: Reversed
     */
    public function scopeReversed($query)
    {
        return $query->where('status', 'reversed');
    }

    /**
     * Generate unique journal number
     */
    public static function generateJournalNumber(): string
    {
        $lastJournal = self::orderBy('id', 'desc')->first();
        $nextNumber = $lastJournal ? ($lastJournal->id + 1) : 1;
        return 'MJ-' . date('Ymd') . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
