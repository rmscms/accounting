<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RMS\Accounting\Models\Account;
use RMS\Accounting\Models\ExpenseCategory;
use RMS\Accounting\Services\ExpenseCategoryCodeService;
use RMS\Core\Data\Field;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\Actions\ChangeBoolField;

class ExpenseCategoriesController extends AccountingAdminController implements
    HasList,
    HasForm,
    ShouldFilter,
    ChangeBoolField
{
    protected ?ExpenseCategoryCodeService $expenseCategoryCodeService = null;

    protected function codeService(): ExpenseCategoryCodeService
    {
        return $this->expenseCategoryCodeService ??= app(ExpenseCategoryCodeService::class);
    }

    public function table(): string
    {
        return 'expense_categories';
    }

    /**
     * Join برای فیلدهای لیست با کلید نقطه‌ای (account.name، parent.name) — بدون join کوئری SQLite/MySQL خطا می‌دهد.
     */
    public function query(Builder $sql): void
    {
        $sql->leftJoin('accounts as account', 'account.id', '=', 'a.account_id')
            ->leftJoin('expense_categories as parent', 'parent.id', '=', 'a.parent_id');
    }

    public function modelName(): string
    {
        return ExpenseCategory::class;
    }

    public function baseRoute(): string
    {
        return 'accounting.expense-categories';
    }

    public function routeParameter(): string
    {
        return 'expense_category';
    }

    public function create(Request $request)
    {
        $this->configureExpenseCategoryFormView(null);

        return $this->view();
    }

    public function edit(Request $request, int|string $id)
    {
        $expense_category = ExpenseCategory::query()->findOrFail($id);
        $this->configureExpenseCategoryFormView($expense_category);

        return $this->view();
    }

    /**
     * اعتبارسنجی در مسیر PUT (ویرایش) — چون والد از Request خام استفاده می‌کند.
     */
    public function update(Request $request, int|string $id): RedirectResponse
    {
        $this->prepareForValidation($request);
        $this->validate($request, $this->rules());

        return parent::update($request, $id);
    }

    public function checkCode(Request $request, ExpenseCategoryCodeService $svc): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:500',
            'except_id' => 'nullable|integer|exists:expense_categories,id',
        ]);

        $normalized = $svc->normalize($validated['code']);
        $fmt = $svc->validateFormat($normalized);
        if (! $fmt['ok']) {
            return response()->json([
                'available' => false,
                'normalized' => $normalized,
                'message' => $fmt['message'],
            ]);
        }

        $exceptId = isset($validated['except_id']) ? (int) $validated['except_id'] : null;
        $available = $svc->isAvailable($normalized, $exceptId);

        return response()->json([
            'available' => $available,
            'normalized' => $normalized,
            'message' => $available
                ? null
                : trans('accounting::accounting.expense_category_form.code_taken'),
        ]);
    }

    protected function configureExpenseCategoryFormView(?ExpenseCategory $model = null): void
    {
        $svc = $this->codeService();
        $isEdit = $model !== null;
        $excludeId = $isEdit ? (int) $model->getKey() : null;

        $parentOptions = ['' => trans('accounting::accounting.expense_category_form.parent_root_label')]
            + $this->getParentCategoryOptions($excludeId);

        $this->view->reset();

        $this->view
            ->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('expense_categories.form')
            ->withPlugins(['advanced-select'])
            ->withJs('form.js')
            ->withJs('vendor/accounting/admin/js/expense-category-form.js', true)
            ->withCss('vendor/accounting/admin/css/expense-category-form.css', true)
            ->withJsVariables([
                'expenseCategoryCheckCodeUrl' => route('admin.accounting.expense-categories.check-code'),
                'expenseCategoryExceptId' => $excludeId,
                'expenseCategoryDebounceMs' => 400,
            ])
            ->withVariables([
                'model' => $model,
                'isEdit' => $isEdit,
                'parentOptions' => $parentOptions,
                'accountOptions' => $this->getExpenseAccountOptions(),
                'expenseCategoryConfig' => $svc->config(),
                'suggestedCode' => $svc->suggestNextCode(),
                'pageTitle' => $isEdit
                    ? trans('accounting::accounting.expense_category_form.edit_title')
                    : trans('accounting::accounting.expense_category_form.create_title'),
            ]);
    }

    public function getFieldsForm(): array
    {
        return [
            Field::string('code', trans('accounting::accounting.fields.category_code'))
                ->required()
                ->withHint(trans('accounting::accounting.hints.category_code')),

            Field::string('name', trans('accounting::accounting.fields.category_name'))
                ->required(),

            Field::select('parent_id', trans('accounting::accounting.fields.parent_category'))
                ->setOptions($this->getParentCategoryOptions())
                ->optional()
                ->withHint(trans('accounting::accounting.hints.parent_category')),

            Field::select('account_id', trans('accounting::accounting.fields.expense_account'))
                ->setOptions($this->getExpenseAccountOptions())
                ->required()
                ->withHint(trans('accounting::accounting.hints.expense_account')),

            Field::textarea('description', trans('accounting::accounting.fields.description'))
                ->optional(),

            Field::boolean('active', trans('accounting::accounting.fields.active'))
                ->withDefaultValue(true),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle('ID')->sortable()->width('80px'),
            Field::make('code')->withTitle(trans('accounting::accounting.fields.category_code'))->searchable()->sortable()->width('120px'),
            Field::make('name')->withTitle(trans('accounting::accounting.fields.category_name'))->searchable()->sortable(),
            Field::make('account.name')->withTitle(trans('accounting::accounting.fields.expense_account'))->width('200px'),
            Field::make('parent.name')->withTitle(trans('accounting::accounting.fields.parent_category'))->width('150px'),
            Field::boolean('active')->withTitle(trans('accounting::accounting.fields.status'))->sortable()->width('100px'),
        ];
    }

    public function prepareForValidation(Request &$request): void
    {
        parent::prepareForValidation($request);

        if ($request->has('code')) {
            $request->merge([
                'code' => $this->codeService()->normalize((string) $request->input('code', '')),
            ]);
        }

        $pid = $request->input('parent_id');
        if ($pid === '' || $pid === null) {
            $request->merge(['parent_id' => null]);
        }
    }

    protected function expenseCategoryRulesId(): ?int
    {
        $p = request()->route('expense_category');
        if ($p instanceof ExpenseCategory) {
            return (int) $p->getKey();
        }
        if ($p !== null && is_numeric($p)) {
            return (int) $p;
        }

        return null;
    }

    public function rules(): array
    {
        $id = $this->expenseCategoryRulesId();
        $svc = $this->codeService();

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('expense_categories', 'code')->ignore($id),
                function (string $attribute, mixed $value, \Closure $fail) use ($svc) {
                    $fmt = $svc->validateFormat((string) $value);
                    if (! $fmt['ok']) {
                        $fail($fmt['message'] ?? trans('accounting::accounting.expense_category_form.validation_code_pattern'));
                    }
                },
            ],
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:expense_categories,id'],
            'account_id' => ['required', 'exists:accounts,id'],
            'description' => ['nullable', 'string'],
            'active' => ['boolean'],
        ];
    }

    public function boolFields(): array
    {
        return ['active'];
    }

    protected function getParentCategoryOptions(?int $excludeId = null): array
    {
        $q = ExpenseCategory::query()
            ->whereNull('parent_id')
            ->orderBy('name');

        if ($excludeId !== null) {
            $q->where('id', '!=', $excludeId);
        }

        return $q->pluck('name', 'id')->toArray();
    }

    protected function getExpenseAccountOptions(): array
    {
        return Account::query()
            ->where('account_type', 'expense')
            ->where('level', '>=', 2)
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn ($account) => [$account->id => "{$account->code} - {$account->name}"])
            ->toArray();
    }
}
