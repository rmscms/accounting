<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\Bank;
use RMS\Accounting\Models\Cheque;
use RMS\Accounting\Models\Chequebook;
use RMS\Accounting\Models\Party;
use RMS\Accounting\Models\PaymentMethod;

class ChequeAutoCreationService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function ensureCheque(array $payload): ?Cheque
    {
        $methodId = (int) ($payload['payment_method_id'] ?? 0);
        if ($methodId <= 0) {
            return null;
        }
        $methodType = (string) PaymentMethod::query()->whereKey($methodId)->value('type');
        if ($methodType !== PaymentMethod::TYPE_CHEQUE) {
            return null;
        }

        $existingChequeId = (int) ($payload['cheque_id'] ?? 0);
        if ($existingChequeId > 0) {
            return Cheque::query()->find($existingChequeId);
        }

        $chequeType = (string) ($payload['cheque_type'] ?? Cheque::TYPE_RECEIVED);
        if (! in_array($chequeType, [Cheque::TYPE_RECEIVED, Cheque::TYPE_ISSUED], true)) {
            $chequeType = Cheque::TYPE_RECEIVED;
        }

        $partyId = (int) ($payload['party_id'] ?? 0);
        $party = $partyId > 0 ? Party::query()->find($partyId) : null;

        $bankId = $this->resolveBankId($payload);
        $chequebookId = null;
        if ($chequeType === Cheque::TYPE_ISSUED) {
            $chequebookId = $this->resolveChequebookId($bankId, $payload);
        }

        $issueDate = $this->normalizeDateString($payload['issue_date'] ?? null) ?? now()->toDateString();
        $dueDate = $this->normalizeDateString($payload['due_date'] ?? null) ?? $issueDate;
        $amount = (float) ($payload['amount'] ?? 0);

        $companyName = (string) config('app.name', 'Company');

        $cheque = Cheque::query()->create([
            'cheque_number' => $this->buildAutoNumber($chequeType, $payload),
            'bank_id' => $bankId,
            'party_id' => $party?->id,
            'chequebook_id' => $chequebookId,
            'cheque_type' => $chequeType,
            'amount' => $amount > 0 ? $amount : 0,
            'currency_code' => (string) ($payload['currency_code'] ?? 'IRR'),
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'payer_name' => $chequeType === Cheque::TYPE_RECEIVED ? (string) ($party?->name ?? '') : $companyName,
            'payee_name' => $chequeType === Cheque::TYPE_ISSUED ? (string) ($party?->name ?? '') : $companyName,
            'status' => $chequeType === Cheque::TYPE_ISSUED ? Cheque::STATUS_ISSUED : Cheque::STATUS_PENDING,
            'notes' => (string) ($payload['notes'] ?? ''),
            'source_type' => isset($payload['source_type']) ? (string) $payload['source_type'] : null,
            'source_id' => isset($payload['source_id']) ? (int) $payload['source_id'] : null,
            'meta_json' => [
                'auto_created' => true,
                'auto_context' => (string) ($payload['context'] ?? ''),
            ],
        ]);

        return $cheque;
    }

    public function attachSource(Cheque $cheque, string $sourceType, int $sourceId): void
    {
        $meta = $cheque->meta_json;
        if (! is_array($meta)) {
            $meta = [];
        }
        $meta['source_linked'] = true;

        $payload = [
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'meta_json' => $meta,
        ];
        if (str_contains($sourceType, 'Payment')) {
            $payload['payment_id'] = $sourceId;
        }

        $cheque->update($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function buildAutoNumber(string $chequeType, array $payload): string
    {
        $prefix = $chequeType === Cheque::TYPE_ISSUED ? 'AUTO-ICHQ-' : 'AUTO-RCHQ-';
        $source = (string) ($payload['source_short'] ?? '');
        if ($source !== '') {
            $prefix .= strtoupper($source).'-';
        }

        return $prefix.now()->format('YmdHis').'-'.random_int(100, 999);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveBankId(array $payload): int
    {
        $bankId = (int) ($payload['bank_id'] ?? 0);
        if ($bankId > 0) {
            return $bankId;
        }

        $bankId = (int) Bank::query()->where('active', true)->orderBy('id')->value('id');

        return $bankId > 0 ? $bankId : (int) Bank::query()->orderBy('id')->value('id');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveChequebookId(int $bankId, array $payload): ?int
    {
        $manual = (int) ($payload['chequebook_id'] ?? 0);
        if ($manual > 0) {
            return $manual;
        }

        if ($bankId > 0) {
            $book = Chequebook::query()
                ->where('bank_id', $bankId)
                ->where('active', true)
                ->orderBy('id')
                ->first();
            if ($book) {
                return (int) $book->id;
            }
        }

        return null;
    }

    protected function normalizeDateString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return $trimmed;
    }
}

