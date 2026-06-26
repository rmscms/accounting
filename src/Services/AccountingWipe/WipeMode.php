<?php

namespace RMS\Accounting\Services\AccountingWipe;

enum WipeMode: string
{
    case Documents = 'documents';

    case AccountingReset = 'accounting-reset';

    case AllTables = 'all-tables';

    public static function tryFromString(string $value): ?self
    {
        $v = strtolower(trim($value));

        return match ($v) {
            'documents' => self::Documents,
            'accounting-reset', 'accounting_reset' => self::AccountingReset,
            'all-tables', 'all_tables', 'full-reset', 'full_reset' => self::AllTables,
            default => null,
        };
    }
}
