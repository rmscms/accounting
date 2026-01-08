<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use RMS\Accounting\Models\CashBox;
use Illuminate\Http\Request;
use RMS\Core\Controllers\Admin\AdminController;
use RMS\Core\Data\Field;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\Actions\ChangeBoolField;

class CashBoxesController extends AccountingAdminController implements
    HasList,
    HasForm,
    ShouldFilter,
    ChangeBoolField
{
    public function table(): string
    {
        return 'cash_boxes';
    }

    public function modelName(): string
    {
        return CashBox::class;
    }

    public function baseRoute(): string
    {
        return 'accounting.cashboxes';
    }

    public function routeParameter(): string
    {
        return 'cashbox';
    }

    public function getFieldsForm(): array
    {
        return [
            Field::string('name', trans('accounting::accounting.fields.cashbox_name'))
                ->required(),

            Field::select('store_id', trans('accounting::accounting.fields.store'))
                ->setOptions($this->getStoreOptions())
                ->required(),

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
            Field::make('name')->withTitle(trans('accounting::accounting.fields.cashbox_name'))->searchable()->sortable(),
            Field::make('store_id')->withTitle(trans('accounting::accounting.fields.store'))->width('150px'),
            Field::make('balance')->withTitle(trans('accounting::accounting.fields.balance'))->sortable()->width('140px'),
            Field::boolean('active')->withTitle(trans('accounting::accounting.fields.status'))->sortable()->width('100px'),
            Field::date('created_at')->withTitle(trans('accounting::accounting.fields.created_at'))->sortable()->width('150px'),
        ];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'store_id' => ['required', 'integer', 'exists:stores,id'],
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
        // TODO: Integrate with stores table when available
        return [1 => 'فروشگاه اصلی'];
    }
}
