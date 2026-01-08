<?php

namespace RMS\Accounting\Http\Controllers\Api\Admin;

use RMS\Accounting\Models\Account;
use RMS\Accounting\Services\AccountService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Admin API Controller for Accounts
 * 
 * @group Admin API - Accounts
 */
class AccountsApiController
{
    protected AccountService $accountService;

    public function __construct(AccountService $accountService)
    {
        $this->accountService = $accountService;
    }

    /**
     * List all accounts
     * 
     * @queryParam search string Filter by code or name
     * @queryParam account_type string Filter by type (asset,liability,equity,revenue,expense)
     * @queryParam per_page int Items per page (default: 50)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Account::with('parent')->orderBy('code');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('account_type')) {
            $query->where('account_type', $request->account_type);
        }

        $perPage = min($request->get('per_page', 50), 100);
        $accounts = $query->paginate($perPage);

        return response()->json($accounts);
    }

    /**
     * Get account details
     */
    public function show(int $id): JsonResponse
    {
        $account = Account::with(['parent', 'children'])->findOrFail($id);

        return response()->json([
            'data' => $account,
        ]);
    }

    /**
     * Create new account
     */
    public function store(Request $request): JsonResponse
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

        return response()->json([
            'message' => trans('accounting::accounting.created_successfully'),
            'data' => $account,
        ], 201);
    }

    /**
     * Update account
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $account = Account::findOrFail($id);

        $validated = $request->validate([
            'code' => 'nullable|string|max:50|unique:accounts,code,' . $id,
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:accounts,id',
            'account_type' => 'required|in:asset,liability,equity,revenue,expense',
            'currency_code' => 'nullable|string|max:3',
            'description' => 'nullable|string',
        ]);

        $account = $this->accountService->updateAccount($account, $validated);

        return response()->json([
            'message' => trans('accounting::accounting.updated_successfully'),
            'data' => $account,
        ]);
    }

    /**
     * Delete account
     */
    public function destroy(int $id): JsonResponse
    {
        $account = Account::findOrFail($id);

        if ($account->is_system) {
            return response()->json([
                'message' => 'Cannot delete system account',
            ], 403);
        }

        if ($account->children()->exists()) {
            return response()->json([
                'message' => 'Cannot delete account with children',
            ], 422);
        }

        $account->delete();

        return response()->json([
            'message' => trans('accounting::accounting.deleted_successfully'),
        ]);
    }

    /**
     * Get account balance
     */
    public function balance(int $id, Request $request): JsonResponse
    {
        $account = Account::findOrFail($id);
        
        $balance = $this->accountService->calculateBalance(
            $account,
            $request->get('start_date'),
            $request->get('end_date')
        );

        return response()->json([
            'data' => [
                'account_id' => $account->id,
                'account_code' => $account->code,
                'account_name' => $account->name,
                'balance' => $balance,
            ],
        ]);
    }
}
