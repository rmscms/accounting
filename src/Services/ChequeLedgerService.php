<?php

namespace RMS\Accounting\Services;

use Illuminate\Support\Facades\DB;
use RMS\Accounting\Models\Account;
use RMS\Accounting\Models\AccountingDocument;
use RMS\Accounting\Models\Bank;
use RMS\Accounting\Models\Cheque;
use RMS\Accounting\Models\FiscalYear;
use RMS\Accounting\Models\Party;
use RMS\Core\Models\Setting;

/**
 * ثبت دفتر کل دو مرحله‌ای برای چک (انتظامی ↔ بانک) و کمک به مسیر پرداخت/دریافت با cheque_id.
 */
class ChequeLedgerService
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {
    }

    public function resolveReceivableClearingAccountId(): ?int
    {
        $code = trim((string) Setting::get('accounting.system_accounts.assets.cheques_receivable_clearing', ''));
        if ($code) {
            $found = (int) Account::query()->where('code', (string) $code)->value('id');
            if ($found > 0) {
                return $found;
            }
        }

        return null;
    }

    public function resolvePayableClearingAccountId(): ?int
    {
        $code = trim((string) Setting::get('accounting.system_accounts.liabilities.cheques_payable_clearing', ''));
        if ($code) {
            $found = (int) Account::query()->where('code', (string) $code)->value('id');
            if ($found > 0) {
                return $found;
            }
        }

        return null;
    }

    public function canCashCheque(Cheque $cheque): bool
    {
        if (in_array($cheque->status, [Cheque::STATUS_CASHED, Cheque::STATUS_BOUNCED, Cheque::STATUS_CANCELLED], true)) {
            return false;
        }
        if ($cheque->cheque_type === Cheque::TYPE_RECEIVED) {
            return $cheque->status === Cheque::STATUS_PENDING;
        }

        return in_array($cheque->status, [Cheque::STATUS_ISSUED, Cheque::STATUS_PENDING], true);
    }

    public function canBounceCheque(Cheque $cheque): bool
    {
        return in_array($cheque->status, [Cheque::STATUS_PENDING, Cheque::STATUS_ISSUED, Cheque::STATUS_CASHED], true);
    }

    public function hasRequiredClearingAccounts(): bool
    {
        return (int) ($this->resolveReceivableClearingAccountId() ?? 0) > 0
            && (int) ($this->resolvePayableClearingAccountId() ?? 0) > 0;
    }

    public function hasRequiredCounterpartyFallbackAccounts(): bool
    {
        return (int) ($this->resolveDefaultReceivableAccountId() ?? 0) > 0
            && (int) ($this->resolveDefaultPayableAccountId() ?? 0) > 0;
    }

    /**
     * مرحلهٔ دوم: وصول چک دریافتی یا پاس شدن چک پرداختی در بانک — بدهکار بانک / بستانکار انتظامی دریافتی یا بدهکار انتظامی پرداختی / بستانکار بانک.
     */
    public function recordChequeCashed(Cheque $cheque): void
    {
        if (! $this->canCashCheque($cheque)) {
            throw new \DomainException(trans('accounting::accounting.errors.cheque_not_cashable'));
        }

        $amount = (float) $cheque->amount;
        $ccy = strtoupper((string) $cheque->currency_code);
        $bankAccountId = $this->resolveBankGlAccountId($cheque);

        DB::transaction(function () use ($cheque, $amount, $ccy, $bankAccountId) {
            if ($cheque->cheque_type === Cheque::TYPE_RECEIVED) {
                $clearingId = $this->resolveReceivableClearingAccountId();
                if ($clearingId) {
                    $this->ledgerService->recordTransaction([
                        'document_type' => AccountingDocument::TYPE_RECEIPT,
                        'description' => trans('accounting::accounting.cheques.ledger.cash_received', ['number' => $cheque->cheque_number]),
                        'store_id' => 0,
                        'fiscal_year_id' => $this->resolveFiscalYearId(),
                        'reference_type' => Cheque::class,
                        'reference_id' => $cheque->id,
                    ], [
                        [
                            'account_id' => $bankAccountId,
                            'debit' => $amount,
                            'credit' => 0,
                            'currency_code' => $ccy,
                            'description' => trans('accounting::accounting.cheques.ledger.bank_debit'),
                        ],
                        [
                            'account_id' => $clearingId,
                            'debit' => 0,
                            'credit' => $amount,
                            'currency_code' => $ccy,
                            'description' => trans('accounting::accounting.cheques.ledger.clearing_receivable_credit'),
                        ],
                    ]);
                }
            } else {
                $clearingId = $this->resolvePayableClearingAccountId();
                if ($clearingId) {
                    $this->ledgerService->recordTransaction([
                        'document_type' => AccountingDocument::TYPE_PAYMENT,
                        'description' => trans('accounting::accounting.cheques.ledger.cash_issued', ['number' => $cheque->cheque_number]),
                        'store_id' => 0,
                        'fiscal_year_id' => $this->resolveFiscalYearId(),
                        'reference_type' => Cheque::class,
                        'reference_id' => $cheque->id,
                    ], [
                        [
                            'account_id' => $clearingId,
                            'debit' => $amount,
                            'credit' => 0,
                            'currency_code' => $ccy,
                            'description' => trans('accounting::accounting.cheques.ledger.clearing_payable_debit'),
                        ],
                        [
                            'account_id' => $bankAccountId,
                            'debit' => 0,
                            'credit' => $amount,
                            'currency_code' => $ccy,
                            'description' => trans('accounting::accounting.cheques.ledger.bank_credit'),
                        ],
                    ]);
                }
            }

            $cheque->update([
                'status' => Cheque::STATUS_CASHED,
                'cashed_at' => now(),
            ]);
        });
    }

    public function recordChequeCreated(Cheque $cheque): void
    {
        if ((int) ($cheque->accounting_document_id ?? 0) > 0) {
            return;
        }

        $amount = (float) $cheque->amount;
        if ($amount <= 0) {
            throw new \DomainException(trans('accounting::accounting.cheques.errors.invalid_amount'));
        }

        $ccy = strtoupper((string) ($cheque->currency_code ?: 'IRR'));

        DB::transaction(function () use ($cheque, $amount, $ccy): void {
            if ($cheque->cheque_type === Cheque::TYPE_RECEIVED) {
                $clearingId = $this->resolveReceivableClearingAccountId();
                $counterpartyId = $this->resolveCounterpartyReceivableAccountId($cheque);
                if (! $clearingId || ! $counterpartyId) {
                    throw new \DomainException($this->buildDetailedPostingSetupError($cheque));
                }

                $document = $this->ledgerService->recordTransaction([
                    'document_type' => AccountingDocument::TYPE_RECEIPT,
                    'description' => trans('accounting::accounting.cheques.ledger.created_received', ['number' => $cheque->cheque_number]),
                    'store_id' => 0,
                    'fiscal_year_id' => $this->resolveFiscalYearId(),
                    'reference_type' => Cheque::class,
                    'reference_id' => $cheque->id,
                ], [
                    [
                        'account_id' => $clearingId,
                        'debit' => $amount,
                        'credit' => 0,
                        'currency_code' => $ccy,
                    ],
                    [
                        'account_id' => $counterpartyId,
                        'debit' => 0,
                        'credit' => $amount,
                        'currency_code' => $ccy,
                    ],
                ]);

                $cheque->update([
                    'status' => Cheque::STATUS_PENDING,
                    'accounting_document_id' => (int) $document->id,
                ]);

                return;
            }

            $clearingId = $this->resolvePayableClearingAccountId();
            $counterpartyId = $this->resolveCounterpartyPayableAccountId($cheque);
            if (! $clearingId || ! $counterpartyId) {
                throw new \DomainException($this->buildDetailedPostingSetupError($cheque));
            }

            $document = $this->ledgerService->recordTransaction([
                'document_type' => AccountingDocument::TYPE_PAYMENT,
                'description' => trans('accounting::accounting.cheques.ledger.created_issued', ['number' => $cheque->cheque_number]),
                'store_id' => 0,
                'fiscal_year_id' => $this->resolveFiscalYearId(),
                'reference_type' => Cheque::class,
                'reference_id' => $cheque->id,
            ], [
                [
                    'account_id' => $counterpartyId,
                    'debit' => $amount,
                    'credit' => 0,
                    'currency_code' => $ccy,
                ],
                [
                    'account_id' => $clearingId,
                    'debit' => 0,
                    'credit' => $amount,
                    'currency_code' => $ccy,
                ],
            ]);

            $cheque->update([
                'status' => Cheque::STATUS_ISSUED,
                'accounting_document_id' => (int) $document->id,
            ]);
        });
    }

    public function recordChequeBounced(
        Cheque $cheque,
        string $bounceReason = '',
        ?float $penaltyAmount = null,
        ?int $penaltyAccountId = null,
        ?string $penaltyNotes = null
    ): void {
        if (! $this->canBounceCheque($cheque)) {
            throw new \DomainException(trans('accounting::accounting.errors.cheque_not_bouncable'));
        }

        DB::transaction(function () use ($cheque, $bounceReason, $penaltyAmount, $penaltyAccountId, $penaltyNotes): void {
            if ((int) ($cheque->accounting_document_id ?? 0) > 0) {
                $this->ledgerService->reverseDocument(
                    (int) $cheque->accounting_document_id,
                    $bounceReason !== '' ? $bounceReason : trans('accounting::accounting.cheques.bounce_auto_reversal')
                );
            }

            $ccy = strtoupper((string) ($cheque->currency_code ?: 'IRR'));

            if ($penaltyAmount !== null && $penaltyAmount > 0) {
                $penaltyAccount = $penaltyAccountId ?: $this->resolvePenaltyExpenseAccountId();
                $counterpartyId = $cheque->cheque_type === Cheque::TYPE_ISSUED
                    ? $this->resolveCounterpartyPayableAccountId($cheque)
                    : $this->resolveCounterpartyReceivableAccountId($cheque);
                if ($penaltyAccount && $counterpartyId) {
                    if ($cheque->cheque_type === Cheque::TYPE_ISSUED) {
                        $this->ledgerService->recordTransaction([
                            'document_type' => AccountingDocument::TYPE_PAYMENT,
                            'description' => trans('accounting::accounting.cheques.ledger.bounce_penalty_issued', ['number' => $cheque->cheque_number]),
                            'store_id' => 0,
                            'fiscal_year_id' => $this->resolveFiscalYearId(),
                            'reference_type' => Cheque::class,
                            'reference_id' => $cheque->id,
                        ], [
                            ['account_id' => $penaltyAccount, 'debit' => $penaltyAmount, 'credit' => 0, 'currency_code' => $ccy],
                            ['account_id' => $counterpartyId, 'debit' => 0, 'credit' => $penaltyAmount, 'currency_code' => $ccy],
                        ]);
                    } else {
                        $this->ledgerService->recordTransaction([
                            'document_type' => AccountingDocument::TYPE_RECEIPT,
                            'description' => trans('accounting::accounting.cheques.ledger.bounce_penalty_received', ['number' => $cheque->cheque_number]),
                            'store_id' => 0,
                            'fiscal_year_id' => $this->resolveFiscalYearId(),
                            'reference_type' => Cheque::class,
                            'reference_id' => $cheque->id,
                        ], [
                            ['account_id' => $counterpartyId, 'debit' => $penaltyAmount, 'credit' => 0, 'currency_code' => $ccy],
                            ['account_id' => $penaltyAccount, 'debit' => 0, 'credit' => $penaltyAmount, 'currency_code' => $ccy],
                        ]);
                    }
                }
            }

            $cheque->update([
                'status' => Cheque::STATUS_BOUNCED,
                'bounced_at' => now(),
                'bounce_reason' => $bounceReason !== '' ? $bounceReason : null,
                'meta_json' => array_filter([
                    'bounce_penalty_amount' => $penaltyAmount,
                    'bounce_penalty_account_id' => $penaltyAccountId,
                    'bounce_penalty_notes' => $penaltyNotes,
                ], static fn ($v) => $v !== null && $v !== ''),
            ]);
        });
    }

    protected function resolveFiscalYearId(): ?int
    {
        $id = config('accounting.current_fiscal_year_id');
        if ($id) {
            return (int) $id;
        }

        return FiscalYear::getCurrentFiscalYear()?->id;
    }

    protected function resolveBankGlAccountId(Cheque $cheque): int
    {
        $bank = Bank::find($cheque->bank_id);
        if ($bank && (int) $bank->account_id) {
            return (int) $bank->account_id;
        }

        foreach (['1102', '1-102', '1020'] as $code) {
            $aid = (int) Account::query()->where('code', (string) $code)->value('id');
            if ($aid > 0) {
                return $aid;
            }
        }

        return 1;
    }

    protected function resolveCounterpartyReceivableAccountId(Cheque $cheque): ?int
    {
        $party = $this->resolveParty($cheque);
        if ($party && $party->customer && (int) ($party->customer->account_id ?? 0) > 0) {
            return (int) $party->customer->account_id;
        }

        return $this->resolveDefaultReceivableAccountId();
    }

    protected function resolveCounterpartyPayableAccountId(Cheque $cheque): ?int
    {
        $party = $this->resolveParty($cheque);
        if ($party && $party->supplier && (int) ($party->supplier->account_id ?? 0) > 0) {
            return (int) $party->supplier->account_id;
        }

        return $this->resolveDefaultPayableAccountId();
    }

    protected function resolveDefaultReceivableAccountId(): ?int
    {
        $configuredCode = trim((string) Setting::get('accounting.system_accounts.assets.accounts_receivable', ''));
        if ($configuredCode !== '') {
            $id = (int) Account::query()->where('code', $configuredCode)->value('id');
            if ($id > 0) {
                return $id;
            }
        }

        return null;
    }

    protected function resolveDefaultPayableAccountId(): ?int
    {
        $configuredCode = trim((string) Setting::get('accounting.system_accounts.liabilities.accounts_payable', ''));
        if ($configuredCode !== '') {
            $id = (int) Account::query()->where('code', $configuredCode)->value('id');
            if ($id > 0) {
                return $id;
            }
        }

        return null;
    }

    protected function resolvePenaltyExpenseAccountId(): ?int
    {
        $cfg = (string) config('accounting.system_accounts.expenses.bank_penalties');
        if ($cfg !== '') {
            $id = (int) Account::query()->where('code', $cfg)->value('id');
            if ($id > 0) {
                return $id;
            }
        }

        return (int) Account::query()
            ->where('account_type', Account::TYPE_EXPENSE)
            ->orderBy('id')
            ->value('id');
    }

    protected function resolveParty(Cheque $cheque): ?Party
    {
        $partyId = (int) ($cheque->party_id ?? 0);
        if ($partyId <= 0) {
            return null;
        }

        return Party::query()->with(['customer', 'supplier'])->find($partyId);
    }

    protected function buildDetailedPostingSetupError(Cheque $cheque): string
    {
        $issues = $this->collectPostingSetupIssues($cheque);
        if ($issues === []) {
            return (string) trans('accounting::accounting.cheques.errors.clearing_or_counterparty_missing');
        }

        return (string) trans('accounting::accounting.cheques.errors.clearing_or_counterparty_missing')
            .' ('.implode(' | ', $issues).')';
    }

    /**
     * @return array<int, string>
     */
    protected function collectPostingSetupIssues(Cheque $cheque): array
    {
        $issues = [];
        $party = $this->resolveParty($cheque);

        if ($cheque->cheque_type === Cheque::TYPE_RECEIVED) {
            if (! $this->resolveReceivableClearingAccountId()) {
                $issues[] = (string) trans('accounting::accounting.cheques.errors.issue_missing_receivable_clearing');
            }

            $hasPartyAccount = $party && $party->customer && (int) ($party->customer->account_id ?? 0) > 0;
            $hasDefaultAccount = (int) ($this->resolveDefaultReceivableAccountId() ?? 0) > 0;
            if (! $hasPartyAccount && ! $hasDefaultAccount) {
                $issues[] = (string) trans('accounting::accounting.cheques.errors.issue_missing_receivable_counterparty_mapping');
            }

            return array_values(array_unique(array_filter($issues)));
        }

        if (! $this->resolvePayableClearingAccountId()) {
            $issues[] = (string) trans('accounting::accounting.cheques.errors.issue_missing_payable_clearing');
        }

        $hasPartyAccount = $party && $party->supplier && (int) ($party->supplier->account_id ?? 0) > 0;
        $hasDefaultAccount = (int) ($this->resolveDefaultPayableAccountId() ?? 0) > 0;
        if (! $hasPartyAccount && ! $hasDefaultAccount) {
            $issues[] = (string) trans('accounting::accounting.cheques.errors.issue_missing_payable_counterparty_mapping');
        }

        return array_values(array_unique(array_filter($issues)));
    }
}
