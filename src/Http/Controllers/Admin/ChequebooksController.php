<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Database\Query\Builder;
use RMS\Accounting\Models\Bank;
use RMS\Accounting\Models\Chequebook;
use RMS\Core\Contracts\Actions\ChangeBoolField;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Data\Field;

class ChequebooksController extends AccountingAdminController implements HasList, HasForm, ShouldFilter, ChangeBoolField
{
    public function table(): string
    {
        return 'chequebooks';
    }

    public function modelName(): string
    {
        return Chequebook::class;
    }

    public function baseRoute(): string
    {
        return 'accounting.chequebooks';
    }

    public function routeParameter(): string
    {
        return 'chequebook';
    }

    public function query(Builder $sql): void
    {
        $sql->leftJoin('banks', 'banks.id', '=', 'a.bank_id')
            ->addSelect('a.*', 'banks.name as bank_name');
    }

    public function getFieldsForm(): array
    {
        return [
            Field::select('bank_id', trans('accounting::accounting.fields.bank'))
                ->setOptions($this->getBankOptions())
                ->required(),
            Field::string('title', trans('accounting::accounting.chequebooks.title'))->required(),
            Field::string('book_number', trans('accounting::accounting.chequebooks.book_number'))->optional(),
            Field::number('serial_from', trans('accounting::accounting.chequebooks.serial_from'))->optional(),
            Field::number('serial_to', trans('accounting::accounting.chequebooks.serial_to'))->optional(),
            Field::number('next_serial', trans('accounting::accounting.chequebooks.next_serial'))->optional(),
            Field::boolean('active', trans('accounting::accounting.common.status'))->withDefaultValue(true),
            Field::textarea('notes', trans('accounting::accounting.fields.notes'))->optional(),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle('ID')->sortable()->width('70px'),
            Field::make('title')->withTitle(trans('accounting::accounting.chequebooks.title'))->searchable()->sortable(),
            Field::make('bank_name', 'banks.name')->withTitle(trans('accounting::accounting.fields.bank'))->searchable()->sortable(),
            Field::make('book_number')->withTitle(trans('accounting::accounting.chequebooks.book_number'))->searchable(),
            Field::make('serial_from')->withTitle(trans('accounting::accounting.chequebooks.serial_from')),
            Field::make('serial_to')->withTitle(trans('accounting::accounting.chequebooks.serial_to')),
            Field::make('next_serial')->withTitle(trans('accounting::accounting.chequebooks.next_serial')),
            Field::boolean('active')->withTitle(trans('accounting::accounting.common.status')),
        ];
    }

    public function rules(): array
    {
        $id = request()->route($this->routeParameter());

        return [
            'bank_id' => ['required', 'integer', 'exists:banks,id'],
            'title' => ['required', 'string', 'max:255'],
            'book_number' => ['nullable', 'string', 'max:100'],
            'serial_from' => ['nullable', 'integer', 'min:1'],
            'serial_to' => ['nullable', 'integer', 'gte:serial_from'],
            'next_serial' => ['nullable', 'integer', 'gte:serial_from'],
            'active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function boolFields(): array
    {
        return ['active'];
    }

    protected function getBankOptions(): array
    {
        return Bank::query()->orderBy('name')->pluck('name', 'id')->all();
    }
}

