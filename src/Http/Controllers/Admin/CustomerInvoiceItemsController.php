<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RMS\Accounting\Models\CustomerInvoice;
use RMS\Accounting\Models\CustomerInvoiceItem;
use RMS\Accounting\Services\CustomerInvoiceItemAdminService;
use RMS\Core\Models\Setting;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CustomerInvoiceItemsController extends AccountingAdminController
{
    public function itemsStore(Request $request, CustomerInvoice $customer_invoice, CustomerInvoiceItemAdminService $service): JsonResponse
    {
        try {
            $data = $this->validatedItemPayload($request);
            $item = $service->createLine($customer_invoice, $data);
        } catch (AccessDeniedHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json($this->successPayload($customer_invoice->fresh(), $item));
    }

    public function itemsUpdate(
        Request $request,
        CustomerInvoice $customer_invoice,
        CustomerInvoiceItem $item,
        CustomerInvoiceItemAdminService $service
    ): JsonResponse {
        try {
            $data = $this->validatedItemPayload($request, false);
            $item = $service->updateLine($customer_invoice, $item, $data);
        } catch (AccessDeniedHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json($this->successPayload($customer_invoice->fresh(), $item));
    }

    public function itemsDestroy(
        Request $request,
        CustomerInvoice $customer_invoice,
        CustomerInvoiceItem $item,
        CustomerInvoiceItemAdminService $service
    ): JsonResponse {
        try {
            $service->deleteLine($customer_invoice, $item, $this->resolveTaxMethod($request));
        } catch (AccessDeniedHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json($this->successPayload($customer_invoice->fresh(), null));
    }

    /**
     * @return array<string,mixed>
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
            'tax_method' => ['nullable', 'string', 'in:inclusive,exclusive'],
        ];

        $v = validator($request->all(), $rules);
        if ($v->fails()) {
            throw ValidationException::withMessages($v->errors()->toArray());
        }

        $payload = $v->validated();
        $payload['price'] = $payload['unit_price'];
        unset($payload['unit_price']);
        if ((! array_key_exists('tax_rate', $payload) || $payload['tax_rate'] === null || $payload['tax_rate'] === '')
            && function_exists('is_vat_enabled')
            && is_vat_enabled()) {
            $payload['tax_rate'] = (float) Setting::get('accounting.vat.rate', 9);
        }
        $payload['tax_method'] = $this->resolveTaxMethod($request);

        return $payload;
    }

    private function resolveTaxMethod(Request $request): string
    {
        $method = strtolower(trim((string) $request->input('tax_method', '')));
        return in_array($method, ['inclusive', 'exclusive'], true)
            ? $method
            : (function_exists('tax_calculation_method') ? tax_calculation_method() : 'exclusive');
    }

    /**
     * @return array<string,mixed>
     */
    private function successPayload(CustomerInvoice $invoice, ?CustomerInvoiceItem $item): array
    {
        return [
            'ok' => true,
            'item' => $item ? $this->serializeItem($item) : null,
            'invoice' => [
                'subtotal' => (string) $invoice->subtotal,
                'tax_amount' => (string) $invoice->tax_amount,
                'discount_amount' => (string) $invoice->discount_amount,
                'total_amount' => (string) $invoice->total_amount,
                'paid_amount' => (string) $invoice->paid_amount,
                'balance_due' => (string) $invoice->balance_due,
            ],
            'lines_locked' => (bool) $invoice->document_id,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function serializeItem(CustomerInvoiceItem $item): array
    {
        return [
            'id' => $item->id,
            'product_name' => $item->product_name,
            'quantity' => (string) $item->quantity,
            'price' => (string) $item->price,
            'tax_rate' => (string) $item->tax_rate,
            'discount_amount' => (string) $item->discount_amount,
            'tax_amount' => (string) $item->tax_amount,
            'total' => (string) $item->total,
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
