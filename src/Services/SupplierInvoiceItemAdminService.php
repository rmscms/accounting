<?php

namespace RMS\Accounting\Services;

use Illuminate\Support\Facades\DB;
use RMS\Accounting\Models\SupplierInvoice;
use RMS\Accounting\Models\SupplierInvoiceItem;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * CRUD اقلام فاکتور خرید در ادمین + هم‌ترازی جمع فاکتور و مالیات.
 */
final class SupplierInvoiceItemAdminService
{
    public function __construct(
        protected TaxService $taxService
    ) {
    }

    public function assertInvoiceLinesEditable(SupplierInvoice $invoice): void
    {
        if ($invoice->document_id) {
            throw new AccessDeniedHttpException((string) trans('accounting::accounting.supplier_invoice.items_locked_document'));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createLine(SupplierInvoice $invoice, array $data): SupplierInvoiceItem
    {
        $this->assertInvoiceLinesEditable($invoice);

        return DB::transaction(function () use ($invoice, $data) {
            $line = new SupplierInvoiceItem;
            $line->supplier_invoice_id = $invoice->id;
            $this->fillLineFromInput($line, $data);
            $line->save();

            $this->refreshInvoiceFromItems($invoice->fresh(), $this->resolveTaxMethod($data, $invoice));

            return $line->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateLine(SupplierInvoice $invoice, SupplierInvoiceItem $item, array $data): SupplierInvoiceItem
    {
        $this->assertInvoiceLinesEditable($invoice);
        if ((int) $item->supplier_invoice_id !== (int) $invoice->id) {
            abort(404);
        }

        return DB::transaction(function () use ($invoice, $item, $data) {
            $this->fillLineFromInput($item, $data);
            $item->save();

            $this->refreshInvoiceFromItems($invoice->fresh(), $this->resolveTaxMethod($data, $invoice));

            return $item->fresh();
        });
    }

    public function deleteLine(SupplierInvoice $invoice, SupplierInvoiceItem $item, ?string $taxMethod = null): void
    {
        $this->assertInvoiceLinesEditable($invoice);
        if ((int) $item->supplier_invoice_id !== (int) $invoice->id) {
            abort(404);
        }

        DB::transaction(function () use ($invoice, $item) {
            $item->delete();
            $this->refreshInvoiceFromItems($invoice->fresh(), $this->resolveTaxMethod(['tax_method' => $taxMethod], $invoice));
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fillLineFromInput(SupplierInvoiceItem $line, array $data): void
    {
        $line->product_id = isset($data['product_id']) ? (int) $data['product_id'] : null;
        $line->product_sku = isset($data['product_sku']) ? (string) $data['product_sku'] : null;
        $line->product_name = trim((string) ($data['product_name'] ?? ''));
        $line->quantity = (string) $data['quantity'];
        $line->unit_price = (string) $data['unit_price'];
        $defaultRate = is_vat_enabled()
            ? (string) \RMS\Core\Models\Setting::get('accounting.vat.rate', 9)
            : '0';
        $line->tax_rate = isset($data['tax_rate']) ? (string) $data['tax_rate'] : $defaultRate;
        $line->discount_amount = isset($data['discount_amount']) ? (string) $data['discount_amount'] : '0';
        $line->shipping_amount = isset($data['shipping_amount']) ? (string) $data['shipping_amount'] : '0';

        $qty = (float) $line->quantity;
        $unit = (float) $line->unit_price;
        $disc = (float) $line->discount_amount;
        $net = max(0, $qty * $unit - $disc);
        $line->total_price = (string) $net;
        if (! is_vat_enabled()) {
            $line->tax_amount = '0';
        }
    }

    private function refreshInvoiceFromItems(SupplierInvoice $invoice, string $taxMethod): void
    {
        $invoice->load(['items' => static fn ($q) => $q->orderBy('id')]);

        if ($invoice->items->isEmpty()) {
            $invoice->subtotal = 0;
            $invoice->tax_amount = 0;
            $invoice->discount_amount = 0;
            $invoice->total_amount = 0;
        } else {
            if (is_vat_enabled()) {
                $invoice = $this->taxService->applyVATToSupplierInvoice($invoice, $taxMethod);
            } else {
                $sub = 0.0;
                $discSum = 0.0;
                foreach ($invoice->items as $it) {
                    $qty = (float) $it->quantity;
                    $unit = (float) $it->unit_price;
                    $disc = (float) ($it->discount_amount ?? 0);
                    $discSum += $disc;
                    $net = max(0, $qty * $unit - $disc);
                    $it->tax_amount = '0';
                    $it->total_price = (string) $net;
                    $it->save();
                    $sub += $net;
                }
                $invoice->subtotal = $sub;
                $invoice->tax_amount = 0;
                $invoice->discount_amount = $discSum;
                $invoice->total_amount = $sub;
            }
        }

        $settlementMode = (string) ($invoice->getAttribute('settlement_mode') ?: SupplierInvoice::SETTLEMENT_ON_ACCOUNT);
        if ($settlementMode === SupplierInvoice::SETTLEMENT_PAID_AT_SOURCE) {
            $invoice->payment_status = SupplierInvoice::STATUS_PAID;
            $invoice->paid_amount = $invoice->total_amount;
            $invoice->balance_due = 0;
        } else {
            $paid = (float) ($invoice->paid_amount ?? 0);
            $invoice->balance_due = max(0, (float) $invoice->total_amount - $paid);
        }
        $invoice->tax_method = $taxMethod;
        $invoice->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveTaxMethod(array $data, ?SupplierInvoice $invoice = null): string
    {
        $candidate = strtolower(trim((string) ($data['tax_method'] ?? '')));
        if (in_array($candidate, ['inclusive', 'exclusive'], true)) {
            return $candidate;
        }
        $existing = strtolower(trim((string) ($invoice?->tax_method ?? '')));
        if (in_array($existing, ['inclusive', 'exclusive'], true)) {
            return $existing;
        }

        return function_exists('tax_calculation_method') ? tax_calculation_method() : 'exclusive';
    }
}
