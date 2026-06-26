<?php

namespace RMS\Accounting\Services;

use Illuminate\Support\Facades\DB;
use RMS\Accounting\Models\CustomerInvoice;
use RMS\Accounting\Models\CustomerInvoiceItem;
use RMS\Accounting\Services\Tax\TaxCalculator;
use RMS\Core\Models\Setting;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class CustomerInvoiceItemAdminService
{
    public function assertInvoiceLinesEditable(CustomerInvoice $invoice): void
    {
        if ((int) ($invoice->document_id ?? 0) > 0) {
            throw new AccessDeniedHttpException((string) trans('accounting::accounting.customer_invoice.items_locked_document'));
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    public function createLine(CustomerInvoice $invoice, array $data): CustomerInvoiceItem
    {
        $this->assertInvoiceLinesEditable($invoice);

        return DB::transaction(function () use ($invoice, $data) {
            $line = new CustomerInvoiceItem();
            $line->customer_invoice_id = (int) $invoice->id;
            $this->fillLine($line, $data, $invoice);
            $line->save();

            $this->refreshInvoiceFromItems($invoice->fresh(), $this->resolveTaxMethod($data, $invoice));

            return $line->fresh();
        });
    }

    /**
     * @param array<string,mixed> $data
     */
    public function updateLine(CustomerInvoice $invoice, CustomerInvoiceItem $item, array $data): CustomerInvoiceItem
    {
        $this->assertInvoiceLinesEditable($invoice);
        if ((int) $item->customer_invoice_id !== (int) $invoice->id) {
            abort(404);
        }

        return DB::transaction(function () use ($invoice, $item, $data) {
            $this->fillLine($item, $data, $invoice);
            $item->save();

            $this->refreshInvoiceFromItems($invoice->fresh(), $this->resolveTaxMethod($data, $invoice));

            return $item->fresh();
        });
    }

    public function deleteLine(CustomerInvoice $invoice, CustomerInvoiceItem $item, ?string $taxMethod = null): void
    {
        $this->assertInvoiceLinesEditable($invoice);
        if ((int) $item->customer_invoice_id !== (int) $invoice->id) {
            abort(404);
        }

        DB::transaction(function () use ($invoice, $item) {
            $item->delete();
            $this->refreshInvoiceFromItems($invoice->fresh(), $this->resolveTaxMethod(['tax_method' => $taxMethod], $invoice));
        });
    }

    /**
     * @param array<string,mixed> $data
     */
    private function fillLine(CustomerInvoiceItem $line, array $data, ?CustomerInvoice $invoice = null): void
    {
        $line->product_id = isset($data['product_id']) ? (int) $data['product_id'] : null;
        $line->product_sku = isset($data['product_sku']) ? (string) $data['product_sku'] : null;
        $line->product_name = trim((string) ($data['product_name'] ?? ''));
        $line->quantity = (string) $data['quantity'];
        $line->price = (string) $data['price'];
        $defaultRate = is_vat_enabled()
            ? (string) Setting::get('accounting.vat.rate', 9)
            : '0';
        $line->tax_rate = isset($data['tax_rate']) ? (string) $data['tax_rate'] : $defaultRate;
        $line->discount_amount = isset($data['discount_amount']) ? (string) $data['discount_amount'] : '0';

        $qty = (float) $line->quantity;
        $price = (float) $line->price;
        $discount = (float) $line->discount_amount;
        $net = max(0, ($qty * $price) - $discount);

        if (is_vat_enabled()) {
            $taxRate = (float) ($line->tax_rate ?? 0);
            $method = $this->resolveTaxMethod($data, $invoice);
            $result = TaxCalculator::calculateVAT($net, $taxRate, $method);
            $line->tax_amount = (string) $result['tax_amount'];
            $line->total = (string) $result['total_amount'];
        } else {
            $line->tax_amount = '0';
            $line->total = (string) $net;
        }
    }

    private function refreshInvoiceFromItems(CustomerInvoice $invoice, string $taxMethod): void
    {
        $invoice->load(['items' => static fn ($q) => $q->orderBy('id')]);

        if ($invoice->items->isEmpty()) {
            $invoice->subtotal = 0;
            $invoice->tax_amount = 0;
            $invoice->discount_amount = 0;
            $invoice->total_amount = 0;
        } else {
            $subtotal = 0.0;
            $discount = 0.0;
            $tax = 0.0;
            $method = $taxMethod;
            foreach ($invoice->items as $it) {
                $qty = (float) $it->quantity;
                $price = (float) $it->price;
                $disc = (float) ($it->discount_amount ?? 0);
                $lineNet = max(0, ($qty * $price) - $disc);
                if (is_vat_enabled()) {
                    $result = TaxCalculator::calculateVAT($lineNet, (float) ($it->tax_rate ?? 0), $method);
                    $lineTax = (float) $result['tax_amount'];
                    $it->tax_amount = (string) $lineTax;
                    $it->total = (string) $result['total_amount'];
                    $it->save();
                    $subtotal += (float) $result['base_amount'];
                    $discount += $disc;
                    $tax += $lineTax;
                } else {
                    $it->tax_amount = '0';
                    $it->total = (string) $lineNet;
                    $it->save();
                    $subtotal += $lineNet;
                    $discount += $disc;
                }
            }
            $invoice->subtotal = $subtotal;
            $invoice->tax_amount = $tax;
            $invoice->discount_amount = $discount;
            $invoice->total_amount = $subtotal + $tax;
        }

        $settlement = (string) ($invoice->settlement_mode ?: CustomerInvoice::SETTLEMENT_CREDIT);
        $upfront = (float) ($invoice->upfront_payment_amount ?? 0);
        $total = (float) ($invoice->total_amount ?? 0);
        if ($settlement === CustomerInvoice::SETTLEMENT_CASH) {
            $upfront = $total;
        } elseif ($settlement === CustomerInvoice::SETTLEMENT_CREDIT) {
            $upfront = 0;
        } else {
            $upfront = max(0, min($upfront, $total));
        }

        $invoice->upfront_payment_amount = $upfront;
        $invoice->paid_amount = $upfront;
        $invoice->balance_due = max(0, $total - $upfront);
        $invoice->payment_status = $invoice->balance_due <= 0.0001
            ? CustomerInvoice::STATUS_PAID
            : ($upfront > 0 ? CustomerInvoice::STATUS_PARTIALLY_PAID : CustomerInvoice::STATUS_UNPAID);
        $invoice->tax_method = $taxMethod;
        $invoice->save();
    }

    /**
     * @param array<string,mixed> $data
     */
    private function resolveTaxMethod(array $data, ?CustomerInvoice $invoice = null): string
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
