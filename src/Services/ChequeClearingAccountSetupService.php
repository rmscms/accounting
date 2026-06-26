<?php

namespace RMS\Accounting\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RMS\Accounting\Models\Account;

/**
 * ایجاد حساب‌های انتظامی چک (در صورت نبود) و درج شناسه‌ها در .env
 */
class ChequeClearingAccountSetupService
{
    /**
     * @return array{
     *   receivable_account_id: int,
     *   payable_account_id: int,
     *   receivable_created: bool,
     *   payable_created: bool,
     *   env_written: bool,
     *   env_error: string|null
     * }
     */
    public function run(bool $writeEnv = true): array
    {
        return DB::transaction(function () use ($writeEnv) {
            $arCode = (string) config('accounting.system_accounts.assets.cheques_receivable_clearing', '1-125');
            $apCode = (string) config('accounting.system_accounts.liabilities.cheques_payable_clearing', '2-215');

            $ar = $this->ensureAccount(
                code: $arCode,
                name: trans('accounting::accounting.setup.cheque_clearing.receivable_name'),
                accountType: Account::TYPE_ASSET,
                parentResolver: fn () => $this->resolveAssetParentId()
            );

            $ap = $this->ensureAccount(
                code: $apCode,
                name: trans('accounting::accounting.setup.cheque_clearing.payable_name'),
                accountType: Account::TYPE_LIABILITY,
                parentResolver: fn () => $this->resolveLiabilityParentId()
            );

            $envError = null;
            $envWritten = false;
            if ($writeEnv) {
                try {
                    $this->writeEnvIds((int) $ar['account']->id, (int) $ap['account']->id);
                    $envWritten = true;
                    if (app()->runningInConsole() === false) {
                        try {
                            Artisan::call('config:clear');
                        } catch (\Throwable $e) {
                            // ignore
                        }
                    }
                } catch (\Throwable $e) {
                    $envError = $e->getMessage();
                }
            }

            return [
                'receivable_account_id' => (int) $ar['account']->id,
                'payable_account_id' => (int) $ap['account']->id,
                'receivable_created' => $ar['created'],
                'payable_created' => $ap['created'],
                'env_written' => $envWritten && $envError === null,
                'env_error' => $envError,
            ];
        });
    }

    /**
     * @return array{account: Account, created: bool}
     */
    protected function ensureAccount(string $code, string $name, string $accountType, callable $parentResolver): array
    {
        $existing = Account::withTrashed()->where('code', $code)->first();
        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            return ['account' => $existing, 'created' => false];
        }

        $parentId = $parentResolver();
        $parent = $parentId ? Account::find($parentId) : null;
        $level = $parent ? min(3, (int) $parent->level + 1) : 2;

        $account = Account::create([
            'code' => $code,
            'name' => $name,
            'level' => $level,
            'parent_id' => $parentId,
            'account_type' => $accountType,
            'is_system' => true,
            'currency_code' => null,
            'active' => true,
            'description' => trans('accounting::accounting.setup.cheque_clearing.account_description'),
        ]);

        return ['account' => $account, 'created' => true];
    }

    protected function resolveAssetParentId(): ?int
    {
        foreach (['1100', '1-100', '1000'] as $code) {
            $id = (int) Account::query()->where('code', $code)->value('id');
            if ($id > 0) {
                return $id;
            }
        }

        return Account::query()
            ->where('account_type', Account::TYPE_ASSET)
            ->whereIn('level', [1, 2])
            ->orderByDesc('level')
            ->value('id');
    }

    protected function resolveLiabilityParentId(): ?int
    {
        foreach (['2100', '2-100', '2000'] as $code) {
            $id = (int) Account::query()->where('code', $code)->value('id');
            if ($id > 0) {
                return $id;
            }
        }

        return Account::query()
            ->where('account_type', Account::TYPE_LIABILITY)
            ->whereIn('level', [1, 2])
            ->orderByDesc('level')
            ->value('id');
    }

    protected function writeEnvIds(int $receivableId, int $payableId): void
    {
        $path = base_path('.env');
        if (! is_file($path)) {
            throw new \RuntimeException(trans('accounting::accounting.setup.cheque_clearing.env_missing_file'));
        }
        if (! is_writable($path)) {
            throw new \RuntimeException(trans('accounting::accounting.setup.cheque_clearing.env_not_writable'));
        }

        $content = (string) file_get_contents($path);
        $pairs = [
            'ACCOUNTING_ACC_CHEQUE_AR_CLEARING_ID' => (string) $receivableId,
            'ACCOUNTING_ACC_CHEQUE_AP_CLEARING_ID' => (string) $payableId,
        ];

        foreach ($pairs as $key => $value) {
            $content = $this->replaceOrAppendEnvLine($content, $key, $value);
        }

        if (file_put_contents($path, $content) === false) {
            throw new \RuntimeException(trans('accounting::accounting.setup.cheque_clearing.env_write_failed'));
        }
    }

    protected function replaceOrAppendEnvLine(string $content, string $key, string $value): string
    {
        $line = "{$key}={$value}";
        $pattern = '/^' . preg_quote($key, '/') . '=.*/m';
        if (preg_match($pattern, $content)) {
            return (string) preg_replace($pattern, $line, $content);
        }

        return rtrim($content) . "\n" . $line . "\n";
    }
}
