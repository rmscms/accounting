<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FiscalYear extends Model
{
    use HasFactory;

    protected $fillable = [
        'year_code',
        'start_date',
        'end_date',
        'status',
        'is_current',
        'closed_at',
        'closed_by_user_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
        'closed_at' => 'datetime',
    ];

    // Status
    const STATUS_OPEN = 'open';
    const STATUS_LOCKED = 'locked';
    const STATUS_CLOSED = 'closed';

    /**
     * Accounting Documents
     */
    public function documents(): HasMany
    {
        return $this->hasMany(AccountingDocument::class);
    }

    /**
     * Close fiscal year
     */
    public function close(int $userId)
    {
        if ($this->status === self::STATUS_CLOSED) {
            throw new \Exception('Fiscal year is already closed');
        }

        $this->status = self::STATUS_CLOSED;
        $this->closed_at = now();
        $this->closed_by_user_id = $userId;
        $this->is_current = false;
        $this->save();

        return $this;
    }

    /**
     * Lock fiscal year
     */
    public function lock()
    {
        if ($this->status === self::STATUS_CLOSED) {
            throw new \Exception('Cannot lock a closed fiscal year');
        }

        $this->status = self::STATUS_LOCKED;
        $this->save();

        return $this;
    }

    /**
     * Set as current fiscal year
     */
    public function setCurrent()
    {
        // Unset other current years
        self::where('is_current', true)->update(['is_current' => false]);

        $this->is_current = true;
        $this->save();

        return $this;
    }

    /**
     * Check if fiscal year is open
     */
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * Check if fiscal year is locked
     */
    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }

    /**
     * Check if fiscal year is closed
     */
    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /**
     * Scope: Current fiscal year
     */
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    /**
     * Scope: Open fiscal years
     */
    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    /**
     * Get current fiscal year
     */
    public static function getCurrentFiscalYear(): ?self
    {
        return self::current()->first();
    }

    /**
     * Check if a date falls within this fiscal year
     */
    public function containsDate($date): bool
    {
        $date = \Carbon\Carbon::parse($date);
        return $date->between($this->start_date, $this->end_date);
    }
}
