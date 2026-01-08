<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\Request;
use RMS\Accounting\Models\AccountingDocument;
use RMS\Accounting\Services\DocumentService;
use RMS\Core\Http\Controllers\AdminController;

/**
 * کنترلر مدیریت اسناد حسابداری
 */
class DocumentsController extends AdminController
{
    protected string $model = AccountingDocument::class;
    protected string $indexView = 'accounting::admin.documents.index';
    protected string $formView = 'accounting::admin.documents.form';
    
    protected DocumentService $documentService;

    public function __construct(DocumentService $documentService)
    {
        $this->documentService = $documentService;
    }

    public function index(Request $request)
    {
        $query = AccountingDocument::with(['fiscalYear', 'ledgers'])
            ->orderByDesc('created_at');

        if ($request->filled('document_type')) {
            $query->where('document_type', $request->document_type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $documents = $query->paginate(50);

        // آمار
        $stats = [
            'total' => AccountingDocument::count(),
            'posted' => AccountingDocument::where('status', 'posted')->count(),
            'draft' => AccountingDocument::where('status', 'draft')->count(),
        ];

        return view($this->indexView, compact('documents', 'stats'));
    }

    public function show(int $id)
    {
        $document = AccountingDocument::with(['fiscalYear', 'ledgers.account'])
            ->findOrFail($id);

        return view('accounting::admin.documents.show', compact('document'));
    }

    /**
     * ثبت قطعی سند
     */
    public function post(int $id)
    {
        try {
            $this->documentService->postDocument($id);

            return response()->json([
                'success' => true,
                'message' => trans('accounting::accounting.document_posted')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * برگشت سند
     */
    public function reverse(Request $request, int $id)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $reversalDocument = $this->documentService->reverseDocument($id, $validated['reason']);

            return response()->json([
                'success' => true,
                'message' => trans('accounting::accounting.document_reversed'),
                'reversal_document_id' => $reversalDocument->id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
