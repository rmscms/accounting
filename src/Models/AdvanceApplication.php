<?php

namespace RMS\Accounting\Models;

use Illuminate\Database\Eloquent\Model;

class AdvanceApplication extends Model
{
    const UPDATED_AT = null;
    
    protected $fillable = ['advance_type', 'advance_id', 'invoice_type', 'invoice_id', 'applied_amount', 'application_date', 'notes'];
    
    protected $casts = ['applied_amount' => 'decimal:2', 'application_date' => 'date', 'created_at' => 'datetime'];
}
