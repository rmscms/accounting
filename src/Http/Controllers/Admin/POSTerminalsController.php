<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use RMS\Accounting\Models\POSTerminal;
use Illuminate\Http\Request;
use RMS\Core\Controllers\Admin\AdminController;
use RMS\Core\Data\Field;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\Actions\ChangeBoolField;

class POSTerminalsController extends AdminController implements
    HasList,
    HasForm,
    ShouldFilter,
    ChangeBoolField
{
    public function table(): string
    {
        return 'pos_terminals';
    }

    public function modelName(): string
    {
        return POSTerminal::class;
    }

    public function baseRoute(): string
    {
        return 'accounting.pos-terminals';
    }

    public function routeParameter(): string
    {
        return 'pos_terminal';
    }

    public function getFieldsForm(): array
    {
        return [
            Field::string('terminal_id', trans('accounting::accounting.fields.terminal_id'))
                ->required()
                ->withHint(trans('accounting::accounting.hints.terminal_id')),

            Field::string('name', trans('accounting::accounting.fields.pos_name'))
                ->required(),

            Field::select('store_id', trans('accounting::accounting.fields.store'))
                ->setOptions($this->getStoreOptions())
                ->required(),

            Field::select('bank_account_id', trans('accounting::accounting.fields.bank_account'))
                ->setOptions($this->getBankAccountOptions())
                ->optional(),

            Field::string('merchant_id', trans('accounting::accounting.fields.merchant_id'))
                ->optional(),

            Field::textarea('description', trans('accounting::accounting.fields.description'))
                ->optional(),

            Field::boolean('active', trans('accounting::accounting.fields.active'))
                ->withDefaultValue(true),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle('ID')->sortable()->width('80px'),
            Field::make('terminal_id')->withTitle(trans('accounting::accounting.fields.terminal_id'))->searchable()->sortable()->width('140px'),
            Field::make('name')->withTitle(trans('accounting::accounting.fields.pos_name'))->searchable()->sortable(),
            Field::make('merchant_id')->withTitle(trans('accounting::accounting.fields.merchant_id'))->width('150px'),
            Field::boolean('active')->withTitle(trans('accounting::accounting.fields.status'))->sortable()->width('100px'),
            Field::date('created_at')->withTitle(trans('accounting::accounting.fields.created_at'))->sortable()->width('150px'),
        ];
    }

    public function rules(): array
    {
        $id = request()->route('pos_terminal');

        return [
            'terminal_id' => ['required', 'string', 'max:50', 'unique:pos_terminals,terminal_id,' . ($id ?? 'NULL')],
            'name' => ['required', 'string', 'max:255'],
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'merchant_id' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'active' => ['boolean'],
        ];
    }

    public function boolFields(): array
    {
        return ['active'];
    }

    protected function getStoreOptions(): array
    {
        return [1 => 'فروشگاه اصلی'];
    }

    protected function getBankAccountOptions(): array
    {
        return \RMS\Accounting\Models\BankAccount::pluck('account_name', 'id')->toArray();
    }
}
