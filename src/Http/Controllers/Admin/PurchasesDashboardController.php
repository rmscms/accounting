<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\Request;

class PurchasesDashboardController extends AccountingAdminController
{
    public function index(Request $request)
    {
        $this->title((string) trans('accounting::accounting.purchases_dashboard.page_title'));
        $this->use_package_namespace = true;
        $this->view->usePackageNamespace('accounting')->setTpl('purchases.dashboard');

        return $this->view();
    }

    public function table(): string
    {
        return '';
    }

    public function modelName(): string
    {
        return '';
    }
}
