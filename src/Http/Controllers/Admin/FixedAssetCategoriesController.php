<?php

namespace RMS\Accounting\Http\Controllers\Admin;
use RMS\Accounting\Http\Controllers\Admin\Concerns\RendersAccountingStructuredResourceForm;

use RMS\Accounting\Models\FixedAssetCategory;
use RMS\Accounting\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Filesystem\Filesystem;
use RMS\Core\Data\Field;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\Actions\ChangeBoolField;

/**
 * مدیریت دسته‌بندی دارایی‌های ثابت
 */
class FixedAssetCategoriesController extends AccountingAdminController implements
    HasList,
    HasForm,
    ShouldFilter,
    ChangeBoolField
{
    use RendersAccountingStructuredResourceForm;

    public function __construct(Filesystem $filesystem)
    {
        parent::__construct($filesystem);
    }

    public function table(): string
    {
        return 'fixed_asset_categories';
    }

    public function modelName(): string
    {
        return FixedAssetCategory::class;
    }

    public function baseRoute(): string
    {
        return 'accounting.fixed-asset-categories';
    }

    public function routeParameter(): string
    {
        return 'fixed_asset_category';
    }

    public function getFieldsForm(): array
    {
        return [
            Field::string('name', trans('accounting::accounting.fixed_asset_category.name'))
                ->required(),

            Field::string('code', trans('accounting::accounting.fixed_asset_category.code'))
                ->required(),

            Field::textarea('description', trans('accounting::accounting.common.description'), 3)
                ->optional(),

            Field::select('asset_account_id', trans('accounting::accounting.fixed_asset_category.asset_account'))
                ->setOptions($this->getAssetAccountOptions())
                ->optional(),

            Field::select('depreciation_account_id', trans('accounting::accounting.fixed_asset_category.depreciation_account'))
                ->setOptions($this->getExpenseAccountOptions())
                ->optional(),

            Field::select('accumulated_depreciation_account_id', trans('accounting::accounting.fixed_asset_category.accumulated_depreciation_account'))
                ->setOptions($this->getAssetAccountOptions())
                ->optional(),

            Field::boolean('active', trans('accounting::accounting.common.is_active'))
                ->withDefaultValue(true),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle(trans('accounting::accounting.common.id'))->sortable()->width('80px'),
            Field::make('name')->withTitle(trans('accounting::accounting.fixed_asset_category.name'))->searchable()->sortable(),
            Field::make('code')->withTitle(trans('accounting::accounting.fixed_asset_category.code'))->searchable()->width('120px'),
            Field::boolean('active')->withTitle(trans('accounting::accounting.common.status'))->sortable()->width('100px'),
        ];
    }

    public function rules(): array
    {
        $id = request()->route('fixed_asset_category');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:fixed_asset_categories,code,' . $id],
            'description' => ['nullable', 'string'],
            'asset_account_id' => ['nullable', 'exists:accounts,id'],
            'depreciation_account_id' => ['nullable', 'exists:accounts,id'],
            'accumulated_depreciation_account_id' => ['nullable', 'exists:accounts,id'],
            'active' => ['boolean'],
        ];
    }

    public function boolFields(): array
    {
        return ['active'];
    }

    protected function getAssetAccountOptions(): array
    {
        return Account::where('account_type', 'asset')
            ->where('active', true)
            ->where('level', '>=', 2)
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn($account) => [$account->id => "{$account->code} - {$account->name}"])
            ->toArray();
    }

    protected function getExpenseAccountOptions(): array
    {
        return Account::where('account_type', 'expense')
            ->where('active', true)
            ->where('level', '>=', 2)
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn($account) => [$account->id => "{$account->code} - {$account->name}"])
            ->toArray();
    }
}
