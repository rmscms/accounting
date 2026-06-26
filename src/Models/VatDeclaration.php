<?php

declare(strict_types=1);

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VatDeclaration extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_AMENDED = 'amended';

    protected $fillable = [
        'period_start',
        'period_end',
        'fiscal_year',
        'fiscal_quarter',
        'version',
        'parent_declaration_id',
        'status',
        'snapshot_json',
        'official_export_json',
        'notes',
        'submitted_at',
        'submitted_by_user_id',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'fiscal_year' => 'integer',
        'fiscal_quarter' => 'integer',
        'version' => 'integer',
        'snapshot_json' => 'array',
        'official_export_json' => 'array',
        'submitted_at' => 'datetime',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_declaration_id');
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(self::class, 'parent_declaration_id');
    }
}
