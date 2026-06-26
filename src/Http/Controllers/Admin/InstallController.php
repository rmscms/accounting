<?php

declare(strict_types=1);

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RMS\Accounting\Services\AccountingInstallService;

class InstallController extends AccountingAdminController
{
    public function table(): string
    {
        return '';
    }

    public function modelName(): string
    {
        return '';
    }

    public function show(Request $request)
    {
        /** @var AccountingInstallService $install */
        $install = app(AccountingInstallService::class);

        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('install.index')
            ->withVariables([
                'isComplete' => $install->isComplete(),
                'wizardRequired' => $install->isWizardRequired(),
                'steps' => session('accounting_install_steps', []),
                'lastSuccess' => session('accounting_install_success'),
            ]);

        return $this->view();
    }

    public function run(Request $request, AccountingInstallService $install): RedirectResponse
    {
        $result = $install->runAll();

        return redirect()
            ->route('admin.accounting.install')
            ->with('accounting_install_steps', $result['steps'])
            ->with('accounting_install_success', $result['success']);
    }
}
