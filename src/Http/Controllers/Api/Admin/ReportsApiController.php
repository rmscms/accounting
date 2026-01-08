<?php

namespace RMS\Accounting\Http\Controllers\Api\Admin;

use RMS\Accounting\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

/**
 * Admin API Controller for Reports
 * 
 * @group Admin API - Reports
 */
class ReportsApiController
{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Get dashboard statistics
     */
    public function dashboard(Request $request): JsonResponse
    {
        $storeId = $request->get('store_id', 1);
        $from = $request->get('from', Carbon::now()->startOfMonth());
        $to = $request->get('to', Carbon::now());

        $data = [
            'income_statement' => $this->reportService->incomeStatement(
                $storeId,
                Carbon::parse($from),
                Carbon::parse($to)
            ),
            'accounts_receivable' => $this->reportService->accountsReceivableAging(
                $storeId,
                Carbon::parse($to)
            ),
            'sales_performance' => $this->reportService->salesPerformance(
                $storeId,
                Carbon::parse($from),
                Carbon::parse($to)
            ),
        ];

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Get trial balance
     */
    public function trialBalance(Request $request): JsonResponse
    {
        $storeId = $request->get('store_id', 1);
        $asOfDate = $request->get('as_of_date', Carbon::now());

        $balanceSheet = $this->reportService->balanceSheet(
            $storeId,
            Carbon::parse($asOfDate)
        );

        return response()->json([
            'data' => $balanceSheet,
        ]);
    }
}
