<?php

declare(strict_types=1);

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RMS\Accounting\Models\Bank;
use RMS\Accounting\Models\CashBox;
use RMS\Accounting\Models\VatDeclaration;
use RMS\Accounting\Models\VatRemittance;
use RMS\Accounting\Models\Wallet;
use RMS\Accounting\Services\AccountingDateInputNormalizer;
use RMS\Accounting\Services\ReportService;
use RMS\Accounting\Services\VatDeclarationService;
use RMS\Accounting\Services\VatRemittanceService;
use RMS\Accounting\Support\AccountingDateUi;

class VatComplianceController extends AccountingAdminController
{
    public function index(Request $request)
    {
        $range = app(ReportService::class)->getVATReport($request->all());
        $plugins = AccountingDateUi::calendarMode() === AccountingDateUi::MODE_JALALI
            ? ['persian-datepicker']
            : [];

        $this->view->usePackageNamespace('accounting')
            ->setTheme('admin')
            ->setTpl('reports.vat-compliance')
            ->withCss('vendor/accounting/admin/css/reports.css', true)
            ->withPlugins($plugins)
            ->withJs('vendor/accounting/admin/js/accounting-date-ui.js', true)
            ->withVariables([
                'data' => $range,
                'banks' => Bank::query()->where('active', true)->orderBy('name')->get(),
                'cashBoxes' => CashBox::query()->where('active', true)->orderBy('name')->get(),
                'wallets' => Wallet::query()->where('active', true)->orderBy('name')->get(),
                'remittances' => VatRemittance::query()->with(['bank', 'cashBox', 'wallet', 'accountingDocument'])->latest('id')->limit(50)->get(),
                'declarations' => VatDeclaration::query()->with('parent')->latest('id')->limit(50)->get(),
            ]);

        return $this->view();
    }

    public function storeRemittance(Request $request, VatRemittanceService $service)
    {
        $payload = $request->validate([
            'amount' => 'required|string|max:64',
            'payment_date' => 'required|string|max:64',
            'period_start' => 'nullable|string|max:64',
            'period_end' => 'nullable|string|max:64',
            'bank_id' => 'nullable|integer|exists:banks,id',
            'cash_box_id' => 'nullable|integer|exists:cash_boxes,id',
            'wallet_id' => 'nullable|integer|exists:wallets,id',
            'notes' => 'nullable|string|max:2000',
        ]);

        $payload['amount'] = $this->normalizeAmount((string) $payload['amount']);
        $payload['payment_date'] = $this->normalizeDate((string) $payload['payment_date']);
        if (! empty($payload['period_start'])) {
            $payload['period_start'] = $this->normalizeDate((string) $payload['period_start']);
        }
        if (! empty($payload['period_end'])) {
            $payload['period_end'] = $this->normalizeDate((string) $payload['period_end']);
        }

        $service->createAndPost($payload);

        return redirect()
            ->route('admin.accounting.reports.vat-compliance')
            ->with('success', 'تسویه VAT با موفقیت ثبت شد.');
    }

    public function createDeclaration(Request $request, VatDeclarationService $service)
    {
        $payload = $request->validate([
            'period_start' => 'required|string|max:64',
            'period_end' => 'required|string|max:64',
            'notes' => 'nullable|string|max:2000',
            'parent_declaration_id' => 'nullable|integer|exists:vat_declarations,id',
        ]);
        $payload['period_start'] = $this->normalizeDate((string) $payload['period_start']);
        $payload['period_end'] = $this->normalizeDate((string) $payload['period_end']);

        $service->createDraft($payload);

        return redirect()
            ->route('admin.accounting.reports.vat-compliance')
            ->with('success', 'پیش‌نویس اظهارنامه VAT ایجاد شد.');
    }

    public function submitDeclaration(Request $request, VatDeclaration $declaration, VatDeclarationService $service)
    {
        $service->markSubmitted($declaration);

        return redirect()
            ->route('admin.accounting.reports.vat-compliance')
            ->with('success', 'اظهارنامه به حالت ارسال‌شده منتقل شد.');
    }

    public function exportDeclarationOfficial(Request $request, VatDeclaration $declaration, VatDeclarationService $service)
    {
        $content = $service->exportCsv($declaration);
        $filename = 'vat-form-169-'.$declaration->id.'-'.now()->format('Ymd-His').'.csv';

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    protected function normalizeDate(string $value): string
    {
        $normalized = app(AccountingDateInputNormalizer::class)->normalizeFilterDateToGregorian($value);
        if ($normalized === null) {
            throw ValidationException::withMessages([
                'date' => [(string) trans('accounting::errors.invalid_date')],
            ]);
        }

        return $normalized;
    }

    protected function normalizeAmount(string $value): float
    {
        $normalized = trim(\RMS\Helper\changeNumberToEn($value));
        $normalized = str_replace(['٬', '،', ','], '', $normalized);
        $normalized = preg_replace('/\s+/', '', $normalized) ?? '';
        if (! is_numeric($normalized)) {
            throw ValidationException::withMessages([
                'amount' => ['فرمت مبلغ معتبر نیست.'],
            ]);
        }

        return (float) $normalized;
    }

    public function table(): string
    {
        return '';
    }

    public function modelName(): string
    {
        return '';
    }
}
