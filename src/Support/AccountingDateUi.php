<?php

declare(strict_types=1);

namespace RMS\Accounting\Support;

use Carbon\Carbon;

/**
 * حالت تقویم UI ادمین حسابداری و نمایش مقدار اولیهٔ فیلدها.
 */
final class AccountingDateUi
{
    public const MODE_JALALI = 'jalali';

    public const MODE_GREGORIAN = 'gregorian';

    /**
     * jalali | gregorian (پس از حل auto)
     */
    public static function calendarMode(): string
    {
        $ui = strtolower((string) config('accounting.date_filter.ui', self::MODE_JALALI));

        if ($ui === 'auto') {
            $loc = strtolower((string) app()->getLocale());

            return str_starts_with($loc, 'fa') ? self::MODE_JALALI : self::MODE_GREGORIAN;
        }

        return $ui === self::MODE_GREGORIAN ? self::MODE_GREGORIAN : self::MODE_JALALI;
    }

    /**
     * مقدار نمایش در اینپوت فیلتر بازه: اگر کاربر مقدار فرستاده همان را؛ وگرنه از میلادی پیش‌فرض به تقویم فعال.
     */
    public static function rangeInputFromRequest(?string $requestValue, ?string $fallbackGregorianYmd): string
    {
        $raw = $requestValue !== null && trim((string) $requestValue) !== ''
            ? trim(\RMS\Helper\changeNumberToEn((string) $requestValue))
            : '';

        if ($raw !== '') {
            return $raw;
        }

        return self::gregorianYmdToInputDisplay($fallbackGregorianYmd);
    }

    /**
     * میلادی Y-m-d (یا null) → مقدار value اینپوت ادمین.
     * در حالت jalali هم همیشه میلادی Y-m-d برمی‌گردد؛ persian-datepicker / RMS2 با همین مقدار، نمایش و ورود شمسی را خودش مدیریت می‌کند (قرار دادن رشتهٔ شمسی در value باعث سال اشتباه مثل ۷۸۳ می‌شود).
     *
     * @param  string  $jalaliFormat  نادیده گرفته می‌شود؛ برای سازگاری امضای قدیمی حفظ شده است.
     */
    public static function gregorianYmdToInputDisplay(?string $gregorianYmd, string $jalaliFormat = 'Y-m-d'): string
    {
        if ($gregorianYmd === null || trim($gregorianYmd) === '') {
            return '';
        }

        try {
            $c = Carbon::parse($gregorianYmd);
        } catch (\Throwable) {
            return '';
        }

        return $c->format('Y-m-d');
    }

    /**
     * برای data-attribute در Blade / JS.
     */
    public static function calendarModeForDom(): string
    {
        return self::calendarMode();
    }

    /**
     * مقدار نمایشی from_date در گزارش‌های بازه‌ای (با پشتیبانی start_date و period).
     */
    public static function reportRangeFromDisplay(?string $fromDate, ?string $startDateLegacy, ?string $periodStartGregorian): string
    {
        if ($fromDate !== null && trim((string) $fromDate) !== '') {
            return trim(\RMS\Helper\changeNumberToEn((string) $fromDate));
        }

        $fb = null;
        if ($startDateLegacy !== null && trim((string) $startDateLegacy) !== '') {
            try {
                $fb = Carbon::parse($startDateLegacy)->format('Y-m-d');
            } catch (\Throwable) {
            }
        }
        if ($fb === null && $periodStartGregorian !== null && trim((string) $periodStartGregorian) !== '') {
            try {
                $fb = Carbon::parse($periodStartGregorian)->format('Y-m-d');
            } catch (\Throwable) {
            }
        }

        return self::gregorianYmdToInputDisplay($fb);
    }

    /**
     * مقدار نمایشی to_date در گزارش‌های بازه‌ای (با پشتیبانی end_date و period).
     */
    public static function reportRangeToDisplay(?string $toDate, ?string $endDateLegacy, ?string $periodEndGregorian): string
    {
        if ($toDate !== null && trim((string) $toDate) !== '') {
            return trim(\RMS\Helper\changeNumberToEn((string) $toDate));
        }

        $fb = null;
        if ($endDateLegacy !== null && trim((string) $endDateLegacy) !== '') {
            try {
                $fb = Carbon::parse($endDateLegacy)->format('Y-m-d');
            } catch (\Throwable) {
            }
        }
        if ($fb === null && $periodEndGregorian !== null && trim((string) $periodEndGregorian) !== '') {
            try {
                $fb = Carbon::parse($periodEndGregorian)->format('Y-m-d');
            } catch (\Throwable) {
            }
        }

        return self::gregorianYmdToInputDisplay($fb);
    }
}
