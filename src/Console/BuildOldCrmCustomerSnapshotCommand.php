<?php

declare(strict_types=1);

namespace RMS\Accounting\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BuildOldCrmCustomerSnapshotCommand extends Command
{
    protected $signature = 'accounting:build-old-crm-customer-snapshot
                            {--limit=200 : Maximum rows to export}
                            {--path= : Custom output file path}';

    protected $description = 'Build fixed JSON snapshot of old CRM customers for accounting sample-data generation';

    public function handle(): int
    {
        $limit = max(1, min(200, (int) $this->option('limit')));
        $connection = (string) config('crm-import.source_connection', 'old_crm');
        $usersTable = (string) config('crm-import.source_users_table', 'users');

        try {
            $schema = DB::connection($connection)->getSchemaBuilder();
            if (! $schema->hasTable($usersTable)) {
                $this->error("Source table [{$usersTable}] was not found on connection [{$connection}].");
                return self::FAILURE;
            }

            $rows = DB::connection($connection)
                ->table($usersTable)
                ->orderBy('id')
                ->limit($limit)
                ->get();
        } catch (\Throwable $e) {
            $this->error('Could not read old CRM source: '.$e->getMessage());
            return self::FAILURE;
        }

        $records = [];
        foreach ($rows as $row) {
            $data = (array) $row;
            $firstName = trim((string) ($data['firstname'] ?? $data['first_name'] ?? $data['name'] ?? ''));
            $lastName = trim((string) ($data['lastname'] ?? $data['last_name'] ?? ''));
            $fullName = trim((string) ($data['full_name'] ?? ($firstName.' '.$lastName)));
            if ($fullName === '') {
                $fullName = 'Legacy Customer #'.((int) ($data['id'] ?? 0));
            }

            $records[] = [
                'source_id' => (int) ($data['id'] ?? 0),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'full_name' => $fullName,
                'mobile' => (string) ($data['mobile'] ?? $data['phone'] ?? ''),
                'email' => (string) ($data['email'] ?? ''),
                'national_code' => (string) ($data['national_code'] ?? ''),
            ];
        }

        $outputPath = trim((string) $this->option('path'));
        if ($outputPath === '') {
            $outputPath = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'sample_data'.DIRECTORY_SEPARATOR.'old_crm_customers.json';
        }

        $dir = dirname($outputPath);
        if (! is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $json = json_encode($records, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false) {
            $this->error('Failed to encode snapshot JSON.');
            return self::FAILURE;
        }

        if (@file_put_contents($outputPath, $json) === false) {
            $this->error("Failed to write snapshot file: {$outputPath}");
            return self::FAILURE;
        }

        $this->info('Old CRM customer snapshot created: '.$outputPath);
        $this->line('Rows exported: '.count($records));

        return self::SUCCESS;
    }
}

