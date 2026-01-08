<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use RMS\Accounting\Models\PaymentReconciliation;
use Illuminate\Http\Request;
use RMS\Core\Data\Field;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;

class ReconciliationsController extends AccountingAdminController implements HasList, HasForm, ShouldFilter
{
    public function table(): string
    {
        return 'payment_reconciliations';
    }

    public function modelName(): string
    {
        return PaymentReconciliation::class;
    }

    public function baseRoute(): string
    {
        return 'admin.accounting.reconciliations';
    }

    public function routeParameter(): string
    {
        return 'reconciliation';
    }

    public function getFieldsForm(): array
    {
        return [
            Field::number('payment_id', trans('accounting::accounting.reconciliation.payment_id'))->required(),
            Field::string('bank_reference', trans('accounting::accounting.reconciliation.bank_reference'))->optional(),
            Field::number('bank_amount', trans('accounting::accounting.reconciliation.bank_amount'))->required(),
            Field::date('bank_date', trans('accounting::accounting.reconciliation.bank_date'))->required(),
            Field::boolean('is_matched', trans('accounting::accounting.reconciliation.is_matched'))->withDefaultValue(false),
            Field::textarea('notes', trans('accounting::accounting.reconciliation.notes'))->optional(),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle(trans('accounting::accounting.common.id'))->sortable()->width('80px'),
            Field::make('payment_id')->withTitle(trans('accounting::accounting.reconciliation.payment_id'))->sortable()->width('100px'),
            Field::make('bank_reference')->withTitle(trans('accounting::accounting.reconciliation.bank_reference'))->searchable()->sortable()->width('150px'),
            Field::make('bank_amount')->withTitle(trans('accounting::accounting.reconciliation.bank_amount'))->sortable()->width('120px'),
            Field::make('bank_date')->withTitle(trans('accounting::accounting.reconciliation.bank_date'))->sortable()->width('120px'),
            Field::make('is_matched')->withTitle(trans('accounting::accounting.reconciliation.is_matched'))->boolean()->sortable()->width('100px'),
            Field::make('created_at')->withTitle(trans('accounting::accounting.common.created_at'))->sortable()->width('150px'),
        ];
    }

    public function filters(): array
    {
        return [
            Field::select('is_matched', trans('accounting::accounting.reconciliation.is_matched'))
                ->options([
                    '' => trans('accounting::accounting.common.all'),
                    '1' => trans('accounting::accounting.common.matched'),
                    '0' => trans('accounting::accounting.common.unmatched'),
                ]),
        ];
    }

    /**
     * تایید تطبیق
     */
    public function confirm(Request $request, int $id)
    {
        $reconciliation = PaymentReconciliation::findOrFail($id);
        
        $reconciliation->is_matched = true;
        $reconciliation->matched_by = auth()->id();
        $reconciliation->matched_at = now();
        $reconciliation->save();

        return redirect()->back()->with('success', trans('accounting::accounting.messages.reconciliation_confirmed'));
    }

    /**
     * تطبیق خودکار
     */
    public function autoReconcile(Request $request)
    {
        // TODO: پیاده‌سازی تطبیق خودکار
        return redirect()->back()->with('success', trans('accounting::accounting.messages.auto_reconcile_completed'));
    }
}
