<?php

declare(strict_types=1);

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RMS\Accounting\Services\AccountingDataWipeService;
use RMS\Accounting\Services\AccountingSampleDataService;
use RMS\Accounting\Services\AccountingWipe\WipeOptions;
use Throwable;

class AccountingSampleDataController extends AccountingAdminController
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
        /** @var AccountingSampleDataService $service */
        $service = app(AccountingSampleDataService::class);

        $result = $request->session()->get('accounting_sample_data.preflight');
        if (! is_array($result)) {
            $result = $service->preflight();
        }

        $summary = $request->session()->get('accounting_sample_data.summary');
        if (! is_array($summary)) {
            $summary = null;
        }

        $formValues = $request->session()->get('accounting_sample_data.form_values');
        if (! is_array($formValues)) {
            $formValues = $service->defaultGenerationOptions();
        }

        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('sample_data.index')
            ->withVariables([
                'result' => $result,
                'summary' => $summary,
                'formValues' => $formValues,
                'preflightRoute' => route('admin.accounting.sample-data.preflight'),
                'generateRoute' => route('admin.accounting.sample-data.generate'),
                'wipeRoute' => route('admin.accounting.sample-data.wipe'),
            ]);

        return $this->view();
    }

    public function preflight(Request $request, AccountingSampleDataService $service): RedirectResponse
    {
        $request->session()->forget('accounting_sample_data.summary');
        $request->session()->put('accounting_sample_data.preflight', $service->preflight());

        return redirect()->route('admin.accounting.sample-data.index');
    }

    public function generate(Request $request, AccountingSampleDataService $service): RedirectResponse
    {
        $validated = $request->validate([
            'mode' => 'nullable|string|in:append,rebuild',
            'wipe_all_before_generate' => 'nullable|boolean',
            'customers_count' => 'nullable|integer|min:0|max:200',
            'shared_suppliers_count' => 'nullable|integer|min:0|max:200',
            'purchase_orders_count' => 'nullable|integer|min:0|max:500',
            'supplier_direct_invoices_count' => 'nullable|integer|min:0|max:500',
            'sales_invoices_count' => 'nullable|integer|min:0|max:500',
            'customer_payments_count' => 'nullable|integer|min:0|max:500',
            'customer_cheque_payments_count' => 'nullable|integer|min:0|max:500',
            'supplier_payments_count' => 'nullable|integer|min:0|max:500',
            'supplier_cheque_payments_count' => 'nullable|integer|min:0|max:500',
            'expenses_count' => 'nullable|integer|min:0|max:500',
            'fixed_assets_count' => 'nullable|integer|min:0|max:500',
            'shareholders_count' => 'nullable|integer|min:1|max:50',
            'capital_contributions_min' => 'nullable|integer|min:1|max:50',
            'capital_contributions_max' => 'nullable|integer|min:1|max:50',
            'withdrawals_min' => 'nullable|integer|min:1|max:50',
            'withdrawals_max' => 'nullable|integer|min:1|max:50',
        ]);

        $preflight = $service->preflight();
        if (! ($preflight['ok'] ?? false)) {
            $request->session()->put('accounting_sample_data.preflight', $preflight);
            $request->session()->put('accounting_sample_data.form_values', $service->normalizeGenerationOptions($validated));
            return redirect()
                ->route('admin.accounting.sample-data.index')
                ->with('error', (string) trans('accounting::accounting.sample_data.errors.preflight_generate_locked'));
        }

        try {
            $mode = (string) ($validated['mode'] ?? AccountingSampleDataService::MODE_REBUILD);
            $wipeAllBeforeGenerate = $request->boolean('wipe_all_before_generate');
            $options = $service->normalizeGenerationOptions($validated);
            $summary = $service->runFreshRebuild(null, $wipeAllBeforeGenerate, $options, $mode);
            $request->session()->put('accounting_sample_data.preflight', $service->preflight());
            $request->session()->put('accounting_sample_data.summary', $summary);
            $request->session()->put('accounting_sample_data.form_values', $options);

            return redirect()
                ->route('admin.accounting.sample-data.index')
                ->with('success', trans('accounting::accounting.sample_data.messages.generated'));
        } catch (Throwable $e) {
            report($e);

            $request->session()->put('accounting_sample_data.preflight', $service->preflight());

            return redirect()
                ->route('admin.accounting.sample-data.index')
                ->with('error', $e->getMessage());
        }
    }

    public function wipe(Request $request, AccountingDataWipeService $wipeService, AccountingSampleDataService $sampleService): RedirectResponse
    {
        try {
            if (! $request->boolean('confirm_full_wipe')) {
                throw new \RuntimeException((string) trans('accounting::accounting.sample_data.errors.wipe_confirmation_required'));
            }

            $wipeService->run(WipeOptions::allTables(
                dryRun: false,
                confirmedReset: true
            ));
            $request->session()->put('accounting_sample_data.preflight', $sampleService->preflight());
            $request->session()->forget('accounting_sample_data.summary');

            if ($request->boolean('redirect_to_install')) {
                return redirect()
                    ->route('admin.accounting.install')
                    ->with(
                        'accounting_sample_wiped_notice',
                        (string) trans('accounting::accounting.sample_data.messages.wiped_redirect_wizard')
                    );
            }

            return redirect()
                ->route('admin.accounting.sample-data.index')
                ->with('success', trans('accounting::accounting.sample_data.messages.wiped'));
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.accounting.sample-data.index')
                ->with('error', $e->getMessage());
        }
    }
}

