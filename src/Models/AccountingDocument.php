<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_number',
        'document_type',
        'store_id',
        'fiscal_year_id',
        'reference_type',
        'reference_id',
        'description',
        'total_debit',
        'total_credit',
        'status',
        'posted_at',
        'reversed_by_document_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'store_id' => 'integer',
        'reference_id' => 'integer',
        'total_debit' => 'decimal:4',
        'total_credit' => 'decimal:4',
        'posted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Document Types
    const TYPE_SALE = 'SALE';
    const TYPE_PURCHASE = 'PURCHASE';
    const TYPE_PAYMENT = 'PAYMENT';
    const TYPE_RECEIPT = 'RECEIPT';
    const TYPE_TAX = 'TAX';
    const TYPE_FX_ADJUST = 'FX_ADJUST';
    const TYPE_CORRECTION = 'CORRECTION';
    const TYPE_OPENING = 'OPENING';
    const TYPE_CLOSING = 'CLOSING';
    const TYPE_EXPENSE = 'EXPENSE';

    // Status
    const STATUS_DRAFT = 'draft';
    const STATUS_POSTED = 'posted';
    const STATUS_REVERSED = 'reversed';

    // Reference Types
    const REF_EVENT = 'event';
    const REF_MANUAL = 'manual';
    const REF_SYSTEM = 'system';

    /**
     * Fiscal Year
     */
    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    /**
     * Ledger Entries
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(FinancialLedger::class, 'accounting_document_id');
    }

    /**
     * Reversed by Document
     */
    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(AccountingDocument::class, 'reversed_by_document_id');
    }

    /**
     * Documents that this reversed
     */
    public function reversals(): HasMany
    {
        return $this->hasMany(AccountingDocument::class, 'reversed_by_document_id');
    }

    /**
     * Post the document (finalize)
     */
    public function post()
    {
        if ($this->status === self::STATUS_POSTED) {
            throw new \Exception('Document is already posted');
        }

        // Validate balance
        if (!$this->isBalanced()) {
            throw new \Exception('Document is not balanced. Total Debit must equal Total Credit.');
        }

        $this->status = self::STATUS_POSTED;
        $this->posted_at = now();
        $this->save();

        return $this;
    }

    /**
     * Check if document is balanced
     */
    public function isBalanced(): bool
    {
        return abs($this->total_debit - $this->total_credit) < 0.01;
    }

    /**
     * Check if document is posted
     */
    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    /**
     * Check if document is reversed
     */
    public function isReversed(): bool
    {
        return $this->status === self::STATUS_REVERSED;
    }

    /**
     * Scope: Posted documents
     */
    public function scopePosted($query)
    {
        return $query->where('status', self::STATUS_POSTED);
    }

    /**
     * Scope: Draft documents
     */
    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * Scope: By type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('document_type', $type);
    }

    /**
     * Scope: By store
     */
    public function scopeForStore($query, int $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    /**
     * Scope: Date range
     */
    public function scopeDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Generate document number
     */
    public static function generateDocumentNumber(string $prefix = 'ACC', ?int $year = null): string
    {
        $year = $year ?? now()->year;
        $lastDocument = self::where('document_number', 'like', "{$prefix}-{$year}-%")
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastDocument 
            ? ((int) substr($lastDocument->document_number, -5)) + 1
            : 1;

        return sprintf('%s-%d-%05d', $prefix, $year, $nextNumber);
    }
}
