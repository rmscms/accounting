<?php

declare(strict_types=1);

namespace RMS\Accounting\Services;

use Illuminate\Support\Facades\DB;
use RMS\Accounting\Models\Bank;
use RMS\Accounting\Models\CashBox;
use RMS\Accounting\Models\Currency;
use RMS\Accounting\Models\Shareholder;
use RMS\Accounting\Models\ShareholderWithdrawal;
use RMS\Accounting\Support\AuditActor;

class ShareholderWithdrawalService
{
    public function __construct(
        protected ManualJournalService $manualJournalService,
        protected ShareholderAccountProvisioningService $shareholderAccountProvisioningService,
    ) {}

    /**
     * برداشت نقد: بدهکار برداشت سهامدار، بستانکار بانک/صندوق. سهامدار اجباری.
     *
     * @param  array{shareholder_id:int,amount:float|int|string,journal_date:string,source_type:string,bank_id?:?int,cash_box_id?:?int,description?:?string,currency_code?:string,post_journal?:bool}  $data
     */
    public function record(array $data): ShareholderWithdrawal
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

            $drawingsAccountId = (int) $shareholder->drawings_account_id;
            $description = (string) ($data['description'] ?? 'برداشت صاحب سهام — '.$shareholder->name);

            $journal = $this->manualJournalService->createJournal([
                'journal_date' => $data['journal_date'],
                'description' => $description,
                'lines' => [
                    [
                        'account_id' => $drawingsAccountId,
                        'debit_amount' => $amount,
                        'credit_amount' => 0,
                        'currency_code' => $currency,
                        'description' => $description,
                    ],
                    [
                        'account_id' => $assetAccountId,
                        'debit_amount' => 0,
                        'credit_amount' => $amount,
                        'currency_code' => $currency,
                        'description' => $description,
                    ],
                ],
            ]);

            $postJournal = array_key_exists('post_journal', $data) ? (bool) $data['post_journal'] : true;
            if ($postJournal) {
                $this->manualJournalService->postJournal($journal->id);
            }

            $payload = [
                'shareholder_id' => $shareholder->id,
                'amount' => $amount,
                'currency_code' => $currency,
                'journal_date' => $data['journal_date'],
                'source_type' => $sourceType,
                'bank_id' => $sourceType === ShareholderWithdrawal::SOURCE_BANK ? ($data['bank_id'] ?? null) : null,
                'cash_box_id' => $sourceType === ShareholderWithdrawal::SOURCE_CASH ? ($data['cash_box_id'] ?? null) : null,
                'description' => $data['description'] ?? null,
                'manual_journal_id' => $journal->id,
            ];
            $payload = AuditActor::stamp($payload, 'shareholder_withdrawals', 'created');

            return ShareholderWithdrawal::create($payload);
        });
    }

    public function postDraftByWithdrawal(ShareholderWithdrawal $withdrawal): ShareholderWithdrawal
    {
        $journalId = (int) ($withdrawal->manual_journal_id ?? 0);
        if ($journalId < 1) {
            throw new \RuntimeException((string) trans('accounting::accounting.withdrawals.error_no_journal'));
        }

        $this->manualJournalService->postJournal($journalId);

        return $withdrawal->fresh(['manualJournal']) ?? $withdrawal;
    }

    protected function resolveTreasuryAccountId(string $sourceType, array $data): int
    {
        if ($sourceType === ShareholderWithdrawal::SOURCE_BANK) {
            $bankId = (int) ($data['bank_id'] ?? 0);
            if ($bankId < 1) {
                throw new \InvalidArgumentException('bank_id is required for bank withdrawals.');
            }
            $bank = Bank::query()->findOrFail($bankId);
            if (! $bank->account_id) {
                throw new \RuntimeException('Bank has no linked GL account.');
            }

            return (int) $bank->account_id;
        }

        if ($sourceType === ShareholderWithdrawal::SOURCE_CASH) {
            $cashId = (int) ($data['cash_box_id'] ?? 0);
            if ($cashId < 1) {
                throw new \InvalidArgumentException('cash_box_id is required for cash withdrawals.');
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
        if ($sourceType === ShareholderWithdrawal::SOURCE_BANK) {
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
