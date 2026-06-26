<?php

declare(strict_types=1);

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RMS\Core\Models\Setting;
use RMS\Accounting\Models\Bank;
use RMS\Accounting\Models\BankReconciliation;
use RMS\Accounting\Models\BankReconciliationItem;
use RMS\Accounting\Services\AccountingAttachmentService;
use RMS\Accounting\Services\AccountingDateInputNormalizer;
use RMS\Accounting\Services\BankReconciliationWorkspaceService;
use RMS\Accounting\Support\AuditActor;
use RMS\Accounting\Support\AccountingDateUi;

class BankReconciliationWorkspaceController extends AccountingAdminController
{
    public function table(): string
    {
        return '';
    }

    public function modelName(): string
    {
        return '';
    }

    public function workspace(Request $request)
    {
        $plugins = AccountingDateUi::calendarMode() === AccountingDateUi::MODE_JALALI ? ['persian-datepicker'] : [];
        if (! in_array('confirm-modal', $plugins, true)) {
            $plugins[] = 'confirm-modal';
        }

        $sessions = BankReconciliation::query()
            ->with('bank')
            ->orderByDesc('statement_date')
            ->orderByDesc('id')
            ->limit(200)
            ->get();
        $latestStatementDate = $sessions->first()?->statement_date;
        $defaultStatementDateYmd = $latestStatementDate
            ? (string) $latestStatementDate->format('Y-m-d')
            : now()->format('Y-m-d');
        $decimalPlaces = (int) Setting::get('accounting.decimal_places', config('accounting.decimal_places', 0));
        $decimalPlaces = max(0, min(4, $decimalPlaces));

        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('bank_reconciliation.workspace')
            ->withPlugins($plugins)
            ->withJs('vendor/accounting/admin/js/bank-reconciliation-workspace.js', true)
            ->withVariables([
                'banks' => Bank::query()->where('active', true)->orderBy('name')->get(),
                'sessions' => $sessions,
                'defaultStatementDateDisplay' => AccountingDateUi::gregorianYmdToInputDisplay($defaultStatementDateYmd),
                'attachmentMaxKb' => (int) config('accounting.attachments.max_size_kb', 10240),
                'decimalPlaces' => $decimalPlaces,
            ]);

        return $this->view();
    }

    public function openSession(Request $request, BankReconciliationWorkspaceService $service): JsonResponse
    {
        $validated = $request->validate([
            'bank_id' => 'required|integer|exists:banks,id',
            'statement_date' => 'required|string|max:64',
            'bank_statement_balance' => 'required|string|max:64',
            'notes' => 'nullable|string|max:2000',
        ]);

        $statementDate = $this->normalizeDate((string) $validated['statement_date']);
        $session = $service->openOrCreateSession([
            'bank_id' => (int) $validated['bank_id'],
            'statement_date' => $statementDate,
            'bank_statement_balance' => $this->normalizeAmountInput((string) $validated['bank_statement_balance']),
            'notes' => (string) ($validated['notes'] ?? ''),
        ], AuditActor::adminId());

        return response()->json([
            'success' => true,
            'session' => $this->sessionToArray($session),
        ]);
    }

    public function loadSession(Request $request, int|string $session, BankReconciliationWorkspaceService $service): JsonResponse
    {
        $model = $service->getSessionOrFail((int) $session);

        return response()->json([
            'success' => true,
            'session' => $this->sessionToArray($model),
        ]);
    }

    public function addItem(Request $request, int|string $session, BankReconciliationWorkspaceService $service): JsonResponse
    {
        $validated = $request->validate([
            'item_type' => 'required|string|in:' . implode(',', [
                BankReconciliationItem::TYPE_OUTSTANDING_CHEQUE,
                BankReconciliationItem::TYPE_DEPOSIT_IN_TRANSIT,
                BankReconciliationItem::TYPE_BANK_CHARGE,
                BankReconciliationItem::TYPE_INTEREST_INCOME,
                BankReconciliationItem::TYPE_MANUAL_ADJUSTMENT,
            ]),
            'amount' => 'required|string|max:64',
            'description' => 'nullable|string|max:500',
            'reference_type' => 'nullable|string|max:80',
            'reference_id' => 'nullable|integer',
            'reference_number' => 'nullable|string|max:120',
            'reference_date' => 'nullable|string|max:64',
        ]);

        $model = $service->getSessionOrFail((int) $session);
        $item = $service->addItem($model, [
            ...$validated,
            'amount' => $this->normalizeAmountInput((string) $validated['amount']),
            'reference_date' => isset($validated['reference_date']) && trim((string) $validated['reference_date']) !== ''
                ? $this->normalizeDate((string) $validated['reference_date'])
                : null,
        ], AuditActor::adminId());

        $model = $service->getSessionOrFail((int) $session);

        return response()->json([
            'success' => true,
            'item' => $this->itemToArray($item),
            'session' => $this->sessionToArray($model),
        ]);
    }

    public function removeItem(Request $request, int|string $session, int|string $itemId, BankReconciliationWorkspaceService $service): JsonResponse
    {
        $model = $service->getSessionOrFail((int) $session);
        $service->removeItem($model, (int) $itemId);
        $model = $service->getSessionOrFail((int) $session);

        return response()->json([
            'success' => true,
            'session' => $this->sessionToArray($model),
        ]);
    }

    public function deleteSession(Request $request, int|string $session, BankReconciliationWorkspaceService $service): JsonResponse
    {
        $model = $service->getSessionOrFail((int) $session);
        $service->deleteSession($model);

        return response()->json([
            'success' => true,
            'message' => trans('accounting::accounting.bank_reconciliation.flash_session_deleted'),
        ]);
    }

    public function validateSession(Request $request, int|string $session, BankReconciliationWorkspaceService $service): JsonResponse
    {
        $model = $service->getSessionOrFail((int) $session);
        $metrics = $service->validateSession($model);
        $model = $service->getSessionOrFail((int) $session);

        return response()->json([
            'success' => true,
            'metrics' => $metrics,
            'session' => $this->sessionToArray($model),
        ]);
    }

    public function finalizeSession(Request $request, int|string $session, BankReconciliationWorkspaceService $service): JsonResponse
    {
        $model = $service->getSessionOrFail((int) $session);
        $missingTags = $service->missingFinalizeAccountTags($model);
        if ($missingTags !== []) {
            $settingsUrl = route('admin.accounting.settings.index', [
                'settings_tab' => 'bank-reconciliation-tab',
                'settings_focus_tags' => implode(',', $missingTags),
                'account_setting_tag' => $missingTags[0],
            ]);

            return response()->json([
                'success' => false,
                'message' => trans('accounting::accounting.bank_reconciliation.errors.missing_finalize_accounts'),
                'settings_url' => $settingsUrl,
                'missing_tags' => $missingTags,
            ], 422);
        }
        $model = $service->finalizeSession($model, AuditActor::adminId());

        return response()->json([
            'success' => true,
            'session' => $this->sessionToArray($model),
            'message' => trans('accounting::accounting.bank_reconciliation.flash_finalized'),
        ]);
    }

    public function outstandingCheques(Request $request, int|string $session, BankReconciliationWorkspaceService $service): JsonResponse
    {
        $model = $service->getSessionOrFail((int) $session);
        $rows = $service->listOutstandingCheques($model);

        return response()->json([
            'success' => true,
            'rows' => $rows,
        ]);
    }

    public function depositsInTransit(Request $request, int|string $session, BankReconciliationWorkspaceService $service): JsonResponse
    {
        $model = $service->getSessionOrFail((int) $session);
        $rows = $service->listDepositsInTransit($model);

        return response()->json([
            'success' => true,
            'rows' => $rows,
        ]);
    }

    public function uploadAttachment(Request $request, int|string $session, AccountingAttachmentService $attachments, BankReconciliationWorkspaceService $service): JsonResponse
    {
        $maxKb = (int) config('accounting.attachments.max_size_kb', 10240);
        $validated = $request->validate([
            'file' => 'required|file|max:' . $maxKb,
        ]);

        $model = $service->getSessionOrFail((int) $session);
        $adminId = AuditActor::adminId();
        $attachment = $attachments->storeOrphan($validated['file'], $adminId);
        $attachment->attachable()->associate($model);
        $attachment->save();

        $model = $service->getSessionOrFail((int) $session);

        return response()->json([
            'success' => true,
            'session' => $this->sessionToArray($model),
            'message' => trans('accounting::accounting.attachments.upload_ok'),
        ]);
    }

    protected function normalizeDate(string $value): string
    {
        $normalized = app(AccountingDateInputNormalizer::class)->normalizeFilterDateToGregorian($value);
        if ($normalized === null) {
            throw ValidationException::withMessages([
                'statement_date' => (string) trans('accounting::errors.invalid_date'),
            ]);
        }

        return $normalized;
    }

    protected function normalizeAmountInput(string $value): string
    {
        $normalized = trim(\RMS\Helper\changeNumberToEn($value));
        $normalized = str_replace(['٬', '،', ',', ' '], '', $normalized);

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    protected function sessionToArray(BankReconciliation $session): array
    {
        return [
            'id' => (int) $session->id,
            'bank_id' => (int) $session->bank_id,
            'bank_name' => (string) ($session->bank?->label_for_select ?? $session->bank?->name ?? ''),
            'statement_date' => $session->statement_date ? (string) $session->statement_date->format('Y-m-d') : null,
            'book_balance' => (float) $session->book_balance,
            'bank_statement_balance' => (float) $session->bank_statement_balance,
            'adjusted_book_balance' => (float) $session->adjusted_book_balance,
            'adjusted_bank_balance' => (float) $session->adjusted_bank_balance,
            'difference_amount' => (float) $session->difference_amount,
            'status' => (string) $session->status,
            'is_balanced' => (bool) $session->is_balanced,
            'items' => $session->items->map(fn (BankReconciliationItem $item) => $this->itemToArray($item))->all(),
            'attachments' => $session->attachments->map(static fn ($att): array => [
                'uuid' => (string) $att->uuid,
                'name' => (string) $att->original_name,
                'download_url' => route('admin.accounting.attachments.download', $att->uuid),
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function itemToArray(BankReconciliationItem $item): array
    {
        $draft = $item->journalDrafts
            ->sortByDesc('id')
            ->first();
        $manualJournal = $draft?->manualJournal;

        return [
            'id' => (int) $item->id,
            'item_type' => (string) $item->item_type,
            'amount' => (float) $item->amount,
            'effect_side' => (string) $item->effect_side,
            'effect_sign' => (float) $item->effect_sign,
            'state' => (string) $item->state,
            'reference_type' => (string) ($item->reference_type ?? ''),
            'reference_id' => (int) ($item->reference_id ?? 0),
            'reference_number' => (string) ($item->reference_number ?? ''),
            'reference_date' => $item->reference_date ? (string) $item->reference_date->format('Y-m-d') : null,
            'description' => (string) ($item->description ?? ''),
            'journal' => (! $draft || ! $manualJournal) ? [] : [
                'id' => (int) $manualJournal->id,
                'number' => (string) ($manualJournal->journal_number ?? ('#' . $manualJournal->id)),
                'url' => route('admin.accounting.manual-journals.show', (int) $manualJournal->id),
                'posted_at' => $draft->posted_at ? (string) $draft->posted_at->format('Y-m-d H:i:s') : null,
            ],
            'has_journal_draft' => (bool) $draft,
        ];
    }
}

