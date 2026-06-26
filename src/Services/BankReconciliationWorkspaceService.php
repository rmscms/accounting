<?php

declare(strict_types=1);

namespace RMS\Accounting\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RMS\Accounting\Models\Account;
use RMS\Accounting\Models\AccountingDocument;
use RMS\Accounting\Models\Bank;
use RMS\Accounting\Models\BankReconciliation;
use RMS\Accounting\Models\BankReconciliationItem;
use RMS\Accounting\Models\BankReconciliationJournalDraft;
use RMS\Accounting\Models\Cheque;
use RMS\Accounting\Models\CustomerPayment;
use RMS\Accounting\Models\FinancialLedger;
use RMS\Accounting\Models\ManualJournal;

class BankReconciliationWorkspaceService
{
    public function __construct(
        protected ManualJournalService $manualJournalService,
        protected SystemAccountLocator $systemAccountLocator
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function openOrCreateSession(array $payload, ?int $adminUserId): BankReconciliation
    {
        $bankId = (int) ($payload['bank_id'] ?? 0);
        $statementDate = (string) ($payload['statement_date'] ?? '');
        if ($bankId < 1 || $statementDate === '') {
            throw new \InvalidArgumentException('bank_id and statement_date are required.');
        }

        $bank = Bank::query()->findOrFail($bankId);
        if (! $bank->account_id) {
            throw new \RuntimeException((string) trans('accounting::accounting.bank_reconciliation.errors.bank_account_missing'));
        }

        $bookBalance = $this->calculateBookBalance((int) $bank->account_id, $statementDate);
        $statementBalance = $this->normalizeAmount((string) ($payload['bank_statement_balance'] ?? '0'));

        $session = BankReconciliation::query()
            ->where('bank_id', $bankId)
            ->whereDate('statement_date', $statementDate)
            ->where('status', BankReconciliation::STATUS_DRAFT)
            ->first();

        if (! $session) {
            $session = BankReconciliation::query()->create([
                'bank_id' => $bankId,
                'gl_account_id' => (int) $bank->account_id,
                'statement_date' => $statementDate,
                'book_balance' => $bookBalance,
                'bank_statement_balance' => $statementBalance,
                'adjusted_book_balance' => $bookBalance,
                'adjusted_bank_balance' => $statementBalance,
                'difference_amount' => $bookBalance - $statementBalance,
                'status' => BankReconciliation::STATUS_DRAFT,
                'is_balanced' => false,
                'created_by_user_id' => $adminUserId,
                'updated_by_user_id' => $adminUserId,
                'notes' => (string) ($payload['notes'] ?? ''),
            ]);
        } else {
            $session->fill([
                'book_balance' => $bookBalance,
                'bank_statement_balance' => $statementBalance,
                'updated_by_user_id' => $adminUserId,
                'notes' => (string) ($payload['notes'] ?? ''),
            ])->save();
        }

        // Draft session باید فقط آیتم Draft/Posted داشته باشد (نه Confirmed)
        if ((string) $session->status === BankReconciliation::STATUS_DRAFT) {
            $session->items()
                ->where('state', BankReconciliationItem::STATE_CONFIRMED)
                ->update(['state' => BankReconciliationItem::STATE_DRAFT]);
        }

        $this->recalculate($session);

        return $session->fresh(['bank', 'items.journalDrafts.manualJournal', 'items.attachments', 'attachments']);
    }

    public function getSessionOrFail(int $sessionId): BankReconciliation
    {
        return BankReconciliation::query()
            ->with(['bank', 'items.journalDrafts.manualJournal', 'items.attachments', 'attachments'])
            ->findOrFail($sessionId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listOutstandingCheques(BankReconciliation $session): array
    {
        $existingChequeIds = $session->items()
            ->where('item_type', BankReconciliationItem::TYPE_OUTSTANDING_CHEQUE)
            ->where('reference_type', Cheque::class)
            ->pluck('reference_id')
            ->filter()
            ->map(static fn ($v) => (int) $v)
            ->all();

        $rows = Cheque::query()
            ->where('bank_id', (int) $session->bank_id)
            ->where('cheque_type', Cheque::TYPE_ISSUED)
            ->whereIn('status', [Cheque::STATUS_ISSUED, Cheque::STATUS_PENDING])
            ->where(function ($query) use ($session): void {
                $statementDate = (string) $session->statement_date?->format('Y-m-d');
                $query
                    ->whereDate('issue_date', '<=', $statementDate)
                    ->orWhere(function ($sub) use ($statementDate): void {
                        $sub->whereNull('issue_date')
                            ->whereDate('due_date', '<=', $statementDate);
                    })
                    ->orWhere(function ($sub): void {
                        $sub->whereNull('issue_date')
                            ->whereNull('due_date');
                    });
            })
            ->when($existingChequeIds !== [], fn ($q) => $q->whereNotIn('id', $existingChequeIds))
            ->orderBy('issue_date')
            ->orderBy('id')
            ->limit(200)
            ->get(['id', 'cheque_number', 'amount', 'issue_date', 'due_date', 'payee_name']);

        return $rows->map(function (Cheque $row): array {
            return [
                'id' => (int) $row->id,
                'number' => (string) $row->cheque_number,
                'amount' => (float) $row->amount,
                'issue_date' => $row->issue_date ? (string) $row->issue_date->format('Y-m-d') : null,
                'due_date' => $row->due_date ? (string) $row->due_date->format('Y-m-d') : null,
                'payee_name' => (string) ($row->payee_name ?? ''),
            ];
        })->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listDepositsInTransit(BankReconciliation $session): array
    {
        $existingPaymentIds = $session->items()
            ->where('item_type', BankReconciliationItem::TYPE_DEPOSIT_IN_TRANSIT)
            ->where('reference_type', CustomerPayment::class)
            ->pluck('reference_id')
            ->filter()
            ->map(static fn ($v) => (int) $v)
            ->all();

        $rows = CustomerPayment::query()
            ->where('bank_id', (int) $session->bank_id)
            ->where('status', CustomerPayment::STATUS_COMPLETED)
            ->whereDate('payment_date', '<=', (string) $session->statement_date?->format('Y-m-d'))
            ->when($existingPaymentIds !== [], fn ($q) => $q->whereNotIn('id', $existingPaymentIds))
            ->orderBy('payment_date')
            ->orderBy('id')
            ->limit(200)
            ->get(['id', 'payment_number', 'amount', 'payment_date', 'notes']);

        return $rows->map(function (CustomerPayment $row): array {
            return [
                'id' => (int) $row->id,
                'number' => (string) $row->payment_number,
                'amount' => (float) $row->amount,
                'payment_date' => $row->payment_date ? (string) $row->payment_date->format('Y-m-d') : null,
                'notes' => (string) ($row->notes ?? ''),
            ];
        })->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function addItem(BankReconciliation $session, array $payload, ?int $adminUserId): BankReconciliationItem
    {
        if ((string) $session->status === BankReconciliation::STATUS_FINALIZED) {
            throw new \RuntimeException((string) trans('accounting::accounting.bank_reconciliation.errors.session_finalized'));
        }

        $itemType = (string) ($payload['item_type'] ?? '');
        $amount = $this->normalizeAmount((string) ($payload['amount'] ?? '0'));
        if ($itemType === '' || $amount <= 0) {
            throw new \InvalidArgumentException((string) trans('accounting::accounting.bank_reconciliation.errors.invalid_item'));
        }

        [$effectSide, $effectSign] = $this->resolveEffectRule($itemType);

        $referenceType = (string) ($payload['reference_type'] ?? '');
        $referenceId = (int) ($payload['reference_id'] ?? 0);

        if ($referenceType !== '' && $referenceId > 0) {
            $already = $session->items()
                ->where('item_type', $itemType)
                ->where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->first();
            if ($already) {
                return $already;
            }
        }

        $maxOrder = (int) ($session->items()->max('display_order') ?? 0);
        $item = BankReconciliationItem::query()->create([
            'bank_reconciliation_id' => (int) $session->id,
            'item_type' => $itemType,
            'amount' => $amount,
            'effect_side' => $effectSide,
            'effect_sign' => $effectSign,
            'state' => BankReconciliationItem::STATE_DRAFT,
            'reference_type' => $referenceType !== '' ? $referenceType : null,
            'reference_id' => $referenceId > 0 ? $referenceId : null,
            'reference_number' => (string) ($payload['reference_number'] ?? ''),
            'reference_date' => (string) ($payload['reference_date'] ?? '') !== '' ? (string) $payload['reference_date'] : null,
            'description' => (string) ($payload['description'] ?? ''),
            'display_order' => $maxOrder + 1,
            'created_by_user_id' => $adminUserId,
            'updated_by_user_id' => $adminUserId,
        ]);

        if (in_array($itemType, [BankReconciliationItem::TYPE_BANK_CHARGE, BankReconciliationItem::TYPE_INTEREST_INCOME], true)) {
            $this->upsertJournalDraftForItem($session, $item);
        }

        $this->recalculate($session);

        return $item->fresh(['journalDrafts.manualJournal']);
    }

    public function removeItem(BankReconciliation $session, int $itemId): void
    {
        if ((string) $session->status === BankReconciliation::STATUS_FINALIZED) {
            throw new \RuntimeException((string) trans('accounting::accounting.bank_reconciliation.errors.session_finalized'));
        }

        $item = $session->items()->whereKey($itemId)->firstOrFail();
        $item->delete();
        $this->recalculate($session);
    }

    public function deleteSession(BankReconciliation $session): void
    {
        if ((string) $session->status === BankReconciliation::STATUS_FINALIZED) {
            throw new \RuntimeException((string) trans('accounting::accounting.bank_reconciliation.errors.session_finalized'));
        }

        $session->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function validateSession(BankReconciliation $session): array
    {
        $session = $this->recalculate($session);

        return $this->sessionMetrics($session);
    }

    /**
     * @return array<int, string>
     */
    public function missingFinalizeAccountTags(BankReconciliation $session): array
    {
        $session->loadMissing('items');

        $missing = [];
        $hasBankCharge = $session->items->contains(
            fn (BankReconciliationItem $item): bool => $item->item_type === BankReconciliationItem::TYPE_BANK_CHARGE
        );
        $hasInterestIncome = $session->items->contains(
            fn (BankReconciliationItem $item): bool => $item->item_type === BankReconciliationItem::TYPE_INTEREST_INCOME
        );

        if ($hasBankCharge && ! $this->systemAccountLocator->accountBySystemKey('expenses.bank_charges')) {
            $missing[] = 'expenses.bank_charges';
        }
        if ($hasInterestIncome && ! $this->systemAccountLocator->accountBySystemKey('revenue.bank_interest_income')) {
            $missing[] = 'revenue.bank_interest_income';
        }

        return $missing;
    }

    public function finalizeSession(BankReconciliation $session, ?int $adminUserId): BankReconciliation
    {
        if ((string) $session->status === BankReconciliation::STATUS_FINALIZED) {
            return $session;
        }

        return DB::transaction(function () use ($session, $adminUserId): BankReconciliation {
            $session = $this->recalculate($session->fresh(['items.journalDrafts']));
            if (! $session->is_balanced) {
                throw new \RuntimeException((string) trans('accounting::accounting.bank_reconciliation.errors.not_balanced'));
            }

            foreach ($session->items as $item) {
                if (! in_array($item->item_type, [BankReconciliationItem::TYPE_BANK_CHARGE, BankReconciliationItem::TYPE_INTEREST_INCOME], true)) {
                    continue;
                }
                $draft = $item->journalDrafts()->latest('id')->first();
                if ($draft && $draft->manual_journal_id) {
                    continue;
                }
                $journal = $this->postJournalFromItem($session, $item);
                $draft = $draft ?: new BankReconciliationJournalDraft(['bank_reconciliation_item_id' => (int) $item->id]);
                $draft->journal_payload_json = $this->buildJournalPayloadForItem($session, $item);
                $draft->manual_journal_id = (int) $journal->id;
                $draft->posted_at = now();
                $draft->save();
                $item->state = BankReconciliationItem::STATE_POSTED;
                $item->save();
            }

            // آیتم‌های غیرپست‌شده در لحظه نهایی‌سازی، تایید می‌شوند
            $session->items()
                ->where('state', BankReconciliationItem::STATE_DRAFT)
                ->update([
                    'state' => BankReconciliationItem::STATE_CONFIRMED,
                    'updated_by_user_id' => $adminUserId,
                ]);

            $session->update([
                'status' => BankReconciliation::STATUS_FINALIZED,
                'finalized_at' => now(),
                'finalized_by_user_id' => $adminUserId,
                'updated_by_user_id' => $adminUserId,
            ]);

            return $session->fresh(['bank', 'items.journalDrafts.manualJournal', 'attachments']);
        });
    }

    public function recalculate(BankReconciliation $session): BankReconciliation
    {
        // Always reload items from DB to avoid stale in-memory relation cache
        // (e.g., right after deleting an item in the same request cycle).
        $session->load('items');

        $book = (float) $session->book_balance;
        $bank = (float) $session->bank_statement_balance;
        $adjustedBook = $book;
        $adjustedBank = $bank;

        foreach ($session->items as $item) {
            $signedAmount = (float) $item->amount * (float) $item->effect_sign;
            if ((string) $item->effect_side === BankReconciliationItem::EFFECT_SIDE_BOOK) {
                $adjustedBook += $signedAmount;
                continue;
            }
            $adjustedBank += $signedAmount;
        }

        $difference = round($adjustedBook - $adjustedBank, 4);
        $isBalanced = abs($difference) < 0.0001;

        $session->update([
            'adjusted_book_balance' => $adjustedBook,
            'adjusted_bank_balance' => $adjustedBank,
            'difference_amount' => $difference,
            'is_balanced' => $isBalanced,
        ]);

        return $session->fresh(['items.journalDrafts.manualJournal', 'attachments', 'bank']);
    }

    /**
     * @return array<string, mixed>
     */
    public function sessionMetrics(BankReconciliation $session): array
    {
        return [
            'book_balance' => (float) $session->book_balance,
            'bank_statement_balance' => (float) $session->bank_statement_balance,
            'adjusted_book_balance' => (float) $session->adjusted_book_balance,
            'adjusted_bank_balance' => (float) $session->adjusted_bank_balance,
            'difference_amount' => (float) $session->difference_amount,
            'is_balanced' => (bool) $session->is_balanced,
            'status' => (string) $session->status,
            'items_count' => (int) $session->items()->count(),
        ];
    }

    protected function calculateBookBalance(int $accountId, string $asOfDate): float
    {
        $endOfDay = Carbon::parse($asOfDate)->endOfDay();
        $docTimeExpr = 'COALESCE(ad.posted_at, ad.created_at)';

        $balance = (float) FinancialLedger::query()
            ->from('financial_ledgers as fl')
            ->join('accounting_documents as ad', 'ad.id', '=', 'fl.accounting_document_id')
            ->where('fl.account_id', $accountId)
            ->where('ad.status', AccountingDocument::STATUS_POSTED)
            ->whereRaw($docTimeExpr.' <= ?', [$endOfDay->format('Y-m-d H:i:s')])
            ->sum('fl.amount_base');

        return round($balance, 4);
    }

    protected function normalizeAmount(string $raw): float
    {
        $value = trim(\RMS\Helper\changeNumberToEn($raw));
        $value = str_replace(['٬', '،', ',', ' '], '', $value);
        if ($value === '' || ! is_numeric($value)) {
            return 0.0;
        }

        return (float) $value;
    }

    /**
     * @return array{0:string,1:float}
     */
    protected function resolveEffectRule(string $itemType): array
    {
        return match ($itemType) {
            BankReconciliationItem::TYPE_OUTSTANDING_CHEQUE => [BankReconciliationItem::EFFECT_SIDE_BANK, -1.0],
            BankReconciliationItem::TYPE_DEPOSIT_IN_TRANSIT => [BankReconciliationItem::EFFECT_SIDE_BANK, 1.0],
            BankReconciliationItem::TYPE_BANK_CHARGE => [BankReconciliationItem::EFFECT_SIDE_BOOK, -1.0],
            BankReconciliationItem::TYPE_INTEREST_INCOME => [BankReconciliationItem::EFFECT_SIDE_BOOK, 1.0],
            BankReconciliationItem::TYPE_MANUAL_ADJUSTMENT => [BankReconciliationItem::EFFECT_SIDE_BOOK, 1.0],
            default => throw new \InvalidArgumentException((string) trans('accounting::accounting.bank_reconciliation.errors.invalid_item_type')),
        };
    }

    protected function upsertJournalDraftForItem(BankReconciliation $session, BankReconciliationItem $item): void
    {
        $draft = $item->journalDrafts()->latest('id')->first();
        $payload = $this->buildJournalPayloadForItem($session, $item);
        if (! $draft) {
            BankReconciliationJournalDraft::query()->create([
                'bank_reconciliation_item_id' => (int) $item->id,
                'journal_payload_json' => $payload,
            ]);

            return;
        }

        if ($draft->manual_journal_id) {
            return;
        }

        $draft->journal_payload_json = $payload;
        $draft->save();
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildJournalPayloadForItem(BankReconciliation $session, BankReconciliationItem $item): array
    {
        $sessionDate = (string) $session->statement_date?->format('Y-m-d');
        $amount = (float) $item->amount;
        $bank = $session->bank()->firstOrFail();
        $bankAccountId = (int) ($bank->account_id ?? 0);
        if ($bankAccountId < 1) {
            throw new \RuntimeException((string) trans('accounting::accounting.bank_reconciliation.errors.bank_account_missing'));
        }

        if ($item->item_type === BankReconciliationItem::TYPE_BANK_CHARGE) {
            $expenseAccount = $this->resolveFallbackAccount([
                'expenses.bank_charges',
                'expenses.operating_expenses',
            ], 'expense');

            return [
                'journal_date' => $sessionDate,
                'description' => (string) trans('accounting::accounting.bank_reconciliation.journal.bank_charge_description'),
                'lines' => [
                    [
                        'account_id' => (int) $expenseAccount->id,
                        'debit_amount' => $amount,
                        'credit_amount' => 0,
                        'currency_code' => 'IRR',
                    ],
                    [
                        'account_id' => $bankAccountId,
                        'debit_amount' => 0,
                        'credit_amount' => $amount,
                        'currency_code' => 'IRR',
                    ],
                ],
            ];
        }

        if ($item->item_type === BankReconciliationItem::TYPE_INTEREST_INCOME) {
            $incomeAccount = $this->resolveFallbackAccount([
                'revenue.bank_interest_income',
                'revenue.employee_loan_interest_income',
            ], 'revenue');

            return [
                'journal_date' => $sessionDate,
                'description' => (string) trans('accounting::accounting.bank_reconciliation.journal.interest_income_description'),
                'lines' => [
                    [
                        'account_id' => $bankAccountId,
                        'debit_amount' => $amount,
                        'credit_amount' => 0,
                        'currency_code' => 'IRR',
                    ],
                    [
                        'account_id' => (int) $incomeAccount->id,
                        'debit_amount' => 0,
                        'credit_amount' => $amount,
                        'currency_code' => 'IRR',
                    ],
                ],
            ];
        }

        return [];
    }

    /**
     * @param  array<int, string>  $systemKeys
     */
    protected function resolveFallbackAccount(array $systemKeys, string $accountType): Account
    {
        foreach ($systemKeys as $key) {
            $account = $this->systemAccountLocator->accountBySystemKey($key);
            if ($account) {
                return $account;
            }
        }

        $fallback = Account::query()
            ->where('account_type', $accountType)
            ->where('active', true)
            ->orderBy('code')
            ->first();
        if ($fallback) {
            return $fallback;
        }

        throw new \RuntimeException((string) trans('accounting::accounting.bank_reconciliation.errors.journal_payload_missing'));
    }

    protected function postJournalFromItem(BankReconciliation $session, BankReconciliationItem $item): ManualJournal
    {
        $payload = $this->buildJournalPayloadForItem($session, $item);
        if ($payload === []) {
            throw new \RuntimeException((string) trans('accounting::accounting.bank_reconciliation.errors.journal_payload_missing'));
        }

        $journal = $this->manualJournalService->createJournal($payload);

        return $this->manualJournalService->postJournal((int) $journal->id);
    }
}

