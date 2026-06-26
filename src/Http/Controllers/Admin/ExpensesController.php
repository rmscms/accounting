<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RMS\Accounting\Http\Controllers\Admin\Concerns\ManagesCustomExpenseForm;
use RMS\Accounting\Models\Expense;
use RMS\Accounting\Support\AuditActor;
use RMS\Accounting\Support\AccountingDateUi;
use RMS\Accounting\Services\AccountingAttachmentService;
use RMS\Accounting\Services\ExpenseService;
use RMS\Accounting\Services\ExpenseStatusHistoryService;
use RMS\Core\Data\Field;
use RMS\Core\Requests\Store;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;

/**
 * فرم ایجاد/ویرایش هزینه با قالب اختصاصی accounting::admin.expenses.form (ثابت، غیر فرم پویا cms).
 */
class ExpensesController extends AccountingAdminController implements HasList, HasForm, ShouldFilter
{
    use ManagesCustomExpenseForm;

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
        return 'accounting.expenses';
    }

    public function routeParameter(): string
    {
        return 'expense';
    }

    public function create(Request $request)
    {
        [$suggested, $other] = $this->buildCategoryGroups();
        $treasury = $this->expenseTreasuryListsForForm();

        $this->title(trans('accounting::accounting.expense_create.title'));
        $this->use_package_namespace = true;
        $this->view->usePackageNamespace('accounting');
        $plugins = array_merge(
            AccountingDateUi::calendarMode() === AccountingDateUi::MODE_JALALI ? ['persian-datepicker'] : [],
            ['advanced-select', 'amount-formatter', 'image-uploader']
        );

        $this->view
            ->setTpl('expenses.form')
            ->withPlugins($plugins)
            ->withJs('vendor/accounting/admin/js/payment-destination-picker.js', true)
            ->withVariables([
                'isEdit' => false,
                'expense' => null,
                'suggestedCategories' => $suggested,
                'otherCategories' => $other,
                'expenseTypes' => $this->expenseTypeOptions(),
                'payeeTypes' => $this->payeeTypeOptions(),
                'statusOptions' => $this->statusOptions(),
                'defaultCurrency' => $this->resolveDefaultCurrencyCode(),
                'amountDecimalPlaces' => $this->resolveAccountingAmountDecimalPlaces(),
                'attachmentMaxKb' => (int) config('accounting.attachments.max_size_kb', 10240),
                'attachmentMaxPerExpense' => (int) config('accounting.attachments.max_per_expense', 5),
                'banks' => $treasury['banks'],
                'cashBoxes' => $treasury['cashBoxes'],
                'posTerminals' => $treasury['posTerminals'],
            ]);

        return $this->view();
    }

    /**
     * قوانین اعتبارسنجی فرم هزینه (برای FormRequest هسته و هم‌خوان با expenseFormValidationRules).
     */
    public function rules(): array
    {
        $maxKb = (int) config('accounting.attachments.max_size_kb', 10240);

        return array_merge($this->expenseFormValidationRules(), [
            'attachments' => ['nullable', 'array'],
            // نوع فایل در AccountingAttachmentService بررسی می‌شود (PDF با MIME نامعتبر / octet-stream)
            'attachments.*' => ['file', 'max:' . $maxKb],
            'attachment_uuids' => ['nullable', 'array'],
            'attachment_uuids.*' => ['string', 'uuid'],
            'keep_attachment_uuids' => ['nullable', 'array'],
            'keep_attachment_uuids.*' => ['string', 'uuid'],
        ]);
    }

    public function edit(Request $request, int|string $id)
    {
        $expense = Expense::with([
            'attachments',
            'statusHistories.admin',
            'bank',
            'cashBox',
            'posTerminal',
        ])->findOrFail($id);
        [$suggested, $other] = $this->buildCategoryGroups();
        $treasury = $this->expenseTreasuryListsForForm();

        $this->title(trans('accounting::accounting.expense_create.title_edit'));
        $this->use_package_namespace = true;
        $this->view->usePackageNamespace('accounting');
        $plugins = array_merge(
            AccountingDateUi::calendarMode() === AccountingDateUi::MODE_JALALI ? ['persian-datepicker'] : [],
            ['advanced-select', 'amount-formatter', 'image-uploader']
        );

        $this->view
            ->setTpl('expenses.form')
            ->withPlugins($plugins)
            ->withJs('vendor/accounting/admin/js/payment-destination-picker.js', true)
            ->withVariables([
                'isEdit' => true,
                'expense' => $expense,
                'suggestedCategories' => $suggested,
                'otherCategories' => $other,
                'expenseTypes' => $this->expenseTypeOptions(),
                'payeeTypes' => $this->payeeTypeOptions(),
                'statusOptions' => $this->statusOptions(),
                'defaultCurrency' => $this->resolveDefaultCurrencyCode(),
                'amountDecimalPlaces' => $this->resolveAccountingAmountDecimalPlaces(),
                'attachmentMaxKb' => (int) config('accounting.attachments.max_size_kb', 10240),
                'attachmentMaxPerExpense' => (int) config('accounting.attachments.max_per_expense', 5),
                'banks' => $treasury['banks'],
                'cashBoxes' => $treasury['cashBoxes'],
                'posTerminals' => $treasury['posTerminals'],
            ]);

        return $this->view();
    }

    public function store(Store $request): RedirectResponse
    {
        $this->normalizeExpensePaymentDestinationPayload($request);
        $attachmentService = app(AccountingAttachmentService::class);
        $data = $request->validated();
        unset($data['attachments'], $data['attachment_uuids'], $data['keep_attachment_uuids'], $data['payment_source_kind']);

        $amount = $this->parseDecimalAmount((string) $data['amount']);
        if ($amount === null || $amount <= 0) {
            return back()
                ->withInput()
                ->withErrors(['amount' => trans('accounting::accounting.expense_create.validation.amount_invalid')]);
        }

        try {
            DB::beginTransaction();
            $attrs = $this->buildExpenseAttributesFromValidated($data, true);
            $expense = Expense::create($attrs);
            app(ExpenseService::class)->ensureLedgerPosted($expense);
            $historyService = app(ExpenseStatusHistoryService::class);
            $adminId = AuditActor::adminId();
            $historyService->record(
                $expense,
                null,
                (string) $expense->status,
                $adminId ? (int) $adminId : null,
                null
            );
            $attachmentService->linkOrphansToExpense(
                $expense,
                array_values(array_filter((array) $request->input('attachment_uuids', []))),
                $adminId ? (int) $adminId : null
            );
            $attachmentService->storeManyForExpense(
                $expense,
                $this->normalizeUploadedFiles($request->file('attachments')),
                $adminId ? (int) $adminId : null
            );
            DB::commit();
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();
            if ($e->getMessage() === 'amount_invalid') {
                return back()
                    ->withInput()
                    ->withErrors(['amount' => trans('accounting::accounting.expense_create.validation.amount_invalid')]);
            }
            if ($e->getMessage() === 'expense_date_invalid') {
                return back()
                    ->withInput()
                    ->withErrors(['expense_date' => trans('accounting::errors.invalid_date')]);
            }
            if (in_array($e->getMessage(), ['file_too_large', 'file_type_not_allowed'], true)) {
                return back()
                    ->withInput()
                    ->withErrors(['attachments' => trans('accounting::accounting.attachments.' . $e->getMessage())]);
            }
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()
                ->withInput()
                ->withErrors(['_form' => trans('accounting::accounting.messages.expense_save_failed')]);
        }

        return redirect()
            ->route('admin.accounting.expenses.index')
            ->with('success', trans('accounting::accounting.messages.expense_saved'));
    }

    public function update(Request $request, int|string $id): RedirectResponse
    {
        $isApplyAction = (string) $request->input('_action', '') === 'apply';
        if ($isApplyAction) {
            $request->merge(['status' => Expense::STATUS_PAID]);
        }
        $this->normalizeExpensePaymentDestinationPayload($request);

        $attachmentService = app(AccountingAttachmentService::class);
        $expense = Expense::findOrFail($id);
        $prevStatus = (string) $expense->status;
        $prevBankId = $expense->bank_id;
        $prevCashBoxId = $expense->cash_box_id;
        $prevPosTerminalId = $expense->pos_terminal_id;
        $this->prepareForValidation($request);
        $data = Validator::make($request->all(), $this->rules())->validate();
        unset($data['attachments'], $data['attachment_uuids'], $data['keep_attachment_uuids'], $data['payment_source_kind']);

        $amount = $this->parseDecimalAmount((string) $data['amount']);
        if ($amount === null || $amount <= 0) {
            return back()
                ->withInput()
                ->withErrors(['amount' => trans('accounting::accounting.expense_create.validation.amount_invalid')]);
        }

        try {
            DB::beginTransaction();
            $attrs = $this->buildExpenseAttributesFromValidated($data, false);
            $expense->fill($attrs);
            $expense->save();
            app(ExpenseService::class)->ensureLedgerPosted($expense);

            $historyService = app(ExpenseStatusHistoryService::class);
            $adminId = AuditActor::adminId();
            $adminIdInt = $adminId ? (int) $adminId : null;
            $statusChanged = $prevStatus !== (string) $expense->status;
            $paymentChanged = ($prevBankId != $expense->bank_id)
                || ($prevCashBoxId != $expense->cash_box_id)
                || ($prevPosTerminalId != $expense->pos_terminal_id);
            if ($statusChanged) {
                $historyService->record($expense, $prevStatus, (string) $expense->status, $adminIdInt, null);
            } elseif ($expense->status === Expense::STATUS_PAID && $paymentChanged) {
                $historyService->record(
                    $expense,
                    $prevStatus,
                    (string) $expense->status,
                    $adminIdInt,
                    trans('accounting::accounting.expense_create.history_note_payment_source')
                );
            }

            if ($request->has('keep_attachment_uuids')) {
                $keep = array_values(array_filter((array) $request->input('keep_attachment_uuids', [])));
            } else {
                $keep = $expense->attachments()->pluck('uuid')->all();
            }
            $attachmentService->syncExpenseAttachments(
                $expense,
                $keep,
                $this->normalizeUploadedFiles($request->file('attachments')),
                $adminId ? (int) $adminId : null
            );
            $attachmentService->linkOrphansToExpense(
                $expense,
                array_values(array_filter((array) $request->input('attachment_uuids', []))),
                $adminId ? (int) $adminId : null
            );
            DB::commit();
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();
            if ($e->getMessage() === 'amount_invalid') {
                return back()
                    ->withInput()
                    ->withErrors(['amount' => trans('accounting::accounting.expense_create.validation.amount_invalid')]);
            }
            if ($e->getMessage() === 'expense_date_invalid') {
                return back()
                    ->withInput()
                    ->withErrors(['expense_date' => trans('accounting::errors.invalid_date')]);
            }
            if (in_array($e->getMessage(), ['file_too_large', 'file_type_not_allowed'], true)) {
                return back()
                    ->withInput()
                    ->withErrors(['attachments' => trans('accounting::accounting.attachments.' . $e->getMessage())]);
            }
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()
                ->withInput()
                ->withErrors(['_form' => trans('accounting::accounting.messages.expense_save_failed')]);
        }

        if ($isApplyAction) {
            return redirect()
                ->back()
                ->with('success', trans('accounting::accounting.expense_create.apply_success'));
        }

        return redirect()
            ->route('admin.accounting.expenses.index')
            ->with('success', trans('accounting::accounting.messages.expense_updated'));
    }

    /**
     * @return array<int, UploadedFile>
     */
    protected function normalizeUploadedFiles(array|UploadedFile|null $files): array
    {
        if ($files === null) {
            return [];
        }
        if ($files instanceof UploadedFile) {
            return $files->isValid() ? [$files] : [];
        }

        return array_values(array_filter($files, fn ($f) => $f instanceof UploadedFile && $f->isValid()));
    }

    /**
     * اضافه کردن join به expense_categories برای نمایش دسته‌بندی
     */
    public function query(Builder $sql): void
    {
        $sql->leftJoin('expense_categories', 'expense_categories.id', '=', 'a.expense_category_id')
            ->addSelect(
                'a.*',
                'expense_categories.name as category_name'
            );
    }

    public function getFieldsForm(): array
    {
        return [
            Field::string('expense_number', trans('accounting::accounting.expense.expense_number'))->required(),
            Field::date('expense_date', trans('accounting::accounting.expense.expense_date'))->required(),
            Field::number('expense_category_id', trans('accounting::accounting.expense.category'))->required(),
            Field::number('amount', trans('accounting::accounting.expense.amount'))->required(),
            Field::textarea('description', trans('accounting::accounting.expense.description'))->optional(),
            Field::select('status', trans('accounting::accounting.expense.status'))
                ->setOptions([
                    'draft' => trans('accounting::accounting.statuses.draft'),
                    'pending' => trans('accounting::accounting.statuses.pending'),
                    'approved' => trans('accounting::accounting.statuses.approved'),
                    'rejected' => trans('accounting::accounting.statuses.rejected'),
                    'paid' => trans('accounting::accounting.statuses.paid'),
                ])
                ->withDefaultValue('draft')
                ->required(),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id', 'a.id')->withTitle(trans('accounting::accounting.common.id'))->sortable()->width('80px'),
            Field::make('expense_number')->withTitle(trans('accounting::accounting.expense.expense_number'))->searchable()->sortable()->width('150px'),
            Field::make('expense_date')->withTitle(trans('accounting::accounting.expense.expense_date'))->sortable()->width('120px'),

            Field::make('category_name', 'expense_categories.name')
                ->withTitle(trans('accounting::accounting.expense.category'))
                ->customMethod('renderCategoryName')
                ->searchable()
                ->width('150px'),

            Field::make('amount')
                ->withTitle(trans('accounting::accounting.expense.amount'))
                ->customMethod('renderAmount')
                ->sortable()
                ->width('150px'),

            Field::make('status')
                ->withTitle(trans('accounting::accounting.expense.status'))
                ->customMethod('renderStatusBadge')
                ->sortable()
                ->width('120px'),

            Field::make('created_at')->withTitle(trans('accounting::accounting.common.created_at'))->sortable()->width('150px'),
        ];
    }

    public function filters(): array
    {
        return [
            Field::select('status', trans('accounting::accounting.expense.status'))
                ->setOptions([
                    '' => trans('accounting::accounting.common.all'),
                    'draft' => trans('accounting::accounting.statuses.draft'),
                    'pending' => trans('accounting::accounting.statuses.pending'),
                    'approved' => trans('accounting::accounting.statuses.approved'),
                    'rejected' => trans('accounting::accounting.statuses.rejected'),
                    'paid' => trans('accounting::accounting.statuses.paid'),
                ]),
        ];
    }

    /**
     * نمایش نام دسته‌بندی
     */
    public function renderCategoryName($row): string
    {
        if (!$row->expense_category_id) {
            return '<span class="text-muted">-</span>';
        }

        $name = e($row->category_name ?: '#'.$row->expense_category_id);
        return '<span>'.$name.'</span>';
    }

    /**
     * نمایش مبلغ با فرمت
     */
    public function renderAmount($row): string
    {
        $p = $this->resolveAccountingAmountDecimalPlaces();
        $amount = number_format((float) $row->amount, $p, '.', ',');

        return '<span class="fw-semibold text-danger">'.$amount.' <small class="text-muted">تومان</small></span>';
    }

    /**
     * نمایش وضعیت با badge رنگی
     */
    public function renderStatusBadge($row): string
    {
        $statusMap = [
            'draft' => [
                'label' => trans('accounting::accounting.statuses.draft'),
                'class' => 'bg-secondary'
            ],
            'pending' => [
                'label' => trans('accounting::accounting.statuses.pending'),
                'class' => 'bg-warning'
            ],
            'approved' => [
                'label' => trans('accounting::accounting.statuses.approved'),
                'class' => 'bg-info'
            ],
            'paid' => [
                'label' => trans('accounting::accounting.statuses.paid'),
                'class' => 'bg-success'
            ],
            'rejected' => [
                'label' => trans('accounting::accounting.statuses.rejected'),
                'class' => 'bg-danger'
            ],
            'cancelled' => [
                'label' => trans('accounting::accounting.statuses.cancelled'),
                'class' => 'bg-dark'
            ],
        ];

        $status = (string)($row->status ?? 'draft');
        $info = $statusMap[$status] ?? ['label' => $status, 'class' => 'bg-secondary'];
        $label = e($info['label']);
        $class = $info['class'];

        return '<span class="badge '.$class.'">'.$label.'</span>';
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
        $expense->approved_by_user_id = AuditActor::userId();
        $expense->approved_at = now();
        $expense->save();

        return redirect()->back()->with('success', trans('accounting::accounting.messages.expense_approved'));
    }

    protected function normalizeExpensePaymentDestinationPayload(Request $request): void
    {
        $status = (string) $request->input('status', '');
        if ($status !== Expense::STATUS_PAID) {
            $request->merge([
                'bank_id' => null,
                'cash_box_id' => null,
                'pos_terminal_id' => null,
                'payment_source_kind' => null,
            ]);

            return;
        }

        $bankId = (int) $request->input('expense_paid_at_source_bank_id', 0);
        $cashBoxId = (int) $request->input('expense_paid_at_source_cash_box_id', 0);
        $posTerminalId = (int) $request->input('expense_paid_at_source_pos_terminal_id', 0);

        $kind = null;
        if ($cashBoxId > 0) {
            $kind = 'cash_box';
        } elseif ($bankId > 0) {
            $kind = 'bank';
        } elseif ($posTerminalId > 0) {
            $kind = 'pos_terminal';
        }

        $request->merge([
            'bank_id' => $bankId > 0 ? $bankId : null,
            'cash_box_id' => $cashBoxId > 0 ? $cashBoxId : null,
            'pos_terminal_id' => $posTerminalId > 0 ? $posTerminalId : null,
            'payment_source_kind' => $kind,
        ]);
    }
}
