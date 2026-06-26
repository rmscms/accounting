<?php

declare(strict_types=1);

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\Request;
use RMS\Accounting\Services\ShareholderEquitySummaryService;

class ShareholderEquityOverviewController extends AccountingAdminController
{
    public function table(): string
    {
        return '';
    }

    public function modelName(): string
    {
        return '';
    }

    public function index(Request $request)
    {
        $summary = app(ShareholderEquitySummaryService::class);
        $data = $summary->build();

        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('shareholder_equity_overview.index')
            ->withVariables($data);

        return $this->view();
    }
}
