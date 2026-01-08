<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\PurchaseOrder;
use RMS\Accounting\Models\PurchaseOrderItem;
use RMS\Accounting\Models\SupplierInvoice;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Purchase Order Service
 * 
 * مدیریت سفارش‌های خرید از تامین‌کنندگان
 */
class PurchaseOrderService
{
    /**
     * ایجاد سفارش خرید جدید
     */
    public function createPurchaseOrder(array $data): PurchaseOrder
    {
        try {
            DB::beginTransaction();

            // Create purchase order
            $po = new PurchaseOrder();
            $po->store_id = $data['store_id'];
            $po->supplier_id = $data['supplier_id'];
            $po->po_number = $data['po_number'] ?? $this->generatePONumber();
            $po->po_date = $data['po_date'] ?? Carbon::now();
            $po->expected_delivery_date = $data['expected_delivery_date'] ?? null;
            $po->status = $data['status'] ?? 'draft';
            $po->currency_code = $data['currency_code'] ?? config('accounting.default_currency');
            $po->notes = $data['notes'] ?? null;
            $po->created_by = auth()->id();
            $po->save();

            // Add items
            if (isset($data['items']) && is_array($data['items'])) {
                $this->addItems($po, $data['items']);
            }

            // Calculate totals
            $this->calculateTotals($po);

            DB::commit();
            return $po->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * افزودن آیتم‌ها به سفارش خرید
     */
    public function addItems(PurchaseOrder $po, array $items): void
    {
        foreach ($items as $item) {
            $poItem = new PurchaseOrderItem();
            $poItem->purchase_order_id = $po->id;
            $poItem->product_id = $item['product_id'] ?? null;
            $poItem->product_name = $item['product_name'] ?? null;
            $poItem->description = $item['description'] ?? null;
            $poItem->quantity = $item['quantity'];
            $poItem->unit_price = $item['unit_price'];
            $poItem->total_price = $item['quantity'] * $item['unit_price'];
            $poItem->save();
        }
    }

    /**
     * محاسبه مجموع سفارش خرید
     */
    public function calculateTotals(PurchaseOrder $po): void
    {
        $total = $po->items()->sum('total_price');
        
        $po->total_amount = $total;
        $po->save();
    }

    /**
     * تغییر وضعیت سفارش خرید
     */
    public function changeStatus(PurchaseOrder $po, string $newStatus): PurchaseOrder
    {
        $validStatuses = ['draft', 'sent', 'confirmed', 'partially_received', 'received', 'cancelled'];
        
        if (!in_array($newStatus, $validStatuses)) {
            throw new \InvalidArgumentException("Invalid status: {$newStatus}");
        }

        $po->status = $newStatus;
        
        if ($newStatus === 'sent') {
            $po->sent_date = Carbon::now();
        }
        
        if ($newStatus === 'confirmed') {
            $po->confirmed_date = Carbon::now();
        }

        $po->save();

        return $po;
    }

    /**
     * ثبت دریافت کالا
     */
    public function receiveItems(PurchaseOrder $po, array $receivedItems): void
    {
        try {
            DB::beginTransaction();

            foreach ($receivedItems as $itemId => $receivedQty) {
                $poItem = PurchaseOrderItem::findOrFail($itemId);
                
                if ($poItem->purchase_order_id !== $po->id) {
                    throw new \InvalidArgumentException("Item does not belong to this PO");
                }

                $poItem->received_quantity = ($poItem->received_quantity ?? 0) + $receivedQty;
                $poItem->save();
            }

            // Update PO status based on received items
            $this->updateStatusBasedOnReceipt($po);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * به‌روزرسانی وضعیت بر اساس دریافت کالا
     */
    protected function updateStatusBasedOnReceipt(PurchaseOrder $po): void
    {
        $items = $po->items;
        $allReceived = true;
        $anyReceived = false;

        foreach ($items as $item) {
            $received = $item->received_quantity ?? 0;
            
            if ($received < $item->quantity) {
                $allReceived = false;
            }
            
            if ($received > 0) {
                $anyReceived = true;
            }
        }

        if ($allReceived) {
            $po->status = 'received';
            $po->received_date = Carbon::now();
        } elseif ($anyReceived) {
            $po->status = 'partially_received';
        }

        $po->save();
    }

    /**
     * تولید شماره سفارش خرید
     */
    protected function generatePONumber(): string
    {
        $date = Carbon::now()->format('Ymd');
        $lastPO = PurchaseOrder::whereDate('created_at', Carbon::today())
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastPO ? (intval(substr($lastPO->po_number, -4)) + 1) : 1;

        return 'PO-' . $date . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * تبدیل سفارش خرید به فاکتور تامین‌کننده
     */
    public function convertToSupplierInvoice(PurchaseOrder $po, array $data = []): SupplierInvoice
    {
        if ($po->status !== 'received' && $po->status !== 'partially_received') {
            throw new \InvalidArgumentException("PO must be received before converting to invoice");
        }

        try {
            DB::beginTransaction();

            $invoice = new SupplierInvoice();
            $invoice->store_id = $po->store_id;
            $invoice->supplier_id = $po->supplier_id;
            $invoice->purchase_order_id = $po->id;
            $invoice->invoice_number = $data['invoice_number'] ?? null;
            $invoice->invoice_date = $data['invoice_date'] ?? Carbon::now();
            $invoice->due_date = $data['due_date'] ?? Carbon::now()->addDays(30);
            $invoice->status = 'pending';
            $invoice->currency_code = $po->currency_code;
            $invoice->subtotal = $po->total_amount;
            $invoice->tax_amount = $data['tax_amount'] ?? 0;
            $invoice->total_amount = $po->total_amount + ($data['tax_amount'] ?? 0);
            $invoice->notes = $data['notes'] ?? $po->notes;
            $invoice->save();

            // Copy items
            foreach ($po->items as $poItem) {
                $invoice->items()->create([
                    'product_id' => $poItem->product_id,
                    'product_name' => $poItem->product_name,
                    'description' => $poItem->description,
                    'quantity' => $poItem->received_quantity ?? $poItem->quantity,
                    'unit_price' => $poItem->unit_price,
                    'total_price' => $poItem->total_price,
                ]);
            }

            DB::commit();
            return $invoice->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * لغو سفارش خرید
     */
    public function cancel(PurchaseOrder $po, string $reason = null): PurchaseOrder
    {
        $po->status = 'cancelled';
        $po->cancelled_date = Carbon::now();
        $po->cancellation_reason = $reason;
        $po->save();

        return $po;
    }

    /**
     * دریافت سفارش‌های در حال انتظار
     */
    public function getPendingOrders(int $storeId = null)
    {
        $query = PurchaseOrder::whereIn('status', ['sent', 'confirmed'])
            ->orderBy('expected_delivery_date', 'asc');

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        return $query->get();
    }

    /**
     * دریافت سفارش‌های دیرکرد
     */
    public function getOverdueOrders(int $storeId = null)
    {
        $query = PurchaseOrder::whereIn('status', ['sent', 'confirmed'])
            ->where('expected_delivery_date', '<', Carbon::now())
            ->orderBy('expected_delivery_date', 'asc');

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        return $query->get();
    }
}
