<?php

namespace RMS\Accounting\Services\AccountingWipe;

final class WipeResult
{
    /** @param array<string, int> $counts */
    public function __construct(
        public readonly bool $dryRun,
        public readonly array $counts,
    ) {}

    public function count(string $key): int
    {
        return (int) ($this->counts[$key] ?? 0);
    }
}
