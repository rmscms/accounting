<?php

declare(strict_types=1);

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RMS\Accounting\Models\Employee;
use RMS\Accounting\Models\EmployeeContract;
use RMS\Accounting\Services\AccountingDateInputNormalizer;
use RMS\Accounting\Services\EmployeeContractService;
use RMS\Accounting\Support\AuditActor;
use RMS\Accounting\Support\AccountingDateUi;

class EmployeeContractsController extends AccountingAdminController
{
    public function table(): string
    {
        return 'employee_contracts';
    }

    public function modelName(): string
    {
        return EmployeeContract::class;
    }

    public function index(Request $request)
    {
        $query = EmployeeContract::query()->with('employee');

        $employeeId = (int) $request->query('employee_id', 0);
        if ($employeeId > 0) {
            $query->where('employee_id', $employeeId);
        }
        $status = (string) $request->query('status', '');
        if ($status !== '') {
            $query->where('status', $status);
        }

        $contracts = $query->orderByDesc('effective_from')->orderByDesc('id')->paginate(25);

        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('employee_contracts.index')
            ->withVariables([
                'contracts' => $contracts,
                'employees' => Employee::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
                'filters' => [
                    'employee_id' => $employeeId,
                    'status' => $status,
                ],
            ]);

        return $this->view();
    }

    public function create(Request $request)
    {
        return $this->renderForm(new EmployeeContract(), 'create');
    }

    public function employeeContractsStore(Request $request, EmployeeContractService $service): RedirectResponse
    {
        $validated = $this->validatedPayload($request);
        $validated['created_by_user_id'] = AuditActor::userId();
        $validated['updated_by_user_id'] = AuditActor::userId();

        try {
            $contract = $service->createContract($validated);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['employee_contract' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.accounting.employee-contracts.show', $contract->id)
            ->with('success', trans('accounting::accounting.employee_contracts.flash_created'));
    }

    public function show(Request $request, int|string $contract)
    {
        $plugins = AccountingDateUi::calendarMode() === AccountingDateUi::MODE_JALALI ? ['persian-datepicker'] : [];
        $model = EmployeeContract::query()
            ->with([
                'employee',
                'employee.contracts' => static function ($query): void {
                    $query->orderByDesc('effective_from')->orderByDesc('id');
                },
            ])
            ->findOrFail((int) $contract);

        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('employee_contracts.show')
            ->withPlugins($plugins)
            ->withJs('document-attachments.js')
            ->withJsVariables([
                'documentAttachmentsApi' => [
                    'list' => route('admin.document-attachments.index'),
                    'upload' => route('admin.document-attachments.store'),
                    'download' => route('admin.document-attachments.download', ['id' => '__ID__']),
                    'delete' => route('admin.document-attachments.destroy', ['id' => '__ID__']),
                ],
                'i18n' => [
                    'document_attachments_no_files' => trans('admin.document_attachments.no_files'),
                    'document_attachments_confirm_delete' => trans('admin.document_attachments.confirm_delete'),
                ],
            ])
            ->withVariables([
                'contract' => $model,
                'timeline' => $model->employee?->contracts ?? collect(),
            ]);

        return $this->view();
    }

    public function edit(Request $request, int|string $contract)
    {
        $model = EmployeeContract::query()->findOrFail((int) $contract);

        return $this->renderForm($model, 'edit');
    }

    public function employeeContractsUpdate(Request $request, int|string $contract, EmployeeContractService $service): RedirectResponse
    {
        $model = EmployeeContract::query()->findOrFail((int) $contract);
        $validated = $this->validatedPayload($request, (int) $model->id);
        $validated['updated_by_user_id'] = AuditActor::userId();

        try {
            $service->updateContract($model, $validated);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['employee_contract' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.accounting.employee-contracts.show', (int) $model->id)
            ->with('success', trans('accounting::accounting.employee_contracts.flash_updated'));
    }

    public function end(Request $request, int|string $contract, EmployeeContractService $service): RedirectResponse
    {
        $validated = $request->validate([
            'effective_to' => 'required|string|max:64',
        ]);
        $normalizer = app(AccountingDateInputNormalizer::class);
        $effectiveTo = $this->normalizeDateString((string) $validated['effective_to'], $normalizer, 'effective_to');

        $model = EmployeeContract::query()->findOrFail((int) $contract);
        try {
            $service->endContract($model, $effectiveTo);
        } catch (\Throwable $e) {
            return back()->withErrors(['employee_contract' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.accounting.employee-contracts.show', (int) $model->id)
            ->with('success', trans('accounting::accounting.employee_contracts.flash_ended'));
    }

    public function cancel(Request $request, int|string $contract, EmployeeContractService $service): RedirectResponse
    {
        $model = EmployeeContract::query()->findOrFail((int) $contract);
        $service->cancelContract($model);

        return redirect()
            ->route('admin.accounting.employee-contracts.show', (int) $model->id)
            ->with('success', trans('accounting::accounting.employee_contracts.flash_cancelled'));
    }

    public function printSummary(Request $request, int|string $contract)
    {
        $model = EmployeeContract::query()->with('employee')->findOrFail((int) $contract);

        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('employee_contracts.print')
            ->withCss('vendor/accounting/admin/css/payroll-runs-print.css', true)
            ->withVariables([
                'contract' => $model,
            ]);

        return $this->view();
    }

    protected function renderForm(EmployeeContract $contract, string $mode)
    {
        $plugins = AccountingDateUi::calendarMode() === AccountingDateUi::MODE_JALALI ? ['persian-datepicker'] : [];
        if (! in_array('amount-formatter', $plugins, true)) {
            $plugins[] = 'amount-formatter';
        }

        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('employee_contracts.form')
            ->withPlugins($plugins)
            ->withJs('vendor/accounting/admin/js/employee-contracts-form.js', true)
            ->withVariables([
                'contract' => $contract,
                'mode' => $mode,
                'employees' => Employee::query()->where('active', true)->orderBy('name')->get(),
            ]);

        return $this->view();
    }

    /**
     * @return array<string,mixed>
     */
    protected function validatedPayload(Request $request, int $contractId = 0): array
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'contract_number' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('employee_contracts', 'contract_number')->ignore($contractId),
            ],
            'status' => ['required', Rule::in(EmployeeContract::statuses())],
            'effective_from' => 'required|string|max:64',
            'effective_to' => 'nullable|string|max:64',
            'signed_at' => 'nullable|string|max:64',
            'base_salary' => 'required|string|max:64',
            'seniority_monthly_default' => 'nullable|string|max:64',
            'salary_cycle' => 'required|string|max:20',
            'employee_insurance_rate' => 'nullable|numeric|min:0|max:100',
            'employer_insurance_rate' => 'nullable|numeric|min:0|max:100',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:5000',
        ]);

        $normalizer = app(AccountingDateInputNormalizer::class);
        $validated['effective_from'] = $this->normalizeDateString((string) $validated['effective_from'], $normalizer, 'effective_from');
        $validated['effective_to'] = ! empty($validated['effective_to'])
            ? $this->normalizeDateString((string) $validated['effective_to'], $normalizer, 'effective_to')
            : null;
        $validated['signed_at'] = ! empty($validated['signed_at'])
            ? $this->normalizeDateString((string) $validated['signed_at'], $normalizer, 'signed_at')
            : null;
        $validated['base_salary'] = $this->normalizePostedAmount((string) $validated['base_salary'], 'base_salary');
        $seniorityRaw = trim((string) ($validated['seniority_monthly_default'] ?? ''));
        $validated['seniority_monthly_default'] = $seniorityRaw !== ''
            ? $this->normalizePostedAmount($seniorityRaw, 'seniority_monthly_default')
            : 0.0;

        return $validated;
    }

    protected function normalizeDateString(string $value, AccountingDateInputNormalizer $normalizer, string $field): string
    {
        $date = $normalizer->normalizeFilterDateToGregorian($value);
        if ($date === null) {
            throw ValidationException::withMessages([$field => [trans('accounting::errors.invalid_date')]]);
        }

        return $date;
    }

    protected function normalizePostedAmount(string $value, string $field): float
    {
        $normalized = trim(\RMS\Helper\changeNumberToEn($value));
        $normalized = str_replace(['٬', '،', ',', ' '], '', $normalized);
        if (! is_numeric($normalized)) {
            throw ValidationException::withMessages([
                $field => [trans('accounting::accounting.employee_contracts.errors.invalid_amount')],
            ]);
        }

        $amount = (float) $normalized;
        if ($amount < 0) {
            throw ValidationException::withMessages([
                $field => [trans('accounting::accounting.employee_contracts.errors.invalid_amount')],
            ]);
        }

        return $amount;
    }
}
