<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\InventoryAdjustment;
use RMS\Accounting\Models\InventoryAdjustmentItem;
use Illuminate\Support\Facades\DB;
use RMS\Accounting\Support\InteractsWithAuditActor;

class InventoryAdjustmentService
{
    use InteractsWithAuditActor;

    protected LedgerService $ledgerService;

    public function __construct(LedgerService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
    }

    /**
     * ایجاد سند تعدیل موجودی
     */
    public function createAdjustment(array $data): InventoryAdjustment
    {
        return DB::transaction(function () use ($data) {
            $createPayload = [
                'adjustment_number' => $data['adjustment_number'] ?? InventoryAdjustment::generateAdjustmentNumber(),
                'adjustment_date' => $data['adjustment_date'],
                'adjustment_type' => $data['adjustment_type'],
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'total_adjustment_value' => 0,
                'status' => 'draft',
            ];
            $createPayload = $this->stampAudit($createPayload, 'inventory_adjustments', 'created');

            $adjustment = InventoryAdjustment::create($createPayload);

            // افزودن اقلام اگر وجود داشته باشد
            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $index => $itemData) {
                    $this->addItem($adjustment->id, array_merge($itemData, ['line_number' => $index + 1]));
                }
            }

            return $adjustment->fresh('items');
        });
    }

    /**
     * افزودن قلم به تعدیل
     */
    public function addItem(int $adjustmentId, array $itemData): InventoryAdjustmentItem
    {
        $adjustment = InventoryAdjustment::findOrFail($adjustmentId);

        if ($adjustment->status !== 'draft') {
            throw new \Exception('Cannot modify approved or posted adjustment');
        }

        $systemQuantity = $itemData['system_quantity'] ?? 0;
        $actualQuantity = $itemData['actual_quantity'] ?? 0;
        $differenceQuantity = $actualQuantity - $systemQuantity;
        $unitCost = $itemData['unit_cost'] ?? 0;
        $adjustmentValue = $differenceQuantity * $unitCost;

        $item = InventoryAdjustmentItem::create([
            'inventory_adjustment_id' => $adjustmentId,
            'line_number' => $itemData['line_number'] ?? ($adjustment->items()->max('line_number') + 1),
            'product_id' => $itemData['product_id'] ?? null,
            'product_type' => $itemData['product_type'] ?? null,
            'product_name' => $itemData['product_name'],
            'sku' => $itemData['sku'] ?? null,
            'system_quantity' => $systemQuantity,
            'actual_quantity' => $actualQuantity,
            'difference_quantity' => $differenceQuantity,
            'unit_cost' => $unitCost,
            'adjustment_value' => $adjustmentValue,
            'reason' => $itemData['reason'] ?? null,
        ]);

        // محاسبه مجدد مجموع
        $this->calculateAdjustmentValue($adjustmentId);

        return $item;
    }

    /**
     * محاسبه ارزش کل تعدیل
     */
    public function calculateAdjustmentValue(int $adjustmentId): float
    {
        $adjustment = InventoryAdjustment::findOrFail($adjustmentId);

        $totalValue = $adjustment->items()->sum('adjustment_value');

        $adjustment->update([
            'total_adjustment_value' => $totalValue,
        ]);

        return $totalValue;
    }

    /**
     * تایید تعدیل
     */
    public function approveAdjustment(int $adjustmentId): InventoryAdjustment
    {
        return DB::transaction(function () use ($adjustmentId) {
            $adjustment = InventoryAdjustment::findOrFail($adjustmentId);

            if ($adjustment->status !== 'draft') {
                throw new \Exception('Only draft adjustments can be approved');
            }

            if ($adjustment->items->isEmpty()) {
                throw new \Exception('Adjustment must have at least one item');
            }

            $approvePayload = [
                'status' => 'approved',
                'approved_at' => now(),
            ];
            $approvePayload = $this->stampAudit($approvePayload, 'inventory_adjustments', 'approved');

            $adjustment->update($approvePayload);

            return $adjustment->fresh();
        });
    }

    /**
     * ثبت تعدیل در دفاتر
     */
    public function postAdjustment(int $adjustmentId, array $accountIds): InventoryAdjustment
    {
        return DB::transaction(function () use ($adjustmentId, $accountIds) {
            $adjustment = InventoryAdjustment::with('items')->findOrFail($adjustmentId);

            if ($adjustment->status === 'posted') {
                throw new \Exception('Adjustment already posted');
            }

            if ($adjustment->status !== 'approved') {
                throw new \Exception('Adjustment must be approved before posting');
            }

            // محاسبه مجموع افزایش و کاهش
            $increaseValue = 0;
            $decreaseValue = 0;

            foreach ($adjustment->items as $item) {
                if ($item->adjustment_value > 0) {
                    $increaseValue += $item->adjustment_value;
                } else {
                    $decreaseValue += abs($item->adjustment_value);
                }
            }

            $entries = [];

            // افزایش موجودی: Debit: Inventory, Credit: Inventory Adjustment Gain
            if ($increaseValue > 0) {
                $entries[] = [
                    'account_id' => $accountIds['inventory_account_id'],
                    'debit' => $increaseValue,
                    'credit' => 0,
                    'description' => "افزایش موجودی - {$adjustment->adjustment_type}",
                ];
                $entries[] = [
                    'account_id' => $accountIds['adjustment_gain_account_id'],
                    'debit' => 0,
                    'credit' => $increaseValue,
                    'description' => "سود تعدیل موجودی",
                ];
            }

            // کاهش موجودی: Debit: Inventory Adjustment Loss/Expense, Credit: Inventory
            if ($decreaseValue > 0) {
                $lossAccountId = $adjustment->adjustment_type === 'writedown'
                    ? $accountIds['writedown_account_id']
                    : $accountIds['adjustment_loss_account_id'];

                $entries[] = [
                    'account_id' => $lossAccountId,
                    'debit' => $decreaseValue,
                    'credit' => 0,
                    'description' => "کاهش موجودی - {$adjustment->adjustment_type}",
                ];
                $entries[] = [
                    'account_id' => $accountIds['inventory_account_id'],
                    'debit' => 0,
                    'credit' => $decreaseValue,
                    'description' => "زیان/هزینه تعدیل موجودی",
                ];
            }

            if (empty($entries)) {
                throw new \Exception('No adjustment value to post');
            }

            // ثبت سند
            $document = $this->ledgerService->recordTransaction([
                'document_type' => 'inventory_adjustment',
                'reference_type' => 'inventory_adjustment',
                'reference_id' => $adjustmentId,
                'description' => "تعدیل موجودی کالا - {$adjustment->adjustment_type} - شماره: {$adjustment->adjustment_number}",
            ], $entries);

            // به‌روزرسانی adjustment
            $postPayload = [
                'status' => 'posted',
                'accounting_document_id' => $document->id,
                'posted_at' => now(),
            ];
            $postPayload = $this->stampAudit($postPayload, 'inventory_adjustments', 'posted');

            $adjustment->update($postPayload);

            return $adjustment->fresh();
        });
    }

    /**
     * برگشت تعدیل
     */
    public function reverseAdjustment(int $adjustmentId, string $reason): InventoryAdjustment
    {
        return DB::transaction(function () use ($adjustmentId, $reason) {
            $adjustment = InventoryAdjustment::findOrFail($adjustmentId);

            if ($adjustment->status !== 'posted') {
                throw new \Exception('Can only reverse posted adjustments');
            }

            // ایجاد تعدیل معکوس
            $reversalPayload = [
                'adjustment_number' => InventoryAdjustment::generateAdjustmentNumber(),
                'adjustment_date' => now()->toDateString(),
                'adjustment_type' => 'other',
                'warehouse_id' => $adjustment->warehouse_id,
                'reason' => "برگشت تعدیل: {$adjustment->adjustment_number} - {$reason}",
                'notes' => $reason,
                'total_adjustment_value' => -$adjustment->total_adjustment_value,
                'status' => 'draft',
            ];
            $reversalPayload = $this->stampAudit($reversalPayload, 'inventory_adjustments', 'created');

            $reversalAdjustment = InventoryAdjustment::create($reversalPayload);

            // ایجاد اقلام معکوس
            foreach ($adjustment->items as $index => $originalItem) {
                InventoryAdjustmentItem::create([
                    'inventory_adjustment_id' => $reversalAdjustment->id,
                    'line_number' => $index + 1,
                    'product_id' => $originalItem->product_id,
                    'product_type' => $originalItem->product_type,
                    'product_name' => $originalItem->product_name,
                    'sku' => $originalItem->sku,
                    'system_quantity' => $originalItem->actual_quantity, // معکوس
                    'actual_quantity' => $originalItem->system_quantity, // معکوس
                    'difference_quantity' => -$originalItem->difference_quantity,
                    'unit_cost' => $originalItem->unit_cost,
                    'adjustment_value' => -$originalItem->adjustment_value,
                    'reason' => "برگشت: " . ($originalItem->reason ?? ''),
                ]);
            }

            return $reversalAdjustment->fresh('items');
        });
    }

    /**
     * حذف قلم
     */
    public function deleteItem(int $adjustmentId, int $itemId): void
    {
        $adjustment = InventoryAdjustment::findOrFail($adjustmentId);

        if ($adjustment->status !== 'draft') {
            throw new \Exception('Cannot modify approved or posted adjustment');
        }

        InventoryAdjustmentItem::where('inventory_adjustment_id', $adjustmentId)
            ->where('id', $itemId)
            ->delete();

        // محاسبه مجدد مجموع
        $this->calculateAdjustmentValue($adjustmentId);
    }
}
