<?php

namespace RMS\Accounting\Services;

use Illuminate\Support\Str;
use RMS\Accounting\Models\DebitNote;
use RMS\Accounting\Models\SupplierInvoice;
use RMS\Accounting\Models\SupplierInvoiceCorrection;

class SupplierInvoiceCorrectionService
{
    /**
     * Ensure invoice has a correction group id.
     */
    public function ensureCorrectionGroupId(SupplierInvoice $invoice): string
    {
        $invoice->refresh();
        $group = trim((string) ($invoice->correction_group_id ?? ''));
        if ($group !== '') {
            return $group;
        }

        if ((int) ($invoice->original_invoice_id ?? 0) > 0) {
            $origin = SupplierInvoice::query()->find((int) $invoice->original_invoice_id);
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
    public function record(SupplierInvoice $invoice, string $actionType, array $payload = []): SupplierInvoiceCorrection
    {
        return SupplierInvoiceCorrection::query()->create([
            'supplier_invoice_id' => $invoice->getKey(),
            'correction_group_id' => $payload['correction_group_id'] ?? $this->ensureCorrectionGroupId($invoice),
            'action_type' => $actionType,
            'source_document_id' => $payload['source_document_id'] ?? null,
            'target_document_id' => $payload['target_document_id'] ?? null,
            'source_invoice_id' => $payload['source_invoice_id'] ?? null,
            'target_invoice_id' => $payload['target_invoice_id'] ?? null,
            'debit_note_id' => $payload['debit_note_id'] ?? null,
            'reason' => $payload['reason'] ?? null,
            'admin_user_id' => $payload['admin_user_id'] ?? \RMS\Accounting\Support\AuditActor::actorId(),
            'created_at' => now(),
        ]);
    }

    public function recordAdjustmentFromDebitNote(DebitNote $debitNote): ?SupplierInvoiceCorrection
    {
        $invoiceId = (int) ($debitNote->supplier_invoice_id ?? 0);
        if ($invoiceId <= 0) {
            return null;
        }

        $invoice = SupplierInvoice::query()->find($invoiceId);
        if (! $invoice) {
            return null;
        }

        return $this->record($invoice, 'adjustment', [
            'source_invoice_id' => $invoice->getKey(),
            'target_invoice_id' => $invoice->getKey(),
            'target_document_id' => $debitNote->accounting_document_id ?: null,
            'debit_note_id' => $debitNote->getKey(),
            'reason' => (string) ($debitNote->reason ?? $debitNote->notes ?? ''),
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, SupplierInvoiceCorrection>
     */
    public function timelineForInvoice(SupplierInvoice $invoice)
    {
        $group = trim((string) ($invoice->correction_group_id ?? ''));

        $q = SupplierInvoiceCorrection::query()
            ->with(['admin', 'sourceInvoice', 'targetInvoice', 'sourceDocument', 'targetDocument', 'debitNote']);

        if ($group !== '') {
            $q->where('correction_group_id', $group);
        } else {
            $q->where('supplier_invoice_id', $invoice->getKey());
        }

        return $q->orderByDesc('created_at')->get();
    }
}
