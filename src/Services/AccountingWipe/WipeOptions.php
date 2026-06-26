<?php

namespace RMS\Accounting\Services\AccountingWipe;

final class WipeOptions
{
    public function __construct(
        public readonly WipeMode $mode,
        public readonly bool $dryRun,
        public readonly bool $confirmedReset,
    ) {}

    public static function documents(bool $dryRun = true): self
    {
        return new self(WipeMode::Documents, $dryRun, false);
    }

    public static function accountingReset(bool $dryRun, bool $confirmedReset): self
    {
        return new self(WipeMode::AccountingReset, $dryRun, $confirmedReset);
    }

    public static function allTables(bool $dryRun, bool $confirmedReset): self
    {
        return new self(WipeMode::AllTables, $dryRun, $confirmedReset);
    }
}
