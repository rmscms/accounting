<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use RMS\Accounting\Http\Controllers\Admin\Concerns\RendersAccountingStructuredResourceForm;
use RMS\Accounting\Models\Currency;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RMS\Core\Data\Field;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\Actions\ChangeBoolField;

class CurrenciesController extends AccountingAdminController implements
    HasList,
    HasForm,
    ShouldFilter,
    ChangeBoolField
{
    use RendersAccountingStructuredResourceForm;

    public function table(): string
    {
        return 'currencies';
    }

    public function modelName(): string
    {
        return Currency::class;
    }

    public function baseRoute(): string
    {
        return 'accounting.currencies';
    }

    public function routeParameter(): string
    {
        return 'currency';
    }

    public function edit(Request $request, string|int $id): View|RedirectResponse
    {
        $resolved = $this->resolveCurrencyRouteKey($id);
        if ((string) $resolved !== (string) $id) {
            return redirect()->route('admin.accounting.currencies.edit', ['currency' => $resolved]);
        }

        $currency = Currency::query()->findOrFail((string) $resolved);

        return $this->renderCurrencyFormView($currency, true);
    }

    public function index(Request $request)
    {
        $currencies = Currency::query()
            ->orderByDesc('is_base')
            ->orderBy('code')
            ->get();
        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('currencies.index')
            ->withVariables([
                'htmlPageTitle' => trans('accounting::accounting.currency.title'),
                'currencies' => $currencies,
            ]);

        return $this->view();
    }

    public function create(Request $request)
    {
        return $this->renderCurrencyFormView(new Currency(), false);
    }

    public function getFieldsForm(): array
    {
        return [
            Field::string('code', trans('accounting::accounting.currency.code'))
                ->required(),

            Field::string('name', trans('accounting::accounting.currency.name'))
                ->required(),

            Field::string('symbol', trans('accounting::accounting.currency.symbol'))
                ->optional(),

            Field::number('decimals', trans('accounting::accounting.currency.decimal_places'))
                ->withDefaultValue(0)
                ->required(),

            Field::boolean('is_base', trans('accounting::accounting.currency.is_base'))
                ->withDefaultValue(false),

            Field::boolean('active', trans('accounting::accounting.currency.is_active'))
                ->withDefaultValue(true),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('code')->withTitle(trans('accounting::accounting.currency.code'))->searchable()->sortable()->width('130px'),
            Field::make('name')->withTitle(trans('accounting::accounting.currency.name'))->searchable()->sortable(),
            Field::make('symbol')->withTitle(trans('accounting::accounting.currency.symbol'))->width('100px'),
            Field::make('decimals')->withTitle(trans('accounting::accounting.currency.decimal_places'))->sortable()->width('120px'),
            Field::make('is_base')->withTitle(trans('accounting::accounting.currency.is_base'))->width('120px'),
            Field::boolean('active')->withTitle(trans('accounting::accounting.common.status'))->sortable()->width('100px'),
            Field::date('updated_at')->withTitle(trans('accounting::accounting.common.updated_at'))->sortable()->width('150px'),
        ];
    }

    public function rules(): array
    {
        $routeCode = request()->route('currency');
        $currentCode = is_object($routeCode) ? (string) data_get($routeCode, 'code', '') : (string) ($routeCode ?? '');

        return [
            'code' => ['required', 'string', 'max:10', Rule::unique('currencies', 'code')->ignore($currentCode, 'code')],
            'name' => ['required', 'string', 'max:100'],
            'symbol' => ['nullable', 'string', 'max:10'],
            'decimals' => ['required', 'integer', 'min:0', 'max:6'],
            'is_base' => ['boolean'],
            'active' => ['boolean'],
        ];
    }

    public function boolFields(): array
    {
        return ['active'];
    }

    public function beforeAdd(Request &$request): void
    {
        if ($request->boolean('is_base')) {
            Currency::query()->where('is_base', true)->update(['is_base' => false]);
        }
    }

    public function beforeUpdate(Request &$request, string|int $id): void
    {
        if ($request->boolean('is_base')) {
            Currency::query()->where('code', '!=', (string) $id)->where('is_base', true)->update(['is_base' => false]);
        }
    }

    protected function resolveCurrencyRouteKey(string|int $id): string|int
    {
        $raw = (string) $id;
        if ($raw === '' || ! ctype_digit($raw) || Currency::query()->whereKey($raw)->exists()) {
            return $id;
        }

        $driver = (string) DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $code = DB::table('currencies')
                ->whereRaw('rowid = ?', [(int) $raw])
                ->value('code');
            if (is_string($code) && trim($code) !== '') {
                return strtoupper(trim($code));
            }
        }

        return $id;
    }

    protected function renderCurrencyFormView(Currency $currency, bool $isEdit)
    {
        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('currencies.form')
            ->withVariables([
                'htmlPageTitle' => trans('accounting::accounting.currency.title'),
                'isEdit' => $isEdit,
                'currency' => $currency,
            ]);

        return $this->view();
    }
}
