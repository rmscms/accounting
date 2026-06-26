<?php

namespace RMS\Accounting\Observers;

use RMS\Accounting\Models\TaxRate;

class TaxRateObserver
{
    public function saved(TaxRate $taxRate): void
    {
        TaxRate::syncVatSettingFromDefault();
    }

    public function deleted(TaxRate $taxRate): void
    {
        TaxRate::syncVatSettingFromDefault();
    }
}

