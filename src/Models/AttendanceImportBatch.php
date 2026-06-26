<?php

declare(strict_types=1);

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceImportBatch extends Model
{
    protected $fillable = [
        'source_type',
        'source_reference',
        'import_hash',
        'period_start',
        'period_end',
        'rows_total',
        'rows_imported',
        'rows_skipped',
        'rows_failed',
        'report_json',
        'created_by_user_id',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'rows_total' => 'integer',
        'rows_imported' => 'integer',
        'rows_skipped' => 'integer',
        'rows_failed' => 'integer',
        'report_json' => 'array',
    ];

    public function rawPunches(): HasMany
    {
        return $this->hasMany(AttendanceRawPunch::class, 'import_batch_id');
    }
}
