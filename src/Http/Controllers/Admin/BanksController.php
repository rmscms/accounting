<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\Request;
use RMS\Accounting\Models\Bank;
use RMS\Core\Http\Controllers\AdminController;
use RMS\Core\Data\Field;

/**
 * کنترلر مدیریت بانک‌ها
 */
class BanksController extends AdminController
{
    protected string $model = Bank::class;
    protected string $indexView = 'accounting::admin.banks.index';
    protected string $formView = 'accounting::admin.banks.form';

    public function index(Request $request)
    {
        $query = Bank::with('account')->orderBy('name');

        if ($request->filled('active')) {
            $query->where('active', $request->active);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('branch', 'like', "%{$request->search}%")
                  ->orWhere('account_number', 'like', "%{$request->search}%");
            });
        }

        $banks = $query->paginate(50);

        return view($this->indexView, compact('banks'));
    }

    public function form(?int $id = null)
    {
        $bank = $id ? Bank::findOrFail($id) : new Bank();

        $accounts = \RMS\Accounting\Models\Account::where('account_type', 'asset')
            ->where('active', true)
            ->orderBy('code')
            ->pluck('name', 'id');

        return view($this->formView, compact('bank', 'accounts'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'branch' => 'nullable|string|max:255',
            'account_number' => 'required|string|max:50',
            'iban' => 'nullable|string|max:50',
            'swift_code' => 'nullable|string|max:20',
            'account_id' => 'required|exists:accounts,id',
            'active' => 'boolean',
        ]);

        Bank::create($validated);

        return redirect()
            ->route('admin.accounting.banks.index')
            ->with('success', trans('accounting::accounting.bank_created'));
    }

    public function update(Request $request, int $id)
    {
        $bank = Bank::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'branch' => 'nullable|string|max:255',
            'account_number' => 'required|string|max:50',
            'iban' => 'nullable|string|max:50',
            'swift_code' => 'nullable|string|max:20',
            'account_id' => 'required|exists:accounts,id',
            'active' => 'boolean',
        ]);

        $bank->update($validated);

        return redirect()
            ->route('admin.accounting.banks.index')
            ->with('success', trans('accounting::accounting.bank_updated'));
    }

    public function destroy(int $id)
    {
        $bank = Bank::findOrFail($id);

        // بررسی وجود تراکنش
        if ($bank->payments()->exists() || $bank->expenses()->exists()) {
            return response()->json([
                'success' => false,
                'message' => trans('accounting::accounting.bank_has_transactions')
            ], 403);
        }

        $bank->delete();

        return response()->json([
            'success' => true,
            'message' => trans('accounting::accounting.bank_deleted')
        ]);
    }
}
