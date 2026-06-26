<?php

declare(strict_types=1);

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RMS\Accounting\Models\Account;
use RMS\Accounting\Models\Bank;
use RMS\Accounting\Models\Employee;
use RMS\Accounting\Models\PayrollInsuranceSettlement;
use RMS\Accounting\Services\AccountingAttachmentService;
use RMS\Accounting\Services\PayrollInsuranceJournalService;
use RMS\Accounting\Support\AuditActor;
use RMS\Accounting\Support\AccountingDateUi;
use RMS\Core\Models\Setting;

class PayrollInsuranceController extends AccountingAdminController
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
        $plugins = AccountingDateUi::calendarMode() === AccountingDateUi::MODE_JALALI
            ? ['persian-datepicker']
            : [];

        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('payroll_insurance.index')
            ->withPlugins($plugins)
            ->withJs('vendor/accounting/admin/js/payroll-insurance-form.js', true)
            ->withVariables([
                'banks' => Bank::query()->where('active', true)->orderBy('name')->get(),
                'employees' => Employee::query()->where('active', true)->orderBy('name')->get(),
                'missingMappings' => $this->resolveMissingMappings(),
                'settlements' => PayrollInsuranceSettlement::query()
                    ->with(['employee', 'bank', 'manualJournal', 'attachments'])
                    ->orderByDesc('id')
                    ->limit(50)
                    ->get(),
                'attachmentMaxKb' => (int) config('accounting.attachments.max_size_kb', 10240),
                'attachmentMaxPerPayment' => max(1, (int) config('accounting.attachments.max_per_expense', 5)),
            ]);

        return $this->view();
    }

    public function postAccrual(Request $request, PayrollInsuranceJournalService $service): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => 'required|string|max:64',
            'journal_date' => 'required|string|max:64',
            'employee_id' => 'nullable|integer|exists:employees,id',
            'description' => 'nullable|string|max:2000',
        ]);
        $validated['amount'] = $this->normalizePostedAmount((string) $validated['amount']);
        $validated['journal_date'] = $this->normalizePostedAccountingDate($request);

        try {
            $service->recordEmployerInsuranceAccrual($validated);
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('admin.accounting.payroll-insurance.index')
                ->withInput()
                ->withErrors(['payroll_insurance' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.accounting.payroll-insurance.index')
            ->with('success', trans('accounting::accounting.payroll_insurance.flash_accrual_posted'));
    }

    public function postPayment(Request $request, PayrollInsuranceJournalService $service): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => 'required|string|max:64',
            'journal_date' => 'required|string|max:64',
            'bank_id' => 'required|integer|exists:banks,id',
            'employee_id' => 'nullable|integer|exists:employees,id',
            'description' => 'nullable|string|max:2000',
            'mark_as_settled' => 'nullable|boolean',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:' . ((int) config('accounting.attachments.max_size_kb', 10240)),
        ]);
        $validated['amount'] = $this->normalizePostedAmount((string) $validated['amount']);
        $validated['journal_date'] = $this->normalizePostedAccountingDate($request);
        $adminId = AuditActor::adminId();
        $attachmentService = app(AccountingAttachmentService::class);
        $markAsSettled = filter_var($validated['mark_as_settled'] ?? true, FILTER_VALIDATE_BOOLEAN);

        try {
            DB::transaction(function () use ($service, $validated, $markAsSettled, $adminId, $request, $attachmentService): void {
                $journal = $service->recordSocialInsurancePayment($validated);
                $settlement = PayrollInsuranceSettlement::query()->create([
                    'manual_journal_id' => (int) $journal->id,
                    'employee_id' => isset($validated['employee_id']) ? (int) $validated['employee_id'] : null,
                    'bank_id' => (int) $validated['bank_id'],
                    'amount' => (float) $validated['amount'],
                    'journal_date' => (string) $validated['journal_date'],
                    'status' => $markAsSettled ? PayrollInsuranceSettlement::STATUS_SETTLED : PayrollInsuranceSettlement::STATUS_OPEN,
                    'description' => (string) ($validated['description'] ?? ''),
                    'settled_at' => $markAsSettled ? now() : null,
                    'settled_by_user_id' => $markAsSettled ? ($adminId ? (int) $adminId : null) : null,
                    'created_by_user_id' => $adminId ? (int) $adminId : null,
                    'updated_by_user_id' => $adminId ? (int) $adminId : null,
                ]);

                $files = $request->file('attachments', []);
                if (is_array($files) && $files !== []) {
                    $maxFiles = max(1, (int) config('accounting.attachments.max_per_expense', 5));
                    $count = 0;
                    foreach ($files as $file) {
                        if (! $file instanceof \Illuminate\Http\UploadedFile || ! $file->isValid()) {
                            continue;
                        }
                        if ($count >= $maxFiles) {
                            break;
                        }
                        $att = $attachmentService->storeOrphan($file, $adminId ? (int) $adminId : null);
                        $att->attachable()->associate($settlement);
                        $att->save();
                        $count++;
                    }
                }
            });
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('admin.accounting.payroll-insurance.index')
                ->withInput()
                ->withErrors(['payroll_insurance' => trans('accounting::accounting.attachments.' . $e->getMessage())]);
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('admin.accounting.payroll-insurance.index')
                ->withInput()
                ->withErrors(['payroll_insurance' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.accounting.payroll-insurance.index')
            ->with('success', trans('accounting::accounting.payroll_insurance.flash_payment_posted'));
    }

    public function closeSettlement(Request $request, PayrollInsuranceSettlement $settlement): RedirectResponse
    {
        if ((string) $settlement->status === PayrollInsuranceSettlement::STATUS_SETTLED) {
            return redirect()
                ->route('admin.accounting.payroll-insurance.index')
                ->with('warning', trans('accounting::accounting.payroll_insurance.flash_settlement_already_closed'));
        }

        $settlement->update([
            'status' => PayrollInsuranceSettlement::STATUS_SETTLED,
            'settled_at' => now(),
            'settled_by_user_id' => AuditActor::userId(),
            'updated_by_user_id' => AuditActor::userId(),
        ]);

        return redirect()
            ->route('admin.accounting.payroll-insurance.index')
            ->with('success', trans('accounting::accounting.payroll_insurance.flash_settlement_closed'));
    }

    protected function normalizePostedAmount(string $value): float
    {
        $normalized = trim(\RMS\Helper\changeNumberToEn($value));
        $normalized = str_replace(['٬', '،', ','], '', $normalized);
        $normalized = preg_replace('/\s+/', '', $normalized) ?? '';
        if (! is_numeric($normalized)) {
            throw ValidationException::withMessages([
                'amount' => [(string) trans('accounting::accounting.payroll_insurance.invalid_amount')],
            ]);
        }

        $amount = (float) $normalized;
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => [(string) trans('accounting::accounting.payroll_insurance.invalid_amount')],
            ]);
        }

        return $amount;
    }

    /**
     * @return array<int,array{label:string,tag:string,message:string}>
     */
    protected function resolveMissingMappings(): array
    {
        $requirements = [
            'expenses.employer_social_insurance' => [
                'label' => (string) trans('accounting::accounting.settings_payroll.employer_social_insurance_expense'),
                'tag' => 'expenses.employer_social_insurance',
            ],
            'liabilities.social_insurance_payable' => [
                'label' => (string) trans('accounting::accounting.settings_payroll.social_insurance_payable'),
                'tag' => 'liabilities.social_insurance_payable',
            ],
        ];

        $missing = [];
        foreach ($requirements as $relativeKey => $meta) {
            $settingKey = 'accounting.system_accounts.' . $relativeKey;
            $mappedCode = trim((string) Setting::get($settingKey, ''));
            if ($mappedCode === '') {
                $missing[] = [
                    'label' => (string) ($meta['label'] ?? $relativeKey),
                    'tag' => (string) ($meta['tag'] ?? $relativeKey),
                    'message' => (string) trans('accounting::accounting.payroll_insurance.not_set'),
                ];
                continue;
            }

            $exists = Account::query()->where('code', $mappedCode)->exists();
            if (! $exists) {
                $missing[] = [
                    'label' => (string) ($meta['label'] ?? $relativeKey),
                    'tag' => (string) ($meta['tag'] ?? $relativeKey),
                    'message' => (string) trans('accounting::accounting.payroll_insurance.code_not_found', ['code' => $mappedCode]),
                ];
            }
        }

        return $missing;
    }
}
