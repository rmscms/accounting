<?php

declare(strict_types=1);

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceAuditLog extends Model
{
    protected $fillable = [
        'entity_type',
        'entity_id',
        'action',
        'reason',
        'old_values_json',
        'new_values_json',
        'acted_by_user_id',
        'acted_at',
    ];

    protected $casts = [
        'old_values_json' => 'array',
        'new_values_json' => 'array',
        'acted_at' => 'datetime',
    ];
}
