<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use RMS\Accounting\Http\Controllers\Admin\Concerns\RendersAccountingStructuredResourceForm;
use RMS\Accounting\Models\PaymentReconciliation;
use Illuminate\Http\Request;
use RMS\Core\Data\Field;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;

class ReconciliationsController extends AccountingAdminController implements HasList, HasForm, ShouldFilter
{
    use RendersAccountingStructuredResourceForm;

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
        return 'accounting.reconciliations';
    }

    public function routeParameter(): string
    {
        return 'reconciliation';
    }

    public function getFieldsForm(): array
    {
        return [
            Field::number('payment_id', trans('accounting::accounting.reconciliation.payment_id'))->required(),
            Field::number('expected_amount', trans('accounting::accounting.reconciliation.expected_amount'))->required(),
            Field::number('actual_amount', trans('accounting::accounting.reconciliation.actual_amount'))->required(),
            Field::date('reconciliation_date', trans('accounting::accounting.reconciliation.bank_date'))->required(),
            Field::string('bank_statement_reference', trans('accounting::accounting.reconciliation.bank_reference'))->optional(),
            Field::textarea('discrepancy_notes', trans('accounting::accounting.reconciliation.notes'))->optional(),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle(trans('accounting::accounting.common.id'))->sortable()->width('80px'),
            Field::make('payment_id')->withTitle(trans('accounting::accounting.reconciliation.payment_id'))->sortable()->width('100px'),
            Field::make('bank_statement_reference')->withTitle(trans('accounting::accounting.reconciliation.bank_reference'))->searchable()->sortable()->width('150px'),
            Field::make('expected_amount')->withTitle(trans('accounting::accounting.reconciliation.expected_amount'))->sortable()->width('120px'),
            Field::make('actual_amount')->withTitle(trans('accounting::accounting.reconciliation.actual_amount'))->sortable()->width('120px'),
            Field::make('reconciliation_date')->withTitle(trans('accounting::accounting.reconciliation.bank_date'))->sortable()->width('120px'),
            Field::make('status')->withTitle(trans('accounting::accounting.common.status'))->sortable()->width('120px'),
            Field::make('created_at')->withTitle(trans('accounting::accounting.common.created_at'))->sortable()->width('150px'),
        ];
    }

    public function filters(): array
    {
        return [
            Field::select('status', trans('accounting::accounting.reconciliation.is_matched'))
                ->setOptions([
                    '' => trans('accounting::accounting.common.all'),
                    PaymentReconciliation::STATUS_MATCHED => trans('accounting::accounting.common.matched'),
                    PaymentReconciliation::STATUS_PENDING => trans('accounting::accounting.common.unmatched'),
                    PaymentReconciliation::STATUS_DISCREPANCY => trans('accounting::accounting.common.discrepancy'),
                ]),
        ];
    }

    /**
     * تایید تطبیق
     */
    public function confirm(Request $request, int $id)
    {
        $reconciliation = PaymentReconciliation::findOrFail($id);
        $reconciliation->is_reconciled = true;
        $reconciliation->reconciled_by_user_id = \RMS\Accounting\Support\AuditActor::userId();
        $reconciliation->reconciled_at = now();
        $reconciliation->discrepancy_amount = max(0, (float) $reconciliation->actual_amount - (float) $reconciliation->expected_amount);
        $reconciliation->status = $reconciliation->discrepancy_amount > 0.0001
            ? PaymentReconciliation::STATUS_DISCREPANCY
            : PaymentReconciliation::STATUS_MATCHED;
        $reconciliation->save();

        return redirect()->back()->with('success', trans('accounting::accounting.messages.reconciliation_confirmed'));
    }

    /**
     * تطبیق خودکار
     */
    public function autoReconcile(Request $request)
    {
        $pending = PaymentReconciliation::query()
            ->where('status', PaymentReconciliation::STATUS_PENDING)
            ->limit(200)
            ->get();
        foreach ($pending as $row) {
            $expected = (float) ($row->expected_amount ?? 0);
            $actual = (float) ($row->actual_amount ?? 0);
            $diff = round($actual - $expected, 4);
            $row->discrepancy_amount = abs($diff);
            if (abs($diff) <= 0.0001) {
                $row->status = PaymentReconciliation::STATUS_MATCHED;
                $row->is_reconciled = true;
                $row->reconciled_by_user_id = \RMS\Accounting\Support\AuditActor::userId();
                $row->reconciled_at = now();
            } else {
                $row->status = PaymentReconciliation::STATUS_DISCREPANCY;
                $row->is_reconciled = false;
            }
            $row->save();
        }
        return redirect()->back()->with('success', trans('accounting::accounting.messages.auto_reconcile_completed'));
    }
}
