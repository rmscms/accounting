<?php

declare(strict_types=1);

namespace RMS\Accounting\Console;

use Illuminate\Console\Command;
use RMS\Accounting\Services\AccountingSampleDataService;

class CheckSampleDataConsistencyCommand extends Command
{
    protected $signature = 'accounting:check-sample-data-consistency {--json : Print raw JSON result}';

    protected $description = 'Run consistency checks for accounting sample data records';

    public function handle(AccountingSampleDataService $service): int
    {
        $report = $service->sampleConsistencyReport();

        if ((bool) $this->option('json')) {
            $json = json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            if ($json === false) {
                $this->error('Failed to encode consistency report as JSON.');
                return self::FAILURE;
            }
            $this->line($json);

            return ($report['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
        }

        $this->info('Sample data consistency report');
        $this->line(str_repeat('-', 48));

        $stats = is_array($report['stats'] ?? null) ? $report['stats'] : [];
        foreach ($stats as $key => $value) {
            $this->line(sprintf('%-45s %d', (string) $key, (int) $value));
        }

        $issues = is_array($report['issues'] ?? null) ? $report['issues'] : [];
        if ($issues === []) {
            $this->info('No consistency issues found.');
            return self::SUCCESS;
        }

        $this->warn('Consistency issues:');
        foreach ($issues as $issue) {
            $this->line(' - '.(string) $issue);
        }

        return self::FAILURE;
    }
}

