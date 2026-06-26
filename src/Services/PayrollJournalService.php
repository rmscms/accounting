<?php

declare(strict_types=1);

namespace RMS\Accounting\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RMS\Accounting\Models\Bank;
use RMS\Accounting\Models\Chequebook;
use RMS\Accounting\Models\Cheque;
use RMS\Accounting\Models\Employee;
use RMS\Accounting\Models\FiscalYear;
use RMS\Accounting\Models\PayrollRun;
use RMS\Accounting\Models\PayrollRunLine;
use RMS\Accounting\Support\PayrollCalculator;

class PayrollJournalService
{
    public function __construct(
        protected ManualJournalService $manualJournalService,
        protected SystemAccountLocator $systemAccountLocator,
        protected EmployeeLoanSettlementService $employeeLoanSettlementService,
        protected AttendanceWorklogService $attendanceWorklogService,
    ) {}

    /**
     * @param  array<string,mixed>  $data
     */
    public function createRun(array $data): PayrollRun
    {
        $this->assertDateInOpenFiscalPeriod((string) ($data['period_end'] ?? ''), 'create');
        $this->assertDateInOpenFiscalPeriod((string) ($data['journal_date'] ?? ''), 'create');
        $this->attendanceWorklogService->assertPayrollAllowed(
            (string) ($data['period_start'] ?? ''),
            (string) ($data['period_end'] ?? ''),
            array_map(static fn (array $line): int => (int) ($line['employee_id'] ?? 0), (array) ($data['lines'] ?? []))
        );

        return DB::transaction(function () use ($data): PayrollRun {
            $run = PayrollRun::create([
                'run_number' => (string) ($data['run_number'] ?? PayrollRun::generateRunNumber()),
                'title' => (string) ($data['title'] ?? ''),
                'period_start' => (string) $data['period_start'],
                'period_end' => (string) $data['period_end'],
                'journal_date' => (string) $data['journal_date'],
                'currency_code' => $this->normalizeLedgerCurrency((string) ($data['currency_code'] ?? 'IRR')),
                'status' => PayrollRun::STATUS_DRAFT,
                'created_by_user_id' => \RMS\Accounting\Support\AuditActor::userId(),
                'updated_by_user_id' => \RMS\Accounting\Support\AuditActor::userId(),
            ]);

            $this->syncLines(
                $run,
                (array) ($data['lines'] ?? []),
                (array) ($data['policy'] ?? [])
            );

            return $run->fresh(['lines.employee']);
        });
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public function updateRun(PayrollRun $run, array $data): PayrollRun
    {
        if ($run->status !== PayrollRun::STATUS_DRAFT) {
            throw new \RuntimeException((string) trans('accounting::accounting.payroll_runs.errors.only_draft_editable'));
        }
        $this->assertDateInOpenFiscalPeriod((string) ($data['period_end'] ?? ''), 'update');
        $this->assertDateInOpenFiscalPeriod((string) ($data['journal_date'] ?? ''), 'update');
        $this->attendanceWorklogService->assertPayrollAllowed(
            (string) ($data['period_start'] ?? ''),
            (string) ($data['period_end'] ?? ''),
            array_map(static fn (array $line): int => (int) ($line['employee_id'] ?? 0), (array) ($data['lines'] ?? []))
        );

        return DB::transaction(function () use ($run, $data): PayrollRun {
            $run->update([
                'title' => (string) ($data['title'] ?? $run->title),
                'period_start' => (string) ($data['period_start'] ?? $run->period_start?->format('Y-m-d')),
                'period_end' => (string) ($data['period_end'] ?? $run->period_end?->format('Y-m-d')),
                'journal_date' => (string) ($data['journal_date'] ?? $run->journal_date?->format('Y-m-d')),
                'currency_code' => $this->normalizeLedgerCurrency((string) ($data['currency_code'] ?? $run->currency_code)),
                'updated_by_user_id' => \RMS\Accounting\Support\AuditActor::userId(),
            ]);

            $this->syncLines(
                $run,
                (array) ($data['lines'] ?? []),
                (array) ($data['policy'] ?? [])
            );

            return $run->fresh(['lines.employee']);
        });
    }

    public function postAccrual(int $runId): PayrollRun
    {
        return DB::transaction(function () use ($runId): PayrollRun {
            $run = PayrollRun::query()->with('lines.employee')->findOrFail($runId);
            $this->assertDateInOpenFiscalPeriod((string) $run->journal_date?->format('Y-m-d'), 'post');
            $this->assertRunAttendanceApproved($run);
            if ($run->accrual_manual_journal_id) {
                throw new \RuntimeException((string) trans('accounting::accounting.payroll_runs.errors.accrual_exists'));
            }
            if ($run->lines->isEmpty()) {
                throw new \RuntimeException((string) trans('accounting::accounting.payroll_runs.errors.no_lines'));
            }

            $this->refreshTotals($run);
            $run->refresh();

            $wagesPayable = $this->systemAccountLocator->accountBySystemKeyOrFail('liabilities.wages_payable');
            $employeeInsurancePayable = $this->resolveEmployeeInsurancePayableAccount();
            $employerInsurancePayable = $this->resolveEmployerInsurancePayableAccount();
            $taxPayable = $this->resolvePayrollTaxPayableAccount();
            $otherDeductionsPayable = $this->resolveOtherPayrollDeductionsPayableAccount();
            $employerInsuranceExpense = $this->systemAccountLocator->accountBySystemKeyOrFail('expenses.employer_social_insurance');
            $payrollSeniorityExpense = $this->resolvePayrollSeniorityExpenseAccount();
            $payrollSeniorityReserve = $this->resolvePayrollSeniorityReserveAccount();

            $expenseByAccount = [];
            /** @var PayrollRunLine $line */
            foreach ($run->lines as $line) {
                $employee = $line->employee;
                if (! $employee instanceof Employee || ! $employee->payroll_expense_account_id) {
                    throw new \RuntimeException((string) trans('accounting::accounting.payroll_runs.errors.employee_expense_missing'));
                }
                $accountId = (int) $employee->payroll_expense_account_id;
                $expenseByAccount[$accountId] = ($expenseByAccount[$accountId] ?? 0.0)
                    + (float) $line->base_salary
                    + (float) $line->benefits;
            }

            $lines = [];
            foreach ($expenseByAccount as $accountId => $amount) {
                if ($amount <= 0) {
                    continue;
                }
                $lines[] = [
                    'account_id' => $accountId,
                    'debit_amount' => $amount,
                    'credit_amount' => 0,
                    'currency_code' => $run->currency_code,
                    'description' => $run->title,
                ];
            }

            if ((float) $run->total_employer_insurance > 0) {
                $lines[] = [
                    'account_id' => $employerInsuranceExpense->id,
                    'debit_amount' => (float) $run->total_employer_insurance,
                    'credit_amount' => 0,
                    'currency_code' => $run->currency_code,
                    'description' => $run->title,
                ];
            }

            if ((float) $run->total_seniority > 0) {
                $lines[] = [
                    'account_id' => $payrollSeniorityExpense->id,
                    'debit_amount' => (float) $run->total_seniority,
                    'credit_amount' => 0,
                    'currency_code' => $run->currency_code,
                    'description' => $run->title,
                ];
                $lines[] = [
                    'account_id' => $payrollSeniorityReserve->id,
                    'debit_amount' => 0,
                    'credit_amount' => (float) $run->total_seniority,
                    'currency_code' => $run->currency_code,
                    'description' => $run->title,
                ];
            }

            if ((float) $run->total_employee_insurance > 0) {
                $lines[] = [
                    'account_id' => $employeeInsurancePayable->id,
                    'debit_amount' => 0,
                    'credit_amount' => (float) $run->total_employee_insurance,
                    'currency_code' => $run->currency_code,
                    'description' => $run->title,
                ];
            }

            if ((float) $run->total_employer_insurance > 0) {
                $lines[] = [
                    'account_id' => $employerInsurancePayable->id,
                    'debit_amount' => 0,
                    'credit_amount' => (float) $run->total_employer_insurance,
                    'currency_code' => $run->currency_code,
                    'description' => $run->title,
                ];
            }

            if ((float) $run->total_tax > 0) {
                $lines[] = [
                    'account_id' => $taxPayable->id,
                    'debit_amount' => 0,
                    'credit_amount' => (float) $run->total_tax,
                    'currency_code' => $run->currency_code,
                    'description' => $run->title,
                ];
            }

            if ((float) $run->total_other_deductions > 0) {
                $lines[] = [
                    'account_id' => $otherDeductionsPayable->id,
                    'debit_amount' => 0,
                    'credit_amount' => (float) $run->total_other_deductions,
                    'currency_code' => $run->currency_code,
                    'description' => $run->title,
                ];
            }

            if ((float) $run->total_net > 0) {
                $lines[] = [
                    'account_id' => $wagesPayable->id,
                    'debit_amount' => 0,
                    'credit_amount' => (float) $run->total_net,
                    'currency_code' => $run->currency_code,
                    'description' => $run->title,
                ];
            }

            $journal = $this->manualJournalService->createJournal([
                'journal_date' => (string) $run->journal_date?->format('Y-m-d'),
                'description' => (string) trans('accounting::accounting.payroll_runs.journal.accrual_description', ['run' => $run->run_number]),
                'lines' => $lines,
            ]);
            $posted = $this->manualJournalService->postJournal($journal->id);

            $run->update([
                'accrual_manual_journal_id' => $posted->id,
                'status' => PayrollRun::STATUS_ACCRUED,
                'updated_by_user_id' => \RMS\Accounting\Support\AuditActor::userId(),
            ]);

            return $run->fresh(['lines.employee', 'accrualJournal']);
        });
    }

    /**
     * @param array<string,mixed> $paymentPayload
     */
    public function postNetPayment(int $runId, string $journalDate, ?string $description = null, array $paymentPayload = []): PayrollRun
    {
        return DB::transaction(function () use ($runId, $journalDate, $description, $paymentPayload): PayrollRun {
            $run = PayrollRun::query()->findOrFail($runId);
            $this->assertDateInOpenFiscalPeriod($journalDate, 'post');
            $this->assertRunAttendanceApproved($run);
            if (! $run->accrual_manual_journal_id) {
                throw new \RuntimeException((string) trans('accounting::accounting.payroll_runs.errors.accrual_required'));
            }
            if ($run->net_payment_manual_journal_id) {
                throw new \RuntimeException((string) trans('accounting::accounting.payroll_runs.errors.net_payment_exists'));
            }
            if ((float) $run->total_net <= 0) {
                throw new \RuntimeException((string) trans('accounting::accounting.payroll_runs.errors.net_amount_zero'));
            }

            $creditAccountId = $this->resolvePaymentCreditAccountId(
                $run,
                (float) $run->total_net,
                $journalDate,
                (array) $paymentPayload,
                'net'
            );
            $wagesPayable = $this->systemAccountLocator->accountBySystemKeyOrFail('liabilities.wages_payable');

            $journal = $this->manualJournalService->createJournal([
                'journal_date' => $journalDate,
                'description' => $description ?: (string) trans('accounting::accounting.payroll_runs.journal.net_payment_description', ['run' => $run->run_number]),
                'lines' => [
                    [
                        'account_id' => $wagesPayable->id,
                        'debit_amount' => (float) $run->total_net,
                        'credit_amount' => 0,
                        'currency_code' => $run->currency_code,
                    ],
                    [
                        'account_id' => $creditAccountId,
                        'debit_amount' => 0,
                        'credit_amount' => (float) $run->total_net,
                        'currency_code' => $run->currency_code,
                    ],
                ],
            ]);
            $posted = $this->manualJournalService->postJournal($journal->id);

            $run->update([
                'net_payment_manual_journal_id' => $posted->id,
                'status' => PayrollRun::STATUS_PAID,
                'updated_by_user_id' => \RMS\Accounting\Support\AuditActor::userId(),
            ]);
            if ($this->isReadyToClose($run)) {
                $run->update(['status' => PayrollRun::STATUS_CLOSED]);
            }

            return $run->fresh(['netPaymentJournal']);
        });
    }

    public function postInsuranceRemittance(int $runId, int $bankId, ?string $journalDate = null, ?string $description = null): PayrollRun
    {
        return DB::transaction(function () use ($runId, $bankId, $journalDate, $description): PayrollRun {
            $run = PayrollRun::query()->findOrFail($runId);
            $effectiveJournalDate = $journalDate ?: (string) $run->journal_date?->format('Y-m-d');
            $this->assertDateInOpenFiscalPeriod($effectiveJournalDate, 'post');
            $this->assertRunAttendanceApproved($run);
            if (! $run->accrual_manual_journal_id) {
                throw new \RuntimeException((string) trans('accounting::accounting.payroll_runs.errors.accrual_required'));
            }
            if ($run->insurance_remittance_manual_journal_id) {
                throw new \RuntimeException((string) trans('accounting::accounting.payroll_runs.errors.insurance_remittance_exists'));
            }
            $amountEmployee = (float) $run->total_employee_insurance;
            $amountEmployer = (float) $run->total_employer_insurance;
            if (($amountEmployee + $amountEmployer) <= 0) {
                throw new \RuntimeException((string) trans('accounting::accounting.payroll_runs.errors.insurance_amount_zero'));
            }

            $bank = Bank::query()->findOrFail($bankId);
            if (! $bank->account_id) {
                throw new \RuntimeException((string) trans('accounting::accounting.payroll_runs.errors.bank_missing_account'));
            }

            $lines = [];
            if ($amountEmployee > 0) {
                $lines[] = [
                    'account_id' => $this->resolveEmployeeInsurancePayableAccount()->id,
                    'debit_amount' => $amountEmployee,
                    'credit_amount' => 0,
                    'currency_code' => $run->currency_code,
                ];
            }
            if ($amountEmployer > 0) {
                $lines[] = [
                    'account_id' => $this->resolveEmployerInsurancePayableAccount()->id,
                    'debit_amount' => $amountEmployer,
                    'credit_amount' => 0,
                    'currency_code' => $run->currency_code,
                ];
            }
            $lines[] = [
                'account_id' => (int) $bank->account_id,
                'debit_amount' => 0,
                'credit_amount' => $amountEmployee + $amountEmployer,
                'currency_code' => $run->currency_code,
            ];

            $journal = $this->manualJournalService->createJournal([
                'journal_date' => $effectiveJournalDate,
                'description' => $description ?: (string) trans('accounting::accounting.payroll_runs.journal.insurance_remittance_description', ['run' => $run->run_number]),
                'lines' => $lines,
            ]);
            $posted = $this->manualJournalService->postJournal($journal->id);

            $run->update([
                'insurance_remittance_manual_journal_id' => $posted->id,
                'status' => PayrollRun::STATUS_INSURANCE_REMITTED,
                'updated_by_user_id' => \RMS\Accounting\Support\AuditActor::userId(),
            ]);
            if ($this->isReadyToClose($run)) {
                $run->update(['status' => PayrollRun::STATUS_CLOSED]);
            }

            return $run->fresh(['insuranceRemittanceJournal']);
        });
    }

    public function postTaxRemittance(int $runId, int $bankId, ?string $journalDate = null, ?string $description = null): PayrollRun
    {
        return DB::transaction(function () use ($runId, $bankId, $journalDate, $description): PayrollRun {
            $run = PayrollRun::query()->findOrFail($runId);
            $effectiveJournalDate = $journalDate ?: (string) $run->journal_date?->format('Y-m-d');
            $this->assertDateInOpenFiscalPeriod($effectiveJournalDate, 'post');
            $this->assertRunAttendanceApproved($run);
            if (! $run->accrual_manual_journal_id) {
                throw new \RuntimeException((string) trans('accounting::accounting.payroll_runs.errors.accrual_required'));
            }
            if ($run->tax_remittance_manual_journal_id) {
                throw new \RuntimeException((string) trans('accounting::accounting.payroll_runs.errors.tax_remittance_exists'));
            }
            if ((float) $run->total_tax <= 0) {
                throw new \RuntimeException((string) trans('accounting::accounting.payroll_runs.errors.tax_amount_zero'));
            }

            $bank = Bank::query()->findOrFail($bankId);
            if (! $bank->account_id) {
                throw new \RuntimeException((string) trans('accounting::accounting.payroll_runs.errors.bank_missing_account'));
            }
            $taxPayable = $this->resolvePayrollTaxPayableAccount();

            $journal = $this->manualJournalService->createJournal([
                'journal_date' => $effectiveJournalDate,
                'description' => $description ?: (string) trans('accounting::accounting.payroll_runs.journal.tax_remittance_description', ['run' => $run->run_number]),
                'lines' => [
                    [
                        'account_id' => $taxPayable->id,
                        'debit_amount' => (float) $run->total_tax,
                        'credit_amount' => 0,
                        'currency_code' => $run->currency_code,
                    ],
                    [
                        'account_id' => (int) $bank->account_id,
                        'debit_amount' => 0,
                        'credit_amount' => (float) $run->total_tax,
                        'currency_code' => $run->currency_code,
                    ],
                ],
            ]);
            $posted = $this->manualJournalService->postJournal($journal->id);

            $run->update([
                'tax_remittance_manual_journal_id' => $posted->id,
                'status' => PayrollRun::STATUS_TAX_REMITTED,
                'updated_by_user_id' => \RMS\Accounting\Support\AuditActor::userId(),
            ]);

            if ($this->isReadyToClose($run)) {
                $run->update(['status' => PayrollRun::STATUS_CLOSED]);
            }

            return $run->fresh(['taxRemittanceJournal']);
        });
    }

    public function postLoanSettlement(int $runId): PayrollRun
    {
        return DB::transaction(function () use ($runId): PayrollRun {
            $run = PayrollRun::query()->with('lineItems')->findOrFail($runId);
            $this->assertDateInOpenFiscalPeriod((string) $run->journal_date?->format('Y-m-d'), 'post');
            $this->assertRunAttendanceApproved($run);
            if (! $this->runHasLoanDeductions($run)) {
                throw new \RuntimeException((string) trans('accounting::accounting.employee_loans.errors.no_due_installment'));
            }

            $journalId = $this->employeeLoanSettlementService->postPayrollLoanSettlement($run);
            $run->refresh();
            $run->update([
                'loan_settlement_manual_journal_id' => $journalId,
                'updated_by_user_id' => \RMS\Accounting\Support\AuditActor::userId(),
            ]);
            if ($this->isReadyToClose($run)) {
                $run->update(['status' => PayrollRun::STATUS_CLOSED]);
            }

            return $run->fresh(['loanSettlementJournal']);
        });
    }

    /**
     * @param array<string,mixed> $paymentPayload
     */
    public function postSenioritySettlement(int $runId, string $journalDate, ?string $description = null, array $paymentPayload = []): PayrollRun
    {
        return DB::transaction(function () use ($runId, $journalDate, $description, $paymentPayload): PayrollRun {
            $run = PayrollRun::query()->findOrFail($runId);
            $this->assertDateInOpenFiscalPeriod($journalDate, 'post');
            $this->assertRunAttendanceApproved($run);
            if (! $run->accrual_manual_journal_id) {
                throw new \RuntimeException((string) trans('accounting::accounting.payroll_runs.errors.accrual_required'));
            }
            if ($run->seniority_settlement_manual_journal_id) {
                throw new \RuntimeException((string) trans('accounting::accounting.payroll_runs.errors.seniority_settlement_exists'));
            }
            if ((float) $run->total_seniority <= 0) {
                throw new \RuntimeException((string) trans('accounting::accounting.payroll_runs.errors.seniority_amount_zero'));
            }

            $creditAccountId = $this->resolvePaymentCreditAccountId(
                $run,
                (float) $run->total_seniority,
                $journalDate,
                (array) $paymentPayload,
                'seniority'
            );
            $seniorityReserve = $this->resolvePayrollSeniorityReserveAccount();

            $journal = $this->manualJournalService->createJournal([
                'journal_date' => $journalDate,
                'description' => $description ?: (string) trans('accounting::accounting.payroll_runs.journal.seniority_settlement_description', ['run' => $run->run_number]),
                'lines' => [
                    [
                        'account_id' => $seniorityReserve->id,
                        'debit_amount' => (float) $run->total_seniority,
                        'credit_amount' => 0,
                        'currency_code' => $run->currency_code,
                    ],
                    [
                        'account_id' => $creditAccountId,
                        'debit_amount' => 0,
                        'credit_amount' => (float) $run->total_seniority,
                        'currency_code' => $run->currency_code,
                    ],
                ],
            ]);
            $posted = $this->manualJournalService->postJournal($journal->id);

            $run->update([
                'seniority_settlement_manual_journal_id' => $posted->id,
                'updated_by_user_id' => \RMS\Accounting\Support\AuditActor::userId(),
            ]);
            if ($this->isReadyToClose($run)) {
                $run->update(['status' => PayrollRun::STATUS_CLOSED]);
            }

            return $run->fresh(['senioritySettlementJournal']);
        });
    }

    public function reverseAccrual(int $runId): PayrollRun
    {
        return DB::transaction(function () use ($runId): PayrollRun {
            $run = PayrollRun::query()->findOrFail($runId);
            if (! $run->accrual_manual_journal_id) {
                throw new \RuntimeException((string) trans('accounting::accounting.payroll_runs.errors.accrual_missing'));
            }
            if (
                $run->net_payment_manual_journal_id
                || $run->insurance_remittance_manual_journal_id
                || $run->tax_remittance_manual_journal_id
                || $run->loan_settlement_manual_journal_id
                || $run->seniority_settlement_manual_journal_id
            ) {
                throw new \RuntimeException((string) trans('accounting::accounting.payroll_runs.errors.reverse_accrual_has_settlements'));
            }

            $this->manualJournalService->reverseJournal(
                (int) $run->accrual_manual_journal_id,
                (string) trans('accounting::accounting.payroll_runs.journal.reverse_accrual_reason', ['run' => $run->run_number])
            );
            $run->update([
                'accrual_manual_journal_id' => null,
                'status' => PayrollRun::STATUS_DRAFT,
                'updated_by_user_id' => \RMS\Accounting\Support\AuditActor::userId(),
            ]);

            return $run->fresh();
        });
    }

    public function reverseNetPayment(int $runId): PayrollRun
    {
        return DB::transaction(function () use ($runId): PayrollRun {
            $run = PayrollRun::query()->findOrFail($runId);
            if (! $run->net_payment_manual_journal_id) {
                throw new \RuntimeException((string) trans('accounting::accounting.payroll_runs.errors.net_payment_missing'));
            }

            $this->manualJournalService->reverseJournal(
                (int) $run->net_payment_manual_journal_id,
                (string) trans('accounting::accounting.payroll_runs.journal.reverse_net_payment_reason', ['run' => $run->run_number])
            );
            $run->update([
                'net_payment_manual_journal_id' => null,
                'updated_by_user_id' => \RMS\Accounting\Support\AuditActor::userId(),
            ]);
            $this->refreshStatusFromPostedJournals($run);

            return $run->fresh();
        });
    }

    public function reverseInsuranceRemittance(int $runId): PayrollRun
    {
        return DB::transaction(function () use ($runId): PayrollRun {
            $run = PayrollRun::query()->findOrFail($runId);
            if (! $run->insurance_remittance_manual_journal_id) {
                throw new \RuntimeException((string) trans('accounting::accounting.payroll_runs.errors.insurance_remittance_missing'));
            }

            $this->manualJournalService->reverseJournal(
                (int) $run->insurance_remittance_manual_journal_id,
                (string) trans('accounting::accounting.payroll_runs.journal.reverse_insurance_remittance_reason', ['run' => $run->run_number])
            );
            $run->update([
                'insurance_remittance_manual_journal_id' => null,
                'updated_by_user_id' => \RMS\Accounting\Support\AuditActor::userId(),
            ]);
            $this->refreshStatusFromPostedJournals($run);

            return $run->fresh();
        });
    }

    public function reverseTaxRemittance(int $runId): PayrollRun
    {
        return DB::transaction(function () use ($runId): PayrollRun {
            $run = PayrollRun::query()->findOrFail($runId);
            if (! $run->tax_remittance_manual_journal_id) {
                throw new \RuntimeException((string) trans('accounting::accounting.payroll_runs.errors.tax_remittance_missing'));
            }

            $this->manualJournalService->reverseJournal(
                (int) $run->tax_remittance_manual_journal_id,
                (string) trans('accounting::accounting.payroll_runs.journal.reverse_tax_remittance_reason', ['run' => $run->run_number])
            );
            $run->update([
                'tax_remittance_manual_journal_id' => null,
                'updated_by_user_id' => \RMS\Accounting\Support\AuditActor::userId(),
            ]);
            $this->refreshStatusFromPostedJournals($run);

            return $run->fresh();
        });
    }

    public function reverseLoanSettlement(int $runId): PayrollRun
    {
        return DB::transaction(function () use ($runId): PayrollRun {
            $run = PayrollRun::query()->findOrFail($runId);
            if (! $run->loan_settlement_manual_journal_id) {
                throw new \RuntimeException((string) trans('accounting::accounting.payroll_runs.errors.loan_settlement_missing'));
            }

            $this->manualJournalService->reverseJournal(
                (int) $run->loan_settlement_manual_journal_id,
                (string) trans('accounting::accounting.payroll_runs.journal.reverse_loan_settlement_reason', ['run' => $run->run_number])
            );
            $run->update([
                'loan_settlement_manual_journal_id' => null,
                'updated_by_user_id' => \RMS\Accounting\Support\AuditActor::userId(),
            ]);
            $this->refreshStatusFromPostedJournals($run);

            return $run->fresh();
        });
    }

    public function reverseSenioritySettlement(int $runId): PayrollRun
    {
        return DB::transaction(function () use ($runId): PayrollRun {
            $run = PayrollRun::query()->findOrFail($runId);
            if (! $run->seniority_settlement_manual_journal_id) {
                throw new \RuntimeException((string) trans('accounting::accounting.payroll_runs.errors.seniority_settlement_missing'));
            }

            $this->manualJournalService->reverseJournal(
                (int) $run->seniority_settlement_manual_journal_id,
                (string) trans('accounting::accounting.payroll_runs.journal.reverse_seniority_settlement_reason', ['run' => $run->run_number])
            );
            $run->update([
                'seniority_settlement_manual_journal_id' => null,
                'updated_by_user_id' => \RMS\Accounting\Support\AuditActor::userId(),
            ]);
            $this->refreshStatusFromPostedJournals($run);

            return $run->fresh();
        });
    }

    /**
     * @param  array<int,array<string,mixed>>  $lines
     */
    protected function syncLines(PayrollRun $run, array $lines, array $policy = []): void
    {
        $run->lines()->delete();
        $run->attendanceSnapshots()->delete();
        $calculatorPolicy = [
            'employee_insurance_rate' => $this->resolveRate($policy, 'employee_insurance_rate', PayrollCalculator::DEFAULT_EMPLOYEE_INSURANCE_RATE),
            'employer_insurance_rate' => $this->resolveRate($policy, 'employer_insurance_rate', PayrollCalculator::DEFAULT_EMPLOYER_INSURANCE_RATE),
            'tax_rate' => $this->resolveRate($policy, 'tax_rate', PayrollCalculator::DEFAULT_TAX_RATE),
        ];
        foreach (array_values($lines) as $index => $line) {
            $computed = PayrollCalculator::computeLine($line, $calculatorPolicy);
            $items = PayrollCalculator::buildLineItems($line, $computed);
            $items = $this->injectLoanItems($items, $line, (string) $run->period_end?->format('Y-m-d'));
            $recomputed = PayrollCalculator::recomputeLineFromItems($items);

            /** @var PayrollRunLine $createdLine */
            $createdLine = $run->lines()->create([
                'employee_id' => (int) ($line['employee_id'] ?? 0),
                'line_number' => $index + 1,
                'base_salary' => $recomputed['base_salary'],
                'benefits' => $recomputed['benefits'],
                'seniority' => $recomputed['seniority'] ?? 0,
                'gross_salary' => $recomputed['gross_salary'],
                'employee_insurance' => $recomputed['employee_insurance'],
                'employer_insurance' => $recomputed['employer_insurance'],
                'tax' => $recomputed['tax'],
                'other_deductions' => $recomputed['other_deductions'],
                'skip_loan_deduction' => filter_var($line['skip_loan_deduction'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'net_salary' => $recomputed['net_salary'],
                'description' => (string) ($line['description'] ?? ''),
            ]);

            $createdLine->items()->createMany($items);
            if (is_array($line['_attendance_snapshot'] ?? null)) {
                $this->attendanceWorklogService->syncPayrollSnapshot(
                    $run,
                    $createdLine,
                    (array) $line['_attendance_snapshot']
                );
            }
        }

        $this->refreshTotals($run);
    }

    /**
     * @param  array<int,array<string,mixed>>  $items
     * @param  array<string,mixed>  $line
     * @return array<int,array<string,mixed>>
     */
    protected function injectLoanItems(array $items, array $line, string $periodEnd): array
    {
        $employeeId = (int) ($line['employee_id'] ?? 0);
        $skipLoanDeduction = filter_var($line['skip_loan_deduction'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($skipLoanDeduction) {
            return array_values(array_filter(
                $items,
                static fn (array $item): bool => ! in_array((string) ($item['code'] ?? ''), ['loan_principal', 'loan_interest'], true)
            ));
        }
        if ($employeeId <= 0 || $periodEnd === '') {
            return $items;
        }

        $due = $this->employeeLoanSettlementService->getDueInstallmentsForEmployee($employeeId, $periodEnd);
        if ((float) $due['total'] <= 0) {
            return $items;
        }

        $items = array_values(array_filter($items, static fn (array $item): bool => ! in_array((string) ($item['code'] ?? ''), ['loan_principal', 'loan_interest'], true)));
        $nextSort = 200 + count($items);
        if ((float) $due['principal'] > 0) {
            $items[] = [
                'type' => 'deduction',
                'code' => 'loan_principal',
                'title' => (string) trans('accounting::accounting.payroll_runs.items.loan_principal'),
                'amount' => round((float) $due['principal'], 4),
                'sort_order' => $nextSort++,
                'is_system' => true,
                'is_auto_calculated' => true,
                'is_manual_override' => false,
                'notes' => null,
            ];
        }
        if ((float) $due['interest'] > 0) {
            $items[] = [
                'type' => 'deduction',
                'code' => 'loan_interest',
                'title' => (string) trans('accounting::accounting.payroll_runs.items.loan_interest'),
                'amount' => round((float) $due['interest'], 4),
                'sort_order' => $nextSort++,
                'is_system' => true,
                'is_auto_calculated' => true,
                'is_manual_override' => false,
                'notes' => null,
            ];
        }

        return $items;
    }

    protected function runHasLoanDeductions(PayrollRun $run): bool
    {
        return $run->lineItems()
            ->whereIn('code', ['loan_principal', 'loan_interest'])
            ->exists();
    }

    protected function resolveRate(array $policy, string $key, float $default): float
    {
        if (! array_key_exists($key, $policy)) {
            return $default;
        }

        return is_numeric($policy[$key]) ? (float) $policy[$key] : $default;
    }

    protected function refreshTotals(PayrollRun $run): void
    {
        $totals = $run->lineItems()
            ->selectRaw('
                COALESCE(SUM(CASE WHEN type = \'earning\' AND code = \'base_salary\' THEN amount ELSE 0 END), 0) as total_base_salary,
                COALESCE(SUM(CASE WHEN type = \'earning\' AND code = \'seniority\' THEN amount ELSE 0 END), 0) as total_seniority,
                COALESCE(SUM(CASE WHEN type = \'earning\' AND code NOT IN (\'base_salary\', \'seniority\') THEN amount ELSE 0 END), 0) as total_benefits,
                COALESCE(SUM(CASE WHEN type = \'earning\' AND code <> \'seniority\' THEN amount ELSE 0 END), 0) as total_gross,
                COALESCE(SUM(CASE WHEN type = \'deduction\' AND code = \'employee_insurance\' THEN amount ELSE 0 END), 0) as total_employee_insurance,
                COALESCE(SUM(CASE WHEN type = \'employer_contribution\' THEN amount ELSE 0 END), 0) as total_employer_insurance,
                COALESCE(SUM(CASE WHEN type = \'deduction\' AND code = \'tax\' THEN amount ELSE 0 END), 0) as total_tax,
                COALESCE(SUM(CASE WHEN type = \'deduction\' AND code NOT IN (\'employee_insurance\', \'tax\') THEN amount ELSE 0 END), 0) as total_other_deductions,
                (
                    COALESCE(SUM(CASE WHEN type = \'earning\' AND code <> \'seniority\' THEN amount ELSE 0 END), 0)
                    - COALESCE(SUM(CASE WHEN type = \'deduction\' THEN amount ELSE 0 END), 0)
                ) as total_net
            ')
            ->first();

        $run->update([
            'total_base_salary' => (float) ($totals->total_base_salary ?? 0),
            'total_benefits' => (float) ($totals->total_benefits ?? 0),
            'total_seniority' => (float) ($totals->total_seniority ?? 0),
            'total_gross' => (float) ($totals->total_gross ?? 0),
            'total_employee_insurance' => (float) ($totals->total_employee_insurance ?? 0),
            'total_employer_insurance' => (float) ($totals->total_employer_insurance ?? 0),
            'total_tax' => (float) ($totals->total_tax ?? 0),
            'total_other_deductions' => (float) ($totals->total_other_deductions ?? 0),
            'total_net' => (float) ($totals->total_net ?? 0),
        ]);
    }

    protected function resolveEmployeeInsurancePayableAccount(): \RMS\Accounting\Models\Account
    {
        return $this->systemAccountLocator->accountBySystemKey('liabilities.employee_insurance_payable')
            ?? $this->systemAccountLocator->accountBySystemKeyOrFail('liabilities.social_insurance_payable');
    }

    protected function resolveEmployerInsurancePayableAccount(): \RMS\Accounting\Models\Account
    {
        return $this->systemAccountLocator->accountBySystemKey('liabilities.employer_insurance_payable')
            ?? $this->systemAccountLocator->accountBySystemKeyOrFail('liabilities.social_insurance_payable');
    }

    protected function resolvePayrollTaxPayableAccount(): \RMS\Accounting\Models\Account
    {
        return $this->systemAccountLocator->accountBySystemKeyOrFail('liabilities.payroll_tax_payable');
    }

    protected function resolveOtherPayrollDeductionsPayableAccount(): \RMS\Accounting\Models\Account
    {
        return $this->systemAccountLocator->accountBySystemKeyOrFail('liabilities.other_payroll_deductions_payable');
    }

    protected function resolvePayrollSeniorityExpenseAccount(): \RMS\Accounting\Models\Account
    {
        return $this->systemAccountLocator->accountBySystemKeyOrFail('expenses.payroll_seniority');
    }

    protected function resolvePayrollSeniorityReserveAccount(): \RMS\Accounting\Models\Account
    {
        return $this->systemAccountLocator->accountBySystemKeyOrFail('liabilities.payroll_seniority_reserve');
    }

    protected function isReadyToClose(PayrollRun $run): bool
    {
        $run->refresh();
        $requiresLoanSettlement = $this->runHasLoanDeductions($run);
        $requiresSenioritySettlement = (float) $run->total_seniority > 0;

        return (bool) $run->net_payment_manual_journal_id
            && (bool) $run->insurance_remittance_manual_journal_id
            && (bool) $run->tax_remittance_manual_journal_id
            && (! $requiresLoanSettlement || (bool) $run->loan_settlement_manual_journal_id)
            && (! $requiresSenioritySettlement || (bool) $run->seniority_settlement_manual_journal_id);
    }

    protected function refreshStatusFromPostedJournals(PayrollRun $run): void
    {
        $run->refresh();
        if (! $run->accrual_manual_journal_id) {
            $run->update(['status' => PayrollRun::STATUS_DRAFT]);

            return;
        }

        if ($this->isReadyToClose($run)) {
            $run->update(['status' => PayrollRun::STATUS_CLOSED]);

            return;
        }

        if ($run->tax_remittance_manual_journal_id) {
            $run->update(['status' => PayrollRun::STATUS_TAX_REMITTED]);

            return;
        }

        if ($run->insurance_remittance_manual_journal_id) {
            $run->update(['status' => PayrollRun::STATUS_INSURANCE_REMITTED]);

            return;
        }

        if ($run->net_payment_manual_journal_id) {
            $run->update(['status' => PayrollRun::STATUS_PAID]);

            return;
        }

        $run->update(['status' => PayrollRun::STATUS_ACCRUED]);
    }

    protected function normalizeLedgerCurrency(string $code): string
    {
        return $code === 'IRT' ? 'IRR' : $code;
    }

    /**
     * @param array<string,mixed> $paymentPayload
     */
    protected function resolvePaymentCreditAccountId(
        PayrollRun $run,
        float $amount,
        string $journalDate,
        array $paymentPayload,
        string $context
    ): int {
        $paymentMethod = (string) ($paymentPayload['payment_method'] ?? 'bank');
        if ($paymentMethod === 'cheque') {
            $this->createIssuedChequeForPayroll($run, $amount, $journalDate, $paymentPayload, $context);
            $clearingAccountId = (int) (app(ChequeLedgerService::class)->resolvePayableClearingAccountId() ?? 0);
            if ($clearingAccountId <= 0) {
                throw new \RuntimeException((string) trans('accounting::accounting.payroll_runs.errors.cheque_clearing_missing'));
            }

            return $clearingAccountId;
        }

        $bankId = (int) ($paymentPayload['bank_id'] ?? 0);
        $bank = Bank::query()->findOrFail($bankId);
        if (! $bank->account_id) {
            throw new \RuntimeException((string) trans('accounting::accounting.payroll_runs.errors.bank_missing_account'));
        }

        return (int) $bank->account_id;
    }

    /**
     * @param array<string,mixed> $paymentPayload
     */
    protected function createIssuedChequeForPayroll(
        PayrollRun $run,
        float $amount,
        string $journalDate,
        array $paymentPayload,
        string $context
    ): Cheque {
        $chequebookId = (int) ($paymentPayload['chequebook_id'] ?? 0);
        $chequebook = Chequebook::query()->findOrFail($chequebookId);
        $bankId = (int) ($chequebook->bank_id ?? 0);
        if ($bankId <= 0) {
            throw new \RuntimeException((string) trans('accounting::accounting.payroll_runs.errors.chequebook_missing_bank'));
        }

        return Cheque::query()->create([
            'cheque_number' => (string) ($paymentPayload['cheque_number'] ?? ''),
            'bank_id' => $bankId,
            'chequebook_id' => $chequebookId,
            'cheque_type' => Cheque::TYPE_ISSUED,
            'amount' => $amount,
            'currency_code' => (string) ($run->currency_code ?: 'IRR'),
            'issue_date' => $journalDate,
            'due_date' => (string) ($paymentPayload['cheque_due_date'] ?? $journalDate),
            'payer_name' => (string) config('app.name', 'Company'),
            'payee_name' => (string) ($paymentPayload['cheque_payee_name'] ?? ''),
            'status' => Cheque::STATUS_ISSUED,
            'notes' => (string) ($paymentPayload['cheque_notes'] ?? ''),
            'source_type' => PayrollRun::class,
            'source_id' => (int) $run->id,
            'meta_json' => [
                'auto_created' => true,
                'auto_context' => 'payroll_'.$context.'_payment',
            ],
        ]);
    }

    protected function assertDateInOpenFiscalPeriod(string $date, string $operation): void
    {
        $trimmedDate = trim($date);
        if ($trimmedDate === '') {
            return;
        }
        if (function_exists('\RMS\Helper\changeNumberToEn')) {
            $trimmedDate = (string) \RMS\Helper\changeNumberToEn($trimmedDate);
        }
        $trimmedDate = str_replace('/', '-', $trimmedDate);

        try {
            $targetDate = Carbon::parse($trimmedDate)->format('Y-m-d');
        } catch (\Throwable) {
            throw new \RuntimeException((string) trans('accounting::errors.invalid_date'));
        }

        $fiscalYear = FiscalYear::query()
            ->whereDate('start_date', '<=', $targetDate)
            ->whereDate('end_date', '>=', $targetDate)
            ->orderByDesc('id')
            ->first();
        if (! $fiscalYear instanceof FiscalYear) {
            return;
        }

        $status = (string) ($fiscalYear->status ?? '');
        $isClosed = (bool) ($fiscalYear->is_closed ?? false)
            || in_array($status, [FiscalYear::STATUS_LOCKED, FiscalYear::STATUS_CLOSED], true);
        if (! $isClosed) {
            return;
        }

        throw new \RuntimeException((string) trans('accounting::accounting.payroll_runs.errors.fiscal_period_closed', [
            'operation' => $operation,
            'year' => (string) ($fiscalYear->year_code ?? ''),
        ]));
    }

    protected function assertRunAttendanceApproved(PayrollRun $run): void
    {
        $employeeIds = $run->lines()
            ->pluck('employee_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $this->attendanceWorklogService->assertPayrollAllowed(
            (string) $run->period_start?->format('Y-m-d'),
            (string) $run->period_end?->format('Y-m-d'),
            $employeeIds
        );
    }
}
