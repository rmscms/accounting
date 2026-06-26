<?php

declare(strict_types=1);

namespace RMS\Accounting\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RMS\Accounting\Models\EmployeeLoan;
use RMS\Accounting\Models\EmployeeLoanInstallment;
use RMS\Accounting\Models\EmployeeLoanPayment;
use RMS\Accounting\Models\Bank;
use RMS\Accounting\Models\PayrollRun;
use RMS\Accounting\Models\PayrollRunLineItem;

class EmployeeLoanSettlementService
{
    public function __construct(
        protected ManualJournalService $manualJournalService,
        protected SystemAccountLocator $systemAccountLocator,
    ) {}

    /**
     * @return array{principal:float,interest:float,total:float,rows:array<int,array<string,mixed>>}
     */
    public function getDueInstallmentsForEmployee(int $employeeId, string $periodEnd): array
    {
        $asOfDate = Carbon::parse($periodEnd)->endOfDay()->format('Y-m-d');
        $installments = EmployeeLoanInstallment::query()
            ->whereHas('loan', static function ($query) use ($employeeId): void {
                $query->where('employee_id', $employeeId)->where('status', EmployeeLoan::STATUS_ACTIVE);
            })
            ->where('due_date', '<=', $asOfDate)
            ->whereIn('status', [
                EmployeeLoanInstallment::STATUS_PENDING,
                EmployeeLoanInstallment::STATUS_PARTIALLY_PAID,
            ])
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        $rows = [];
        $principal = 0.0;
        $interest = 0.0;
        foreach ($installments as $installment) {
            $remainingPrincipal = max(0.0, (float) $installment->principal_amount - (float) $installment->paid_principal);
            $remainingInterest = max(0.0, (float) $installment->interest_amount - (float) $installment->paid_interest);
            $remainingTotal = round($remainingPrincipal + $remainingInterest, 4);
            if ($remainingTotal <= 0.0) {
                continue;
            }

            $rows[] = [
                'installment_id' => $installment->id,
                'loan_id' => $installment->employee_loan_id,
                'due_date' => (string) $installment->due_date?->format('Y-m-d'),
                'principal' => $remainingPrincipal,
                'interest' => $remainingInterest,
                'total' => $remainingTotal,
            ];
            $principal += $remainingPrincipal;
            $interest += $remainingInterest;
        }

        $total = round($principal + $interest, 4);

        return [
            'principal' => round($principal, 4),
            'interest' => round($interest, 4),
            'total' => $total,
            'rows' => $rows,
        ];
    }

    public function postPayrollLoanSettlement(PayrollRun $run): int
    {
        if ($run->loan_settlement_manual_journal_id) {
            return (int) $run->loan_settlement_manual_journal_id;
        }
        if (! $run->accrual_manual_journal_id) {
            throw new \RuntimeException((string) trans('accounting::accounting.payroll_runs.errors.settlement_requires_accrual'));
        }

        return DB::transaction(function () use ($run): int {
            /** @var Collection<int,array<string,mixed>> $rows */
            $rows = PayrollRunLineItem::query()
                ->selectRaw('payroll_run_line_items.code, SUM(payroll_run_line_items.amount) AS amount')
                ->join('payroll_run_lines', 'payroll_run_lines.id', '=', 'payroll_run_line_items.payroll_run_line_id')
                ->where('payroll_run_lines.payroll_run_id', $run->id)
                ->whereIn('payroll_run_line_items.code', ['loan_principal', 'loan_interest'])
                ->groupBy('payroll_run_line_items.code')
                ->get();

            $principal = (float) optional($rows->firstWhere('code', 'loan_principal'))->amount;
            $interest = (float) optional($rows->firstWhere('code', 'loan_interest'))->amount;
            $total = round($principal + $interest, 4);
            if ($total <= 0.0) {
                throw new \RuntimeException((string) trans('accounting::accounting.employee_loans.errors.no_due_installment'));
            }

            $deductionsPayable = $this->systemAccountLocator->accountBySystemKeyOrFail('liabilities.other_payroll_deductions_payable');
            $loanReceivable = $this->systemAccountLocator->accountBySystemKeyOrFail('assets.employee_loans_receivable');
            $loanInterestIncome = $this->systemAccountLocator->accountBySystemKeyOrFail('revenue.employee_loan_interest_income');

            $journal = $this->manualJournalService->createJournal([
                'journal_date' => $run->journal_date?->format('Y-m-d') ?? now()->format('Y-m-d'),
                'description' => 'تسویه کسورات وام دوره حقوق '.$run->run_number,
                'lines' => array_values(array_filter([
                    [
                        'account_id' => $deductionsPayable->id,
                        'debit_amount' => $total,
                        'credit_amount' => 0,
                        'currency_code' => $run->currency_code ?: 'IRR',
                    ],
                    $principal > 0 ? [
                        'account_id' => $loanReceivable->id,
                        'debit_amount' => 0,
                        'credit_amount' => $principal,
                        'currency_code' => $run->currency_code ?: 'IRR',
                    ] : null,
                    $interest > 0 ? [
                        'account_id' => $loanInterestIncome->id,
                        'debit_amount' => 0,
                        'credit_amount' => $interest,
                        'currency_code' => $run->currency_code ?: 'IRR',
                    ] : null,
                ])),
            ]);
            $posted = $this->manualJournalService->postJournal($journal->id);

            $run->forceFill([
                'loan_settlement_manual_journal_id' => $posted->id,
                'status' => $run->status === PayrollRun::STATUS_CLOSED ? PayrollRun::STATUS_CLOSED : PayrollRun::STATUS_TAX_REMITTED,
            ])->save();

            $this->registerInstallmentPaymentsFromRun($run, $principal, $interest, (int) $posted->id);

            return (int) $posted->id;
        });
    }

    protected function registerInstallmentPaymentsFromRun(
        PayrollRun $run,
        float $principal,
        float $interest,
        int $journalId
    ): void {
        $remainingPrincipal = $principal;
        $remainingInterest = $interest;
        $asOfDate = $run->period_end?->format('Y-m-d') ?? now()->format('Y-m-d');

        $lines = $run->lines()->with('employee')->get();
        foreach ($lines as $line) {
            if ($remainingPrincipal <= 0.0 && $remainingInterest <= 0.0) {
                break;
            }

            $due = $this->getDueInstallmentsForEmployee((int) $line->employee_id, $asOfDate);
            foreach ($due['rows'] as $row) {
                if ($remainingPrincipal <= 0.0 && $remainingInterest <= 0.0) {
                    break;
                }
                $installment = EmployeeLoanInstallment::query()->find((int) $row['installment_id']);
                if (! $installment) {
                    continue;
                }

                $payPrincipal = min($remainingPrincipal, (float) $row['principal']);
                $payInterest = min($remainingInterest, (float) $row['interest']);
                $payAmount = round($payPrincipal + $payInterest, 4);
                if ($payAmount <= 0.0) {
                    continue;
                }

                $installment->paid_principal = round((float) $installment->paid_principal + $payPrincipal, 4);
                $installment->paid_interest = round((float) $installment->paid_interest + $payInterest, 4);
                $installment->paid_total = round((float) $installment->paid_total + $payAmount, 4);
                $installment->remaining_amount = max(0.0, round((float) $installment->installment_amount - (float) $installment->paid_total, 4));
                $installment->status = $installment->remaining_amount <= 0.0001
                    ? EmployeeLoanInstallment::STATUS_PAID
                    : EmployeeLoanInstallment::STATUS_PARTIALLY_PAID;
                $installment->save();

                EmployeeLoanPayment::query()->create([
                    'employee_loan_id' => $installment->employee_loan_id,
                    'employee_loan_installment_id' => $installment->id,
                    'payroll_run_id' => $run->id,
                    'payment_date' => $run->journal_date?->format('Y-m-d') ?? now()->format('Y-m-d'),
                    'source' => 'payroll',
                    'principal_amount' => $payPrincipal,
                    'interest_amount' => $payInterest,
                    'amount' => $payAmount,
                    'manual_journal_id' => $journalId,
                    'description' => 'تسویه اتوماتیک از دوره حقوق '.$run->run_number,
                    'created_by_user_id' => \RMS\Accounting\Support\AuditActor::userId(),
                ]);

                $this->refreshLoanBalances((int) $installment->employee_loan_id);
                $remainingPrincipal = round($remainingPrincipal - $payPrincipal, 4);
                $remainingInterest = round($remainingInterest - $payInterest, 4);
            }
        }
    }

    public function refreshLoanBalances(int $loanId): void
    {
        $loan = EmployeeLoan::query()->with('installments')->find($loanId);
        if (! $loan) {
            return;
        }

        $remainingPrincipal = 0.0;
        $remainingInterest = 0.0;
        foreach ($loan->installments as $installment) {
            $remainingPrincipal += max(0.0, (float) $installment->principal_amount - (float) $installment->paid_principal);
            $remainingInterest += max(0.0, (float) $installment->interest_amount - (float) $installment->paid_interest);
        }
        $remainingTotal = round($remainingPrincipal + $remainingInterest, 4);

        $loan->forceFill([
            'remaining_principal' => round($remainingPrincipal, 4),
            'remaining_interest' => round($remainingInterest, 4),
            'remaining_total' => $remainingTotal,
            'status' => $remainingTotal <= 0.0001 ? EmployeeLoan::STATUS_CLOSED : EmployeeLoan::STATUS_ACTIVE,
            'updated_by_user_id' => \RMS\Accounting\Support\AuditActor::userId(),
        ])->save();
    }

    public function postManualCollection(EmployeeLoan $loan, int $bankId, float $amount, string $paymentDate, string $description = ''): int
    {
        if ($amount <= 0) {
            throw new \RuntimeException((string) trans('accounting::accounting.employee_loans.errors.invalid_amount'));
        }

        return DB::transaction(function () use ($loan, $bankId, $amount, $paymentDate, $description): int {
            $bank = Bank::query()->findOrFail($bankId);
            if (! $bank->account_id) {
                throw new \RuntimeException((string) trans('accounting::accounting.payroll_runs.errors.bank_missing_account'));
            }
            $loanReceivable = $this->systemAccountLocator->accountBySystemKeyOrFail('assets.employee_loans_receivable');
            $loanInterestIncome = $this->systemAccountLocator->accountBySystemKeyOrFail('revenue.employee_loan_interest_income');

            $remaining = $amount;
            $principalPaid = 0.0;
            $interestPaid = 0.0;
            $installments = $loan->installments()
                ->whereIn('status', [EmployeeLoanInstallment::STATUS_PENDING, EmployeeLoanInstallment::STATUS_PARTIALLY_PAID])
                ->orderBy('due_date')
                ->get();

            foreach ($installments as $installment) {
                if ($remaining <= 0) {
                    break;
                }
                $remainInterest = max(0.0, (float) $installment->interest_amount - (float) $installment->paid_interest);
                $remainPrincipal = max(0.0, (float) $installment->principal_amount - (float) $installment->paid_principal);

                $payInterest = min($remaining, $remainInterest);
                $remaining -= $payInterest;
                $payPrincipal = min($remaining, $remainPrincipal);
                $remaining -= $payPrincipal;
                $payAmount = round($payPrincipal + $payInterest, 4);
                if ($payAmount <= 0.0) {
                    continue;
                }

                $installment->paid_principal = round((float) $installment->paid_principal + $payPrincipal, 4);
                $installment->paid_interest = round((float) $installment->paid_interest + $payInterest, 4);
                $installment->paid_total = round((float) $installment->paid_total + $payAmount, 4);
                $installment->remaining_amount = max(0.0, round((float) $installment->installment_amount - (float) $installment->paid_total, 4));
                $installment->status = $installment->remaining_amount <= 0.0001
                    ? EmployeeLoanInstallment::STATUS_PAID
                    : EmployeeLoanInstallment::STATUS_PARTIALLY_PAID;
                $installment->save();

                EmployeeLoanPayment::query()->create([
                    'employee_loan_id' => $loan->id,
                    'employee_loan_installment_id' => $installment->id,
                    'payment_date' => $paymentDate,
                    'source' => 'manual',
                    'principal_amount' => $payPrincipal,
                    'interest_amount' => $payInterest,
                    'amount' => $payAmount,
                    'description' => $description,
                    'created_by_user_id' => \RMS\Accounting\Support\AuditActor::userId(),
                ]);

                $principalPaid += $payPrincipal;
                $interestPaid += $payInterest;
            }

            $totalPaid = round($principalPaid + $interestPaid, 4);
            if ($totalPaid <= 0.0) {
                throw new \RuntimeException((string) trans('accounting::accounting.employee_loans.errors.no_due_installment'));
            }

            $journal = $this->manualJournalService->createJournal([
                'journal_date' => $paymentDate,
                'description' => $description !== '' ? $description : ('دریافت دستی قسط وام '.$loan->loan_number),
                'lines' => array_values(array_filter([
                    [
                        'account_id' => (int) $bank->account_id,
                        'debit_amount' => $totalPaid,
                        'credit_amount' => 0,
                        'currency_code' => 'IRR',
                    ],
                    $principalPaid > 0 ? [
                        'account_id' => $loanReceivable->id,
                        'debit_amount' => 0,
                        'credit_amount' => $principalPaid,
                        'currency_code' => 'IRR',
                    ] : null,
                    $interestPaid > 0 ? [
                        'account_id' => $loanInterestIncome->id,
                        'debit_amount' => 0,
                        'credit_amount' => $interestPaid,
                        'currency_code' => 'IRR',
                    ] : null,
                ])),
            ]);
            $posted = $this->manualJournalService->postJournal($journal->id);

            EmployeeLoanPayment::query()
                ->where('employee_loan_id', $loan->id)
                ->whereNull('manual_journal_id')
                ->where('source', 'manual')
                ->whereDate('payment_date', $paymentDate)
                ->update(['manual_journal_id' => $posted->id]);

            $this->refreshLoanBalances((int) $loan->id);

            return (int) $posted->id;
        });
    }
}
