<?php

declare(strict_types=1);

namespace RMS\Accounting\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RMS\Accounting\Models\AccountingDocument;
use RMS\Accounting\Models\FiscalYear;

class AccountingHealthCommand extends Command
{
    protected $signature = 'accounting:health {--json : Print JSON payload}';

    protected $description = 'Run basic accounting health checks (ledger balance, orphans, fiscal year)';

    public function handle(): int
    {
        $payload = $this->buildPayload();

        if ((bool) $this->option('json')) {
            $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                $this->error('Failed to encode health payload.');
                return self::FAILURE;
            }
            $this->line($json);

            return ($payload['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
        }

        $this->info('Accounting health report');
        $this->line(str_repeat('-', 40));
        $this->line('Open fiscal year: '.(($payload['checks']['open_fiscal_year']['ok'] ?? false) ? 'OK' : 'FAIL'));
        $this->line('Unbalanced documents: '.(string) ($payload['stats']['unbalanced_documents'] ?? 0));
        $this->line('Orphan ledger rows: '.(string) ($payload['stats']['orphan_ledger_rows'] ?? 0));
        $this->line('Recent documents (30d): '.(string) ($payload['stats']['documents_last_30_days'] ?? 0));

        if (! ($payload['ok'] ?? false)) {
            $this->warn('Accounting health check detected issues.');
            foreach ((array) ($payload['issues'] ?? []) as $issue) {
                $this->line(' - '.(string) $issue);
            }

            return self::FAILURE;
        }

        $this->info('Accounting health check passed.');

        return self::SUCCESS;
    }

    /**
     * @return array<string,mixed>
     */
    private function buildPayload(): array
    {
        $hasOpenFiscalYear = FiscalYear::query()->where('status', 'open')->exists();
        $documentsLast30Days = AccountingDocument::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $unbalancedDocuments = (int) DB::table('financial_ledgers')
            ->select('accounting_document_id')
            ->whereNotNull('accounting_document_id')
            ->groupBy('accounting_document_id')
            ->havingRaw('ABS(SUM(COALESCE(debit_amount,0)) - SUM(COALESCE(credit_amount,0))) > 0.01')
            ->count();

        $orphanLedgerRows = (int) DB::table('financial_ledgers AS fl')
            ->leftJoin('accounting_documents AS ad', 'ad.id', '=', 'fl.accounting_document_id')
            ->whereNull('fl.accounting_document_id')
            ->orWhereNull('ad.id')
            ->count();

        $issues = [];
        if (! $hasOpenFiscalYear) {
            $issues[] = 'No open fiscal year detected.';
        }
        if ($unbalancedDocuments > 0) {
            $issues[] = 'There are unbalanced accounting documents.';
        }
        if ($orphanLedgerRows > 0) {
            $issues[] = 'There are ledger rows without a valid accounting document.';
        }

        return [
            'ok' => $issues === [],
            'checked_at' => now()->toDateTimeString(),
            'checks' => [
                'open_fiscal_year' => ['ok' => $hasOpenFiscalYear],
                'documents_balanced' => ['ok' => $unbalancedDocuments === 0],
                'ledger_orphans' => ['ok' => $orphanLedgerRows === 0],
            ],
            'stats' => [
                'documents_last_30_days' => $documentsLast30Days,
                'unbalanced_documents' => $unbalancedDocuments,
                'orphan_ledger_rows' => $orphanLedgerRows,
            ],
            'issues' => $issues,
        ];
    }
}
