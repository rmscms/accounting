<?php

declare(strict_types=1);

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRawPunch extends Model
{
    protected $fillable = [
        'employee_id',
        'import_batch_id',
        'punch_at',
        'direction',
        'device_id',
        'source_reference',
        'dedupe_hash',
        'meta_json',
    ];

    protected $casts = [
        'punch_at' => 'datetime',
        'meta_json' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(AttendanceImportBatch::class, 'import_batch_id');
    }
}
