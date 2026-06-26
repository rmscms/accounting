<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use RMS\Accounting\Models\FinancialLedger;
use RMS\Accounting\Models\Account;
use RMS\Accounting\Services\AccountingDateInputNormalizer;
use RMS\Accounting\Support\AccountingDateUi;
use Illuminate\Http\Request;

/**
 * دفتر روزنامه: فهرست خطوط دفتر مالی (\RMS\Accounting\Models\FinancialLedger) به ترتیب زمان.
 * گزارش «دفتر کل» به تفکیک حساب در ReportsController::generalLedger است.
 */
class LedgerController extends AccountingAdminController
{
    /**
     * لیست ثبت‌ها به ترتیب created_at (مفهوم نزدیک به دفتر روزنامه).
     */
    public function index(Request $request)
    {
        $query = FinancialLedger::with(['account', 'document'])
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc');

        // فیلترها
        if ($request->filled('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        if ($request->filled('document_id')) {
            $query->where('accounting_document_id', $request->document_id);
        }

        $dateNorm = app(AccountingDateInputNormalizer::class);

        if ($request->filled('from_date')) {
            $fromDate = $dateNorm->normalizeFilterDateToGregorian((string) $request->from_date);
            if ($fromDate) {
                $query->where('created_at', '>=', $fromDate . (strlen($fromDate) === 10 ? ' 00:00:00' : ''));
            }
        }

        if ($request->filled('to_date')) {
            $toDate = $dateNorm->normalizeFilterDateToGregorian((string) $request->to_date);
            if ($toDate) {
                $suffix = strlen($toDate) === 10 ? ' 23:59:59' : '';
                $query->where('created_at', '<=', $toDate . $suffix);
            }
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

        // دریافت لیست حساب‌ها برای فیلتر
        $accounts = Account::where('active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $plugins = AccountingDateUi::calendarMode() === AccountingDateUi::MODE_JALALI
            ? ['persian-datepicker']
            : [];

        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('ledger/index')
            ->withPlugins($plugins)
            ->withCss('vendor/accounting/admin/css/ledger.css', true)
            ->withVariables([
                'entries' => $entries,
                'totals' => $totals,
                'accounts' => $accounts,
            ]);

        return $this->view();
    }

    /**
     * جزئیات یک خط ثبت (دفتر روزنامه).
     */
    public function show(int $id)
    {
        $entry = FinancialLedger::with(['account', 'document'])
            ->findOrFail($id);

        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('ledger.show')
            ->withCss('vendor/accounting/admin/css/ledger.css', true)
            ->withVariables([
                'entry' => $entry,
            ]);

        return $this->view();
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
