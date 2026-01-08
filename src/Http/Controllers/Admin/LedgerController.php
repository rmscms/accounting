<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use RMS\Accounting\Models\FinancialLedger;
use RMS\Accounting\Services\LedgerService;
use Illuminate\Http\Request;

/**
 * کنترلر دفتر کل
 */
class LedgerController extends AccountingAdminController
{
    /**
     * نمایش دفتر کل
     */
    public function index(Request $request, LedgerService $ledgerService)
    {
        $query = FinancialLedger::with(['account', 'document', 'fiscalYear'])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc');

        // فیلترها
        if ($request->filled('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        if ($request->filled('document_id')) {
            $query->where('document_id', $request->document_id);
        }

        if ($request->filled('from_date')) {
            $query->where('transaction_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->where('transaction_date', '<=', $request->to_date);
        }

        if ($request->filled('fiscal_year_id')) {
            $query->where('fiscal_year_id', $request->fiscal_year_id);
        }

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }

        $entries = $query->paginate(50);

        // محاسبه مجموع
        $totals = [
            'debit' => $entries->sum('debit_amount'),
            'credit' => $entries->sum('credit_amount'),
        ];

        return $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('ledger.index')
            ->withCss('vendor/accounting/admin/css/ledger.css', true)
            ->withJs('vendor/accounting/admin/js/ledger.js', true)
            ->with([
                'entries' => $entries,
                'totals' => $totals,
            ]);
    }

    /**
     * نمایش جزئیات یک ثبت
     */
    public function show(int $id)
    {
        $entry = FinancialLedger::with(['account', 'document', 'fiscalYear'])
            ->findOrFail($id);

        return $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('ledger.show')
            ->withCss('vendor/accounting/admin/css/ledger.css', true)
            ->with([
                'entry' => $entry,
            ]);
    }

    /**
     * Export به Excel
     */
    public function exportLedger(Request $request)
    {
        // TODO: Implement export functionality
        return response()->json(['message' => 'Export functionality coming soon']);
    }

    // متدهای abstract
    public function table(): string
    {
        return 'financial_ledgers';
    }

    public function modelName(): string
    {
        return FinancialLedger::class;
    }
}
