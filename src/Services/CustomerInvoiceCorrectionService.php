<?php

namespace RMS\Accounting\Services;

use Illuminate\Support\Str;
use RMS\Accounting\Models\CreditNote;
use RMS\Accounting\Models\CustomerInvoice;
use RMS\Accounting\Models\CustomerInvoiceCorrection;

class CustomerInvoiceCorrectionService
{
    public function ensureCorrectionGroupId(CustomerInvoice $invoice): string
    {
        $invoice->refresh();
        $group = trim((string) ($invoice->correction_group_id ?? ''));
        if ($group !== '') {
            return $group;
        }

        if ((int) ($invoice->original_invoice_id ?? 0) > 0) {
            $origin = CustomerInvoice::query()->find((int) $invoice->original_invoice_id);
            $originGroup = trim((string) ($origin?->correction_group_id ?? ''));
            if ($originGroup !== '') {
                $invoice->forceFill(['correction_group_id' => $originGroup])->saveQuietly();

                return $originGroup;
            }
        }

        $group = (string) Str::uuid();
        $invoice->forceFill(['correction_group_id' => $group])->saveQuietly();

        return $group;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function record(CustomerInvoice $invoice, string $actionType, array $payload = []): CustomerInvoiceCorrection
    {
        return CustomerInvoiceCorrection::query()->create([
            'customer_invoice_id' => $invoice->getKey(),
            'correction_group_id' => $payload['correction_group_id'] ?? $this->ensureCorrectionGroupId($invoice),
            'action_type' => $actionType,
            'source_document_id' => $payload['source_document_id'] ?? null,
            'target_document_id' => $payload['target_document_id'] ?? null,
            'source_invoice_id' => $payload['source_invoice_id'] ?? null,
            'target_invoice_id' => $payload['target_invoice_id'] ?? null,
            'credit_note_id' => $payload['credit_note_id'] ?? null,
            'reason' => $payload['reason'] ?? null,
            'admin_user_id' => $payload['admin_user_id'] ?? \RMS\Accounting\Support\AuditActor::actorId(),
            'created_at' => now(),
        ]);
    }

    public function recordAdjustmentFromCreditNote(CreditNote $creditNote): ?CustomerInvoiceCorrection
    {
        $invoiceId = (int) ($creditNote->customer_invoice_id ?: $creditNote->applied_to_invoice_id);
        if ($invoiceId <= 0) {
            return null;
        }

        $invoice = CustomerInvoice::query()->find($invoiceId);
        if (! $invoice) {
            return null;
        }

        return $this->record($invoice, 'adjustment', [
            'source_invoice_id' => $invoice->getKey(),
            'target_invoice_id' => $invoice->getKey(),
            'target_document_id' => $creditNote->accounting_document_id ?: null,
            'credit_note_id' => $creditNote->getKey(),
            'reason' => (string) ($creditNote->reason ?? $creditNote->notes ?? ''),
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, CustomerInvoiceCorrection>
     */
    public function timelineForInvoice(CustomerInvoice $invoice)
    {
        $group = trim((string) ($invoice->correction_group_id ?? ''));

        $query = CustomerInvoiceCorrection::query()
            ->with(['admin', 'sourceInvoice', 'targetInvoice', 'sourceDocument', 'targetDocument', 'creditNote']);

        if ($group !== '') {
            $query->where('correction_group_id', $group);
        } else {
            $query->where('customer_invoice_id', $invoice->getKey());
        }

        return $query->orderByDesc('created_at')->get();
    }
}
