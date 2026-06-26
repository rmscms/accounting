<?php

declare(strict_types=1);

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\Account;
use RMS\Accounting\Models\Shareholder;

class ShareholderAccountProvisioningService
{
    public function __construct(
        protected SystemAccountLocator $systemAccountLocator
    ) {}

    /**
     * زیرحساب سرمایه و برداشت per سهامدار زیر 3100 و 3300 (یا مقادیر نگاشت‌شده در settings).
     */
    public function ensureAccounts(Shareholder $shareholder): void
    {
        $capitalParent = $this->systemAccountLocator->accountBySystemKey('equity.capital')
            ?? $this->systemAccountLocator->accountByCode('3100');
        $drawingsParent = $this->systemAccountLocator->accountBySystemKey('equity.shareholder_drawings')
            ?? $this->systemAccountLocator->accountByCode('3300');

        if (! $capitalParent || ! $drawingsParent) {
            throw new \RuntimeException('Capital or shareholder drawings parent account missing from chart.');
        }

        if (! $shareholder->capital_account_id) {
            $shareholder->capital_account_id = $this->createChildAccount(
                parent: $capitalParent,
                name: $shareholder->name.' — سرمایه',
                suffix: 'CAP',
                shareholderId: $shareholder->id,
                accountType: Account::TYPE_EQUITY,
            )->id;
        }

        if (! $shareholder->drawings_account_id) {
            $shareholder->drawings_account_id = $this->createChildAccount(
                parent: $drawingsParent,
                name: $shareholder->name.' — برداشت',
                suffix: 'DRW',
                shareholderId: $shareholder->id,
                accountType: Account::TYPE_EQUITY,
            )->id;
        }

        $shareholder->save();
    }

    protected function createChildAccount(
        Account $parent,
        string $name,
        string $suffix,
        int $shareholderId,
        string $accountType,
    ): Account {
        $base = preg_replace('/[^A-Za-z0-9]/', '', (string) $parent->code) ?: 'P';
        $code = $base.'-'.$suffix.'-'.str_pad((string) $shareholderId, 5, '0', STR_PAD_LEFT);
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
