<?php

namespace RMS\Accounting\Http\Controllers\Api\Admin;

use RMS\Accounting\Models\FinancialLedger;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Admin API Controller for Ledger
 * 
 * @group Admin API - Ledger
 */
class LedgerApiController
{
    /**
     * Get ledger entries
     * 
     * @queryParam account_code string Filter by account code
     * @queryParam document_id int Filter by document
     * @queryParam start_date date Filter from date
     * @queryParam end_date date Filter to date
     * @queryParam per_page int Items per page (default: 50)
     */
    public function index(Request $request): JsonResponse
    {
        $query = FinancialLedger::with(['document', 'account'])
            ->orderBy('entry_date', 'desc')
            ->orderBy('id', 'desc');

        if ($request->filled('account_code')) {
            $query->where('account_code', $request->account_code);
        }

        if ($request->filled('document_id')) {
            $query->where('document_id', $request->document_id);
        }

        if ($request->filled('start_date')) {
            $query->where('entry_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->where('entry_date', '<=', $request->end_date);
        }

        if ($request->filled('reference_type')) {
            $query->where('reference_type', $request->reference_type);
        }

        $perPage = min($request->get('per_page', 50), 100);
        $entries = $query->paginate($perPage);

        // Add running balance
        $balance = 0;
        $entries->getCollection()->transform(function ($entry) use (&$balance) {
            $balance += ($entry->debit - $entry->credit);
            $entry->running_balance = $balance;
            return $entry;
        });

        return response()->json($entries);
    }
}
