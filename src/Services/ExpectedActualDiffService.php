<?php

declare(strict_types=1);

namespace RMS\Accounting\Services;

class ExpectedActualDiffService
{
    /**
     * @param  array<int,array<string,mixed>>  $expected
     * @param  array<int,array<string,mixed>>  $before
     * @param  array<int,array<string,mixed>>  $after
     * @return array<string,mixed>
     */
    public function compare(array $expected, array $before, array $after): array
    {
        $expected = $this->aggregateExpectedByAccountId($expected);
        $beforeMap = $this->keyByAccountId($before);
        $afterMap = $this->keyByAccountId($after);

        $rows = [];
        $allPass = true;
        foreach ($expected as $entry) {
            $accountId = (int) ($entry['account_id'] ?? 0);
            if ($accountId <= 0) {
                continue;
            }

            $beforeNet = (float) ($beforeMap[$accountId]['net_base'] ?? 0);
            $afterNet = (float) ($afterMap[$accountId]['net_base'] ?? 0);
            $actualDelta = round($afterNet - $beforeNet, 4);
            $expectedDelta = round((float) ($entry['expected_delta'] ?? 0), 4);
            $difference = round($actualDelta - $expectedDelta, 4);
            $pass = abs($difference) < 0.01;
            if (! $pass) {
                $allPass = false;
            }

            $rows[] = [
                'account_id' => $accountId,
                'account_code' => (string) ($entry['account_code'] ?? ''),
                'account_name' => (string) ($entry['account_name'] ?? ''),
                'expected_debit' => round((float) ($entry['debit'] ?? 0), 4),
                'expected_credit' => round((float) ($entry['credit'] ?? 0), 4),
                'expected_delta' => $expectedDelta,
                'actual_delta' => $actualDelta,
                'difference' => $difference,
                'pass' => $pass,
                'note' => (string) ($entry['note'] ?? ''),
            ];
        }

        return [
            'ok' => $allPass,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<int,array<string,mixed>>  $expected
     * @return array<int,array<string,mixed>>
     */
    private function aggregateExpectedByAccountId(array $expected): array
    {
        $grouped = [];

        foreach ($expected as $entry) {
            $accountId = (int) ($entry['account_id'] ?? 0);
            if ($accountId <= 0) {
                continue;
            }

            if (! isset($grouped[$accountId])) {
                $grouped[$accountId] = [
                    'account_id' => $accountId,
                    'account_code' => (string) ($entry['account_code'] ?? ''),
                    'account_name' => (string) ($entry['account_name'] ?? ''),
                    'debit' => 0.0,
                    'credit' => 0.0,
                    'note' => (string) ($entry['note'] ?? ''),
                ];
            }

            $grouped[$accountId]['debit'] += round((float) ($entry['debit'] ?? 0), 4);
            $grouped[$accountId]['credit'] += round((float) ($entry['credit'] ?? 0), 4);

            $note = trim((string) ($entry['note'] ?? ''));
            if ($note !== '' && $note !== (string) $grouped[$accountId]['note']) {
                $existing = trim((string) $grouped[$accountId]['note']);
                $grouped[$accountId]['note'] = $existing === '' ? $note : $existing.' | '.$note;
            }
        }

        $rows = [];
        foreach ($grouped as $row) {
            $row['debit'] = round((float) $row['debit'], 4);
            $row['credit'] = round((float) $row['credit'], 4);
            $row['expected_delta'] = round((float) $row['debit'] - (float) $row['credit'], 4);
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows
     * @return array<int,array<string,mixed>>
     */
    private function keyByAccountId(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $accountId = (int) ($row['account_id'] ?? 0);
            if ($accountId <= 0) {
                continue;
            }
            $map[$accountId] = $row;
        }

        return $map;
    }
}

