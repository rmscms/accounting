<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class POSTerminal extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pos_terminals';

    protected $fillable = [
        'name',
        'serial_number',
        'terminal_id',
        'bank_id',
        'merchant_id',
        'location',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
