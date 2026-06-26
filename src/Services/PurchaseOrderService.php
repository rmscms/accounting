<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\PurchaseOrder;
use RMS\Accounting\Models\PurchaseOrderItem;
use RMS\Accounting\Models\SupplierInvoice;
use RMS\Accounting\Models\Currency;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Throwable;

/**
 * Purchase Order Service
 *
 * مدیریت سفارش‌های خرید از تامین‌کنندگان.
 *
 * ثبت دفترکل خرید فقط از مسیر {@see \RMS\Accounting\Services\SupplierInvoiceService::postPurchaseAccountingDocument}
 * روی فاکتور خرید انجام می‌شود؛ این سرویس سفارش را در GL ثبت نمی‌کند.
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
            $po->po_number = $data['po_number'] ?? $this->suggestNextPoNumber();
            $po->po_date = $data['po_date'] ?? Carbon::now();
            $po->expected_delivery_date = $data['expected_delivery_date'] ?? null;
            $po->status = $data['status'] ?? 'draft';
            $po->currency_code = $data['currency_code']
                ?? Currency::resolveBaseCurrencyCode('IRR');
            $po->notes = $data['notes'] ?? null;
            $po->created_by = \RMS\Accounting\Support\AuditActor::actorId();
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
            $poItem->product_sku = $item['product_sku'] ?? null;
            $poItem->product_name = $item['product_name'] ?? '';
            $poItem->notes = $item['notes'] ?? null;
            $poItem->quantity = $item['quantity'];
            $poItem->unit_price = $item['unit_price'];
            $poItem->tax_rate = $item['tax_rate'] ?? 0;
            $poItem->discount_amount = $item['discount_amount'] ?? 0;
            $qty = (float) $item['quantity'];
            $unit = (float) $item['unit_price'];
            $disc = (float) ($item['discount_amount'] ?? 0);
            $poItem->total_price = max(0, $qty * $unit - $disc);
            $poItem->save();
        }
    }

    /**
     * محاسبه مجموع سفارش خرید
     */
    public function calculateTotals(PurchaseOrder $po): void
    {
        $po->load(['items' => static fn ($q) => $q->orderBy('id')]);
        $gross = 0.0;
        $disc = 0.0;
        foreach ($po->items as $i) {
            $qty = (float) $i->quantity;
            $unit = (float) $i->unit_price;
            $lineDisc = (float) ($i->discount_amount ?? 0);
            $gross += $qty * $unit;
            $disc += $lineDisc;
        }
        $tax = 0.0;
        $po->subtotal = $gross;
        $po->discount_amount = $disc;
        $po->tax_amount = $tax;
        $po->total_amount = $gross - $disc + $tax;
        $po->amount_base_at_order = $po->total_amount;
        $po->save();
    }

    /**
     * تغییر وضعیت سفارش خرید
     */
    public function changeStatus(PurchaseOrder $po, string $newStatus): PurchaseOrder
    {
        $validStatuses = ['draft', 'sent', 'confirmed', 'partially_received', 'received', 'invoiced', 'cancelled'];
        
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
     * پیشنهاد شمارهٔ سفارش خرید بعدی (برای فرم create و پر کردن خودکار).
     */
    public function suggestNextPoNumber(): string
    {
        $date = Carbon::now()->format('Ymd');
        $lastPO = PurchaseOrder::whereDate('created_at', Carbon::today())
            ->orderBy('id', 'desc')
            ->first();

        $sequence = 1;
        if ($lastPO && is_string($lastPO->po_number) && preg_match('/-(\d{1,6})$/', $lastPO->po_number, $m)) {
            $sequence = max(1, (int) $m[1] + 1);
        }

        return 'PO-'.$date.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @deprecated use suggestNextPoNumber()
     */
    protected function generatePONumber(): string
    {
        return $this->suggestNextPoNumber();
    }

    /**
     * آیا می‌توان از این سفارش یک فاکتور خرید تولید کرد؟ (بدون ثبت خودکار در دفتر کل)
     *
     * @return array{can: bool, reason: ?string, existing_invoice_id: ?int}
     */
    public function gateCreateSupplierInvoiceFromPurchaseOrder(PurchaseOrder $po): array
    {
        $po->loadMissing(['items']);

        if ($po->items->isEmpty()) {
            return [
                'can' => false,
                'reason' => (string) trans('accounting::accounting.purchase_order.invoice_from_po_no_lines'),
                'existing_invoice_id' => null,
            ];
        }

        if (! in_array((string) $po->status, PurchaseOrder::statusesEligibleForSupplierInvoice(), true)) {
            return [
                'can' => false,
                'reason' => (string) trans('accounting::accounting.purchase_order.invoice_from_po_bad_status'),
                'existing_invoice_id' => null,
            ];
        }

        $existingId = SupplierInvoice::query()
            ->where('purchase_order_id', $po->getKey())
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->value('id');

        if ($existingId) {
            return [
                'can' => false,
                'reason' => (string) trans('accounting::accounting.purchase_order.invoice_from_po_duplicate'),
                'existing_invoice_id' => (int) $existingId,
            ];
        }

        return ['can' => true, 'reason' => null, 'existing_invoice_id' => null];
    }

    /**
     * تبدیل سفارش خرید به فاکتور تأمین‌کننده — فقط رکورد فاکتور و اقلام؛
     * ثبت دفتر کل عمداً انجام نمی‌شود (همان دکمهٔ «ثبت سند» روی فاکتور).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws \InvalidArgumentException
     */
    public function convertToSupplierInvoice(PurchaseOrder $po, array $data = []): SupplierInvoice
    {
        $po->load(['items' => static fn ($q) => $q->orderBy('id')]);

        $gate = $this->gateCreateSupplierInvoiceFromPurchaseOrder($po);
        if (! $gate['can']) {
            throw new \InvalidArgumentException($gate['reason'] ?? (string) trans('accounting::accounting.purchase_order.invoice_from_po_failed'));
        }

        try {
            DB::beginTransaction();

            $invoiceDate = isset($data['invoice_date']) ? Carbon::parse($data['invoice_date']) : Carbon::now();
            $dueDate = isset($data['due_date']) ? Carbon::parse($data['due_date']) : $invoiceDate->copy()->addDays(30);

            $subtotal = (float) $po->subtotal;
            $discHeader = (float) ($po->discount_amount ?? 0);
            $taxHeader = (float) ($po->tax_amount ?? 0);
            $total = (float) $po->total_amount;

            $invoice = new SupplierInvoice;
            $invoice->store_id = $po->store_id;
            $invoice->supplier_id = $po->supplier_id;
            $invoice->purchase_order_id = $po->getKey();
            $invoice->invoice_number = isset($data['invoice_number']) && (string) $data['invoice_number'] !== ''
                ? (string) $data['invoice_number']
                : SupplierInvoice::suggestNextInvoiceNumber();
            $invoice->invoice_date = $invoiceDate->toDateString();
            $invoice->due_date = $dueDate->toDateString();
            $invoice->currency_code = (string) ($po->currency_code ?: 'IRR');
            $invoice->fx_rate_at_invoice = (float) ($po->fx_rate_at_order ?? 1);
            $invoice->subtotal = $subtotal;
            $invoice->discount_amount = $discHeader;
            $invoice->tax_amount = $taxHeader;
            $invoice->total_amount = $total;
            $invoice->amount_base_at_invoice = $total * (float) ($po->fx_rate_at_order ?? 1);
            $invoice->shipping_amount = 0;
            $invoice->payment_status = SupplierInvoice::STATUS_UNPAID;
            $invoice->paid_amount = 0;
            $invoice->balance_due = $total;
            $invoice->settlement_mode = SupplierInvoice::SETTLEMENT_ON_ACCOUNT;
            $invoice->paid_at_source_bank_id = null;
            $invoice->paid_at_source_cash_box_id = null;
            $invoice->paid_at_source_wallet_id = null;
            $invoice->notes = isset($data['notes']) ? (string) $data['notes'] : ($po->notes ? (string) $po->notes : null);
            $invoice->document_id = null;
            $invoice->save();

            foreach ($po->items as $poItem) {
                $qtyOrdered = (float) $poItem->quantity;
                $qtyReceived = $poItem->received_quantity !== null ? (float) $poItem->received_quantity : null;
                $qty = $qtyReceived !== null && $qtyReceived > 0 ? min($qtyReceived, $qtyOrdered) : $qtyOrdered;
                $unit = (float) $poItem->unit_price;
                $lineDisc = (float) ($poItem->discount_amount ?? 0);
                $lineTotal = max(0, round($qty * $unit - $lineDisc, 4));

                $invoice->items()->create([
                    'product_id' => $poItem->product_id,
                    'product_sku' => $poItem->product_sku,
                    'product_name' => (string) ($poItem->product_name ?? ''),
                    'quantity' => $qty,
                    'unit_price' => $unit,
                    'tax_rate' => (float) ($poItem->tax_rate ?? 0),
                    'discount_amount' => $lineDisc,
                    'total_price' => $lineTotal,
                    'tax_amount' => 0,
                    'shipping_amount' => 0,
                ]);
            }

            DB::commit();

            return $invoice->fresh(['items']);
        } catch (Throwable $e) {
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
