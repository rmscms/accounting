<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RMS\Core\Models\Admin;

class ExpenseStatusHistory extends Model
{
    public $timestamps = false;

    protected $table = 'expense_status_histories';

    protected $fillable = [
        'expense_id',
        'from_status',
        'to_status',
        'admin_user_id',
        'note',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_user_id');
    }
}
