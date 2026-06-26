<?php

declare(strict_types=1);

namespace RMS\Accounting\Http\Controllers\Admin;

use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RMS\Core\Models\Setting;
use RMS\Accounting\Models\Bank;
use RMS\Accounting\Models\Chequebook;
use RMS\Accounting\Models\Cheque;
use RMS\Accounting\Models\Employee;
use RMS\Accounting\Models\EmployeeLoan;
use RMS\Accounting\Models\EmployeeLoanInstallment;
use RMS\Accounting\Services\EmployeeContractService;
use RMS\Accounting\Services\AttendanceWorklogService;
use RMS\Accounting\Models\PayrollRun;
use RMS\Accounting\Models\PayrollRunLine;
use RMS\Accounting\Services\AccountingDateInputNormalizer;
use RMS\Accounting\Services\EmployeeLoanSettlementService;
use RMS\Accounting\Services\PayrollJournalService;
use RMS\Accounting\Support\PayrollCalculator;
use RMS\Accounting\Support\AccountingDateUi;

class PayrollRunsController extends AccountingAdminController
{
    public function table(): string
    {
        return 'payroll_runs';
    }

    public function modelName(): string
    {
        return PayrollRun::class;
    }

    public function index(Request $request)
    {
        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('payroll_runs.index')
            ->withVariables([
                'runs' => PayrollRun::query()->orderByDesc('id')->paginate(25),
            ]);

        return $this->view();
    }

    public function create(Request $request)
    {
        return $this->renderForm(new PayrollRun, 'create');
    }

    public function payrollRunsStore(Request $request, PayrollJournalService $service): RedirectResponse
    {
        $payload = $this->validatedPayload($request);
        $run = $service->createRun($payload);

        return redirect()
            ->route('admin.accounting.payroll-runs.show', $run->id)
            ->with('success', trans('accounting::accounting.payroll_runs.flash_created'));
    }

    public function edit(Request $request, int|string $run)
    {
        $model = PayrollRun::query()->with('lines')->findOrFail((int) $run);
        $model->load('lines.items');

        return $this->renderForm($model, 'edit');
    }

    public function payrollRunsUpdate(Request $request, int|string $run, PayrollJournalService $service): RedirectResponse
    {
        $model = PayrollRun::query()->with('lines')->findOrFail((int) $run);
        $payload = $this->validatedPayload($request);
        $service->updateRun($model, $payload);

        return redirect()
            ->route('admin.accounting.payroll-runs.show', $model->id)
            ->with('success', trans('accounting::accounting.payroll_runs.flash_updated'));
    }

    public function show(Request $request, int|string $run)
    {
        $model = PayrollRun::query()
            ->with(['lines.employee', 'lines.items', 'lineItems', 'accrualJournal', 'netPaymentJournal', 'insuranceRemittanceJournal', 'taxRemittanceJournal', 'loanSettlementJournal', 'senioritySettlementJournal'])
            ->findOrFail((int) $run);
        $paymentCheques = [
            'payroll_net_payment' => null,
            'payroll_seniority_payment' => null,
        ];
        $cheques = Cheque::query()
            ->where('source_type', PayrollRun::class)
            ->where('source_id', (int) $model->id)
            ->orderByDesc('id')
            ->get();
        foreach ($cheques as $cheque) {
            $context = (string) data_get($cheque->meta_json, 'auto_context', '');
            if (array_key_exists($context, $paymentCheques) && $paymentCheques[$context] === null) {
                $paymentCheques[$context] = $cheque;
            }
        }

        $plugins = AccountingDateUi::calendarMode() === AccountingDateUi::MODE_JALALI
            ? ['persian-datepicker']
            : [];
        if (! in_array('confirm-modal', $plugins, true)) {
            $plugins[] = 'confirm-modal';
        }

        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('payroll_runs.show')
            ->withPlugins($plugins)
            ->withJs('vendor/accounting/admin/js/payroll-runs-show.js', true)
            ->withVariables([
                'run' => $model,
                'banks' => Bank::query()->where('active', true)->orderBy('name')->get(),
                'chequebooks' => Chequebook::query()->where('active', true)->with('bank')->orderBy('title')->get(),
                'paymentCheques' => $paymentCheques,
            ]);

        return $this->view();
    }

    public function postAccrual(Request $request, int|string $run, PayrollJournalService $service): RedirectResponse
    {
        $service->postAccrual((int) $run);

        return redirect()
            ->route('admin.accounting.payroll-runs.show', (int) $run)
            ->with('success', trans('accounting::accounting.payroll_runs.flash_accrual_posted'));
    }

    public function reverseAccrual(Request $request, int|string $run, PayrollJournalService $service): RedirectResponse
    {
        $service->reverseAccrual((int) $run);

        return redirect()
            ->route('admin.accounting.payroll-runs.show', (int) $run)
            ->with('success', trans('accounting::accounting.payroll_runs.flash_accrual_reversed'));
    }

    public function postNetPayment(Request $request, int|string $run, PayrollJournalService $service): RedirectResponse
    {
        $payload = $this->validatedPaymentPayload($request);
        $payload['journal_date'] = $this->normalizePostedAccountingDate($request);

        $service->postNetPayment(
            (int) $run,
            (string) $payload['journal_date'],
            $payload['description'] ?? null,
            $payload
        );

        return redirect()
            ->route('admin.accounting.payroll-runs.show', (int) $run)
            ->with('success', trans('accounting::accounting.payroll_runs.flash_net_paid'));
    }

    public function reverseNetPayment(Request $request, int|string $run, PayrollJournalService $service): RedirectResponse
    {
        $service->reverseNetPayment((int) $run);

        return redirect()
            ->route('admin.accounting.payroll-runs.show', (int) $run)
            ->with('success', trans('accounting::accounting.payroll_runs.flash_net_payment_reversed'));
    }

    public function postInsuranceRemittance(Request $request, int|string $run, PayrollJournalService $service): RedirectResponse
    {
        $payload = $request->validate([
            'bank_id' => 'required|integer|exists:banks,id',
            'journal_date' => 'required|string|max:64',
            'description' => 'nullable|string|max:2000',
        ]);
        $journalDate = $this->normalizePostedAccountingDate($request);

        $service->postInsuranceRemittance((int) $run, (int) $payload['bank_id'], $journalDate, $payload['description'] ?? null);

        return redirect()
            ->route('admin.accounting.payroll-runs.show', (int) $run)
            ->with('success', trans('accounting::accounting.payroll_runs.flash_insurance_remitted'));
    }

    public function reverseInsuranceRemittance(Request $request, int|string $run, PayrollJournalService $service): RedirectResponse
    {
        $service->reverseInsuranceRemittance((int) $run);

        return redirect()
            ->route('admin.accounting.payroll-runs.show', (int) $run)
            ->with('success', trans('accounting::accounting.payroll_runs.flash_insurance_remittance_reversed'));
    }

    public function postTaxRemittance(Request $request, int|string $run, PayrollJournalService $service): RedirectResponse
    {
        $payload = $request->validate([
            'bank_id' => 'required|integer|exists:banks,id',
            'journal_date' => 'required|string|max:64',
            'description' => 'nullable|string|max:2000',
        ]);
        $journalDate = $this->normalizePostedAccountingDate($request);

        $service->postTaxRemittance((int) $run, (int) $payload['bank_id'], $journalDate, $payload['description'] ?? null);

        return redirect()
            ->route('admin.accounting.payroll-runs.show', (int) $run)
            ->with('success', trans('accounting::accounting.payroll_runs.flash_tax_remitted'));
    }

    public function reverseTaxRemittance(Request $request, int|string $run, PayrollJournalService $service): RedirectResponse
    {
        $service->reverseTaxRemittance((int) $run);

        return redirect()
            ->route('admin.accounting.payroll-runs.show', (int) $run)
            ->with('success', trans('accounting::accounting.payroll_runs.flash_tax_remittance_reversed'));
    }

    public function postLoanSettlement(Request $request, int|string $run, PayrollJournalService $service): RedirectResponse
    {
        $service->postLoanSettlement((int) $run);

        return redirect()
            ->route('admin.accounting.payroll-runs.show', (int) $run)
            ->with('success', trans('accounting::accounting.payroll_runs.flash_loan_settled'));
    }

    public function reverseLoanSettlement(Request $request, int|string $run, PayrollJournalService $service): RedirectResponse
    {
        $service->reverseLoanSettlement((int) $run);

        return redirect()
            ->route('admin.accounting.payroll-runs.show', (int) $run)
            ->with('success', trans('accounting::accounting.payroll_runs.flash_loan_settlement_reversed'));
    }

    public function postSenioritySettlement(Request $request, int|string $run, PayrollJournalService $service): RedirectResponse
    {
        $payload = $this->validatedPaymentPayload($request);
        $payload['journal_date'] = $this->normalizePostedAccountingDate($request);

        $service->postSenioritySettlement(
            (int) $run,
            (string) $payload['journal_date'],
            $payload['description'] ?? null,
            $payload
        );

        return redirect()
            ->route('admin.accounting.payroll-runs.show', (int) $run)
            ->with('success', trans('accounting::accounting.payroll_runs.flash_seniority_settled'));
    }

    public function reverseSenioritySettlement(Request $request, int|string $run, PayrollJournalService $service): RedirectResponse
    {
        $service->reverseSenioritySettlement((int) $run);

        return redirect()
            ->route('admin.accounting.payroll-runs.show', (int) $run)
            ->with('success', trans('accounting::accounting.payroll_runs.flash_seniority_settlement_reversed'));
    }

    public function printPayslip(Request $request, int|string $run, int|string $line)
    {
        $payrollRun = PayrollRun::query()
            ->with([
                'lines' => static function ($query): void {
                    $query->with(['employee', 'items']);
                },
            ])
            ->findOrFail((int) $run);

        /** @var PayrollRunLine $lineModel */
        $lineModel = $payrollRun->lines->firstWhere('id', (int) $line);
        abort_unless($lineModel instanceof PayrollRunLine, 404);

        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('payroll_runs.payslip_print')
            ->withCss('vendor/accounting/admin/css/payroll-runs-print.css', true)
            ->withVariables([
                'run' => $payrollRun,
                'line' => $lineModel,
            ]);

        return $this->view();
    }

    protected function renderForm(PayrollRun $run, string $mode)
    {
        $plugins = AccountingDateUi::calendarMode() === AccountingDateUi::MODE_JALALI
            ? ['persian-datepicker']
            : [];
        if (! in_array('confirm-modal', $plugins, true)) {
            $plugins[] = 'confirm-modal';
        }
        $fallbackPeriodEnd = $run->period_end ? $run->period_end->format('Y-m-d') : now()->endOfMonth()->format('Y-m-d');
        $periodEndCandidate = (string) old('period_end', $fallbackPeriodEnd);
        $normalizedPeriodEnd = app(AccountingDateInputNormalizer::class)->normalizeFilterDateToGregorian($periodEndCandidate);
        $effectivePeriodEnd = (string) ($normalizedPeriodEnd ?: $fallbackPeriodEnd);
        $effectivePeriodEndDisplay = AccountingDateUi::gregorianYmdToInputDisplay($effectivePeriodEnd);

        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('payroll_runs.form')
            ->withPlugins($plugins)
            ->withJs('vendor/accounting/admin/js/payroll-runs-form.js', true)
            ->withVariables([
                'run' => $run,
                'mode' => $mode,
                'employees' => Employee::query()->where('active', true)->orderBy('name')->get(),
                'decimalPlaces' => min(4, max(0, (int) Setting::get('accounting.decimal_places', config('accounting.decimal_places', 0)))),
                'effectivePeriodEnd' => $effectivePeriodEnd,
                'effectivePeriodEndDisplay' => $effectivePeriodEndDisplay,
                'employeeSalaryDefaults' => app(EmployeeContractService::class)->salaryDefaultsByEmployee($effectivePeriodEnd),
                'loanPreviewByEmployee' => $this->buildLoanPreviewByEmployee($effectivePeriodEnd),
                'payrollMinimumWage' => (float) Setting::get('accounting.payroll.minimum_wage', 0),
                'attendanceFeatureEnabled' => app(AttendanceWorklogService::class)->featureEnabled(),
            ]);

        return $this->view();
    }

    /**
     * @return array<string,mixed>
     */
    protected function validatedPayload(Request $request): array
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'period_start' => 'required|string|max:64',
            'period_end' => 'required|string|max:64',
            'journal_date' => 'required|string|max:64',
            'currency_code' => 'nullable|string|size:3',
            'policy' => 'nullable|array',
            'policy.employee_insurance_rate' => 'nullable|numeric|min:0|max:100',
            'policy.employer_insurance_rate' => 'nullable|numeric|min:0|max:100',
            'policy.tax_rate' => 'nullable|numeric|min:0|max:100',
            'lines' => 'required|array|min:1',
            'lines.*.employee_id' => 'required|integer|exists:employees,id',
            'lines.*.base_salary' => 'required|numeric|min:0',
            'lines.*.benefits' => 'nullable|numeric|min:0',
            'lines.*.seniority' => 'nullable|numeric|min:0',
            'lines.*.employee_insurance' => 'nullable|numeric|min:0',
            'lines.*.employer_insurance' => 'nullable|numeric|min:0',
            'lines.*.tax' => 'nullable|numeric|min:0',
            'lines.*.other_deductions' => 'nullable|numeric|min:0',
            'lines.*.employee_insurance_manual' => 'nullable|boolean',
            'lines.*.employer_insurance_manual' => 'nullable|boolean',
            'lines.*.tax_manual' => 'nullable|boolean',
            'lines.*.skip_loan_deduction' => 'nullable|boolean',
            'lines.*.description' => 'nullable|string|max:1000',
            'lines.*.items' => 'nullable|array',
            'lines.*.items.*.type' => 'required_with:lines.*.items|string|in:earning,deduction,employer_contribution',
            'lines.*.items.*.code' => 'nullable|string|max:80',
            'lines.*.items.*.title' => 'required_with:lines.*.items|string|max:255',
            'lines.*.items.*.amount' => 'required_with:lines.*.items|numeric|min:0',
            'lines.*.items.*.notes' => 'nullable|string|max:1000',
            'confirm_min_wage_override' => 'nullable|boolean',
            'confirm_zero_net_override' => 'nullable|boolean',
        ]);

        $normalizer = app(AccountingDateInputNormalizer::class);
        $validated['period_start'] = $this->normalizeDateString((string) $validated['period_start'], $normalizer, 'period_start');
        $validated['period_end'] = $this->normalizeDateString((string) $validated['period_end'], $normalizer, 'period_end');
        $validated['journal_date'] = $this->normalizeDateString((string) $validated['journal_date'], $normalizer, 'journal_date');
        $validated['policy'] = [
            'employee_insurance_rate' => (float) data_get($validated, 'policy.employee_insurance_rate', PayrollCalculator::DEFAULT_EMPLOYEE_INSURANCE_RATE * 100),
            'employer_insurance_rate' => (float) data_get($validated, 'policy.employer_insurance_rate', PayrollCalculator::DEFAULT_EMPLOYER_INSURANCE_RATE * 100),
            'tax_rate' => (float) data_get($validated, 'policy.tax_rate', PayrollCalculator::DEFAULT_TAX_RATE * 100),
        ];
        $attendanceService = app(AttendanceWorklogService::class);
        $attendanceService->assertPayrollAllowed(
            (string) $validated['period_start'],
            (string) $validated['period_end'],
            array_map(
                static fn (array $line): int => (int) ($line['employee_id'] ?? 0),
                (array) $validated['lines']
            )
        );
        $confirmedMinWageOverride = filter_var($validated['confirm_min_wage_override'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $confirmedZeroNetOverride = filter_var($validated['confirm_zero_net_override'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $contractDefaultsByEmployee = app(EmployeeContractService::class)->salaryDefaultsByEmployee((string) $validated['period_end']);
        $minimumWage = (float) Setting::get('accounting.payroll.minimum_wage', 0);

        $lines = [];
        foreach ((array) $validated['lines'] as $lineIndex => $line) {
            $line = $attendanceService->applyProrationToLine(
                (array) $line,
                (string) $validated['period_start'],
                (string) $validated['period_end']
            );
            $computed = PayrollCalculator::computeLine($line, $validated['policy']);
            if ($computed['net_salary'] < 0) {
                throw ValidationException::withMessages([
                    "lines.$lineIndex.net_salary" => [trans('accounting::accounting.payroll_runs.errors.net_negative')],
                ]);
            }
            if (abs((float) $computed['net_salary']) < 0.0000001 && ! $confirmedZeroNetOverride) {
                throw ValidationException::withMessages([
                    "lines.$lineIndex.net_salary" => [trans('accounting::accounting.payroll_runs.errors.net_zero_requires_confirmation')],
                ]);
            }
            $employeeId = (int) ($line['employee_id'] ?? 0);
            $contractBase = (float) data_get($contractDefaultsByEmployee, $employeeId.'.base_salary', 0);
            if ($contractBase > 0 && (float) $computed['base_salary'] < $contractBase) {
                throw ValidationException::withMessages([
                    "lines.$lineIndex.base_salary" => [
                        trans('accounting::accounting.payroll_runs.errors.base_below_contract', [
                            'contract_base' => number_format($contractBase, 0),
                        ]),
                    ],
                ]);
            }
            if ($minimumWage > 0 && (float) $computed['base_salary'] < $minimumWage && ! $confirmedMinWageOverride) {
                throw ValidationException::withMessages([
                    "lines.$lineIndex.base_salary" => [
                        trans('accounting::accounting.payroll_runs.errors.base_below_minimum_wage_requires_confirmation', [
                            'minimum_wage' => number_format($minimumWage, 0),
                        ]),
                    ],
                ]);
            }

            $lines[] = [
                'employee_id' => $employeeId,
                'base_salary' => $computed['base_salary'],
                'benefits' => $computed['benefits'],
                'seniority' => $computed['seniority'] ?? 0,
                'gross_salary' => $computed['gross_salary'],
                'employee_insurance' => $computed['employee_insurance'],
                'employer_insurance' => $computed['employer_insurance'],
                'tax' => $computed['tax'],
                'other_deductions' => $computed['other_deductions'],
                'net_salary' => $computed['net_salary'],
                'employee_insurance_manual' => filter_var($line['employee_insurance_manual'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'employer_insurance_manual' => filter_var($line['employer_insurance_manual'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'tax_manual' => filter_var($line['tax_manual'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'skip_loan_deduction' => filter_var($line['skip_loan_deduction'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'items' => (array) ($line['items'] ?? []),
                'description' => (string) ($line['description'] ?? ''),
                '_attendance_snapshot' => is_array($line['_attendance_snapshot'] ?? null) ? $line['_attendance_snapshot'] : null,
            ];
        }
        $validated['lines'] = $lines;

        // Warm up installment breakdown to surface validation/config errors early.
        /** @var EmployeeLoanSettlementService $loanService */
        $loanService = app(EmployeeLoanSettlementService::class);
        foreach ($validated['lines'] as $line) {
            if (filter_var($line['skip_loan_deduction'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }
            $loanService->getDueInstallmentsForEmployee((int) $line['employee_id'], (string) $validated['period_end']);
        }

        return $validated;
    }

    /**
     * @return array<int,array<int,array<string,mixed>>>
     */
    protected function buildLoanPreviewByEmployee(string $periodEnd): array
    {
        $normalizedPeriodEnd = app(AccountingDateInputNormalizer::class)->normalizeFilterDateToGregorian($periodEnd) ?: $periodEnd;
        $asOfDate = Carbon::parse($normalizedPeriodEnd)->endOfDay()->format('Y-m-d');
        $loans = EmployeeLoan::query()
            ->where('status', EmployeeLoan::STATUS_ACTIVE)
            ->where('remaining_total', '>', 0)
            ->with(['installments' => static function ($query): void {
                $query->whereIn('status', [
                    EmployeeLoanInstallment::STATUS_PENDING,
                    EmployeeLoanInstallment::STATUS_PARTIALLY_PAID,
                ])->orderBy('due_date')->orderBy('id');
            }])
            ->orderBy('id')
            ->get();

        $byEmployee = [];
        foreach ($loans as $loan) {
            $duePrincipal = 0.0;
            $dueInterest = 0.0;
            $nextDueDate = null;
            $nextDueDateDisplay = null;
            foreach ($loan->installments as $installment) {
                if ($nextDueDate === null) {
                    $nextDueDate = $installment->due_date?->format('Y-m-d');
                    if ($nextDueDate !== null) {
                        $nextDueDateDisplay = AccountingDateUi::gregorianYmdToInputDisplay($nextDueDate);
                    }
                }
                if (($installment->due_date?->format('Y-m-d') ?? '') > $asOfDate) {
                    continue;
                }
                $duePrincipal += max(0.0, (float) $installment->principal_amount - (float) $installment->paid_principal);
                $dueInterest += max(0.0, (float) $installment->interest_amount - (float) $installment->paid_interest);
            }

            $byEmployee[(int) $loan->employee_id][] = [
                'loan_id' => (int) $loan->id,
                'loan_number' => (string) $loan->loan_number,
                'remaining_total' => round((float) $loan->remaining_total, 4),
                'due_principal' => round($duePrincipal, 4),
                'due_interest' => round($dueInterest, 4),
                'due_total' => round($duePrincipal + $dueInterest, 4),
                'next_due_date' => $nextDueDate,
                'next_due_date_display' => $nextDueDateDisplay,
            ];
        }

        return $byEmployee;
    }

    protected function normalizeDateString(string $value, AccountingDateInputNormalizer $normalizer, string $field): string
    {
        $date = $normalizer->normalizeFilterDateToGregorian($value);
        if ($date === null) {
            throw ValidationException::withMessages([$field => [trans('accounting::errors.invalid_date')]]);
        }

        return $date;
    }

    /**
     * @return array<string,mixed>
     */
    protected function validatedPaymentPayload(Request $request): array
    {
        $payload = $request->validate([
            'payment_method' => 'required|string|in:bank,cheque',
            'journal_date' => 'required|string|max:64',
            'description' => 'nullable|string|max:2000',
            'bank_id' => 'required_if:payment_method,bank|nullable|integer|exists:banks,id',
            'chequebook_id' => 'required_if:payment_method,cheque|nullable|integer|exists:chequebooks,id',
            'cheque_number' => 'required_if:payment_method,cheque|nullable|string|max:120',
            'cheque_due_date' => 'required_if:payment_method,cheque|nullable|string|max:64',
            'cheque_payee_name' => 'required_if:payment_method,cheque|nullable|string|max:255',
            'cheque_notes' => 'nullable|string|max:1000',
        ]);

        if (($payload['payment_method'] ?? null) === 'cheque') {
            $normalizedChequeNumber = trim((string) ($payload['cheque_number'] ?? ''));
            if ($normalizedChequeNumber !== '' && Cheque::query()->where('cheque_number', $normalizedChequeNumber)->exists()) {
                throw ValidationException::withMessages([
                    'cheque_number' => [trans('accounting::accounting.payroll_runs.errors.cheque_number_not_unique')],
                ]);
            }
            $payload['cheque_number'] = $normalizedChequeNumber;
            $payload['cheque_due_date'] = $this->normalizeDateString(
                (string) ($payload['cheque_due_date'] ?? ''),
                app(AccountingDateInputNormalizer::class),
                'cheque_due_date'
            );
        }

        return $payload;
    }
}
