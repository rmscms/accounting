<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use RMS\Accounting\Http\Controllers\Admin\Concerns\RendersAccountingStructuredResourceForm;
use RMS\Accounting\Models\Account;
use RMS\Accounting\Models\Currency;
use RMS\Accounting\Models\Wallet;
use RMS\Accounting\Models\WalletTransaction;
use RMS\Core\Contracts\Actions\ChangeBoolField;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Data\Field;
use RMS\Core\Models\User;
use Illuminate\Validation\Rule;

class WalletsController extends AccountingAdminController implements
    HasList,
    HasForm,
    ShouldFilter,
    ChangeBoolField
{
    use RendersAccountingStructuredResourceForm;

    public function table(): string
    {
        return 'wallets';
    }

    public function modelName(): string
    {
        return Wallet::class;
    }

    public function baseRoute(): string
    {
        return 'accounting.wallets';
    }

    public function routeParameter(): string
    {
        return 'wallet';
    }

    public function getFieldsForm(): array
    {
        return [
            Field::select('wallet_type', trans('accounting::accounting.wallets.fields.wallet_type'))
                ->setOptions($this->getWalletTypeOptions())
                ->required(),

            Field::select('user_id', trans('accounting::accounting.wallets.fields.user_id'))
                ->setOptions($this->getUserOptions())
                ->optional(),

            Field::select('currency_code', trans('accounting::accounting.wallets.fields.currency_code'))
                ->setOptions($this->getCurrencyOptions())
                ->required(),

            Field::select('account_id', trans('accounting::accounting.wallets.fields.account_id'))
                ->setOptions($this->getWalletAccountOptions())
                ->required(),

            Field::boolean('active', trans('accounting::accounting.fields.active'))
                ->withDefaultValue(true),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle('ID')->sortable()->width('80px'),
            Field::make('user_id')->withTitle(trans('accounting::accounting.wallets.fields.user_id'))->sortable()->width('120px'),
            Field::make('wallet_type')->withTitle(trans('accounting::accounting.wallets.fields.wallet_type'))->sortable()->width('130px'),
            Field::make('currency_code')->withTitle(trans('accounting::accounting.wallets.fields.currency_code'))->sortable()->width('110px'),
            Field::make('account_id')->withTitle(trans('accounting::accounting.wallets.fields.account_id'))->sortable()->width('120px'),
            Field::number('balance')->withTitle(trans('accounting::accounting.fields.balance'))->sortable()->width('150px'),
            Field::boolean('active')->withTitle(trans('accounting::accounting.fields.status'))->sortable()->width('100px'),
            Field::date('created_at')->withTitle(trans('accounting::accounting.fields.created_at'))->sortable()->width('150px'),
        ];
    }

    public function rules(): array
    {
        return [
            'wallet_type' => ['required', 'string', Rule::in([
                Wallet::TYPE_CUSTOMER,
                Wallet::TYPE_SUPPLIER,
                Wallet::TYPE_EMPLOYEE,
                Wallet::TYPE_TREASURY,
            ])],
            'user_id' => $this->userIdRules(),
            'currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'active' => ['boolean'],
        ];
    }

    public function boolFields(): array
    {
        return ['active'];
    }

    /**
     * @return array<string, string>
     */
    protected function getWalletTypeOptions(): array
    {
        return [
            Wallet::TYPE_CUSTOMER => trans('accounting::accounting.wallets.types.customer'),
            Wallet::TYPE_SUPPLIER => trans('accounting::accounting.wallets.types.supplier'),
            Wallet::TYPE_EMPLOYEE => trans('accounting::accounting.wallets.types.employee'),
            Wallet::TYPE_TREASURY => trans('accounting::accounting.wallets.types.treasury'),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function getUserOptions(): array
    {
        return User::query()
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'email'])
            ->mapWithKeys(fn (User $u) => [
                $u->id => trim((string) $u->name) !== ''
                    ? ((string) $u->name . ' (#' . $u->id . ')')
                    : ((string) ($u->email ?? ('#' . $u->id))),
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function getCurrencyOptions(): array
    {
        return Currency::query()
            ->where('active', true)
            ->orderBy('code')
            ->get(['code', 'name'])
            ->mapWithKeys(fn (Currency $currency) => [
                strtoupper((string) $currency->code) => strtoupper((string) $currency->code) . ' — ' . (string) ($currency->name ?? $currency->code),
            ])
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    protected function getWalletAccountOptions(): array
    {
        return Account::query()
            ->where('active', true)
            ->orderBy('code')
            ->limit(500)
            ->get(['id', 'code', 'name'])
            ->mapWithKeys(fn (Account $account) => [$account->id => $account->code . ' — ' . $account->name])
            ->all();
    }

    public function beforeDelete(\Illuminate\Http\Request &$request, string|int $id): void
    {
        $wallet = Wallet::query()->findOrFail($id);

        if (WalletTransaction::query()->where('wallet_id', (int) $wallet->id)->exists()) {
            throw new \Exception(trans('accounting::accounting.wallets.delete_has_transactions'));
        }
    }

    protected function userIdRules(): array
    {
        $walletType = (string) request()->input('wallet_type');
        $rules = ['nullable', 'integer', 'exists:users,id'];
        if ($walletType === Wallet::TYPE_TREASURY) {
            return $rules;
        }

        $routeWallet = request()->route('wallet');
        $walletId = is_object($routeWallet) && method_exists($routeWallet, 'getKey')
            ? (int) $routeWallet->getKey()
            : ((int) $routeWallet ?: null);

        return [
            'required',
            'integer',
            'exists:users,id',
            Rule::unique('wallets', 'user_id')
                ->where(fn ($query) => $query->where('wallet_type', $walletType))
                ->ignore($walletId),
        ];
    }
}
