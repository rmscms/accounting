<?php

declare(strict_types=1);

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RMS\Accounting\Models\Shareholder;
use RMS\Accounting\Services\ShareholderAccountProvisioningService;

class ShareholdersController extends AccountingAdminController
{
    public function table(): string
    {
        return 'shareholders';
    }

    public function modelName(): string
    {
        return Shareholder::class;
    }

    public function shareholdersIndex(Request $request)
    {
        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('shareholders.index')
            ->withVariables([
                'shareholders' => Shareholder::query()->orderByDesc('id')->paginate(25),
            ]);

        return $this->view();
    }

    public function shareholdersCreate(Request $request)
    {
        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('shareholders.form')
            ->withVariables(['shareholder' => new Shareholder, 'mode' => 'create']);

        return $this->view();
    }

    public function shareholdersStore(Request $request, ShareholderAccountProvisioningService $provisioning): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'national_id' => 'nullable|string|max:32',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:32',
            'notes' => 'nullable|string|max:5000',
        ]);

        $validated['active'] = $request->boolean('active', true);
        $shareholder = Shareholder::create($validated);
        $provisioning->ensureAccounts($shareholder);

        return redirect()
            ->route('admin.accounting.shareholders.index')
            ->with('success', trans('accounting::accounting.shareholders.flash_created'));
    }

    public function shareholdersEdit(Request $request, int|string $shareholder)
    {
        $model = Shareholder::query()->findOrFail((int) $shareholder);
        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('shareholders.form')
            ->withVariables(['shareholder' => $model, 'mode' => 'edit']);

        return $this->view();
    }

    public function shareholdersUpdate(Request $request, int|string $shareholder, ShareholderAccountProvisioningService $provisioning): RedirectResponse
    {
        $model = Shareholder::query()->findOrFail((int) $shareholder);
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
            ->route('admin.accounting.shareholders.index')
            ->with('success', trans('accounting::accounting.shareholders.flash_updated'));
    }

    public function shareholdersDestroy(Request $request, int|string $shareholder): RedirectResponse
    {
        Shareholder::query()->findOrFail((int) $shareholder)->delete();

        return redirect()
            ->route('admin.accounting.shareholders.index')
            ->with('success', trans('accounting::accounting.shareholders.flash_deleted'));
    }
}
