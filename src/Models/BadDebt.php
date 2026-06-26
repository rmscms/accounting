<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\{Model, SoftDeletes, Relations\BelongsTo};

class BadDebtProvision extends Model
{
    use SoftDeletes;
    protected $fillable = ['provision_number', 'customer_id', 'provision_date', 'provision_amount', 'calculation_method', 'percentage_used', 'status', 'accounting_document_id', 'notes', 'created_by_user_id'];
    protected $casts = ['provision_date' => 'date', 'provision_amount' => 'string', 'percentage_used' => 'decimal:2'];
    const STATUS_ACTIVE = 'active'; const STATUS_WRITTEN_OFF = 'written_off'; const STATUS_RECOVERED = 'recovered'; const STATUS_CANCELLED = 'cancelled';
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function accountingDocument(): BelongsTo { return $this->belongsTo(AccountingDocument::class); }
}

class BadDebtWriteoff extends Model
{
    use SoftDeletes;
    protected $fillable = ['writeoff_number', 'bad_debt_provision_id', 'customer_id', 'customer_invoice_id', 'writeoff_date', 'writeoff_amount', 'reason', 'status', 'accounting_document_id', 'notes', 'created_by_user_id', 'approved_by_user_id', 'approved_at'];
    protected $casts = ['writeoff_date' => 'date', 'writeoff_amount' => 'decimal:2', 'approved_at' => 'datetime'];
    const STATUS_PENDING = 'pending'; const STATUS_APPROVED = 'approved'; const STATUS_CANCELLED = 'cancelled';
    public function provision(): BelongsTo { return $this->belongsTo(BadDebtProvision::class, 'bad_debt_provision_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function invoice(): BelongsTo { return $this->belongsTo(CustomerInvoice::class, 'customer_invoice_id'); }
    public function accountingDocument(): BelongsTo { return $this->belongsTo(AccountingDocument::class); }
}
