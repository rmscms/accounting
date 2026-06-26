<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use RMS\Accounting\Http\Controllers\Admin\Concerns\RendersAccountingStructuredResourceForm;
use RMS\Accounting\Models\Account;
use RMS\Accounting\Models\ManualJournal;
use RMS\Accounting\Models\ManualJournalLine;
use RMS\Accounting\Services\AccountingDateInputNormalizer;
use RMS\Accounting\Services\ManualJournalService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Validation\ValidationException;
use RMS\Core\Data\Field;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;

class ManualJournalsController extends AccountingAdminController implements
    HasList,
    HasForm,
    ShouldFilter
{
    use RendersAccountingStructuredResourceForm;

    protected ManualJournalService $journalService;

    public function __construct(Filesystem $filesystem, ManualJournalService $journalService)
    {
        parent::__construct($filesystem);
        $this->journalService = $journalService;
    }

    /**
     * ویرایش سند دستی: بارگذاری JS/CSS سطرها از vendor (پس از publish با tag=accounting-assets).
     */
    public function edit(Request $request, int|string $id)
    {
        $model = $this->modelOrFail($id);
        if ($model instanceof ManualJournal) {
            $this->view->withCss('vendor/accounting/admin/css/manual-journal-lines.css', true);
            if (($model->status ?? '') === 'draft') {
                $this->view->withPlugins(['confirm-modal']);
                $this->view->withJs('vendor/accounting/admin/js/manual-journal-lines.js', true);
            } else {
                $this->view->withPlugins(['confirm-modal']);
                $this->view->withJs('vendor/accounting/admin/js/manual-journal-actions.js', true);
            }
        }

        return $this->renderAccountingStructuredResourceForm($request, true, $model);
    }

    public function table(): string
    {
        return 'manual_journals';
    }

    public function modelName(): string
    {
        return ManualJournal::class;
    }

    public function baseRoute(): string
    {
        return 'accounting.manual-journals';
    }

    public function routeParameter(): string
    {
        return 'manual_journal';
    }

    /**
     * پس از اولین ذخیره (create) یا به‌روزرسانی، به ویرایش همان سند برگردد تا سطرها را بدون رفتن به لیست اضافه کنید.
     */
    protected function getRedirectResponse(Request $request, int|string $id): RedirectResponse
    {
        return redirect()->route(
            $this->accountingNamedRoute('edit'),
            [$this->routeParameter() => $id]
        )->with('success', trans('admin.success_action'));
    }

    public function getFieldsForm(): array
    {
        return [
            Field::date('journal_date', 'تاریخ سند')
                ->withDefaultValue(now())
                ->required(),

            Field::textarea('description', 'شرح سند', 3)
                ->required(),

            Field::textarea('notes', 'یادداشت', 2)
                ->optional(),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle('شناسه')->sortable()->width('80px'),
            Field::make('journal_number')->withTitle('شماره سند')->searchable()->sortable()->width('150px'),
            Field::make('journal_date')->withTitle('تاریخ')->sortable()->width('120px'),
            Field::make('description')->withTitle('شرح')->searchable(),
            Field::number('total_debit')->withTitle('جمع بدهکار')->sortable()->width('130px'),
            Field::number('total_credit')->withTitle('جمع بستانکار')->sortable()->width('130px'),
            Field::make('status')->withTitle('وضعیت')->customMethod('renderStatus')->sortable()->width('120px'),
        ];
    }

    public function rules(): array
    {
        return [
            'journal_date' => ['required', 'string', 'max:64'],
            'description' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * {@inheritDoc}
     *
     * فیلدهای شماره سند و سال مالی در فرم نیستند؛ قبل از ذخیره پر می‌شوند و اینجا به لیست فیلدهای دیتابیس اضافه می‌گردند.
     */
    protected function filterDatabaseFields(array $requestData): array
    {
        $filtered = parent::filterDatabaseFields($requestData);
        foreach (['journal_number', 'fiscal_year_id'] as $key) {
            if (! array_key_exists($key, $requestData)) {
                continue;
            }
            $value = $requestData[$key];
            if ($value !== null && $value !== '') {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

    /**
     * {@inheritDoc}
     */
    protected function beforeAdd(Request &$request): void
    {
        parent::beforeAdd($request);

        $rawDate = $request->input('journal_date');
        if (is_string($rawDate) && trim($rawDate) !== '') {
            $gregorian = app(AccountingDateInputNormalizer::class)->normalizeFilterDateToGregorian(trim($rawDate));
            if ($gregorian !== null) {
                $request->merge(['journal_date' => $gregorian]);
                $rawDate = $gregorian;
            }
        }

        if (! $request->filled('journal_number')) {
            $request->merge(['journal_number' => ManualJournal::generateJournalNumber()]);
        }

        if (! $request->filled('fiscal_year_id')) {
            try {
                $dateForFy = (is_string($rawDate) && trim($rawDate) !== '') ? trim($rawDate) : null;
                $request->merge([
                    'fiscal_year_id' => $this->journalService->resolveFiscalYearIdForJournalDate($dateForFy),
                ]);
            } catch (\Exception $e) {
                throw ValidationException::withMessages([
                    'journal_date' => [$e->getMessage()],
                ]);
            }
        }
    }

    public function post(Request $request, $id): RedirectResponse|JsonResponse
    {
        try {
            $this->journalService->postJournal($id);
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'message' => (string) trans('accounting::accounting.manual_journal_lines.posted_success'),
                    'redirect' => route('admin.accounting.manual-journals.edit', $id),
                ]);
            }

            return redirect()->back()->with('success', (string) trans('accounting::accounting.manual_journal_lines.posted_success'));
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function reverse(Request $request, $id)
    {
        $request->validate([
            'reason' => ['required', 'string'],
        ]);

        try {
            $this->journalService->reverseJournal($id, $request->reason);
            return redirect()->back()->with('success', 'سند برگشت خورد');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function duplicate(Request $request, $id)
    {
        try {
            $newJournal = $this->journalService->duplicateJournal($id);
            return redirect()->route('admin.accounting.manual-journals.edit', $newJournal->id)
                ->with('success', 'سند کپی شد');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function storeLine(Request $request, int|string $id): RedirectResponse|JsonResponse
    {
        $journal = ManualJournal::query()->findOrFail($id);
        if ($journal->status !== 'draft') {
            return $this->manualJournalLineFailureResponse(
                $request,
                trans('accounting::accounting.manual_journal_lines.not_draft')
            );
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'debit_amount' => ['nullable', 'string', 'max:40'],
            'credit_amount' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return $this->manualJournalLineValidationErrorResponse($request, $validator);
        }

        $debitRaw = trim((string) $request->input('debit_amount', ''));
        $creditRaw = trim((string) $request->input('credit_amount', ''));
        $debit = $this->parseDecimalAmount($debitRaw === '' ? '0' : $debitRaw) ?? 0.0;
        $credit = $this->parseDecimalAmount($creditRaw === '' ? '0' : $creditRaw) ?? 0.0;

        if ($debit <= 0.0 && $credit <= 0.0) {
            return $this->manualJournalLineAmountRuleErrorResponse(
                $request,
                'debit_amount',
                trans('accounting::accounting.manual_journal_lines.validation_need_one_side')
            );
        }
        if ($debit > 0.0000001 && $credit > 0.0000001) {
            return $this->manualJournalLineAmountRuleErrorResponse(
                $request,
                'debit_amount',
                trans('accounting::accounting.manual_journal_lines.validation_one_side_only')
            );
        }

        try {
            $line = $this->journalService->addLine((int) $journal->getKey(), [
                'account_id' => (int) $request->input('account_id'),
                'debit_amount' => round($debit, 4),
                'credit_amount' => round($credit, 4),
                'description' => $request->input('description'),
                'currency_code' => $this->resolveDefaultCurrencyCode(),
            ]);
        } catch (\Throwable $e) {
            return $this->manualJournalLineFailureResponse($request, $e->getMessage(), true);
        }

        $journal->refresh();

        return $this->manualJournalLineSuccessResponse(
            $request,
            trans('accounting::accounting.manual_journal_lines.line_added'),
            $line->fresh(['account']),
            $journal
        );
    }

    public function updateLine(Request $request, int|string $id, int|string $line): RedirectResponse|JsonResponse
    {
        $journal = ManualJournal::query()->findOrFail($id);
        if ($journal->status !== 'draft') {
            return $this->manualJournalLineFailureResponse(
                $request,
                trans('accounting::accounting.manual_journal_lines.not_draft')
            );
        }

        ManualJournalLine::query()
            ->where('manual_journal_id', $journal->getKey())
            ->whereKey($line)
            ->firstOrFail();

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'debit_amount' => ['nullable', 'string', 'max:40'],
            'credit_amount' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return $this->manualJournalLineValidationErrorResponse($request, $validator);
        }

        $debitRaw = trim((string) $request->input('debit_amount', ''));
        $creditRaw = trim((string) $request->input('credit_amount', ''));
        $debit = $this->parseDecimalAmount($debitRaw === '' ? '0' : $debitRaw) ?? 0.0;
        $credit = $this->parseDecimalAmount($creditRaw === '' ? '0' : $creditRaw) ?? 0.0;

        if ($debit <= 0.0 && $credit <= 0.0) {
            return $this->manualJournalLineAmountRuleErrorResponse(
                $request,
                'debit_amount',
                trans('accounting::accounting.manual_journal_lines.validation_need_one_side')
            );
        }
        if ($debit > 0.0000001 && $credit > 0.0000001) {
            return $this->manualJournalLineAmountRuleErrorResponse(
                $request,
                'debit_amount',
                trans('accounting::accounting.manual_journal_lines.validation_one_side_only')
            );
        }

        try {
            $updated = $this->journalService->updateLine((int) $journal->getKey(), (int) $line, [
                'account_id' => (int) $request->input('account_id'),
                'debit_amount' => round($debit, 4),
                'credit_amount' => round($credit, 4),
                'description' => $request->input('description'),
            ]);
        } catch (\Throwable $e) {
            return $this->manualJournalLineFailureResponse($request, $e->getMessage(), true);
        }

        $journal->refresh();

        return $this->manualJournalLineSuccessResponse(
            $request,
            trans('accounting::accounting.manual_journal_lines.line_updated'),
            $updated,
            $journal
        );
    }

    public function destroyLine(Request $request, int|string $id, int|string $line): RedirectResponse|JsonResponse
    {
        $journal = ManualJournal::query()->findOrFail($id);
        if ($journal->status !== 'draft') {
            return $this->manualJournalLineFailureResponse(
                $request,
                trans('accounting::accounting.manual_journal_lines.not_draft')
            );
        }

        ManualJournalLine::query()
            ->where('manual_journal_id', $journal->getKey())
            ->whereKey($line)
            ->firstOrFail();

        try {
            $this->journalService->deleteLine((int) $journal->getKey(), (int) $line);
        } catch (\Throwable $e) {
            return $this->manualJournalLineFailureResponse($request, $e->getMessage());
        }

        $journal->refresh();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => (string) trans('accounting::accounting.manual_journal_lines.line_deleted'),
                'removed_line_id' => (int) $line,
                'journal' => $this->manualJournalTotalsPayload($journal),
            ]);
        }

        return redirect()->back()->with('success', trans('accounting::accounting.manual_journal_lines.line_deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function manualJournalLineToArray(ManualJournalLine $line): array
    {
        $line->loadMissing('account');

        return [
            'id' => (int) $line->getKey(),
            'line_number' => (int) $line->line_number,
            'account_id' => (int) $line->account_id,
            'account_code' => $line->account !== null ? (string) $line->account->code : '',
            'account_name' => $line->account !== null ? (string) $line->account->name : '',
            'debit_amount' => (string) $line->debit_amount,
            'credit_amount' => (string) $line->credit_amount,
            'description' => $line->description !== null ? (string) $line->description : '',
        ];
    }

    /**
     * @return array<string, float|string>
     */
    protected function manualJournalTotalsPayload(ManualJournal $journal): array
    {
        return [
            'total_debit' => (float) $journal->total_debit,
            'total_credit' => (float) $journal->total_credit,
        ];
    }

    protected function manualJournalLineSuccessResponse(
        Request $request,
        string $message,
        ManualJournalLine $line,
        ManualJournal $journal
    ): RedirectResponse|JsonResponse {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'line' => $this->manualJournalLineToArray($line),
                'journal' => $this->manualJournalTotalsPayload($journal),
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    protected function manualJournalLineFailureResponse(
        Request $request,
        string $message,
        bool $withInput = false
    ): RedirectResponse|JsonResponse {
        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'message' => $message], 422);
        }

        if ($withInput) {
            return redirect()->back()->withInput()->with('error', $message);
        }

        return redirect()->back()->with('error', $message);
    }

    protected function manualJournalLineValidationErrorResponse(
        Request $request,
        \Illuminate\Contracts\Validation\Validator $validator
    ): RedirectResponse|JsonResponse {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'message' => (string) trans('accounting::accounting.manual_journal_lines.validation_failed'),
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        return redirect()->back()->withErrors($validator)->withInput();
    }

    protected function manualJournalLineAmountRuleErrorResponse(
        Request $request,
        string $field,
        string $message
    ): RedirectResponse|JsonResponse {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'message' => $message,
                'errors' => [$field => [$message]],
            ], 422);
        }

        return redirect()->back()->withInput()->withErrors([$field => $message]);
    }

    /**
     * {@inheritDoc}
     */
    protected function structuredAccountingFormExtraViewVariables(Request $request, bool $isEdit, ?Model $model): array
    {
        if (! $model instanceof ManualJournal || ! $isEdit) {
            return [];
        }

        $model->loadMissing([
            'lines' => fn ($q) => $q->orderBy('line_number'),
            'lines.account',
        ]);

        $jid = $model->getKey();
        $reversedByJournalId = (int) ($model->reversed_journal_id ?? 0);
        $reversalOfJournalId = (int) (ManualJournal::query()
            ->where('reversed_journal_id', $jid)
            ->value('id') ?? 0);
        $draftAccounts = $model->status === 'draft'
            ? $this->accountsForManualJournalLineSelect()
            : [];

        return [
            'manualJournalAccountsSelect' => $draftAccounts,
            'manualJournalLineStoreRoute' => $model->status === 'draft'
                ? route('admin.accounting.manual-journals.lines.store', ['id' => $jid])
                : null,
            'manualJournalReversedByJournalId' => $reversedByJournalId > 0 ? $reversedByJournalId : null,
            'manualJournalReversalOfJournalId' => $reversalOfJournalId > 0 ? $reversalOfJournalId : null,
            'manualJournalReverseRoute' => $model->status === 'posted' && $reversedByJournalId <= 0 && $reversalOfJournalId <= 0
                ? route('admin.accounting.manual-journals.reverse', ['id' => $jid])
                : null,
            'manualJournalReverseDefaultReason' => $model->status === 'posted' && $reversedByJournalId <= 0 && $reversalOfJournalId <= 0
                ? (string) trans('accounting::accounting.manual_journal_lines.reverse_reason_default')
                : null,
            'manualJournalAjax' => $model->status === 'draft'
                ? [
                    'csrf' => csrf_token(),
                    'decimals' => (int) $this->resolveAccountingAmountDecimalPlaces(),
                    'urls' => [
                        'storeLine' => route('admin.accounting.manual-journals.lines.store', ['id' => $jid]),
                        'updateLineTpl' => route('admin.accounting.manual-journals.lines.update', ['id' => $jid, 'line' => '__MJ_LINE__']),
                        'destroyLineTpl' => route('admin.accounting.manual-journals.lines.destroy', ['id' => $jid, 'line' => '__MJ_LINE__']),
                        'post' => route('admin.accounting.manual-journals.post', ['id' => $jid]),
                    ],
                    'i18n' => [
                        'confirmDelete' => (string) trans('accounting::accounting.manual_journal_lines.confirm_delete'),
                        'confirmDeleteTitle' => (string) trans('accounting::accounting.manual_journal_lines.confirm_delete_title'),
                        'confirmDeleteBtn' => (string) trans('accounting::accounting.manual_journal_lines.confirm_delete_btn'),
                        'confirmPost' => (string) trans('accounting::accounting.manual_journal_lines.confirm_post'),
                        'confirmPostTitle' => (string) trans('accounting::accounting.manual_journal_lines.confirm_post_title'),
                        'confirmPostBtn' => (string) trans('accounting::accounting.manual_journal_lines.confirm_post_btn'),
                        'btnEdit' => (string) trans('accounting::accounting.manual_journal_lines.btn_edit'),
                        'btnSave' => (string) trans('accounting::accounting.manual_journal_lines.btn_save'),
                        'btnCancel' => (string) trans('accounting::accounting.manual_journal_lines.btn_cancel'),
                        'btnDelete' => (string) trans('accounting::accounting.manual_journal_lines.btn_delete'),
                        'genericError' => (string) trans('accounting::accounting.manual_journal_lines.ajax_generic_error'),
                        'emptyLinesRow' => (string) trans('accounting::accounting.manual_journal_lines.empty_lines'),
                    ],
                ]
                : null,
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function accountsForManualJournalLineSelect(): array
    {
        return Account::query()
            ->where('active', true)
            ->where('level', '>=', Account::LEVEL_SUBSIDIARY)
            ->orderBy('code')
            ->limit(5000)
            ->get(['id', 'code', 'name'])
            ->map(static fn (Account $a): array => [
                'value' => (string) $a->getKey(),
                'label' => $a->code.' — '.$a->name,
            ])
            ->all();
    }

    public function renderStatus($row): string
    {
        $badges = [
            // Bootstrap 5 + خوانایی در تم روشن/تاریک (نه کلاس‌های قدیمی badge-*)
            'draft' => '<span class="badge bg-secondary rounded-pill">پیش‌نویس</span>',
            'posted' => '<span class="badge bg-success rounded-pill">ثبت شده</span>',
            'reversed' => '<span class="badge bg-danger rounded-pill">برگشت خورده</span>',
        ];

        $status = is_string($row->status ?? null) ? $row->status : '';

        return $badges[$status] ?? '<span class="badge bg-light text-dark border rounded-pill">'.e($status).'</span>';
    }
}
