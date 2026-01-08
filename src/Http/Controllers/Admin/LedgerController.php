<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\Request;
use RMS\Accounting\Services\LedgerService;
use RMS\Accounting\Models\FinancialLedger;
use RMS\Core\Http\Controllers\AdminController;

/**
 * کنترلر دفتر کل
 */
class LedgerController extends AdminController
{
    protected LedgerService $ledgerService;

    public function __construct(LedgerService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
    }

    /**
     * نمایش دفتر کل
     */
    public function index(Request $request)
    {
        $query = FinancialLedger::with(['account', 'document'])
            ->orderBy('created_at', 'desc');

        // فیلترها
        if ($request->filled('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        if ($request->filled('event_type')) {
            $query->where('event_type', $request->event_type);
        }

        $ledgers = $query->paginate(100);

        // محاسبه مجموع
        $totals = [
            'debit' => $query->sum('debit_amount'),
            'credit' => $query->sum('credit_amount'),
        ];

        // لیست حساب‌ها برای فیلتر
        $accounts = \RMS\Accounting\Models\Account::where('active', true)
            ->orderBy('code')
            ->pluck('name', 'id');

        return view('accounting::admin.ledger.index', compact('ledgers', 'totals', 'accounts'));
    }

    /**
     * خروجی Excel
     */
    public function export(Request $request)
    {
        // پیاده‌سازی export در نسخه بعد
        return response()->json(['message' => 'Export functionality']);
    }
}
