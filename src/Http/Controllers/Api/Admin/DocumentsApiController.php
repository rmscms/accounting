<?php

namespace RMS\Accounting\Http\Controllers\Api\Admin;

use RMS\Accounting\Models\AccountingDocument;
use RMS\Accounting\Services\DocumentService;
use RMS\Accounting\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Admin API Controller for Documents
 * 
 * @group Admin API - Documents
 */
class DocumentsApiController
{
    protected DocumentService $documentService;
    protected LedgerService $ledgerService;

    public function __construct(
        DocumentService $documentService,
        LedgerService $ledgerService
    ) {
        $this->documentService = $documentService;
        $this->ledgerService = $ledgerService;
    }

    /**
     * List all documents
     */
    public function index(Request $request): JsonResponse
    {
        $query = AccountingDocument::with('entries')
            ->orderBy('document_date', 'desc')
            ->orderBy('id', 'desc');

        if ($request->filled('document_type')) {
            $query->where('document_type', $request->document_type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('start_date')) {
            $query->where('document_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->where('document_date', '<=', $request->end_date);
        }

        $perPage = min($request->get('per_page', 50), 100);
        $documents = $query->paginate($perPage);

        return response()->json($documents);
    }

    /**
     * Get document details
     */
    public function show(int $id): JsonResponse
    {
        $document = AccountingDocument::with('entries.account')->findOrFail($id);

        return response()->json([
            'data' => $document,
        ]);
    }

    /**
     * Create new document
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document_type' => 'required|string',
            'document_number' => 'nullable|string',
            'document_date' => 'required|date',
            'description' => 'nullable|string',
            'store_id' => 'required|integer',
            'entries' => 'required|array|min:2',
            'entries.*.account_code' => 'required|string|exists:accounts,code',
            'entries.*.debit' => 'required|numeric|min:0',
            'entries.*.credit' => 'required|numeric|min:0',
            'entries.*.description' => 'nullable|string',
        ]);

        $document = $this->ledgerService->createDocument($validated);

        // Add entries
        foreach ($validated['entries'] as $entry) {
            $this->ledgerService->recordEntry(array_merge($entry, [
                'document_id' => $document->id,
                'entry_date' => $validated['document_date'],
                'store_id' => $validated['store_id'],
            ]));
        }

        return response()->json([
            'message' => trans('accounting::accounting.created_successfully'),
            'data' => $document->fresh('entries'),
        ], 201);
    }

    /**
     * Post document (make it permanent)
     */
    public function post(int $id): JsonResponse
    {
        $document = AccountingDocument::findOrFail($id);

        if ($document->status === 'posted') {
            return response()->json([
                'message' => 'Document already posted',
            ], 422);
        }

        $this->documentService->postDocument($document->id);

        return response()->json([
            'message' => 'Document posted successfully',
            'data' => $document->fresh(),
        ]);
    }

    /**
     * Delete document (only if draft)
     */
    public function destroy(int $id): JsonResponse
    {
        $document = AccountingDocument::findOrFail($id);

        if ($document->status === 'posted') {
            return response()->json([
                'message' => 'Cannot delete posted document',
            ], 403);
        }

        $document->delete();

        return response()->json([
            'message' => trans('accounting::accounting.deleted_successfully'),
        ]);
    }
}
