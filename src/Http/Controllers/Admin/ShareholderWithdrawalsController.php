<?php

declare(strict_types=1);

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RMS\Accounting\Models\Bank;
use RMS\Accounting\Models\CashBox;
use RMS\Accounting\Models\Shareholder;
use RMS\Accounting\Models\ShareholderWithdrawal;
use RMS\Accounting\Http\Controllers\Admin\Concerns\ParsesAccountingMoneyInput;
use RMS\Accounting\Services\ShareholderWithdrawalService;
use RMS\Accounting\Support\AccountingDateUi;

class ShareholderWithdrawalsController extends AccountingAdminController
{
    use ParsesAccountingMoneyInput;

    public function table(): string
    {
        return 'shareholder_withdrawals';
    }

    public function modelName(): string
    {
        return ShareholderWithdrawal::class;
    }

    public function index(Request $request)
    {
        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('shareholder_withdrawals.index')
            ->withVariables([
                'withdrawals' => ShareholderWithdrawal::query()
                    ->with(['shareholder', 'manualJournal'])
                    ->orderByDesc('id')
                    ->paginate(25),
            ]);

        return $this->view();
    }

    public function create(Request $request)
    {
        $plugins = AccountingDateUi::calendarMode() === AccountingDateUi::MODE_JALALI
            ? ['persian-datepicker']
            : [];

        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('shareholder_withdrawals.form')
            ->withPlugins($plugins)
            ->withJs('vendor/accounting/admin/js/shareholder-withdrawals-form.js', true)
            ->withVariables([
                'shareholders' => Shareholder::query()->where('active', true)->orderBy('name')->get(),
                'banks' => Bank::query()->where('active', true)->orderBy('name')->get(),
                'cashBoxes' => CashBox::query()->where('active', true)->orderBy('name')->get(),
                'baseCurrencyCode' => $this->resolveDefaultCurrencyCode(),
                'amountDecimalPlaces' => $this->resolveAccountingAmountDecimalPlaces(),
            ]);

        return $this->view();
    }

    public function recordWithdrawal(Request $request, ShareholderWithdrawalService $service): RedirectResponse
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
            'submit_mode' => 'nullable|in:draft,post',
        ]);
        $validated['journal_date'] = $this->normalizePostedAccountingDate($request);
        $validated['post_journal'] = (string) ($validated['submit_mode'] ?? 'post') !== 'draft';

        $withdrawal = $service->record($validated);

        return redirect()
            ->route('admin.accounting.shareholder-withdrawals.index')
            ->with('success', $withdrawal->manualJournal && $withdrawal->manualJournal->status === 'draft'
                ? trans('accounting::accounting.withdrawals.flash_saved_draft')
                : trans('accounting::accounting.withdrawals.flash_recorded'));
    }

    public function postDraft(Request $request, int|string $withdrawal, ShareholderWithdrawalService $service): RedirectResponse
    {
        $record = ShareholderWithdrawal::query()->with('manualJournal')->findOrFail((int) $withdrawal);
        $journalId = (int) ($record->manual_journal_id ?? 0);
        if ($journalId < 1) {
            return redirect()
                ->route('admin.accounting.shareholder-withdrawals.index')
                ->with('error', trans('accounting::accounting.withdrawals.error_no_journal'));
        }

        try {
            $service->postDraftByWithdrawal($record);
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.accounting.shareholder-withdrawals.index')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.accounting.shareholder-withdrawals.index')
            ->with('success', trans('accounting::accounting.withdrawals.flash_posted'));
    }
}
