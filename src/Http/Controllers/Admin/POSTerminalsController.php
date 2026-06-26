<?php

namespace RMS\Accounting\Http\Controllers\Admin;
use RMS\Accounting\Http\Controllers\Admin\Concerns\RendersAccountingStructuredResourceForm;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RMS\Accounting\Models\Bank;
use RMS\Accounting\Models\POSTerminal;
use RMS\Core\Data\Field;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\Actions\ChangeBoolField;

class POSTerminalsController extends AccountingAdminController implements
    HasList,
    HasForm,
    ShouldFilter,
    ChangeBoolField
{
    use RendersAccountingStructuredResourceForm;

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
            Field::string('terminal_id', trans('accounting::accounting.pos_terminal.terminal_id'))
                ->required()
                ->withHint(trans('accounting::accounting.hints.terminal_id')),

            Field::string('name', trans('accounting::accounting.pos_terminal.name'))
                ->required(),

            Field::string('serial_number', trans('accounting::accounting.pos_terminal.serial_number'))
                ->required(),

            Field::select('bank_id', trans('accounting::accounting.pos_terminal.bank_id'))
                ->setOptions($this->getBankOptions())
                ->required(),

            Field::string('merchant_id', trans('accounting::accounting.pos_terminal.merchant_id'))
                ->optional(),

            Field::string('location', trans('accounting::accounting.pos_terminal.location'))
                ->optional(),

            Field::boolean('active', trans('accounting::accounting.pos_terminal.is_active'))
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
        $posTerminal = request()->route('pos_terminal');
        $id = is_object($posTerminal) ? $posTerminal->getKey() : $posTerminal;

        return [
            'terminal_id' => [
                'required',
                'string',
                'max:50',
                Rule::unique('pos_terminals', 'terminal_id')->ignore($id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'serial_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('pos_terminals', 'serial_number')->ignore($id),
            ],
            'bank_id' => ['required', 'integer', 'exists:banks,id'],
            'merchant_id' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'active' => ['boolean'],
        ];
    }

    public function boolFields(): array
    {
        return ['active'];
    }

    /**
     * @return array<int|string, string>
     */
    protected function getBankOptions(): array
    {
        return Bank::query()
            ->where('active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
