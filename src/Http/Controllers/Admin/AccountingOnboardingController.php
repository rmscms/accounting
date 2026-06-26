<?php

declare(strict_types=1);

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;
use RMS\Accounting\Models\Account;
use RMS\Accounting\Services\AccountingInstallService;
use RMS\Accounting\Services\AccountingReadinessService;

class AccountingOnboardingController extends AccountingAdminController
{
    public function table(): string
    {
        return '';
    }

    public function modelName(): string
    {
        return '';
    }

    public function show(Request $request, AccountingReadinessService $readiness, AccountingInstallService $install)
    {
        $step = (int) $request->query('step', 1);
        if ($step < 1) {
            $step = 1;
        }
        if ($step > 6) {
            $step = 6;
        }

        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('onboarding.index')
            ->withJs('vendor/accounting/admin/js/accounting-onboarding-readiness.js', true)
            ->withVariables([
                'step' => $step,
                'readiness' => $readiness->summary(),
                'install' => $install,
            ]);

        return $this->view();
    }

    public function runChartInstall(Request $request, AccountingInstallService $install): JsonResponse
    {
        if (!Schema::hasTable('accounts')) {
            return response()->json([
                'success' => false,
                'message' => trans('accounting::accounting.onboarding.chart_install_no_table'),
            ], 422);
        }

        $activeBefore = Account::query()->where('active', true)->count();
        if ($activeBefore >= 3) {
            return response()->json([
                'success' => true,
                'message' => trans('accounting::accounting.onboarding.chart_install_not_needed'),
                'active_accounts' => $activeBefore,
                'reloaded' => false,
            ]);
        }

        $result = $install->runAll();
        $activeAfter = Account::query()->where('active', true)->count();

        if (!$result['success'] || $activeAfter < 3) {
            $errorStep = collect($result['steps'])->firstWhere('status', 'error');
            $detail = is_array($errorStep) ? (string) ($errorStep['detail'] ?? '') : '';

            return response()->json([
                'success' => false,
                'message' => $detail !== ''
                    ? trans('accounting::accounting.onboarding.chart_install_failed_detail', ['detail' => $detail])
                    : trans('accounting::accounting.onboarding.chart_install_failed'),
                'active_accounts' => $activeAfter,
                'steps' => $result['steps'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => trans('accounting::accounting.onboarding.chart_install_success', ['count' => $activeAfter]),
            'active_accounts' => $activeAfter,
            'reloaded' => true,
        ]);
    }
}
