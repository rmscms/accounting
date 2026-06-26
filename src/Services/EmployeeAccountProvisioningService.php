<?php

declare(strict_types=1);

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\Account;
use RMS\Accounting\Models\Employee;

class EmployeeAccountProvisioningService
{
    public function __construct(
        protected SystemAccountLocator $systemAccountLocator
    ) {}

    /**
     * زیرحساب هزینهٔ حقوق per کارمند زیر 5201 و بدهی حقوق پرداختنی زیر 2103.
     */
    public function ensureAccounts(Employee $employee): void
    {
        $wagesExpenseParentCode = (string) config('accounting.payroll.wages_expense_parent_code', '5201');
        $wagesExpenseParent = $this->systemAccountLocator->accountByCode($wagesExpenseParentCode)
            ?? $this->systemAccountLocator->accountByCode('5-2-1');

        $wagesPayableParent = $this->systemAccountLocator->accountBySystemKey('liabilities.wages_payable')
            ?? $this->systemAccountLocator->accountByCode('2103')
            ?? $this->systemAccountLocator->accountByCode('2-1-4');

        if (! $wagesExpenseParent || ! $wagesPayableParent) {
            throw new \RuntimeException('Payroll parent accounts (wages expense / wages payable) missing from chart.');
        }

        if (! $employee->payroll_expense_account_id) {
            $employee->payroll_expense_account_id = $this->createChildAccount(
                parent: $wagesExpenseParent,
                name: $employee->name.' — حقوق',
                suffix: 'WEXP',
                entityId: $employee->id,
                accountType: Account::TYPE_EXPENSE,
            )->id;
        }

        if (! $employee->wages_payable_account_id) {
            $employee->wages_payable_account_id = $this->createChildAccount(
                parent: $wagesPayableParent,
                name: $employee->name.' — حقوق پرداختنی',
                suffix: 'WPAY',
                entityId: $employee->id,
                accountType: Account::TYPE_LIABILITY,
            )->id;
        }

        $employee->save();
    }

    protected function createChildAccount(
        Account $parent,
        string $name,
        string $suffix,
        int $entityId,
        string $accountType,
    ): Account {
        $base = preg_replace('/[^A-Za-z0-9]/', '', (string) $parent->code) ?: 'P';
        $code = $base.'-'.$suffix.'-'.str_pad((string) $entityId, 5, '0', STR_PAD_LEFT);
        $existing = Account::query()->where('code', $code)->first();
        if ($existing) {
            return $existing;
        }

        return Account::create([
            'code' => $code,
            'name' => $name,
            'level' => min(9, (int) $parent->level + 1),
            'parent_id' => $parent->id,
            'account_type' => $accountType,
            'is_system' => false,
            'active' => true,
        ]);
    }
}
