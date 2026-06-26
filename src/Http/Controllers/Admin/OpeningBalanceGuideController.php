<?php

declare(strict_types=1);

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\Request;
use RMS\Accounting\Services\AccountingReadinessService;

class OpeningBalanceGuideController extends AccountingAdminController
{
    public function table(): string
    {
        return '';
    }

    public function modelName(): string
    {
        return '';
    }

    public function show(Request $request, AccountingReadinessService $readiness)
    {
        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('guides.opening_balance')
            ->withVariables([
                'readiness' => $readiness->summary(),
            ]);

        return $this->view();
    }
}
