<?php

declare(strict_types=1);

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendancePeriodLock extends Model
{
    protected $fillable = [
        'attendance_period_id',
        'action',
        'reason',
        'acted_by_user_id',
        'acted_at',
    ];

    protected $casts = [
        'acted_at' => 'datetime',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(AttendancePeriod::class, 'attendance_period_id');
    }
}
