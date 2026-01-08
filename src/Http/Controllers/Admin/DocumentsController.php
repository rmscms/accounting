<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use RMS\Accounting\Models\AccountingDocument;
use Illuminate\Http\Request;
use RMS\Core\Data\Field;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;

class DocumentsController extends AccountingAdminController implements HasList, HasForm, ShouldFilter
{
    public function table(): string
    {
        return 'accounting_documents';
    }

    public function modelName(): string
    {
        return AccountingDocument::class;
    }

    public function baseRoute(): string
    {
        return 'admin.accounting.documents';
    }

    public function routeParameter(): string
    {
        return 'document';
    }

    public function getFieldsForm(): array
    {
        return [
            Field::string('document_number', trans('accounting::accounting.document.document_number'))->required(),
            Field::select('document_type', trans('accounting::accounting.document.document_type'))
                ->options([
                    'SALE' => trans('accounting::accounting.document_types.sale'),
                    'PURCHASE' => trans('accounting::accounting.document_types.purchase'),
                    'PAYMENT' => trans('accounting::accounting.document_types.payment'),
                    'RECEIPT' => trans('accounting::accounting.document_types.receipt'),
                    'TAX' => trans('accounting::accounting.document_types.tax'),
                    'FX_ADJUST' => trans('accounting::accounting.document_types.fx_adjust'),
                    'CORRECTION' => trans('accounting::accounting.document_types.correction'),
                    'OPENING' => trans('accounting::accounting.document_types.opening'),
                    'CLOSING' => trans('accounting::accounting.document_types.closing'),
                    'EXPENSE' => trans('accounting::accounting.document_types.expense'),
                ])
                ->required(),
            Field::textarea('description', trans('accounting::accounting.document.description'))->required(),
            Field::select('status', trans('accounting::accounting.document.status'))
                ->options([
                    'draft' => trans('accounting::accounting.statuses.draft'),
                    'posted' => trans('accounting::accounting.statuses.posted'),
                    'reversed' => trans('accounting::accounting.statuses.reversed'),
                ])
                ->withDefaultValue('draft')
                ->required(),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle(trans('accounting::accounting.common.id'))->sortable()->width('80px'),
            Field::make('document_number')->withTitle(trans('accounting::accounting.document.document_number'))->searchable()->sortable()->width('150px'),
            Field::make('document_type')->withTitle(trans('accounting::accounting.document.document_type'))->sortable()->width('120px'),
            Field::make('description')->withTitle(trans('accounting::accounting.document.description'))->searchable(),
            Field::make('total_debit')->withTitle(trans('accounting::accounting.document.total_debit'))->sortable()->width('120px'),
            Field::make('total_credit')->withTitle(trans('accounting::accounting.document.total_credit'))->sortable()->width('120px'),
            Field::make('status')->withTitle(trans('accounting::accounting.document.status'))->sortable()->width('100px'),
            Field::make('created_at')->withTitle(trans('accounting::accounting.common.created_at'))->sortable()->width('150px'),
        ];
    }

    public function filters(): array
    {
        return [
            Field::select('document_type', trans('accounting::accounting.document.document_type'))
                ->options([
                    '' => trans('accounting::accounting.common.all'),
                    'SALE' => trans('accounting::accounting.document_types.sale'),
                    'PURCHASE' => trans('accounting::accounting.document_types.purchase'),
                    'PAYMENT' => trans('accounting::accounting.document_types.payment'),
                    'RECEIPT' => trans('accounting::accounting.document_types.receipt'),
                ]),
            Field::select('status', trans('accounting::accounting.document.status'))
                ->options([
                    '' => trans('accounting::accounting.common.all'),
                    'draft' => trans('accounting::accounting.statuses.draft'),
                    'posted' => trans('accounting::accounting.statuses.posted'),
                    'reversed' => trans('accounting::accounting.statuses.reversed'),
                ]),
        ];
    }

    /**
     * ثبت قطعی سند
     */
    public function post(Request $request, int $id)
    {
        $document = AccountingDocument::findOrFail($id);
        
        if ($document->status !== 'draft') {
            return redirect()->back()->with('error', trans('accounting::accounting.errors.document_already_posted'));
        }

        // TODO: استفاده از DocumentService برای ثبت قطعی
        
        return redirect()->back()->with('success', trans('accounting::accounting.messages.document_posted'));
    }

    /**
     * برگشت سند
     */
    public function reverse(Request $request, int $id)
    {
        $document = AccountingDocument::findOrFail($id);
        
        if ($document->status !== 'posted') {
            return redirect()->back()->with('error', trans('accounting::accounting.errors.document_not_posted'));
        }

        // TODO: استفاده از DocumentService برای برگشت
        
        return redirect()->back()->with('success', trans('accounting::accounting.messages.document_reversed'));
    }
}
