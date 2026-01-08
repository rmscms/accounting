<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use RMS\Accounting\Models\Cheque;
use Illuminate\Http\Request;
use Illuminate\Database\Query\Builder as QueryBuilder;
use RMS\Core\Controllers\Admin\AdminController;
use RMS\Core\Data\Field;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;

class ChequesController extends AccountingAdminController implements
    HasList,
    HasForm,
    ShouldFilter
{
    public function table(): string
    {
        return 'cheques';
    }

    public function modelName(): string
    {
        return Cheque::class;
    }

    public function baseRoute(): string
    {
        return 'accounting.cheques';
    }

    public function routeParameter(): string
    {
        return 'cheque';
    }

    public function getFieldsForm(): array
    {
        return [
            Field::string('cheque_number', trans('accounting::accounting.fields.cheque_number'))
                ->required(),

            Field::select('store_id', trans('accounting::accounting.fields.store'))
                ->setOptions($this->getStoreOptions())
                ->required(),

            Field::select('type', trans('accounting::accounting.fields.cheque_type'))
                ->setOptions($this->getChequeTypeOptions())
                ->required(),

            Field::select('bank_id', trans('accounting::accounting.fields.bank'))
                ->setOptions($this->getBankOptions())
                ->required(),

            Field::price('amount', trans('accounting::accounting.fields.amount'))
                ->required(),

            Field::date('issue_date', trans('accounting::accounting.fields.issue_date'))
                ->required(),

            Field::date('due_date', trans('accounting::accounting.fields.due_date'))
                ->required(),

            Field::string('payer_name', trans('accounting::accounting.fields.payer_name'))
                ->optional(),

            Field::string('receiver_name', trans('accounting::accounting.fields.receiver_name'))
                ->optional(),

            Field::select('status', trans('accounting::accounting.fields.status'))
                ->setOptions($this->getChequeStatusOptions())
                ->required(),

            Field::textarea('notes', trans('accounting::accounting.fields.notes'))
                ->optional(),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle('ID')->sortable()->width('80px'),
            Field::make('cheque_number')->withTitle(trans('accounting::accounting.fields.cheque_number'))->searchable()->sortable()->width('140px'),
            Field::make('type')->withTitle(trans('accounting::accounting.fields.cheque_type'))->width('100px'),
            Field::make('amount')->withTitle(trans('accounting::accounting.fields.amount'))->sortable()->width('140px'),
            Field::date('issue_date')->withTitle(trans('accounting::accounting.fields.issue_date'))->sortable()->width('130px'),
            Field::date('due_date')->withTitle(trans('accounting::accounting.fields.due_date'))->sortable()->width('130px'),
            Field::make('status')->withTitle(trans('accounting::accounting.fields.status'))->width('120px'),
        ];
    }

    public function rules(): array
    {
        return [
            'cheque_number' => ['required', 'string', 'max:50'],
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'type' => ['required', 'in:received,issued'],
            'bank_id' => ['required', 'integer', 'exists:banks,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'payer_name' => ['nullable', 'string', 'max:255'],
            'receiver_name' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:pending,cleared,bounced,cancelled'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function getStoreOptions(): array
    {
        return [1 => 'فروشگاه اصلی'];
    }

    protected function getBankOptions(): array
    {
        return \RMS\Accounting\Models\Bank::pluck('name', 'id')->toArray();
    }

    protected function getChequeTypeOptions(): array
    {
        return [
            'received' => trans('accounting::accounting.cheque_types.received'),
            'issued' => trans('accounting::accounting.cheque_types.issued'),
        ];
    }

    protected function getChequeStatusOptions(): array
    {
        return [
            'pending' => trans('accounting::accounting.cheque_statuses.pending'),
            'cleared' => trans('accounting::accounting.cheque_statuses.cleared'),
            'bounced' => trans('accounting::accounting.cheque_statuses.bounced'),
            'cancelled' => trans('accounting::accounting.cheque_statuses.cancelled'),
        ];
    }
}
