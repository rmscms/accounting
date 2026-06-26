<?php

namespace RMS\Accounting\Services;

use Illuminate\Support\Facades\DB;
use RMS\Accounting\Models\PurchaseOrder;
use RMS\Accounting\Models\PurchaseOrderItem;

/**
 * CRUD اقلام سفارش خرید در ادمین + هم‌ترازی جمع سفارش.
 */
final class PurchaseOrderItemAdminService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createLine(PurchaseOrder $order, array $data): PurchaseOrderItem
    {
        return DB::transaction(function () use ($order, $data) {
            $line = new PurchaseOrderItem;
            $line->purchase_order_id = $order->id;
            $this->fillLineFromInput($line, $data);
            $line->save();
            $this->refreshOrderTotals($order->fresh());

            return $line->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateLine(PurchaseOrder $order, PurchaseOrderItem $item, array $data): PurchaseOrderItem
    {
        if ((int) $item->purchase_order_id !== (int) $order->id) {
            abort(404);
        }

        return DB::transaction(function () use ($order, $item, $data) {
            $this->fillLineFromInput($item, $data);
            $item->save();
            $this->refreshOrderTotals($order->fresh());

            return $item->fresh();
        });
    }

    public function deleteLine(PurchaseOrder $order, PurchaseOrderItem $item): void
    {
        if ((int) $item->purchase_order_id !== (int) $order->id) {
            abort(404);
        }

        DB::transaction(function () use ($order, $item) {
            $item->delete();
            $this->refreshOrderTotals($order->fresh());
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fillLineFromInput(PurchaseOrderItem $line, array $data): void
    {
        $line->product_id = isset($data['product_id']) ? (int) $data['product_id'] : null;
        $line->product_sku = isset($data['product_sku']) ? (string) $data['product_sku'] : null;
        $line->product_name = trim((string) ($data['product_name'] ?? ''));
        $line->quantity = (string) $data['quantity'];
        $line->unit_price = (string) $data['unit_price'];
        $line->tax_rate = isset($data['tax_rate']) ? (string) $data['tax_rate'] : '0';
        $line->discount_amount = isset($data['discount_amount']) ? (string) $data['discount_amount'] : '0';
        $line->notes = isset($data['notes']) ? (string) $data['notes'] : null;

        $qty = (float) $line->quantity;
        $unit = (float) $line->unit_price;
        $disc = (float) $line->discount_amount;
        $line->total_price = (string) max(0, $qty * $unit - $disc);
    }

    private function refreshOrderTotals(PurchaseOrder $order): void
    {
        $order->load(['items' => static fn ($q) => $q->orderBy('id')]);
        $gross = 0.0;
        $disc = 0.0;
        $netSum = 0.0;
        foreach ($order->items as $i) {
            $qty = (float) $i->quantity;
            $unit = (float) $i->unit_price;
            $lineDisc = (float) ($i->discount_amount ?? 0);
            $gross += $qty * $unit;
            $disc += $lineDisc;
            $netSum += max(0, $qty * $unit - $lineDisc);
        }
        $tax = 0.0;
        $order->subtotal = $gross;
        $order->discount_amount = $disc;
        $order->tax_amount = $tax;
        $order->total_amount = $gross - $disc + $tax;
        $order->amount_base_at_order = $order->total_amount;
        $order->save();
    }
}
