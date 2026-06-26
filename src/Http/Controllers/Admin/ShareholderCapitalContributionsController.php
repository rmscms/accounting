<?php

declare(strict_types=1);

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RMS\Accounting\Models\Bank;
use RMS\Accounting\Models\CashBox;
use RMS\Accounting\Models\Shareholder;
use RMS\Accounting\Models\ShareholderCapitalContribution;
use RMS\Accounting\Http\Controllers\Admin\Concerns\ParsesAccountingMoneyInput;
use RMS\Accounting\Services\ShareholderCapitalContributionService;
use RMS\Accounting\Support\AccountingDateUi;

class ShareholderCapitalContributionsController extends AccountingAdminController
{
    use ParsesAccountingMoneyInput;

    public function table(): string
    {
        return 'shareholder_capital_contributions';
    }

    public function modelName(): string
    {
        return ShareholderCapitalContribution::class;
    }

    public function index(Request $request)
    {
        $summary = ShareholderCapitalContribution::query()
            ->select('shareholder_id', DB::raw('SUM(amount) as total_amount'))
            ->groupBy('shareholder_id')
            ->get()
            ->keyBy('shareholder_id');

        $shareholderModels = Shareholder::query()->with('capitalAccount')->get()->keyBy('id');

        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('shareholder_capital_contributions.index')
            ->withVariables([
                'contributions' => ShareholderCapitalContribution::query()
                    ->with('shareholder')
                    ->orderByDesc('id')
                    ->paginate(25),
                'summary' => $summary,
                'shareholderModels' => $shareholderModels,
            ]);

        return $this->view();
    }

    public function create(Request $request)
    {
        $plugins = [];
        if (AccountingDateUi::calendarMode() === AccountingDateUi::MODE_JALALI) {
            $plugins[] = 'persian-datepicker';
        }
        $plugins[] = 'amount-formatter';

        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('shareholder_capital_contributions.form')
            ->withPlugins($plugins)
            ->withVariables([
                'shareholders' => Shareholder::query()->where('active', true)->orderBy('name')->get(),
                'banks' => Bank::query()->where('active', true)->orderBy('name')->get(),
                'cashBoxes' => CashBox::query()->where('active', true)->orderBy('name')->get(),
                'baseCurrencyCode' => $this->resolveDefaultCurrencyCode(),
                'amountDecimalPlaces' => $this->resolveAccountingAmountDecimalPlaces(),
            ]);

        return $this->view();
    }

    public function recordContribution(Request $request, ShareholderCapitalContributionService $service): RedirectResponse
    {
        $this->mergeParsedDecimalFields($request, ['amount']);
        $validated = $request->validate([
            'shareholder_id' => 'required|integer|exists:shareholders,id',
            'amount' => 'required|numeric|min:0.0001',
            'journal_date' => 'required|string|max:64',
            'source_type' => 'required|in:bank,cash',
            'bank_id' => 'required_if:source_type,bank|nullable|integer|exists:banks,id',
            'cash_box_id' => 'required_if:source_type,cash|nullable|integer|exists:cash_boxes,id',
            'description' => 'nullable|string|max:2000',
        ]);
        $validated['journal_date'] = $this->normalizePostedAccountingDate($request);

        $service->record($validated);

        return redirect()
            ->route('admin.accounting.shareholder-capital-contributions.index')
            ->with('success', trans('accounting::accounting.capital.flash_recorded'));
    }
}
