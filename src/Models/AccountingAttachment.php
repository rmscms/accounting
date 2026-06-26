<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountingAttachment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'disk',
        'path',
        'original_name',
        'mime',
        'size',
        'uploaded_by',
        'attachable_type',
        'attachable_id',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isOrphan(): bool
    {
        return $this->attachable_id === null;
    }
}
