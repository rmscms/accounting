<?php

declare(strict_types=1);

namespace RMS\Accounting\Services;

use Morilog\Jalali\CalendarUtils;

/**
 * نرمال‌سازی ورودی تاریخ ادمین (جلالی یا میلادی) به میلادی Y-m-d برای ذخیره و کوئری.
 */
final class AccountingDateInputNormalizer
{
    /**
     * میلادی Y-m-d یا رشتهٔ شمسی قابل تبدیل (مثل فیلتر گزارش‌ها و دفتر روزنامه).
     */
    public function normalizeFilterDateToGregorian(?string $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $trim = trim(\RMS\Helper\changeNumberToEn((string) $value));

        if (preg_match('/^(19|20)\d{2}-\d{2}-\d{2}$/', $trim)) {
            return $trim;
        }

        return $this->convertPersianDateToGregorian($trim);
    }

    /**
     * همان normalizeFilterDateToGregorian برای فیلدهایی که قبلاً parseFlexibleDateFilter نام داشتند.
     */
    public function parseFlexibleDateFilter(string $value): ?string
    {
        $trim = trim($value);
        if ($trim === '') {
            return null;
        }

        if (preg_match('/^(19|20)\d{2}-\d{2}-\d{2}$/', $trim)) {
            return $trim;
        }

        return $this->convertPersianDateToGregorian($trim);
    }

    protected function convertPersianDateToGregorian(?string $persianDate): ?string
    {
        if ($persianDate === null || $persianDate === '') {
            return null;
        }

        try {
            $normalizedDate = \RMS\Helper\changeNumberToEn($persianDate);
            $separator = strpos($normalizedDate, '-') !== false ? '-' : '/';
            $parts = explode($separator, trim($normalizedDate));
            if (count($parts) !== 3) {
                return null;
            }

            $year = (int) ($parts[0] ?? 0);
            $month = (int) ($parts[1] ?? 0);
            $day = (int) ($parts[2] ?? 0);

            if (! CalendarUtils::checkDate($year, $month, $day, true)) {
                return null;
            }

            $gregorian = CalendarUtils::toGregorian($year, $month, $day);
            if (! is_array($gregorian) || count($gregorian) !== 3) {
                return null;
            }

            return sprintf('%04d-%02d-%02d', (int) $gregorian[0], (int) $gregorian[1], (int) $gregorian[2]);
        } catch (\Throwable) {
            return null;
        }
    }
}
