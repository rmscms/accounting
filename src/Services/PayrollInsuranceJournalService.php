<?php

declare(strict_types=1);

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\Bank;
use RMS\Accounting\Models\Employee;
use RMS\Accounting\Models\ManualJournal;
use RMS\Core\Models\Setting;

/**
 * ثبت سندهای حداقلی بیمهٔ تأمین اجتماعی (سهم کارفرما): تشخیص هزینه/بدهی و پرداخت به سازمان.
 * این سرویس فقط از تنظیمات کاربر (settings) استفاده می‌کند و fallback به config/codes ندارد.
 */
class PayrollInsuranceJournalService
{
    public function __construct(
        protected ManualJournalService $manualJournalService,
        protected SystemAccountLocator $systemAccountLocator,
    ) {}

    /**
     * تشخیص سهم کارفرما: بدهکار هزینهٔ بیمه کارفرما، بستانکار پرداختنی تأمین.
     *
     * @param  array{amount:float|int|string,journal_date:string,employee_id?:?int,description?:?string,currency_code?:string}  $data
     */
    public function recordEmployerInsuranceAccrual(array $data): ManualJournal
    {
        $amount = (float) $data['amount'];
        if ($amount <= 0) {
            throw new \InvalidArgumentException('amount must be positive.');
        }

        $expense = $this->resolveMappedAccountFromSettings('expenses.employer_social_insurance');
        $payable = $this->resolveMappedAccountFromSettings('liabilities.social_insurance_payable');

        $currency = $this->normalizeLedgerCurrency((string) ($data['currency_code'] ?? 'IRR'));
        $description = $this->resolveJournalDescription(
            $data,
            (string) trans('accounting::accounting.payroll_insurance.accrual_default_description')
        );

        $journal = $this->manualJournalService->createJournal([
            'journal_date' => $data['journal_date'],
            'description' => $description,
            'lines' => [
                [
                    'account_id' => $expense->id,
                    'debit_amount' => $amount,
                    'credit_amount' => 0,
                    'currency_code' => $currency,
                    'description' => $description,
                ],
                [
                    'account_id' => $payable->id,
                    'debit_amount' => 0,
                    'credit_amount' => $amount,
                    'currency_code' => $currency,
                    'description' => $description,
                ],
            ],
        ]);

        return $this->manualJournalService->postJournal($journal->id);
    }

    /**
     * پرداخت به سازمان تأمین: بدهکار پرداختنی تأمین، بستانکار بانک.
     *
     * @param  array{amount:float|int|string,journal_date:string,bank_id:int,employee_id?:?int,description?:?string,currency_code?:string}  $data
     */
    public function recordSocialInsurancePayment(array $data): ManualJournal
    {
        $amount = (float) $data['amount'];
        if ($amount <= 0) {
            throw new \InvalidArgumentException('amount must be positive.');
        }

        $bankId = (int) ($data['bank_id'] ?? 0);
        if ($bankId < 1) {
            throw new \InvalidArgumentException('bank_id is required.');
        }

        $bank = Bank::query()->findOrFail($bankId);
        if (! $bank->account_id) {
            throw new \RuntimeException('Bank has no linked GL account.');
        }

        $payable = $this->resolveMappedAccountFromSettings('liabilities.social_insurance_payable');

        $currency = $this->normalizeLedgerCurrency((string) ($data['currency_code'] ?? ($bank->currency_code ? (string) $bank->currency_code : 'IRR')));
        $description = $this->resolveJournalDescription(
            $data,
            (string) trans('accounting::accounting.payroll_insurance.payment_default_description')
        );

        $journal = $this->manualJournalService->createJournal([
            'journal_date' => $data['journal_date'],
            'description' => $description,
            'lines' => [
                [
                    'account_id' => $payable->id,
                    'debit_amount' => $amount,
                    'credit_amount' => 0,
                    'currency_code' => $currency,
                    'description' => $description,
                ],
                [
                    'account_id' => (int) $bank->account_id,
                    'debit_amount' => 0,
                    'credit_amount' => $amount,
                    'currency_code' => $currency,
                    'description' => $description,
                ],
            ],
        ]);

        return $this->manualJournalService->postJournal($journal->id);
    }

    protected function normalizeLedgerCurrency(string $code): string
    {
        return $code === 'IRT' ? 'IRR' : $code;
    }

    protected function resolveMappedAccountFromSettings(string $relativeKey): \RMS\Accounting\Models\Account
    {
        $settingKey = 'accounting.system_accounts.' . $relativeKey;
        $mappedCode = trim((string) Setting::get($settingKey, ''));
        if ($mappedCode === '') {
            throw new \RuntimeException((string) trans('accounting::accounting.payroll_insurance.error_account_not_mapped'));
        }

        $account = $this->systemAccountLocator->accountByCode($mappedCode);
        if (! $account) {
            throw new \RuntimeException((string) trans('accounting::accounting.payroll_insurance.error_mapped_account_missing', [
                'code' => $mappedCode,
            ]));
        }

        return $account;
    }

    /**
     * @param  array<string,mixed>  $data
     */
    protected function resolveJournalDescription(array $data, string $default): string
    {
        $description = trim((string) ($data['description'] ?? ''));
        if ($description !== '') {
            return $description;
        }

        $employeeId = (int) ($data['employee_id'] ?? 0);
        if ($employeeId > 0) {
            $employeeName = (string) (Employee::query()->find($employeeId)?->name ?? '');
            if ($employeeName !== '') {
                return $default . ' - ' . $employeeName;
            }
        }

        return $default;
    }
}
