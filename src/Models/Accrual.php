<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\{Model, SoftDeletes, Relations\BelongsTo};

class Accrual extends Model
{
    use SoftDeletes;
    protected $fillable = ['accrual_number', 'accrual_type', 'accrual_date', 'amount', 'account_id', 'description', 'reversal_date', 'is_reversed', 'reversal_document_id', 'accounting_document_id', 'notes', 'created_by_user_id'];
    protected $casts = ['accrual_date' => 'date', 'reversal_date' => 'date', 'amount' => 'decimal:2', 'is_reversed' => 'boolean'];
    const TYPE_ACCRUED_REVENUE = 'accrued_revenue';
    const TYPE_ACCRUED_EXPENSE = 'accrued_expense';
    const TYPE_DEFERRED_REVENUE = 'deferred_revenue';
    const TYPE_DEFERRED_EXPENSE = 'deferred_expense';
    public function account(): BelongsTo { return $this->belongsTo(Account::class); }
    public function accountingDocument(): BelongsTo { return $this->belongsTo(AccountingDocument::class); }
    public function reversalDocument(): BelongsTo { return $this->belongsTo(AccountingDocument::class, 'reversal_document_id'); }
    public function scopeActive($query) { return $query->where('is_reversed', false); }
}
