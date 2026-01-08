<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\Request;
use RMS\Accounting\Models\FiscalYear;
use RMS\Accounting\Services\FiscalYearService;
use RMS\Core\Http\Controllers\AdminController;

/**
 * کنترلر مدیریت سال‌های مالی
 */
class FiscalYearsController extends AdminController
{
    protected string $model = FiscalYear::class;
    protected string $indexView = 'accounting::admin.fiscal-years.index';
    protected string $formView = 'accounting::admin.fiscal-years.form';
    
    protected FiscalYearService $fiscalYearService;

    public function __construct(FiscalYearService $fiscalYearService)
    {
        $this->fiscalYearService = $fiscalYearService;
    }

    public function index(Request $request)
    {
        $fiscalYears = FiscalYear::orderBy('year_code', 'desc')->paginate(20);

        $currentYear = $this->fiscalYearService->getCurrentFiscalYear();

        return view($this->indexView, compact('fiscalYears', 'currentYear'));
    }

    public function form(?int $id = null)
    {
        $fiscalYear = $id ? FiscalYear::findOrFail($id) : new FiscalYear();

        return view($this->formView, compact('fiscalYear'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'year_code' => 'required|string|max:10|unique:fiscal_years,year_code',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'status' => 'required|in:open,locked,closed',
            'is_current' => 'boolean',
        ]);

        $this->fiscalYearService->createFiscalYear($validated);

        return redirect()
            ->route('admin.accounting.fiscal-years.index')
            ->with('success', trans('accounting::accounting.fiscal_year_created'));
    }

    public function update(Request $request, int $id)
    {
        $fiscalYear = FiscalYear::findOrFail($id);

        if ($fiscalYear->status === 'closed') {
            return back()->with('error', trans('accounting::accounting.cannot_edit_closed_year'));
        }

        $validated = $request->validate([
            'year_code' => 'required|string|max:10|unique:fiscal_years,year_code,' . $id,
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'status' => 'required|in:open,locked,closed',
            'is_current' => 'boolean',
        ]);

        $fiscalYear->update($validated);

        return redirect()
            ->route('admin.accounting.fiscal-years.index')
            ->with('success', trans('accounting::accounting.fiscal_year_updated'));
    }

    /**
     * بستن سال مالی
     */
    public function close(int $id)
    {
        try {
            $this->fiscalYearService->closeFiscalYear($id, auth()->id());

            return response()->json([
                'success' => true,
                'message' => trans('accounting::accounting.fiscal_year_closed')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function destroy(int $id)
    {
        $fiscalYear = FiscalYear::findOrFail($id);

        if ($fiscalYear->status !== 'open' || $fiscalYear->is_current) {
            return response()->json([
                'success' => false,
                'message' => trans('accounting::accounting.cannot_delete_fiscal_year')
            ], 403);
        }

        $fiscalYear->delete();

        return response()->json([
            'success' => true,
            'message' => trans('accounting::accounting.fiscal_year_deleted')
        ]);
    }
}
