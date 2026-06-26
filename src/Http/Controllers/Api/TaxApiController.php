<?php

namespace RMS\Accounting\Http\Controllers\Api;

use RMS\Accounting\Services\TaxService;
use RMS\Accounting\Services\Tax\TaxCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * API کنترلر برای تنظیمات و محاسبات مالیاتی
 */
class TaxApiController extends Controller
{
    protected TaxService $taxService;

    public function __construct(TaxService $taxService)
    {
        $this->taxService = $taxService;
    }

    /**
     * دریافت تنظیمات مالیاتی
     * 
     * GET /api/accounting/tax/settings
     */
    public function getSettings(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->taxService->getTaxSettings(),
        ]);
    }

    /**
     * محاسبه مالیات بر ارزش افزوده
     * 
     * POST /api/accounting/tax/calculate-vat
     * Body: {amount: float, tax_rate?: float, method?: string}
     */
    public function calculateVAT(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'method' => 'nullable|in:exclusive,inclusive',
        ]);

        $result = TaxCalculator::calculateVAT(
            $validated['amount'],
            $validated['tax_rate'] ?? null,
            $validated['method'] ?? null
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * محاسبه مالیات بر درآمد
     * 
     * POST /api/accounting/tax/calculate-income-tax
     * Body: {income: float, tax_rate?: float}
     */
    public function calculateIncomeTax(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'income' => 'required|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $result = TaxCalculator::calculateIncomeTax(
            $validated['income'],
            $validated['tax_rate'] ?? null
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * دریافت نرخ‌های VAT
     * 
     * GET /api/accounting/tax/vat-rates
     */
    public function getVATRates(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'standard' => TaxCalculator::getVATRate('standard'),
                'reduced' => TaxCalculator::getVATRate('reduced'),
                'zero' => TaxCalculator::getVATRate('zero'),
            ],
        ]);
    }

    /**
     * محاسبه مالیات قابل پرداخت (VAT Payable)
     * 
     * GET /api/accounting/tax/vat-payable?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD
     */
    public function getVATPayable(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $vatPayable = $this->taxService->calculateVATPayable(
            $validated['start_date'],
            $validated['end_date']
        );

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start' => $validated['start_date'],
                    'end' => $validated['end_date'],
                ],
                'vat_payable' => $vatPayable,
            ],
        ]);
    }

    /**
     * محاسبه مالیات چند آیتم
     * 
     * POST /api/accounting/tax/calculate-multiple
     * Body: {items: [{amount: float, tax_rate?: float}, ...]}
     */
    public function calculateMultiple(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.amount' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $result = TaxCalculator::calculateMultipleItems($validated['items']);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
