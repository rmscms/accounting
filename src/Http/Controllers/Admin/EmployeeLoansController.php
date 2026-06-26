<?php

declare(strict_types=1);

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RMS\Core\Models\Setting;
use RMS\Accounting\Models\Bank;
use RMS\Accounting\Models\Employee;
use RMS\Accounting\Models\EmployeeLoan;
use RMS\Accounting\Services\AccountingDateInputNormalizer;
use RMS\Accounting\Services\EmployeeLoanService;
use RMS\Accounting\Services\EmployeeLoanSettlementService;
use RMS\Accounting\Support\AccountingDateUi;

class EmployeeLoansController extends AccountingAdminController
{
    public function table(): string
    {
        return 'employee_loans';
    }

    public function modelName(): string
    {
        return EmployeeLoan::class;
    }

    public function index(Request $request)
    {
        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('employee_loans.index')
            ->withVariables([
                'loans' => EmployeeLoan::query()->with('employee')->withCount('payments')->orderByDesc('id')->paginate(25),
            ]);

        return $this->view();
    }

    public function create(Request $request)
    {
        $plugins = AccountingDateUi::calendarMode() === AccountingDateUi::MODE_JALALI ? ['persian-datepicker'] : [];
        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('employee_loans.create')
            ->withPlugins($plugins)
            ->withJs('vendor/accounting/admin/js/employee-loans-form.js', true)
            ->withVariables([
                'employees' => Employee::query()->where('active', true)->orderBy('name')->get(),
                'banks' => Bank::query()->where('active', true)->orderBy('name')->get(),
                'decimalPlaces' => min(4, max(0, (int) Setting::get('accounting.decimal_places', config('accounting.decimal_places', 0)))),
            ]);

        return $this->view();
    }

    public function employeeLoansStore(Request $request, EmployeeLoanService $service): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'disbursement_bank_id' => 'required|integer|exists:banks,id',
            'disbursement_date' => 'required|string|max:64',
            'first_due_date' => 'required|string|max:64',
            'principal_amount' => 'required|string|max:64',
            'annual_interest_rate' => 'nullable|numeric|min:0|max:100',
            'installments_count' => 'required|integer|min:1|max:240',
            'notes' => 'nullable|string|max:2000',
            'description' => 'nullable|string|max:2000',
        ]);

        $normalizer = app(AccountingDateInputNormalizer::class);
        $validated['disbursement_date'] = $this->normalizeDateString((string) $validated['disbursement_date'], $normalizer, 'disbursement_date');
        $validated['first_due_date'] = $this->normalizeDateString((string) $validated['first_due_date'], $normalizer, 'first_due_date');
        $validated['principal_amount'] = $this->normalizePostedAmount((string) $validated['principal_amount']);

        $loan = $service->createLoanWithDisbursement($validated);

        return redirect()
            ->route('admin.accounting.employee-loans.show', $loan->id)
            ->with('success', trans('accounting::accounting.employee_loans.flash_created'));
    }

    public function show(Request $request, int|string $loan)
    {
        $plugins = AccountingDateUi::calendarMode() === AccountingDateUi::MODE_JALALI ? ['persian-datepicker'] : [];
        $model = EmployeeLoan::query()
            ->with(['employee', 'disbursementBank', 'installments', 'payments.journal'])
            ->findOrFail((int) $loan);

        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('employee_loans.show')
            ->withPlugins($plugins)
            ->withVariables([
                'loan' => $model,
                'banks' => Bank::query()->where('active', true)->orderBy('name')->get(),
            ]);

        return $this->view();
    }

    public function edit(Request $request, int|string $loan)
    {
        $plugins = AccountingDateUi::calendarMode() === AccountingDateUi::MODE_JALALI ? ['persian-datepicker'] : [];
        $model = EmployeeLoan::query()->findOrFail((int) $loan);

        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('employee_loans.edit')
            ->withPlugins($plugins)
            ->withJs('vendor/accounting/admin/js/employee-loans-form.js', true)
            ->withVariables([
                'loan' => $model,
                'employees' => Employee::query()->where('active', true)->orderBy('name')->get(),
                'banks' => Bank::query()->where('active', true)->orderBy('name')->get(),
                'decimalPlaces' => min(4, max(0, (int) Setting::get('accounting.decimal_places', config('accounting.decimal_places', 0)))),
            ]);

        return $this->view();
    }

    public function employeeLoansUpdate(Request $request, int|string $loan, EmployeeLoanService $service): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'disbursement_bank_id' => 'required|integer|exists:banks,id',
            'disbursement_date' => 'required|string|max:64',
            'first_due_date' => 'required|string|max:64',
            'principal_amount' => 'required|string|max:64',
            'annual_interest_rate' => 'nullable|numeric|min:0|max:100',
            'installments_count' => 'required|integer|min:1|max:240',
            'notes' => 'nullable|string|max:2000',
            'description' => 'nullable|string|max:2000',
        ]);

        $normalizer = app(AccountingDateInputNormalizer::class);
        $validated['disbursement_date'] = $this->normalizeDateString((string) $validated['disbursement_date'], $normalizer, 'disbursement_date');
        $validated['first_due_date'] = $this->normalizeDateString((string) $validated['first_due_date'], $normalizer, 'first_due_date');
        $validated['principal_amount'] = $this->normalizePostedAmount((string) $validated['principal_amount']);

        $model = EmployeeLoan::query()->findOrFail((int) $loan);
        try {
            $service->updateLoan($model, $validated);
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('admin.accounting.employee-loans.edit', (int) $loan)
                ->withInput()
                ->withErrors(['employee_loan' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.accounting.employee-loans.show', (int) $loan)
            ->with('success', trans('accounting::accounting.employee_loans.flash_updated'));
    }

    public function postManualPayment(Request $request, int|string $loan, EmployeeLoanSettlementService $service): RedirectResponse
    {
        $validated = $request->validate([
            'bank_id' => 'required|integer|exists:banks,id',
            'payment_date' => 'required|string|max:64',
            'amount' => 'required|string|max:64',
            'description' => 'nullable|string|max:2000',
        ]);
        $normalizer = app(AccountingDateInputNormalizer::class);
        $paymentDate = $this->normalizeDateString((string) $validated['payment_date'], $normalizer, 'payment_date');
        $amount = $this->normalizePostedAmount((string) $validated['amount']);

        $loanModel = EmployeeLoan::query()->findOrFail((int) $loan);
        $service->postManualCollection(
            $loanModel,
            (int) $validated['bank_id'],
            $amount,
            $paymentDate,
            (string) ($validated['description'] ?? '')
        );

        return redirect()
            ->route('admin.accounting.employee-loans.show', (int) $loan)
            ->with('success', trans('accounting::accounting.employee_loans.flash_manual_payment_posted'));
    }

    public function cancel(Request $request, int|string $loan, EmployeeLoanService $service): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);
        $model = EmployeeLoan::query()->findOrFail((int) $loan);
        try {
            $service->cancelLoan($model, (string) $validated['reason']);
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('admin.accounting.employee-loans.show', (int) $loan)
                ->withErrors(['employee_loan' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.accounting.employee-loans.show', (int) $loan)
            ->with('success', trans('accounting::accounting.employee_loans.flash_cancelled'));
    }

    public function printInstallments(Request $request, int|string $loan)
    {
        $model = EmployeeLoan::query()
            ->with(['employee', 'installments', 'payments'])
            ->findOrFail((int) $loan);

        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('employee_loans.installments_print')
            ->withCss('vendor/accounting/admin/css/payroll-runs-print.css', true)
            ->withVariables([
                'loan' => $model,
            ]);

        return $this->view();
    }

    protected function normalizeDateString(string $value, AccountingDateInputNormalizer $normalizer, string $field): string
    {
        $date = $normalizer->normalizeFilterDateToGregorian($value);
        if ($date === null) {
            throw ValidationException::withMessages([$field => [trans('accounting::errors.invalid_date')]]);
        }

        return $date;
    }

    protected function normalizePostedAmount(string $value): float
    {
        $normalized = trim(\RMS\Helper\changeNumberToEn($value));
        $normalized = str_replace(['٬', '،', ',', ' '], '', $normalized);
        if (! is_numeric($normalized)) {
            throw ValidationException::withMessages(['amount' => [trans('accounting::accounting.employee_loans.errors.invalid_amount')]]);
        }
        $amount = (float) $normalized;
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => [trans('accounting::accounting.employee_loans.errors.invalid_amount')]]);
        }

        return $amount;
    }
}
