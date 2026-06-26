<?php

namespace RMS\Accounting\Support;

use RMS\Core\Models\Setting;

/**
 * حساب‌های VAT از تنظیمات ادمین با fallback به config/env.
 */
final class AccountingVatAccounts
{
    public static function resolvePayableAccountId(): ?int
    {
        $fromSetting = Setting::get('accounting.vat.account_payable_id');
        if ($fromSetting !== null && $fromSetting !== '' && (int) $fromSetting > 0) {
            return (int) $fromSetting;
        }

        $fromConfig = config('accounting.accounts.vat_payable');

        return $fromConfig !== null && $fromConfig !== '' && (int) $fromConfig > 0
            ? (int) $fromConfig
            : null;
    }

    public static function resolveReceivableAccountId(): ?int
    {
        $fromSetting = Setting::get('accounting.vat.account_receivable_id');
        if ($fromSetting !== null && $fromSetting !== '' && (int) $fromSetting > 0) {
            return (int) $fromSetting;
        }

        $fromConfig = config('accounting.accounts.vat_receivable');

        return $fromConfig !== null && $fromConfig !== '' && (int) $fromConfig > 0
            ? (int) $fromConfig
            : null;
    }
}
