<?php

declare(strict_types=1);

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollRunLineItem extends Model
{
    protected $fillable = [
        'payroll_run_line_id',
        'type',
        'code',
        'title',
        'amount',
        'sort_order',
        'is_system',
        'is_auto_calculated',
        'is_manual_override',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'sort_order' => 'integer',
        'is_system' => 'boolean',
        'is_auto_calculated' => 'boolean',
        'is_manual_override' => 'boolean',
    ];

    public function payrollRunLine(): BelongsTo
    {
        return $this->belongsTo(PayrollRunLine::class);
    }
}
