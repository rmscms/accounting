<?php

declare(strict_types=1);

namespace RMS\Accounting\Enums;

/**
 * رقم نوع سرویس در قبوض ایران (مستندات عمومی درگاه‌ها / رسانه‌ها).
 * نوع «جرائم رانندگی» ممکن است قالب شناسه متفاوتی داشته باشد؛ اعتبارسنجی طول در اپ جدا اعمال شود.
 */
enum IranUtilityBillServiceType: string
{
    case Water = 'water';
    case Electricity = 'electricity';
    case Gas = 'gas';
    case Landline = 'landline';
    case Mobile = 'mobile';
    case Municipality = 'municipality';
    case Tax = 'tax';
    case TrafficFine = 'traffic_fine';

    public function iranServiceDigit(): int
    {
        return match ($this) {
            self::Water => 1,
            self::Electricity => 2,
            self::Gas => 3,
            self::Landline => 4,
            self::Mobile => 5,
            self::Municipality => 6,
            self::Tax => 7,
            self::TrafficFine => 8,
        };
    }

    public function label(): string
    {
        return trans('accounting::accounting.utility_bills.service_types.' . $this->value);
    }
}
