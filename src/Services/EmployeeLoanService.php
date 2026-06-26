<?php

declare(strict_types=1);

namespace RMS\Accounting\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RMS\Accounting\Models\Bank;
use RMS\Accounting\Models\EmployeeLoan;
use RMS\Accounting\Models\EmployeeLoanInstallment;

class EmployeeLoanService
{
    public function __construct(
        protected ManualJournalService $manualJournalService,
        protected SystemAccountLocator $systemAccountLocator,
    ) {}

    /**
     * @param  array<string,mixed>  $data
     */
    public function createLoanWithDisbursement(array $data): EmployeeLoan
    {
        return DB::transaction(function () use ($data): EmployeeLoan {
            $principal = (float) ($data['principal_amount'] ?? 0);
            $rate = (float) ($data['annual_interest_rate'] ?? 0);
            $count = max(1, (int) ($data['installments_count'] ?? 1));
            $disbursementDate = (string) ($data['disbursement_date'] ?? now()->format('Y-m-d'));
            $firstDueDate = (string) ($data['first_due_date'] ?? $disbursementDate);

            $schedule = $this->buildInstallmentSchedule($principal, $rate, $count, $firstDueDate);
            $installmentAmount = (float) ($schedule[0]['installment_amount'] ?? 0);
            $totalInterest = array_sum(array_map(static fn (array $row): float => (float) $row['interest_amount'], $schedule));
            $totalAmount = $principal + $totalInterest;

            $loan = EmployeeLoan::query()->create([
                'loan_number' => (string) ($data['loan_number'] ?? EmployeeLoan::generateLoanNumber()),
                'employee_id' => (int) $data['employee_id'],
                'disbursement_bank_id' => (int) ($data['disbursement_bank_id'] ?? 0) ?: null,
                'disbursement_date' => $disbursementDate,
                'first_due_date' => $firstDueDate,
                'principal_amount' => $principal,
                'annual_interest_rate' => $rate,
                'installments_count' => $count,
                'installment_amount' => $installmentAmount,
                'total_interest_amount' => $totalInterest,
                'total_amount' => $totalAmount,
                'remaining_principal' => $principal,
                'remaining_interest' => $totalInterest,
                'remaining_total' => $totalAmount,
                'status' => EmployeeLoan::STATUS_ACTIVE,
                'notes' => (string) ($data['notes'] ?? ''),
                'created_by_user_id' => \RMS\Accounting\Support\AuditActor::userId(),
                'updated_by_user_id' => \RMS\Accounting\Support\AuditActor::userId(),
            ]);

            foreach ($schedule as $index => $row) {
                $loan->installments()->create([
                    'installment_number' => $index + 1,
                    'due_date' => $row['due_date'],
                    'opening_principal' => $row['opening_principal'],
                    'principal_amount' => $row['principal_amount'],
                    'interest_amount' => $row['interest_amount'],
                    'installment_amount' => $row['installment_amount'],
                    'remaining_amount' => $row['installment_amount'],
                    'status' => EmployeeLoanInstallment::STATUS_PENDING,
                ]);
            }

            $journalId = $this->postDisbursementJournal(
                $loan,
                (int) ($data['disbursement_bank_id'] ?? 0),
                (string) ($data['disbursement_date'] ?? now()->format('Y-m-d')),
                (string) ($data['description'] ?? '')
            );
            $loan->update(['disbursement_manual_journal_id' => $journalId]);

            return $loan->fresh(['employee', 'installments', 'disbursementJournal']);
        });
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public function updateLoan(EmployeeLoan $loan, array $data): EmployeeLoan
    {
        if ($loan->payments()->exists()) {
            throw new \RuntimeException((string) trans('accounting::accounting.employee_loans.errors.cannot_edit_with_payments'));
        }

        return DB::transaction(function () use ($loan, $data): EmployeeLoan {
            $principal = (float) ($data['principal_amount'] ?? $loan->principal_amount);
            $rate = (float) ($data['annual_interest_rate'] ?? $loan->annual_interest_rate);
            $count = max(1, (int) ($data['installments_count'] ?? $loan->installments_count));
            $disbursementDate = (string) ($data['disbursement_date'] ?? $loan->disbursement_date?->format('Y-m-d') ?? now()->format('Y-m-d'));
            $firstDueDate = (string) ($data['first_due_date'] ?? $loan->first_due_date?->format('Y-m-d') ?? $disbursementDate);
            $bankId = (int) ($data['disbursement_bank_id'] ?? $loan->disbursement_bank_id ?? 0);

            $needsJournalRepost = (
                round((float) $loan->principal_amount, 4) !== round($principal, 4)
                || (int) ($loan->disbursement_bank_id ?? 0) !== $bankId
                || ($loan->disbursement_date?->format('Y-m-d') ?? '') !== $disbursementDate
            );

            if ($needsJournalRepost && $loan->disbursement_manual_journal_id) {
                $this->manualJournalService->reverseJournal(
                    (int) $loan->disbursement_manual_journal_id,
                    'ویرایش اطلاعات وام '.$loan->loan_number
                );
            }

            $schedule = $this->buildInstallmentSchedule($principal, $rate, $count, $firstDueDate);
            $installmentAmount = (float) ($schedule[0]['installment_amount'] ?? 0);
            $totalInterest = array_sum(array_map(static fn (array $row): float => (float) $row['interest_amount'], $schedule));
            $totalAmount = $principal + $totalInterest;

            $loan->forceFill([
                'employee_id' => (int) ($data['employee_id'] ?? $loan->employee_id),
                'disbursement_bank_id' => $bankId ?: null,
                'disbursement_date' => $disbursementDate,
                'first_due_date' => $firstDueDate,
                'principal_amount' => $principal,
                'annual_interest_rate' => $rate,
                'installments_count' => $count,
                'installment_amount' => $installmentAmount,
                'total_interest_amount' => $totalInterest,
                'total_amount' => $totalAmount,
                'remaining_principal' => $principal,
                'remaining_interest' => $totalInterest,
                'remaining_total' => $totalAmount,
                'status' => EmployeeLoan::STATUS_ACTIVE,
                'notes' => (string) ($data['notes'] ?? $loan->notes),
                'updated_by_user_id' => \RMS\Accounting\Support\AuditActor::userId(),
            ])->save();

            $loan->installments()->delete();
            foreach ($schedule as $index => $row) {
                $loan->installments()->create([
                    'installment_number' => $index + 1,
                    'due_date' => $row['due_date'],
                    'opening_principal' => $row['opening_principal'],
                    'principal_amount' => $row['principal_amount'],
                    'interest_amount' => $row['interest_amount'],
                    'installment_amount' => $row['installment_amount'],
                    'remaining_amount' => $row['installment_amount'],
                    'status' => EmployeeLoanInstallment::STATUS_PENDING,
                ]);
            }

            if ($needsJournalRepost) {
                $journalId = $this->postDisbursementJournal(
                    $loan,
                    $bankId,
                    $disbursementDate,
                    (string) ($data['description'] ?? '')
                );
                $loan->forceFill(['disbursement_manual_journal_id' => $journalId])->save();
            }

            return $loan->fresh(['employee', 'installments', 'disbursementJournal']);
        });
    }

    public function cancelLoan(EmployeeLoan $loan, string $reason): EmployeeLoan
    {
        if ($loan->payments()->exists()) {
            throw new \RuntimeException((string) trans('accounting::accounting.employee_loans.errors.cannot_cancel_with_payments'));
        }
        if ($loan->status === EmployeeLoan::STATUS_CLOSED) {
            throw new \RuntimeException((string) trans('accounting::accounting.employee_loans.errors.cannot_cancel_closed'));
        }
        if ($loan->status === EmployeeLoan::STATUS_CANCELLED) {
            return $loan;
        }

        return DB::transaction(function () use ($loan, $reason): EmployeeLoan {
            if ($loan->disbursement_manual_journal_id) {
                $this->manualJournalService->reverseJournal(
                    (int) $loan->disbursement_manual_journal_id,
                    $reason !== '' ? $reason : ('ابطال وام '.$loan->loan_number)
                );
            }

            $loan->forceFill([
                'status' => EmployeeLoan::STATUS_CANCELLED,
                'remaining_principal' => 0,
                'remaining_interest' => 0,
                'remaining_total' => 0,
                'notes' => trim((string) $loan->notes."\n".'-- Cancel reason: '.$reason),
                'updated_by_user_id' => \RMS\Accounting\Support\AuditActor::userId(),
            ])->save();

            return $loan->fresh(['employee', 'installments', 'disbursementJournal']);
        });
    }

    /**
     * @return array<int,array<string,float|string>>
     */
    public function buildInstallmentSchedule(float $principal, float $annualRatePercent, int $count, string $firstDueDate): array
    {
        $count = max(1, $count);
        $monthlyRate = max(0.0, $annualRatePercent) / 100 / 12;
        $payment = $this->annuityInstallmentAmount($principal, $monthlyRate, $count);
        $remaining = $principal;
        $rows = [];
        $baseDate = Carbon::parse($firstDueDate);

        for ($i = 0; $i < $count; $i++) {
            $opening = $remaining;
            $interest = round($opening * $monthlyRate, 4);
            $principalPart = round($payment - $interest, 4);
            if ($i === $count - 1 || $principalPart > $remaining) {
                $principalPart = round($remaining, 4);
                $payment = round($principalPart + $interest, 4);
            }
            $remaining = round($remaining - $principalPart, 4);
            if ($remaining < 0) {
                $remaining = 0.0;
            }

            $rows[] = [
                'due_date' => $baseDate->copy()->addMonths($i)->format('Y-m-d'),
                'opening_principal' => round($opening, 4),
                'principal_amount' => $principalPart,
                'interest_amount' => $interest,
                'installment_amount' => round($payment, 4),
            ];
        }

        return $rows;
    }

    protected function annuityInstallmentAmount(float $principal, float $monthlyRate, int $count): float
    {
        if ($count <= 0) {
            return 0.0;
        }
        if ($monthlyRate <= 0) {
            return round($principal / $count, 4);
        }

        $factor = pow(1 + $monthlyRate, $count);

        return round($principal * ($monthlyRate * $factor) / ($factor - 1), 4);
    }

    protected function postDisbursementJournal(
        EmployeeLoan $loan,
        int $bankId,
        string $journalDate,
        string $description = ''
    ): int {
        $bank = Bank::query()->findOrFail($bankId);
        if (! $bank->account_id) {
            throw new \RuntimeException((string) trans('accounting::accounting.payroll_runs.errors.bank_missing_account'));
        }
        $loanReceivable = $this->systemAccountLocator->accountBySystemKeyOrFail('assets.employee_loans_receivable');

        $journal = $this->manualJournalService->createJournal([
            'journal_date' => $journalDate,
            'description' => $description !== '' ? $description : 'پرداخت وام کارمند '.$loan->loan_number,
            'lines' => [
                [
                    'account_id' => $loanReceivable->id,
                    'debit_amount' => (float) $loan->principal_amount,
                    'credit_amount' => 0,
                    'currency_code' => 'IRR',
                ],
                [
                    'account_id' => (int) $bank->account_id,
                    'debit_amount' => 0,
                    'credit_amount' => (float) $loan->principal_amount,
                    'currency_code' => 'IRR',
                ],
            ],
        ]);
        $posted = $this->manualJournalService->postJournal($journal->id);

        return (int) $posted->id;
    }
}
