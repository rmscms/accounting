<?php

declare(strict_types=1);

namespace RMS\Accounting\Services;

use Illuminate\Database\QueryException;
use RMS\Accounting\Models\Account;
use RMS\Core\Models\Setting;

class TreasurySubAccountProvisioningService
{
    public const TYPE_BANK = 'bank';
    public const TYPE_CASHBOX = 'cashbox';
    public const SETTING_BANK_PARENT_ACCOUNT_CODE = 'accounting.treasury.bank_parent_account_code';
    public const SETTING_CASHBOX_PARENT_ACCOUNT_CODE = 'accounting.treasury.cashbox_parent_account_code';

    public function provisionFor(string $treasuryType, string $accountName): Account
    {
        $resolvedName = trim($accountName);
        if ($resolvedName === '') {
            throw new \RuntimeException((string) trans('accounting::accounting.treasury_sub_accounts.errors.name_required'));
        }

        $parent = $this->resolveParentAccount($treasuryType);

        $existing = Account::query()
            ->where('parent_id', $parent->id)
            ->where('name', $resolvedName)
            ->first();
        if ($existing instanceof Account) {
            return $existing;
        }

        $nextSuffix = $this->detectNextNumericSuffix($parent);
        $maxRetries = 25;

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            $suffix = $nextSuffix + $attempt;
            $code = $this->buildChildCode((string) $parent->code, $suffix);

            try {
                return Account::create([
                    'code' => $code,
                    'name' => $resolvedName,
                    'level' => max(1, ((int) $parent->level) + 1),
                    'parent_id' => $parent->id,
                    'account_type' => (string) ($parent->account_type ?: Account::TYPE_ASSET),
                    'is_system' => false,
                    'active' => true,
                ]);
            } catch (QueryException $e) {
                if ($this->isDuplicateCodeException($e)) {
                    continue;
                }

                throw $e;
            }
        }

        throw new \RuntimeException((string) trans('accounting::accounting.treasury_sub_accounts.errors.code_allocation_failed'));
    }

    public function getParentSettingKey(string $treasuryType): string
    {
        return match ($treasuryType) {
            self::TYPE_BANK => self::SETTING_BANK_PARENT_ACCOUNT_CODE,
            self::TYPE_CASHBOX => self::SETTING_CASHBOX_PARENT_ACCOUNT_CODE,
            default => throw new \InvalidArgumentException('Unsupported treasury type: ' . $treasuryType),
        };
    }

    public function resolveParentAccount(string $treasuryType): Account
    {
        $settingKey = $this->getParentSettingKey($treasuryType);
        $parentCode = trim((string) Setting::get($settingKey, ''));
        $treasuryLabel = $treasuryType === self::TYPE_BANK
            ? (string) trans('accounting::accounting.treasury_sub_accounts.types.bank')
            : (string) trans('accounting::accounting.treasury_sub_accounts.types.cashbox');

        if ($parentCode === '') {
            throw new \RuntimeException((string) trans(
                'accounting::accounting.treasury_sub_accounts.errors.parent_setting_required',
                ['type' => $treasuryLabel]
            ));
        }

        $parent = Account::query()
            ->where('code', $parentCode)
            ->where('active', true)
            ->where('account_type', Account::TYPE_ASSET)
            ->first();
        if (! $parent instanceof Account) {
            throw new \RuntimeException((string) trans(
                'accounting::accounting.treasury_sub_accounts.errors.parent_invalid',
                ['type' => $treasuryLabel, 'code' => $parentCode]
            ));
        }

        return $parent;
    }

    protected function detectNextNumericSuffix(Account $parent): int
    {
        $prefix = (string) $parent->code . '-';
        $codes = Account::query()
            ->where('parent_id', $parent->id)
            ->where('code', 'like', $prefix . '%')
            ->pluck('code');

        $max = 0;
        $pattern = '/^' . preg_quote((string) $parent->code, '/') . '\-(\d+)$/';
        foreach ($codes as $code) {
            $codeValue = (string) $code;
            if (preg_match($pattern, $codeValue, $matches) === 1) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return max(1, $max + 1);
    }

    protected function buildChildCode(string $parentCode, int $suffix): string
    {
        return $parentCode . '-' . str_pad((string) $suffix, 2, '0', STR_PAD_LEFT);
    }

    protected function isDuplicateCodeException(QueryException $exception): bool
    {
        $message = strtolower((string) $exception->getMessage());

        return str_contains($message, 'accounts.code')
            && (str_contains($message, 'duplicate') || str_contains($message, 'unique'));
    }
}

