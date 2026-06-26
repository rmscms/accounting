<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chequebook extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_id',
        'title',
        'book_number',
        'serial_from',
        'serial_to',
        'next_serial',
        'active',
        'notes',
    ];

    protected $casts = [
        'serial_from' => 'integer',
        'serial_to' => 'integer',
        'next_serial' => 'integer',
        'active' => 'boolean',
    ];

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function cheques(): HasMany
    {
        return $this->hasMany(Cheque::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function consumeNextSerial(): ?string
    {
        if ($this->next_serial === null) {
            return null;
        }

        $current = (int) $this->next_serial;
        if ($this->serial_to !== null && $current > (int) $this->serial_to) {
            return null;
        }

        $this->next_serial = $current + 1;
        $this->save();

        return (string) $current;
    }
}

