<?php

declare(strict_types=1);

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PayrollInsuranceSettlement extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_SETTLED = 'settled';

    protected $fillable = [
        'manual_journal_id',
        'employee_id',
        'bank_id',
        'amount',
        'journal_date',
        'status',
        'description',
        'settled_at',
        'settled_by_user_id',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'journal_date' => 'date',
        'settled_at' => 'datetime',
    ];

    public function manualJournal(): BelongsTo
    {
        return $this->belongsTo(ManualJournal::class, 'manual_journal_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(AccountingAttachment::class, 'attachable');
    }
}

