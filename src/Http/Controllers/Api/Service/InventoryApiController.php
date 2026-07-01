<?php

namespace RMS\Accounting\Http\Controllers\Api\Service;

use RMS\Accounting\Models\Account;
use RMS\Accounting\Services\COGSService;
use RMS\Accounting\Services\InventoryAdjustmentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use RMS\Core\Models\Setting;

/**
 * Service API Controller for Inventory
 * برای ثبت بهای تمام شده از پکیج inventory
 * 
 * @group Service API - Inventory
 */
class InventoryApiController
{
    protected COGSService $cogsService;
    protected InventoryAdjustmentService $inventoryAdjustmentService;

    public function __construct(
        COGSService $cogsService,
        InventoryAdjustmentService $inventoryAdjustmentService
    )
    {
        $this->cogsService = $cogsService;
        $this->inventoryAdjustmentService = $inventoryAdjustmentService;
    }

    /**
     * Record Cost of Goods Sold
     * 
     * @bodyParam order_id int required ID سفارش
     * @bodyParam product_id int required ID محصول
     * @bodyParam quantity numeric required تعداد
     * @bodyParam unit_cost numeric required بهای واحد
     * @bodyParam total_cost numeric required بهای کل
     */
    public function recordCOGS(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|integer',
            'product_id' => 'required|integer',
            'store_id' => 'required|integer',
            'sale_date' => 'required|date',
            'quantity' => 'required|numeric|min:0',
            'unit_cost' => 'required|numeric|min:0',
            'total_cost' => 'required|numeric|min:0',
            'currency_code' => 'required|string|max:3',
            'cost_method' => 'nullable|in:fifo,lifo,average,specific',
        ]);

        try {
            $cogsEntry = $this->cogsService->recordCOGS($validated);

            return response()->json([
                'success' => true,
                'message' => 'COGS recorded successfully',
                'data' => [
                    'cogs_entry_id' => $cogsEntry->id,
                    'total_cost' => $cogsEntry->total_cost,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record COGS: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function recordAdjustment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'adjustment_date' => 'required|date',
            'adjustment_type' => 'required|string|in:surplus,shortage,writedown,other',
            'warehouse_id' => 'nullable',
            'reason' => 'required|string|max:1000',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable',
            'items.*.product_name' => 'required|string',
            'items.*.sku' => 'nullable|string',
            'items.*.system_quantity' => 'required|numeric',
            'items.*.actual_quantity' => 'required|numeric',
            'items.*.unit_cost' => 'required|numeric|min:0',
        ]);

        $adjustment = $this->inventoryAdjustmentService->createAdjustment($validated);
        $this->inventoryAdjustmentService->approveAdjustment((int) $adjustment->id);

        if ($request->boolean('post_to_ledger', true)) {
            $this->inventoryAdjustmentService->postAdjustment((int) $adjustment->id, [
                'inventory_account_id' => $this->resolveInventoryAccountId(),
                'adjustment_gain_account_id' => (int) config('accounting.accounts.inventory_adjustment_gain', 1),
                'adjustment_loss_account_id' => (int) config('accounting.accounts.inventory_adjustment_loss', 1),
                'writedown_account_id' => (int) config('accounting.accounts.inventory_writedown', 1),
            ]);
            $adjustment = $adjustment->fresh();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'adjustment_id' => (int) $adjustment->id,
                'adjustment_number' => (string) $adjustment->adjustment_number,
                'status' => (string) $adjustment->status,
            ],
        ], 201);
    }

    public function reverseAdjustment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'adjustment_id' => 'required|integer|exists:inventory_adjustments,id',
            'reason' => 'required|string|max:1000',
        ]);

        $reversal = $this->inventoryAdjustmentService->reverseAdjustment(
            (int) $validated['adjustment_id'],
            (string) $validated['reason']
        );

        return response()->json([
            'success' => true,
            'data' => [
                'adjustment_id' => (int) $reversal->id,
                'adjustment_number' => (string) $reversal->adjustment_number,
                'status' => (string) $reversal->status,
            ],
        ]);
    }

    protected function resolveInventoryAccountId(): int
    {
        $configuredCode = trim((string) Setting::get('accounting.system_accounts.assets.inventory', ''));
        if ($configuredCode === '') {
            throw ValidationException::withMessages([
                'inventory_account_id' => (string) trans('accounting::accounting.sample_data.preflight.inventory', [
                    'code' => '—',
                ]),
            ]);
        }

        $inventoryId = (int) (Account::query()
            ->where('account_type', Account::TYPE_ASSET)
            ->where('active', true)
            ->where('code', $configuredCode)
            ->value('id') ?? 0);
        if ($inventoryId <= 0) {
            throw ValidationException::withMessages([
                'inventory_account_id' => (string) trans('accounting::accounting.sample_data.preflight.inventory', [
                    'code' => $configuredCode,
                ]),
            ]);
        }

        return $inventoryId;
    }
}
