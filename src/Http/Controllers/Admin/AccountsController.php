<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\Request;
use RMS\Accounting\Models\Account;
use RMS\Accounting\Services\AccountService;
use RMS\Core\Http\Controllers\AdminController;

/**
 * کنترلر مدیریت حساب‌ها (Chart of Accounts)
 */
class AccountsController extends AdminController
{
    protected string $model = Account::class;
    protected string $indexView = 'accounting::admin.accounts.index';
    protected string $formView = 'accounting::admin.accounts.form';
    
    protected AccountService $accountService;

    public function __construct(AccountService $accountService)
    {
        $this->accountService = $accountService;
    }

    /**
     * لیست حساب‌ها
     */
    public function index(Request $request)
    {
        $query = Account::with('parent')->orderBy('code');

        if ($request->filled('account_type')) {
            $query->where('account_type', $request->account_type);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('code', 'like', "%{$request->search}%")
                  ->orWhere('name', 'like', "%{$request->search}%");
            });
        }

        $accounts = $query->paginate(50);

        return view($this->indexView, compact('accounts'));
    }

    /**
     * درخت حساب‌ها
     */
    public function tree()
    {
        $tree = $this->accountService->getAccountTree();

        return view('accounting::admin.accounts.tree', compact('tree'));
    }

    /**
     * فرم ایجاد/ویرایش
     */
    public function form(?int $id = null)
    {
        $account = $id ? Account::findOrFail($id) : new Account();
        
        $parentAccounts = Account::whereNull('parent_id')
            ->orWhere('level', '<', 4)
            ->orderBy('code')
            ->get();

        return view($this->formView, compact('account', 'parentAccounts'));
    }

    /**
     * ذخیره حساب
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:50|unique:accounts,code',
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:accounts,id',
            'account_type' => 'required|in:asset,liability,equity,revenue,expense',
            'currency_code' => 'nullable|string|max:3',
            'description' => 'nullable|string',
        ]);

        $account = $this->accountService->createAccount($validated);

        return redirect()
            ->route('admin.accounting.accounts.index')
            ->with('success', trans('accounting::accounting.account_created'));
    }

    /**
     * بروزرسانی حساب
     */
    public function update(Request $request, int $id)
    {
        $account = Account::findOrFail($id);

        if ($account->is_system) {
            return back()->with('error', trans('accounting::accounting.cannot_edit_system_account'));
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'active' => 'boolean',
        ]);

        $account->update($validated);

        return redirect()
            ->route('admin.accounting.accounts.index')
            ->with('success', trans('accounting::accounting.account_updated'));
    }

    /**
     * گردش حساب
     */
    public function statement(Request $request, int $id)
    {
        $account = Account::findOrFail($id);

        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');
        $storeId = $request->get('store_id');

        $ledgers = $this->accountService->getAccountStatement(
            accountId: $id,
            fromDate: $fromDate,
            toDate: $toDate,
            storeId: $storeId
        );

        $balance = $this->accountService->getAccountBalance(
            accountId: $id,
            fromDate: $fromDate,
            toDate: $toDate,
            storeId: $storeId
        );

        return view('accounting::admin.accounts.statement', compact(
            'account',
            'ledgers',
            'balance',
            'fromDate',
            'toDate',
            'storeId'
        ));
    }

    /**
     * حذف حساب
     */
    public function destroy(int $id)
    {
        $account = Account::findOrFail($id);

        if ($account->is_system) {
            return response()->json([
                'success' => false,
                'message' => trans('accounting::accounting.cannot_delete_system_account')
            ], 403);
        }

        if ($account->ledgers()->exists()) {
            return response()->json([
                'success' => false,
                'message' => trans('accounting::accounting.account_has_transactions')
            ], 403);
        }

        $account->delete();

        return response()->json([
            'success' => true,
            'message' => trans('accounting::accounting.account_deleted')
        ]);
    }
}
