<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RMS\Core\Models\Admin;

class SupplierInvoiceCorrection extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'supplier_invoice_id',
        'correction_group_id',
        'action_type',
        'source_document_id',
        'target_document_id',
        'source_invoice_id',
        'target_invoice_id',
        'debit_note_id',
        'reason',
        'admin_user_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class, 'supplier_invoice_id');
    }

    public function sourceInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class, 'source_invoice_id');
    }

    public function targetInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class, 'target_invoice_id');
    }

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(AccountingDocument::class, 'source_document_id');
    }

    public function targetDocument(): BelongsTo
    {
        return $this->belongsTo(AccountingDocument::class, 'target_document_id');
    }

    public function debitNote(): BelongsTo
    {
        return $this->belongsTo(DebitNote::class, 'debit_note_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_user_id');
    }
}
