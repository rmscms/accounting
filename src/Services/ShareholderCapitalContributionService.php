<?php

declare(strict_types=1);

namespace RMS\Accounting\Services;

use Illuminate\Support\Facades\DB;
use RMS\Accounting\Models\Bank;
use RMS\Accounting\Models\CashBox;
use RMS\Accounting\Models\Currency;
use RMS\Accounting\Models\Shareholder;
use RMS\Accounting\Models\ShareholderCapitalContribution;
use RMS\Accounting\Support\AuditActor;

class ShareholderCapitalContributionService
{
    public function __construct(
        protected ManualJournalService $manualJournalService,
        protected ShareholderAccountProvisioningService $shareholderAccountProvisioningService,
    ) {}

    /**
     * واریز / افزایش سرمایه: بدهکار بانک/صندوق، بستانکار سرمایهٔ همان سهامدار.
     *
     * @param  array{shareholder_id:int,amount:float|int|string,journal_date:string,source_type:string,bank_id?:?int,cash_box_id?:?int,description?:?string,currency_code?:string}  $data
     */
    public function record(array $data): ShareholderCapitalContribution
    {
        if (empty($data['shareholder_id'])) {
            throw new \InvalidArgumentException('shareholder_id is required.');
        }

        return DB::transaction(function () use ($data) {
            /** @var Shareholder $shareholder */
            $shareholder = Shareholder::query()->findOrFail((int) $data['shareholder_id']);
            $this->shareholderAccountProvisioningService->ensureAccounts($shareholder);
            $shareholder->refresh();

            $sourceType = (string) $data['source_type'];
            $assetAccountId = $this->resolveTreasuryAccountId($sourceType, $data);
            $currency = $this->normalizeLedgerCurrency((string) ($data['currency_code'] ?? $this->defaultCurrencyForTreasury($sourceType, $data)));
            $amount = (float) $data['amount'];
            if ($amount <= 0) {
                throw new \InvalidArgumentException('amount must be positive.');
            }

            $capitalAccountId = (int) $shareholder->capital_account_id;
            $description = (string) ($data['description'] ?? 'واریز / افزایش سرمایه — '.$shareholder->name);

            $journal = $this->manualJournalService->createJournal([
                'journal_date' => $data['journal_date'],
                'description' => $description,
                'lines' => [
                    [
                        'account_id' => $assetAccountId,
                        'debit_amount' => $amount,
                        'credit_amount' => 0,
                        'currency_code' => $currency,
                        'description' => $description,
                    ],
                    [
                        'account_id' => $capitalAccountId,
                        'debit_amount' => 0,
                        'credit_amount' => $amount,
                        'currency_code' => $currency,
                        'description' => $description,
                    ],
                ],
            ]);

            $this->manualJournalService->postJournal($journal->id);

            $payload = [
                'shareholder_id' => $shareholder->id,
                'amount' => $amount,
                'currency_code' => $currency,
                'journal_date' => $data['journal_date'],
                'source_type' => $sourceType,
                'bank_id' => $sourceType === ShareholderCapitalContribution::SOURCE_BANK ? ($data['bank_id'] ?? null) : null,
                'cash_box_id' => $sourceType === ShareholderCapitalContribution::SOURCE_CASH ? ($data['cash_box_id'] ?? null) : null,
                'description' => $data['description'] ?? null,
                'manual_journal_id' => $journal->id,
            ];
            $payload = AuditActor::stamp($payload, 'shareholder_capital_contributions', 'created');

            return ShareholderCapitalContribution::create($payload);
        });
    }

    protected function resolveTreasuryAccountId(string $sourceType, array $data): int
    {
        if ($sourceType === ShareholderCapitalContribution::SOURCE_BANK) {
            $bankId = (int) ($data['bank_id'] ?? 0);
            if ($bankId < 1) {
                throw new \InvalidArgumentException('bank_id is required for bank contributions.');
            }
            $bank = Bank::query()->findOrFail($bankId);
            if (! $bank->account_id) {
                throw new \RuntimeException('Bank has no linked GL account.');
            }

            return (int) $bank->account_id;
        }

        if ($sourceType === ShareholderCapitalContribution::SOURCE_CASH) {
            $cashId = (int) ($data['cash_box_id'] ?? 0);
            if ($cashId < 1) {
                throw new \InvalidArgumentException('cash_box_id is required for cash contributions.');
            }
            $cash = CashBox::query()->findOrFail($cashId);
            if (! $cash->account_id) {
                throw new \RuntimeException('Cash box has no linked GL account.');
            }

            return (int) $cash->account_id;
        }

        throw new \InvalidArgumentException('Invalid source_type.');
    }

    protected function defaultCurrencyForTreasury(string $sourceType, array $data): string
    {
        if ($sourceType === ShareholderCapitalContribution::SOURCE_BANK) {
            $bank = Bank::query()->find($data['bank_id'] ?? 0);

            return $this->normalizeLedgerCurrency($bank && $bank->currency_code
                ? (string) $bank->currency_code
                : $this->resolveSystemBaseCurrencyCode());
        }
        $cash = CashBox::query()->find($data['cash_box_id'] ?? 0);

        return $this->normalizeLedgerCurrency($cash && $cash->currency_code
            ? (string) $cash->currency_code
            : $this->resolveSystemBaseCurrencyCode());
    }

    protected function normalizeLedgerCurrency(string $code): string
    {
        $normalized = strtoupper(trim($code));

        return $normalized !== '' ? $normalized : $this->resolveSystemBaseCurrencyCode();
    }

    protected function resolveSystemBaseCurrencyCode(): string
    {
        return Currency::resolveBaseCurrencyCode('IRR');
    }
}
