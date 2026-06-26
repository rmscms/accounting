<?php

declare(strict_types=1);

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RMS\Accounting\Models\Employee;
use RMS\Accounting\Services\EmployeeAccountProvisioningService;

class EmployeesController extends AccountingAdminController
{
    public function table(): string
    {
        return 'employees';
    }

    public function modelName(): string
    {
        return Employee::class;
    }

    public function employeesIndex(Request $request)
    {
        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('employees.index')
            ->withPlugins(['confirm-modal'])
            ->withJs('vendor/accounting/admin/js/employees-confirm-modal-v2.js', true)
            ->withVariables([
                'employees' => Employee::query()->orderByDesc('id')->paginate(25),
            ]);

        return $this->view();
    }

    public function employeesCreate(Request $request)
    {
        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('employees.form')
            ->withVariables(['employee' => new Employee, 'mode' => 'create']);

        return $this->view();
    }

    public function employeesStore(Request $request, EmployeeAccountProvisioningService $provisioning): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'national_id' => 'nullable|string|max:32',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:32',
            'notes' => 'nullable|string|max:5000',
        ]);

        $validated['active'] = $request->boolean('active', true);
        $employee = Employee::create($validated);
        $provisioning->ensureAccounts($employee);

        return redirect()
            ->route('admin.accounting.employees.index')
            ->with('success', trans('accounting::accounting.employees.flash_created'));
    }

    public function employeesEdit(Request $request, int|string $employee)
    {
        $model = Employee::query()->findOrFail((int) $employee);
        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('employees.form')
            ->withVariables(['employee' => $model, 'mode' => 'edit']);

        return $this->view();
    }

    public function employeesUpdate(Request $request, int|string $employee, EmployeeAccountProvisioningService $provisioning): RedirectResponse
    {
        $model = Employee::query()->findOrFail((int) $employee);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'national_id' => 'nullable|string|max:32',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:32',
            'notes' => 'nullable|string|max:5000',
        ]);
        $validated['active'] = $request->boolean('active', true);
        $model->update($validated);
        $provisioning->ensureAccounts($model);

        return redirect()
            ->route('admin.accounting.employees.index')
            ->with('success', trans('accounting::accounting.employees.flash_updated'));
    }

    public function employeesDestroy(Request $request, int|string $employee): RedirectResponse
    {
        Employee::query()->findOrFail((int) $employee)->delete();

        return redirect()
            ->route('admin.accounting.employees.index')
            ->with('success', trans('accounting::accounting.employees.flash_deleted'));
    }
}
