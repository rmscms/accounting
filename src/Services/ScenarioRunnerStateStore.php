<?php

declare(strict_types=1);

namespace RMS\Accounting\Services;

use RuntimeException;

class ScenarioRunnerStateStore
{
    private const STATE_VERSION = 2;

    /**
     * @param  array<string,array<string,mixed>>  $scenarioDefinitions
     * @return array<string,mixed>
     */
    public function getStateWithDefinitions(array $scenarioDefinitions): array
    {
        $state = $this->loadState($scenarioDefinitions);
        $rows = [];
        $summary = [
            'total' => 0,
            'executed' => 0,
            'not_run' => 0,
            'success' => 0,
            'failed' => 0,
            'mixed' => 0,
            'total_runs' => 0,
        ];

        foreach ($scenarioDefinitions as $scenarioKey => $meta) {
            $run = (array) ($state['runs'][$scenarioKey] ?? []);
            $totalRuns = max(0, (int) ($run['total_runs'] ?? 0));
            $successRuns = max(0, (int) ($run['success_runs'] ?? 0));
            $failedRuns = max(0, (int) ($run['failed_runs'] ?? 0));
            $lastStatus = (string) ($run['last_status'] ?? 'not_run');
            $status = $this->resolveStatus($totalRuns, $lastStatus);
            $lastMessage = trim((string) ($run['last_message'] ?? ''));
            $lastRunAt = (string) ($run['last_run_at'] ?? '');
            $errorLogs = is_array($run['error_logs'] ?? null) ? $run['error_logs'] : [];
            $errorCount = count($errorLogs);

            $rows[$scenarioKey] = [
                'scenario_key' => $scenarioKey,
                'title' => (string) ($meta['title'] ?? $scenarioKey),
                'module' => (string) ($meta['module'] ?? ''),
                'status' => $status,
                'total_runs' => $totalRuns,
                'success_runs' => $successRuns,
                'failed_runs' => $failedRuns,
                'last_status' => (string) ($run['last_status'] ?? 'not_run'),
                'last_run_at' => $lastRunAt,
                'last_message' => $lastMessage,
                'error_count' => $errorCount,
            ];

            $summary['total']++;
            $summary['total_runs'] += $totalRuns;
            if ($totalRuns > 0) {
                $summary['executed']++;
            }
            if ($status === 'not_run') {
                $summary['not_run']++;
            } elseif ($status === 'success') {
                $summary['success']++;
            } elseif ($status === 'failed') {
                $summary['failed']++;
            } else {
                $summary['mixed']++;
            }
        }

        return [
            'state' => $state,
            'rows' => $rows,
            'summary' => $summary,
            'file_path' => $this->stateFilePath(),
        ];
    }

    /**
     * @param  array<string,array<string,mixed>>  $scenarioDefinitions
     * @return array<string,mixed>
     */
    public function resetAll(array $scenarioDefinitions): array
    {
        $state = $this->defaultState($scenarioDefinitions);
        $this->persistState($state);

        return $state;
    }

    /**
     * @param  array<string,array<string,mixed>>  $scenarioDefinitions
     * @return array<string,mixed>
     */
    public function recordRun(
        array $scenarioDefinitions,
        string $scenarioKey,
        bool $isSuccess,
        string $message = '',
        bool $trackAsError = false
    ): array
    {
        $state = $this->loadState($scenarioDefinitions);
        if (! array_key_exists($scenarioKey, $scenarioDefinitions)) {
            throw new RuntimeException('Unknown scenario key for state tracking: '.$scenarioKey);
        }

        $run = (array) ($state['runs'][$scenarioKey] ?? []);
        $totalRuns = max(0, (int) ($run['total_runs'] ?? 0)) + 1;
        $successRuns = max(0, (int) ($run['success_runs'] ?? 0));
        $failedRuns = max(0, (int) ($run['failed_runs'] ?? 0));

        if ($isSuccess) {
            $successRuns++;
        } else {
            $failedRuns++;
        }

        $normalizedMessage = mb_substr(trim($message), 0, 500);
        $errorLogs = is_array($run['error_logs'] ?? null) ? $run['error_logs'] : [];
        if (! $isSuccess && $trackAsError && $normalizedMessage !== '') {
            $errorLogs[] = [
                'at' => now()->toDateTimeString(),
                'message' => $normalizedMessage,
            ];
            if (count($errorLogs) > 50) {
                $errorLogs = array_slice($errorLogs, -50);
            }
        }

        $state['runs'][$scenarioKey] = [
            'total_runs' => $totalRuns,
            'success_runs' => $successRuns,
            'failed_runs' => $failedRuns,
            'last_status' => $isSuccess ? 'success' : 'failed',
            'last_run_at' => now()->toDateTimeString(),
            'last_message' => $normalizedMessage,
            'error_logs' => $errorLogs,
        ];
        $state['updated_at'] = now()->toDateTimeString();

        $this->persistState($state);

        return $state;
    }

    /**
     * @param  array<string,array<string,mixed>>  $scenarioDefinitions
     * @return array<int,array{at: string, message: string}>
     */
    public function getScenarioErrorLogs(array $scenarioDefinitions, string $scenarioKey, int $limit = 50): array
    {
        if (! array_key_exists($scenarioKey, $scenarioDefinitions)) {
            throw new RuntimeException('Unknown scenario key for error logs: '.$scenarioKey);
        }

        $state = $this->loadState($scenarioDefinitions);
        $run = (array) ($state['runs'][$scenarioKey] ?? []);
        $logs = is_array($run['error_logs'] ?? null) ? $run['error_logs'] : [];
        $normalized = [];
        foreach ($logs as $log) {
            if (! is_array($log)) {
                continue;
            }
            $message = trim((string) ($log['message'] ?? ''));
            if ($message === '') {
                continue;
            }
            $normalized[] = [
                'at' => (string) ($log['at'] ?? ''),
                'message' => mb_substr($message, 0, 500),
            ];
        }

        $max = max(1, min(200, $limit));
        if (count($normalized) > $max) {
            $normalized = array_slice($normalized, -$max);
        }

        return array_reverse($normalized);
    }

    public function stateFilePath(): string
    {
        return storage_path('app/accounting/scenario-runner/state.json');
    }

    /**
     * @param  array<string,array<string,mixed>>  $scenarioDefinitions
     * @return array<string,mixed>
     */
    private function loadState(array $scenarioDefinitions): array
    {
        $default = $this->defaultState($scenarioDefinitions);
        $path = $this->stateFilePath();
        if (! is_file($path)) {
            return $default;
        }

        $raw = @file_get_contents($path);
        if (! is_string($raw) || trim($raw) === '') {
            return $default;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return $default;
        }

        $runs = is_array($decoded['runs'] ?? null) ? $decoded['runs'] : [];
        $order = [];
        foreach ($scenarioDefinitions as $scenarioKey => $_meta) {
            $order[] = $scenarioKey;
        }

        $normalizedRuns = [];
        foreach ($scenarioDefinitions as $scenarioKey => $_meta) {
            $run = (array) ($runs[$scenarioKey] ?? []);
            $normalizedRuns[$scenarioKey] = [
                'total_runs' => max(0, (int) ($run['total_runs'] ?? 0)),
                'success_runs' => max(0, (int) ($run['success_runs'] ?? 0)),
                'failed_runs' => max(0, (int) ($run['failed_runs'] ?? 0)),
                'last_status' => (string) ($run['last_status'] ?? 'not_run'),
                'last_run_at' => (string) ($run['last_run_at'] ?? ''),
                'last_message' => (string) ($run['last_message'] ?? ''),
                'error_logs' => $this->normalizeErrorLogs($run['error_logs'] ?? []),
            ];
        }

        return [
            'version' => self::STATE_VERSION,
            'updated_at' => (string) ($decoded['updated_at'] ?? now()->toDateTimeString()),
            'order' => $order,
            'runs' => $normalizedRuns,
        ];
    }

    /**
     * @param  array<string,mixed>  $state
     */
    private function persistState(array $state): void
    {
        $path = $this->stateFilePath();
        $directory = dirname($path);
        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create scenario runner state directory.');
        }

        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (! is_string($json)) {
            throw new RuntimeException('Unable to encode scenario runner state.');
        }

        if (@file_put_contents($path, $json, LOCK_EX) === false) {
            throw new RuntimeException('Unable to persist scenario runner state.');
        }
    }

    /**
     * @param  array<string,array<string,mixed>>  $scenarioDefinitions
     * @return array<string,mixed>
     */
    private function defaultState(array $scenarioDefinitions): array
    {
        $runs = [];
        $order = [];
        foreach ($scenarioDefinitions as $scenarioKey => $_meta) {
            $order[] = $scenarioKey;
            $runs[$scenarioKey] = [
                'total_runs' => 0,
                'success_runs' => 0,
                'failed_runs' => 0,
                'last_status' => 'not_run',
                'last_run_at' => '',
                'last_message' => '',
                'error_logs' => [],
            ];
        }

        return [
            'version' => self::STATE_VERSION,
            'updated_at' => now()->toDateTimeString(),
            'order' => $order,
            'runs' => $runs,
        ];
    }

    private function resolveStatus(int $totalRuns, string $lastStatus): string
    {
        if ($totalRuns <= 0) {
            return 'not_run';
        }

        if ($lastStatus === 'success') {
            return 'success';
        }

        return 'failed';
    }

    /**
     * @return array<int,array{at: string, message: string}>
     */
    private function normalizeErrorLogs(mixed $logs): array
    {
        if (! is_array($logs)) {
            return [];
        }

        $normalized = [];
        foreach ($logs as $log) {
            if (! is_array($log)) {
                continue;
            }
            $message = trim((string) ($log['message'] ?? ''));
            if ($message === '') {
                continue;
            }
            $normalized[] = [
                'at' => (string) ($log['at'] ?? ''),
                'message' => mb_substr($message, 0, 500),
            ];
        }
        if (count($normalized) > 50) {
            $normalized = array_slice($normalized, -50);
        }

        return $normalized;
    }
}
