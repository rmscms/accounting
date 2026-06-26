<?php

namespace RMS\Accounting\Http\Controllers\Admin\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use RMS\Accounting\Services\AccountingDateInputNormalizer;
use RMS\Accounting\Support\AccountingDateUi;
use RMS\Accounting\Support\AccountingFormFieldExporter;
use RMS\Core\Data\Field;

/**
 * فرم create/edit اختصاصی از روی getFieldsForm() با قالب Blade یکسان و تمایز نمایهٔ بصری (catalog / entity / document / treasury).
 */
trait RendersAccountingStructuredResourceForm
{
    use ParsesAccountingMoneyInput;

    /**
     * متغیرهای اضافه برای قالب فرم ساختاریافته (مثلاً سطرهای سند دستی).
     *
     * @return array<string, mixed>
     */
    protected function structuredAccountingFormExtraViewVariables(Request $request, bool $isEdit, ?Model $model): array
    {
        return [];
    }

    /**
     * پس از تنظیم کامل view فرم ساختاریافته و قبل از رندر (برای attach کردن JS/CSS اختصاصی).
     */
    protected function afterConfigureStructuredAccountingFormView(Request $request, bool $isEdit, ?Model $model): void
    {
    }

    /**
     * انتهای baseRoute پس از «accounting.»؛ برای کلید ترجمهٔ structured_resource_forms.
     */
    protected function structuredAccountingFormSlug(): string
    {
        $base = $this->baseRoute();
        $tail = str_starts_with($base, 'accounting.') ? substr($base, strlen('accounting.')) : $base;

        return str_replace('-', '_', $tail);
    }

    protected function structuredAccountingFormProfile(): string
    {
        return match ($this->structuredAccountingFormSlug()) {
            'customers', 'suppliers' => 'entity',
            'manual_journals', 'customer_invoices', 'supplier_invoices', 'purchase_orders',
            'customer_payments', 'supplier_payments', 'customer_advances', 'supplier_advances',
            'customer_refunds', 'supplier_refunds', 'debit_notes', 'credit_notes', 'accruals' => 'document',
            'bank_transactions', 'cheques', 'bank_transfers', 'wallets' => 'treasury',
            default => 'catalog',
        };
    }

    public function create(Request $request)
    {
        return $this->renderAccountingStructuredResourceForm($request, false, null);
    }

    public function edit(Request $request, int|string $id)
    {
        $model = $this->modelOrFail($id);

        return $this->renderAccountingStructuredResourceForm($request, true, $model);
    }

    public function prepareForValidation(Request &$request): void
    {
        // همان نام متد در RequestFormHelper روی AdminController است؛ بدون parent پاک‌سازی مبلغ/تاریخ شمسی اجرا نمی‌شود.
        parent::prepareForValidation($request);
        $this->applyStructuredAccountingFormRequestNormalization($request);
        $this->afterStructuredAccountingFormPrepareForValidation($request);
    }

    /**
     * پس از نرمال‌سازی ورودی فرم ساختاریافته (مثلاً پیش‌فرض فیلدهایی که در POST نیامده‌اند).
     */
    protected function afterStructuredAccountingFormPrepareForValidation(Request $request): void
    {
    }

    protected function renderAccountingStructuredResourceForm(Request $request, bool $isEdit, ?Model $model)
    {
        $slug = $this->structuredAccountingFormSlug();
        $profile = $this->structuredAccountingFormProfile();
        $htmlPageTitle = $this->resolveStructuredAccountingFormDocumentTitle($isEdit);

        $this->title($htmlPageTitle);
        $this->use_package_namespace = true;
        $this->view->usePackageNamespace('accounting');

        $plugins = array_merge(
            AccountingDateUi::calendarMode() === AccountingDateUi::MODE_JALALI ? ['persian-datepicker'] : [],
            ['advanced-select', 'amount-formatter']
        );

        $fieldRows = AccountingFormFieldExporter::toViewRows($this->getFieldsForm());

        $this->view
            ->setTpl('form.structured_resource_form')
            ->withPlugins($plugins)
            ->withJs('vendor/accounting/admin/js/entity-card-picker.js', true)
            ->withJs('vendor/accounting/admin/js/sales-customer-picker.js', true)
            ->withVariables(array_merge([
                'isEdit' => $isEdit,
                'model' => $model,
                'htmlPageTitle' => $htmlPageTitle,
                'formProfile' => $profile,
                'formSlug' => $slug,
                'fieldRows' => $fieldRows,
                'baseRoute' => $this->baseRoute(),
                'routeParam' => $this->routeParameter(),
                'indexRoute' => $this->accountingNamedRoute('index'),
                'defaultCurrency' => $this->resolveDefaultCurrencyCode(),
                'amountDecimalPlaces' => $this->resolveAccountingAmountDecimalPlaces(),
            ], $this->structuredAccountingFormExtraViewVariables($request, $isEdit, $model)));

        $this->afterConfigureStructuredAccountingFormView($request, $isEdit, $model);

        return $this->view();
    }

    protected function resolveStructuredAccountingFormDocumentTitle(bool $isEdit): string
    {
        $slug = str_replace('_', '-', $this->structuredAccountingFormSlug());
        $suffix = $isEdit ? '_edit' : '_create';
        $k = 'accounting::accounting.page_titles.'.$slug.$suffix;
        if (\Illuminate\Support\Facades\Lang::has($k)) {
            return (string) trans($k);
        }
        $kBase = 'accounting::accounting.page_titles.'.$slug;
        if (\Illuminate\Support\Facades\Lang::has($kBase)) {
            $base = (string) trans($kBase);

            return $isEdit
                ? (string) trans('accounting::accounting.structured_resource_forms.title_edit_suffix', ['base' => $base])
                : (string) trans('accounting::accounting.structured_resource_forms.title_create_suffix', ['base' => $base]);
        }

        return $isEdit
            ? (string) trans('accounting::accounting.structured_resource_forms.title_edit_generic')
            : (string) trans('accounting::accounting.structured_resource_forms.title_create_generic');
    }

    protected function applyStructuredAccountingFormRequestNormalization(Request $request): void
    {
        foreach ($this->getFieldsForm() as $field) {
            if (! $field instanceof Field) {
                continue;
            }
            $key = $field->key;
            if (! $request->has($key)) {
                continue;
            }
            if ($field->type === Field::DATE || $field->type === Field::DATE_TIME) {
                $raw = $request->input($key);
                if (is_string($raw) && trim($raw) !== '') {
                    $g = app(AccountingDateInputNormalizer::class)->normalizeFilterDateToGregorian(trim($raw));
                    if ($g !== null) {
                        $request->merge([$key => $g]);
                    }
                }

                continue;
            }
            if ($field->type === Field::PRICE) {
                $this->mergeParsedDecimalFields($request, [$key], null);

                continue;
            }
            if ($field->type === Field::NUMBER) {
                $exported = AccountingFormFieldExporter::exportOne($field);
                if (($exported['widget'] ?? '') === 'amount') {
                    $this->mergeParsedDecimalFields($request, [$key], null);
                }
            }
        }
    }
}
