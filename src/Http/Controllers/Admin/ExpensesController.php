<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use RMS\Accounting\Models\Expense;
use Illuminate\Http\Request;
use RMS\Core\Data\Field;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;

class ExpensesController extends AccountingAdminController implements HasList, HasForm, ShouldFilter
{
    public function table(): string
    {
        return 'expenses';
    }

    public function modelName(): string
    {
        return Expense::class;
    }

    public function baseRoute(): string
    {
        return 'admin.accounting.expenses';
    }

    public function routeParameter(): string
    {
        return 'expense';
    }

    public function getFieldsForm(): array
    {
        return [
            Field::string('expense_number', trans('accounting::accounting.expense.expense_number'))->required(),
            Field::date('expense_date', trans('accounting::accounting.expense.expense_date'))->required(),
            Field::number('expense_category_id', trans('accounting::accounting.expense.category'))->required(),
            Field::number('total_amount', trans('accounting::accounting.expense.total_amount'))->required(),
            Field::textarea('description', trans('accounting::accounting.expense.description'))->optional(),
            Field::select('status', trans('accounting::accounting.expense.status'))
                ->options([
                    'pending' => trans('accounting::accounting.statuses.pending'),
                    'approved' => trans('accounting::accounting.statuses.approved'),
                    'rejected' => trans('accounting::accounting.statuses.rejected'),
                    'paid' => trans('accounting::accounting.statuses.paid'),
                ])
                ->withDefaultValue('pending')
                ->required(),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle(trans('accounting::accounting.common.id'))->sortable()->width('80px'),
            Field::make('expense_number')->withTitle(trans('accounting::accounting.expense.expense_number'))->searchable()->sortable()->width('150px'),
            Field::make('expense_date')->withTitle(trans('accounting::accounting.expense.expense_date'))->sortable()->width('120px'),
            Field::make('total_amount')->withTitle(trans('accounting::accounting.expense.total_amount'))->sortable()->width('120px'),
            Field::make('status')->withTitle(trans('accounting::accounting.expense.status'))->sortable()->width('100px'),
            Field::make('created_at')->withTitle(trans('accounting::accounting.common.created_at'))->sortable()->width('150px'),
        ];
    }

    public function filters(): array
    {
        return [
            Field::select('status', trans('accounting::accounting.expense.status'))
                ->options([
                    '' => trans('accounting::accounting.common.all'),
                    'pending' => trans('accounting::accounting.statuses.pending'),
                    'approved' => trans('accounting::accounting.statuses.approved'),
                    'rejected' => trans('accounting::accounting.statuses.rejected'),
                    'paid' => trans('accounting::accounting.statuses.paid'),
                ]),
        ];
    }

    /**
     * تایید هزینه
     */
    public function approve(Request $request, int $id)
    {
        $expense = Expense::findOrFail($id);
        
        if ($expense->status !== 'pending') {
            return redirect()->back()->with('error', trans('accounting::accounting.errors.expense_not_pending'));
        }

        $expense->status = 'approved';
        $expense->approved_by = auth()->id();
        $expense->approved_at = now();
        $expense->save();

        return redirect()->back()->with('success', trans('accounting::accounting.messages.expense_approved'));
    }
}
