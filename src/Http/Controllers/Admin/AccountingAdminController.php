<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\ValidationException;
use RMS\Accounting\Services\AccountingDateInputNormalizer;
use RMS\Core\Controllers\Admin\ProjectAdminController;

/**
 * Base Admin Controller for Accounting Package
 *
 * همه کنترلرهای ادمین accounting باید از این کلاس extend کنند
 * این امکان تغییرات متمرکز را در تمام کنترلرها فراهم می‌کند
 */
abstract class AccountingAdminController extends ProjectAdminController
{
    protected function beforeRenderView(): void
    {
        parent::beforeRenderView();
        $this->ensureAccountingAdminPageTitle();
    }

    /**
     * اگر عنوان صفحه (برای تگ title و هدر لیست) خالی باشد، از نام مسیر یا baseRoute پر می‌شود.
     */
    protected function ensureAccountingAdminPageTitle(): void
    {
        $existing = $this->view->getVariable('title');
        if (is_string($existing) && trim($existing) !== '') {
            $trimmed = trim($existing);
            if (($this->getTitle() === null || $this->getTitle() === '') && $trimmed !== '') {
                $this->setTitle($trimmed);
            }

            return;
        }

        $fromTrait = $this->getTitle();
        if (is_string($fromTrait) && trim($fromTrait) !== '') {
            $this->title(trim($fromTrait));

            return;
        }

        $fallback = $this->resolveAccountingAdminFallbackTitle();
        if ($fallback !== '') {
            $this->setTitle($fallback);
            $this->title($fallback);
        }
    }

    protected function resolveAccountingAdminFallbackTitle(): string
    {
        $prefix = 'accounting::accounting.page_titles.';

        if (method_exists($this, 'baseRoute')) {
            $route = (string) $this->baseRoute();
            if ($route !== '' && str_starts_with($route, 'accounting.')) {
                $slug = str_replace(['-', '.'], '_', substr($route, strlen('accounting.')));
                $key = $prefix . $slug;
                if (Lang::has($key)) {
                    return (string) trans($key);
                }
            }
        }

        $name = request()->route()?->getName();
        if (! is_string($name) || ! str_starts_with($name, 'admin.accounting.')) {
            return (string) trans($prefix . 'default');
        }

        $tail = substr($name, strlen('admin.accounting.'));

        if (str_starts_with($tail, 'reports.')) {
            $rKey = $prefix . 'reports';
            if (Lang::has($rKey)) {
                return (string) trans($rKey);
            }
        }

        $candidates = [];
        $candidates[] = str_replace(['-', '.'], '_', $tail);
        $parts = explode('.', $tail);
        if ($parts !== [] && isset($parts[0])) {
            $candidates[] = str_replace('-', '_', $parts[0]);
        }
        foreach ($candidates as $slug) {
            if (! is_string($slug) || $slug === '') {
                continue;
            }
            $k = $prefix . $slug;
            if (Lang::has($k)) {
                return (string) trans($k);
            }
        }

        return (string) trans($prefix . 'default');
    }

    /**
     * نام کامل روت ادمین (پیشوند admin. مثل گروه در routes/admin.php).
     *
     * baseRoute() فقط دنبالهٔ accounting.* را برمی‌گرداند؛ نام ثبت‌شده در Laravel admin.accounting.* است.
     */
    protected function accountingNamedRoute(string $action): string
    {
        return 'admin.'.$this->baseRoute().'.'.$action;
    }

    /**
     * ورودی تاریخ ادمین (جلالی/میلادی) → میلادی Y-m-d برای ذخیره.
     *
     * @throws ValidationException
     */
    protected function normalizePostedAccountingDate(Request $request, string $key = 'journal_date'): string
    {
        $raw = $request->input($key);
        if (! is_string($raw) || trim($raw) === '') {
            throw ValidationException::withMessages([
                $key => [trans('validation.required', ['attribute' => $key])],
            ]);
        }

        $gregorian = app(AccountingDateInputNormalizer::class)->normalizeFilterDateToGregorian(trim($raw));
        if ($gregorian === null) {
            throw ValidationException::withMessages([
                $key => [trans('accounting::errors.invalid_date')],
            ]);
        }

        return $gregorian;
    }

    /**
     * Override core converter to return field-aware validation errors
     * instead of bubbling raw helper exceptions to "update failed".
     *
     * @throws ValidationException
     */
    protected function convertPersianDatesToGregorian(Request &$request): void
    {
        if (! method_exists($this, 'getDateFieldsForConversion')) {
            return;
        }

        $dateFields = $this->getDateFieldsForConversion();
        if (! is_array($dateFields) || $dateFields === []) {
            return;
        }

        $requestData = $request->all();
        $errors = [];
        $normalizer = app(AccountingDateInputNormalizer::class);

        foreach ($dateFields as $fieldKey) {
            if (! is_string($fieldKey) || $fieldKey === '' || ! $request->has($fieldKey)) {
                continue;
            }

            $rawValue = $request->input($fieldKey);
            if (! is_string($rawValue) || trim($rawValue) === '') {
                continue;
            }

            $rawValue = trim($rawValue);
            $normalizedInput = trim(\RMS\Helper\changeNumberToEn($rawValue));

            try {
                $gregorian = $normalizer->normalizeFilterDateToGregorian($normalizedInput);
            } catch (\Throwable $e) {
                \Log::warning('Accounting date conversion threw exception', [
                    'controller' => static::class,
                    'route' => request()->route()?->getName(),
                    'field' => $fieldKey,
                    'raw_value' => $rawValue,
                    'normalized' => $normalizedInput,
                    'exception' => $e->getMessage(),
                ]);

                $gregorian = null;
            }

            if ($gregorian === null) {
                $fieldLabel = $this->resolveDateFieldLabel($fieldKey);

                \Log::warning('Accounting date conversion failed', [
                    'controller' => static::class,
                    'route' => request()->route()?->getName(),
                    'field' => $fieldKey,
                    'label' => $fieldLabel,
                    'raw_value' => $rawValue,
                    'normalized' => $normalizedInput,
                ]);

                $errors[$fieldKey] = [
                    sprintf(
                        'فرمت تاریخ «%s» نامعتبر است. مقدار واردشده: %s',
                        $fieldLabel,
                        $rawValue
                    ),
                ];
                continue;
            }

            $requestData[$fieldKey] = $gregorian;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $request->merge($requestData);
    }

    protected function resolveDateFieldLabel(string $fieldKey): string
    {
        if (! method_exists($this, 'getFieldsForm')) {
            return $fieldKey;
        }

        foreach ((array) $this->getFieldsForm() as $field) {
            if (! isset($field->key) || (string) $field->key !== $fieldKey) {
                continue;
            }

            $label = (string) ($field->title ?? $fieldKey);
            $label = trim(strip_tags($label));

            return $label !== '' ? $label : $fieldKey;
        }

        return $fieldKey;
    }
}
