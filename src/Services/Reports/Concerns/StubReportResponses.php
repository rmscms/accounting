<?php

namespace RMS\Accounting\Services\Reports\Concerns;

/**
 * پاسخ یکنواخت برای گزارش‌های هنوز پیاده‌سازی‌نشده (placeholder).
 */
trait StubReportResponses
{
    protected function stubReportResponse(string $titleTranslationKey): array
    {
        return [
            'title' => trans($titleTranslationKey),
            'message' => trans('accounting::accounting.reports.placeholder.stub_message'),
            'layout' => 'placeholder',
        ];
    }

    /**
     * @param  array<string, string>  $extra  کلیدهای اضافه در خروجی (مثلاً hint)
     */
    protected function stubReportResponseWith(string $titleTranslationKey, array $extra = []): array
    {
        return array_merge($this->stubReportResponse($titleTranslationKey), $extra);
    }
}
