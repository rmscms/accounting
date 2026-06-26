<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RMS\Accounting\Models\PurchaseOrder;
use RMS\Accounting\Models\PurchaseOrderItem;
use RMS\Accounting\Services\PurchaseOrderItemAdminService;

class PurchaseOrderItemsController extends AccountingAdminController
{
    public function itemsStore(Request $request, PurchaseOrder $purchase_order, PurchaseOrderItemAdminService $service): JsonResponse
    {
        $data = $this->validatedItemPayload($request);
        $item = $service->createLine($purchase_order, $data);

        return response()->json($this->successPayload($purchase_order->fresh(), $item));
    }

    public function itemsUpdate(
        Request $request,
        PurchaseOrder $purchase_order,
        PurchaseOrderItem $item,
        PurchaseOrderItemAdminService $service
    ): JsonResponse {
        $data = $this->validatedItemPayload($request, false);
        $item = $service->updateLine($purchase_order, $item, $data);

        return response()->json($this->successPayload($purchase_order->fresh(), $item));
    }

    public function itemsDestroy(
        PurchaseOrder $purchase_order,
        PurchaseOrderItem $item,
        PurchaseOrderItemAdminService $service
    ): JsonResponse {
        $service->deleteLine($purchase_order, $item);

        return response()->json($this->successPayload($purchase_order->fresh(), null));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedItemPayload(Request $request, bool $requireName = true): array
    {
        $rules = [
            'product_id' => ['nullable', 'integer'],
            'product_sku' => ['nullable', 'string', 'max:100'],
            'product_name' => $requireName ? ['required', 'string', 'max:255'] : ['sometimes', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        $v = validator($request->all(), $rules, [], [
            'product_name' => (string) trans('accounting::accounting.purchase_order.item_product'),
        ]);

        if ($v->fails()) {
            throw ValidationException::withMessages($v->errors()->toArray());
        }

        return $v->validated();
    }

    /**
     * @return array<string, mixed>
     */
    private function successPayload(PurchaseOrder $order, ?PurchaseOrderItem $item): array
    {
        return [
            'ok' => true,
            'item' => $item ? $this->serializeItem($item) : null,
            'order' => [
                'subtotal' => (string) $order->subtotal,
                'tax_amount' => (string) $order->tax_amount,
                'discount_amount' => (string) $order->discount_amount,
                'total_amount' => (string) $order->total_amount,
                'gross_before_discount' => (string) $order->subtotal,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeItem(PurchaseOrderItem $item): array
    {
        return [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'product_sku' => $item->product_sku,
            'product_name' => $item->product_name,
            'quantity' => (string) $item->quantity,
            'unit_price' => (string) $item->unit_price,
            'tax_rate' => (string) $item->tax_rate,
            'discount_amount' => (string) $item->discount_amount,
            'total_price' => (string) $item->total_price,
            'notes' => $item->notes,
        ];
    }

    public function table(): string
    {
        return '';
    }

    public function modelName(): string
    {
        return '';
    }
}
