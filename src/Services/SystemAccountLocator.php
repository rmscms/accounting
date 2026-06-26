<?php

declare(strict_types=1);

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\Account;
use RMS\Core\Models\Setting;

/**
 * حل کد/رکورد حساب سیستمی از settings (پس از نصب) یا config.
 */
class SystemAccountLocator
{
    /**
     * @param  string  $relative  مثل equity.capital → کلید setting accounting.system_accounts.equity.capital
     */
    public function accountBySystemKey(string $relative): ?Account
    {
        $fullKey = 'accounting.system_accounts.'.$relative;
        $code = Setting::get($fullKey);
        if (! $code || $code === '') {
            $code = config($fullKey);
        }
        if (! $code || $code === '') {
            $code = $this->defaultSystemAccountCode($relative);
        }
        if (! $code || $code === '') {
            return null;
        }

        return Account::query()->where('code', (string) $code)->first();
    }

    public function accountBySystemKeyOrFail(string $relative): Account
    {
        $account = $this->accountBySystemKey($relative);
        if (! $account) {
            throw new \RuntimeException('Accounting system account not found or not mapped: '.$relative);
        }

        return $account;
    }

    public function accountByCode(string $code): ?Account
    {
        return Account::query()->where('code', $code)->first();
    }

    public function accountByCodeOrFail(string $code): Account
    {
        $account = $this->accountByCode($code);
        if (! $account) {
            throw new \RuntimeException('Accounting account code not found: '.$code);
        }

        return $account;
    }

    protected function defaultSystemAccountCode(string $relative): ?string
    {
        $defaults = [
            'expenses.payroll_seniority' => '5211',
            'liabilities.payroll_seniority_reserve' => '2109',
        ];

        return $defaults[$relative] ?? null;
    }
}
