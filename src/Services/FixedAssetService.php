<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\FixedAsset;
use RMS\Accounting\Models\FixedAssetCategory;
use RMS\Accounting\Models\DepreciationSchedule;
use RMS\Accounting\Models\DepreciationEntry;
use RMS\Accounting\Models\Account;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FixedAssetService
{
    protected LedgerService $ledgerService;

    public function __construct(LedgerService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
    }

    /**
     * ثبت دارایی ثابت جدید + ثبت سند خرید
     */
    public function createAsset(array $data): FixedAsset
    {
        return DB::transaction(function () use ($data) {
            // ایجاد دارایی
            $asset = FixedAsset::create([
                'asset_code' => $data['asset_code'] ?? FixedAsset::generateAssetCode(),
                'name' => $data['name'],
                'category_id' => $data['category_id'],
                'purchase_date' => $data['purchase_date'],
                'purchase_price' => $data['purchase_price'],
                'useful_life_years' => $data['useful_life_years'],
                'useful_life_months' => $data['useful_life_months'] ?? 0,
                'depreciation_method' => $data['depreciation_method'] ?? 'straight_line',
                'declining_balance_rate' => $data['declining_balance_rate'] ?? null,
                'total_units' => $data['total_units'] ?? null,
                'salvage_value' => $data['salvage_value'] ?? 0,
                'accumulated_depreciation' => 0,
                'book_value' => $data['purchase_price'],
                'asset_account_id' => $data['asset_account_id'] ?? null,
                'depreciation_account_id' => $data['depreciation_account_id'] ?? null,
                'accumulated_depreciation_account_id' => $data['accumulated_depreciation_account_id'] ?? null,
                'location' => $data['location'] ?? null,
                'serial_number' => $data['serial_number'] ?? null,
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'active',
            ]);

            // تولید برنامه استهلاک
            if ($data['generate_schedule'] ?? true) {
                $this->generateDepreciationSchedule($asset->id);
            }

            // ثبت سند خرید در دفاتر (اختیاری)
            if ($data['record_purchase'] ?? false) {
                $this->recordAssetPurchase($asset, $data);
            }

            return $asset->fresh();
        });
    }

    /**
     * ثبت سند خرید دارایی
     */
    protected function recordAssetPurchase(FixedAsset $asset, array $data): void
    {
        $category = $asset->category;
        $assetAccountId = $asset->asset_account_id ?? $category->asset_account_id;

        if (!$assetAccountId) {
            throw new \Exception('Asset account not specified');
        }

        // Debit: Asset Account
        // Credit: Cash/Bank or Accounts Payable
        $entries = [
            [
                'account_id' => $assetAccountId,
                'debit' => $asset->purchase_price,
                'credit' => 0,
                'description' => "خرید دارایی ثابت: {$asset->name}",
            ],
            [
                'account_id' => $data['payment_account_id'], // حساب پرداخت (بانک/صندوق)
                'debit' => 0,
                'credit' => $asset->purchase_price,
                'description' => "پرداخت بابت خرید دارایی: {$asset->name}",
            ],
        ];

        $this->ledgerService->recordTransaction([
            'document_type' => 'asset_purchase',
            'reference_type' => 'fixed_asset',
            'reference_id' => $asset->id,
            'description' => "خرید دارایی ثابت: {$asset->name} - کد: {$asset->asset_code}",
        ], $entries);
    }

    /**
     * تولید برنامه استهلاک
     */
    public function generateDepreciationSchedule(int $assetId): array
    {
        $asset = FixedAsset::findOrFail($assetId);

        if ($asset->status !== 'active') {
            throw new \Exception('Cannot generate schedule for inactive asset');
        }

        // پاک کردن برنامه قبلی (فقط اگر ثبت نشده باشند)
        DepreciationSchedule::where('fixed_asset_id', $assetId)
            ->where('posted', false)
            ->delete();

        $schedules = [];
        $totalMonths = ($asset->useful_life_years * 12) + $asset->useful_life_months;
        $startDate = Carbon::parse($asset->purchase_date);
        $bookValue = $asset->purchase_price;

        for ($month = 1; $month <= $totalMonths; $month++) {
            $periodDate = $startDate->copy()->addMonths($month)->startOfMonth();
            $depreciation = $this->calculateDepreciation($asset, $periodDate, $bookValue, $month, $totalMonths);

            $closingBookValue = $bookValue - $depreciation;

            $schedule = DepreciationSchedule::create([
                'fixed_asset_id' => $assetId,
                'period_date' => $periodDate,
                'opening_book_value' => $bookValue,
                'depreciation_amount' => $depreciation,
                'closing_book_value' => $closingBookValue,
                'posted' => false,
            ]);

            $schedules[] = $schedule;
            $bookValue = $closingBookValue;

            // توقف در salvage value
            if ($closingBookValue <= $asset->salvage_value) {
                break;
            }
        }

        return $schedules;
    }

    /**
     * محاسبه استهلاک دوره
     */
    public function calculateDepreciation(
        FixedAsset $asset,
        Carbon $periodDate,
        float $currentBookValue,
        int $currentPeriod,
        int $totalPeriods
    ): float {
        $depreciableAmount = $asset->purchase_price - $asset->salvage_value;

        switch ($asset->depreciation_method) {
            case 'straight_line':
                // (Cost - Salvage) / Useful Life
                return $depreciableAmount / $totalPeriods;

            case 'declining_balance':
                // Book Value * Rate
                $rate = $asset->declining_balance_rate / 100;
                $depreciation = $currentBookValue * $rate / 12; // ماهانه
                // نباید از salvage value کمتر شود
                return min($depreciation, $currentBookValue - $asset->salvage_value);

            case 'units_of_production':
                // (Cost - Salvage) * (Units Produced / Total Units)
                // این متد نیاز به ورودی واحدهای تولید شده دارد
                // برای تولید خودکار از straight_line استفاده می‌کنیم
                return $depreciableAmount / $totalPeriods;

            default:
                return $depreciableAmount / $totalPeriods;
        }
    }

    /**
     * ثبت استهلاک در دفاتر
     */
    public function recordDepreciation(int $assetId, string $periodDate): DepreciationEntry
    {
        return DB::transaction(function () use ($assetId, $periodDate) {
            $asset = FixedAsset::findOrFail($assetId);
            $category = $asset->category;

            // یافتن schedule مربوطه
            $schedule = DepreciationSchedule::where('fixed_asset_id', $assetId)
                ->whereDate('period_date', $periodDate)
                ->first();

            if (!$schedule) {
                throw new \Exception('Depreciation schedule not found for this period');
            }

            if ($schedule->posted) {
                throw new \Exception('Depreciation already recorded for this period');
            }

            // تعیین حساب‌ها
            $depreciationAccountId = $asset->depreciation_account_id
                ?? $category->depreciation_account_id;

            $accumulatedDepreciationAccountId = $asset->accumulated_depreciation_account_id
                ?? $category->accumulated_depreciation_account_id;

            if (!$depreciationAccountId || !$accumulatedDepreciationAccountId) {
                throw new \Exception('Depreciation accounts not configured');
            }

            // ثبت سند: Debit: Depreciation Expense, Credit: Accumulated Depreciation
            $entries = [
                [
                    'account_id' => $depreciationAccountId,
                    'debit' => $schedule->depreciation_amount,
                    'credit' => 0,
                    'description' => "استهلاک دارایی: {$asset->name} - دوره: {$periodDate}",
                ],
                [
                    'account_id' => $accumulatedDepreciationAccountId,
                    'debit' => 0,
                    'credit' => $schedule->depreciation_amount,
                    'description' => "استهلاک انباشته: {$asset->name} - دوره: {$periodDate}",
                ],
            ];

            $document = $this->ledgerService->recordTransaction([
                'document_type' => 'depreciation',
                'reference_type' => 'fixed_asset',
                'reference_id' => $assetId,
                'description' => "استهلاک دارایی ثابت: {$asset->name} - کد: {$asset->asset_code} - دوره: {$periodDate}",
            ], $entries);

            // به‌روزرسانی schedule
            $schedule->update([
                'posted' => true,
                'accounting_document_id' => $document->id,
            ]);

            // به‌روزرسانی دارایی
            $asset->increment('accumulated_depreciation', $schedule->depreciation_amount);
            $asset->update([
                'book_value' => $asset->purchase_price - $asset->accumulated_depreciation,
            ]);

            // بررسی استهلاک کامل
            if ($asset->book_value <= $asset->salvage_value) {
                $asset->update(['status' => 'fully_depreciated']);
            }

            // ثبت در جدول depreciation_entries
            $entry = DepreciationEntry::create([
                'fixed_asset_id' => $assetId,
                'depreciation_schedule_id' => $schedule->id,
                'entry_date' => $periodDate,
                'depreciation_amount' => $schedule->depreciation_amount,
                'accounting_document_id' => $document->id,
                'description' => "استهلاک دوره {$periodDate}",
            ]);

            return $entry;
        });
    }

    /**
     * فروش/خروج دارایی + محاسبه سود/زیان
     */
    public function disposeAsset(int $assetId, array $data): FixedAsset
    {
        return DB::transaction(function () use ($assetId, $data) {
            $asset = FixedAsset::findOrFail($assetId);

            if ($asset->status === 'disposed') {
                throw new \Exception('Asset already disposed');
            }

            $disposalDate = $data['disposal_date'];
            $disposalValue = $data['disposal_value'];
            $bookValue = $asset->book_value;

            // محاسبه سود/زیان
            $gainLoss = $disposalValue - $bookValue;

            // به‌روزرسانی دارایی
            $asset->update([
                'status' => 'disposed',
                'disposal_date' => $disposalDate,
                'disposal_value' => $disposalValue,
            ]);

            // ثبت سند فروش
            $category = $asset->category;
            $assetAccountId = $asset->asset_account_id ?? $category->asset_account_id;
            $accumulatedDepreciationAccountId = $asset->accumulated_depreciation_account_id
                ?? $category->accumulated_depreciation_account_id;

            $entries = [
                // Debit: Cash/Bank (فروش)
                [
                    'account_id' => $data['cash_account_id'],
                    'debit' => $disposalValue,
                    'credit' => 0,
                    'description' => "دریافت از فروش دارایی: {$asset->name}",
                ],
                // Debit: Accumulated Depreciation
                [
                    'account_id' => $accumulatedDepreciationAccountId,
                    'debit' => $asset->accumulated_depreciation,
                    'credit' => 0,
                    'description' => "استهلاک انباشته: {$asset->name}",
                ],
                // Credit: Asset Account
                [
                    'account_id' => $assetAccountId,
                    'debit' => 0,
                    'credit' => $asset->purchase_price,
                    'description' => "خروج دارایی: {$asset->name}",
                ],
            ];

            // ثبت سود یا زیان
            if ($gainLoss > 0) {
                // سود
                $entries[] = [
                    'account_id' => $data['gain_account_id'], // حساب سود فروش دارایی
                    'debit' => 0,
                    'credit' => $gainLoss,
                    'description' => "سود فروش دارایی: {$asset->name}",
                ];
            } elseif ($gainLoss < 0) {
                // زیان
                $entries[] = [
                    'account_id' => $data['loss_account_id'], // حساب زیان فروش دارایی
                    'debit' => abs($gainLoss),
                    'credit' => 0,
                    'description' => "زیان فروش دارایی: {$asset->name}",
                ];
            }

            $this->ledgerService->recordTransaction([
                'document_type' => 'asset_disposal',
                'reference_type' => 'fixed_asset',
                'reference_id' => $assetId,
                'description' => "فروش/خروج دارایی ثابت: {$asset->name} - کد: {$asset->asset_code}",
            ], $entries);

            return $asset->fresh();
        });
    }

    /**
     * ارزیابی مجدد دارایی (IAS 16 Revaluation)
     */
    public function revaluateAsset(int $assetId, float $newValue, string $revaluationDate): FixedAsset
    {
        return DB::transaction(function () use ($assetId, $newValue, $revaluationDate) {
            $asset = FixedAsset::findOrFail($assetId);

            if ($asset->status !== 'active') {
                throw new \Exception('Cannot revaluate inactive asset');
            }

            $currentBookValue = $asset->book_value;
            $revaluationAmount = $newValue - $currentBookValue;

            if ($revaluationAmount == 0) {
                throw new \Exception('No revaluation needed');
            }

            // به‌روزرسانی دارایی
            $asset->update([
                'book_value' => $newValue,
                'purchase_price' => $asset->purchase_price + $revaluationAmount,
            ]);

            // ثبت سند ارزیابی مجدد
            $category = $asset->category;
            $assetAccountId = $asset->asset_account_id ?? $category->asset_account_id;

            if ($revaluationAmount > 0) {
                // افزایش ارزش: Debit: Asset, Credit: Revaluation Surplus (Equity)
                $entries = [
                    [
                        'account_id' => $assetAccountId,
                        'debit' => $revaluationAmount,
                        'credit' => 0,
                        'description' => "افزایش ارزش دارایی (ارزیابی مجدد): {$asset->name}",
                    ],
                    [
                        'account_id' => config('accounting.revaluation_surplus_account_id'),
                        'debit' => 0,
                        'credit' => $revaluationAmount,
                        'description' => "مازاد ارزیابی مجدد: {$asset->name}",
                    ],
                ];
            } else {
                // کاهش ارزش: Debit: Revaluation Loss (Expense), Credit: Asset
                $entries = [
                    [
                        'account_id' => config('accounting.revaluation_loss_account_id'),
                        'debit' => abs($revaluationAmount),
                        'credit' => 0,
                        'description' => "زیان ارزیابی مجدد: {$asset->name}",
                    ],
                    [
                        'account_id' => $assetAccountId,
                        'debit' => 0,
                        'credit' => abs($revaluationAmount),
                        'description' => "کاهش ارزش دارایی (ارزیابی مجدد): {$asset->name}",
                    ],
                ];
            }

            $this->ledgerService->recordTransaction([
                'document_type' => 'asset_revaluation',
                'reference_type' => 'fixed_asset',
                'reference_id' => $assetId,
                'description' => "ارزیابی مجدد دارایی ثابت: {$asset->name} - کد: {$asset->asset_code}",
            ], $entries);

            return $asset->fresh();
        });
    }
}
