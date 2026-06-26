<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RMS\Core\Models\Admin;

class CustomerInvoiceCorrection extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'customer_invoice_id',
        'correction_group_id',
        'action_type',
        'source_document_id',
        'target_document_id',
        'source_invoice_id',
        'target_invoice_id',
        'credit_note_id',
        'reason',
        'admin_user_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CustomerInvoice::class, 'customer_invoice_id');
    }

    public function sourceInvoice(): BelongsTo
    {
        return $this->belongsTo(CustomerInvoice::class, 'source_invoice_id');
    }

    public function targetInvoice(): BelongsTo
    {
        return $this->belongsTo(CustomerInvoice::class, 'target_invoice_id');
    }

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(AccountingDocument::class, 'source_document_id');
    }

    public function targetDocument(): BelongsTo
    {
        return $this->belongsTo(AccountingDocument::class, 'target_document_id');
    }

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class, 'credit_note_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_user_id');
    }
}
