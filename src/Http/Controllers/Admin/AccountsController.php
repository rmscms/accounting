<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use RMS\Accounting\Models\Account;
use RMS\Accounting\Models\Currency;
use Illuminate\Http\Request;
use RMS\Core\Data\Field;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\Actions\ChangeBoolField;

class AccountsController extends AccountingAdminController implements HasList, HasForm, ShouldFilter, ChangeBoolField
{
    public function table(): string
    {
        return 'accounts';
    }

    public function modelName(): string
    {
        return Account::class;
    }

    public function baseRoute(): string
    {
        return 'accounting.accounts';
    }

    public function routeParameter(): string
    {
        return 'account';
    }

    public function getFieldsForm(): array
    {
        // دریافت لیست حساب‌های والد برای select
        $parentAccounts = Account::where('active', true)
            ->orderBy('code')
            ->get()
            ->mapWithKeys(function ($account) {
                return [$account->id => $account->code . ' - ' . $account->name];
            })
            ->toArray();

        return [
            Field::string('code', trans('accounting::accounting.account.code'))->required(),
            Field::string('name', trans('accounting::accounting.account.name'))->required(),
            Field::select('account_type', trans('accounting::accounting.account.account_type'))
                ->setOptions([
                    'asset' => trans('accounting::accounting.account_types.asset'),
                    'liability' => trans('accounting::accounting.account_types.liability'),
                    'equity' => trans('accounting::accounting.account_types.equity'),
                    'revenue' => trans('accounting::accounting.account_types.revenue'),
                    'expense' => trans('accounting::accounting.account_types.expense'),
                ])
                ->required(),
            Field::select('parent_id', trans('accounting::accounting.account.parent'))
                ->setOptions(['' => trans('accounting::accounting.common.none')] + $parentAccounts)
                ->optional(),
            Field::number('level', trans('accounting::accounting.account.level'))->withDefaultValue(1)->required(),
            Field::boolean('active', trans('accounting::accounting.account.active'))->withDefaultValue(true),
            Field::string('currency_code', trans('accounting::accounting.account.currency_code'))
                ->withDefaultValue(Currency::resolveBaseCurrencyCode('IRR'))
                ->required(),
            Field::textarea('description', trans('accounting::accounting.account.description'))->optional(),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle(trans('accounting::accounting.common.id'))->sortable()->width('80px'),
            Field::make('code')->withTitle(trans('accounting::accounting.account.code'))->searchable()->sortable()->width('120px'),
            Field::make('name')->withTitle(trans('accounting::accounting.account.name'))->searchable()->sortable(),
            Field::make('account_type')->withTitle(trans('accounting::accounting.account.account_type'))->sortable()->width('120px'),
            Field::make('level')->withTitle(trans('accounting::accounting.account.level'))->sortable()->width('80px'),
            Field::boolean('active')->withTitle(trans('accounting::accounting.account.active'))->sortable()->width('100px'),
        ];
    }

    public function filters(): array
    {
        return [
            Field::select('account_type', trans('accounting::accounting.account.account_type'))
                ->setOptions([
                    '' => trans('accounting::accounting.common.all'),
                    'asset' => trans('accounting::accounting.account_types.asset'),
                    'liability' => trans('accounting::accounting.account_types.liability'),
                    'equity' => trans('accounting::accounting.account_types.equity'),
                    'revenue' => trans('accounting::accounting.account_types.revenue'),
                    'expense' => trans('accounting::accounting.account_types.expense'),
                ]),
            Field::select('active', trans('accounting::accounting.account.active'))
                ->setOptions([
                    '' => trans('accounting::accounting.common.all'),
                    '1' => trans('accounting::accounting.common.active'),
                    '0' => trans('accounting::accounting.common.inactive'),
                ]),
        ];
    }

    /**
     * صفحه درخت حساب‌ها (Custom Page)
     */
    public function tree(Request $request)
    {
        $accounts = Account::with('parent', 'children')
            ->whereNull('parent_id')
            ->orderBy('code')
            ->get();

        return $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('accounts.tree')
            ->withPlugins(['jstree'])
            ->withCss('vendor/accounting/admin/css/accounts-tree.css', true)
            ->withJs('vendor/accounting/admin/js/accounts-tree.js', true)
            ->with([
                'accounts' => $accounts,
            ]);
    }

    /**
     * صفحه صورت حساب (Custom Page)
     */
    public function statement(Request $request, int $id)
    {
        $account = Account::findOrFail($id);
        
        // TODO: دریافت تراکنش‌های حساب از LedgerService
        
        return $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('accounts.statement')
            ->withCss('vendor/accounting/admin/css/account-statement.css', true)
            ->withJs('vendor/accounting/admin/js/account-statement.js', true)
            ->with([
                'account' => $account,
            ]);
    }
}
