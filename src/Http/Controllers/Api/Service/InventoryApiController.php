<?php

namespace RMS\Accounting\Http\Controllers\Api\Service;

use RMS\Accounting\Services\COGSService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Service API Controller for Inventory
 * برای ثبت بهای تمام شده از پکیج inventory
 * 
 * @group Service API - Inventory
 */
class InventoryApiController
{
    protected COGSService $cogsService;

    public function __construct(COGSService $cogsService)
    {
        $this->cogsService = $cogsService;
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
}
