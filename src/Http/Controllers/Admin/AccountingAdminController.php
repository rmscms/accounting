<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use RMS\Core\Controllers\Admin\ProjectAdminController;

/**
 * Base Admin Controller for Accounting Package
 * 
 * همه کنترلرهای ادمین accounting باید از این کلاس extend کنند
 * این امکان تغییرات متمرکز را در تمام کنترلرها فراهم می‌کند
 */
abstract class AccountingAdminController extends ProjectAdminController
{
    // Base controller for all Accounting admin controllers
    // Extend this class instead of ProjectAdminController directly
    // This allows centralized changes to all accounting controllers
    
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        
        // اینجا می‌تونیم تنظیمات مشترک همه کنترلرهای accounting رو بذاریم
        // مثلاً: middleware اضافی، permission check، و غیره
    }
}
