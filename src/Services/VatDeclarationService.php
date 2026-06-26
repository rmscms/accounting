<?php

declare(strict_types=1);

namespace RMS\Accounting\Services;

use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use RMS\Accounting\Models\VatDeclaration;
use RMS\Accounting\Support\AuditActor;

class VatDeclarationService
{
    public function __construct(
        protected ReportService $reportService,
    ) {}

    /**
     * @param array<string,mixed> $payload
     */
    public function createDraft(array $payload): VatDeclaration
    {
        $periodStart = (string) ($payload['period_start'] ?? '');
        $periodEnd = (string) ($payload['period_end'] ?? '');
        if ($periodStart === '' || $periodEnd === '') {
            throw ValidationException::withMessages([
                'period' => ['شروع و پایان دوره برای اظهارنامه الزامی است.'],
            ]);
        }

        $start = Carbon::parse($periodStart)->startOfDay();
        $end = Carbon::parse($periodEnd)->endOfDay();
        if ($end->lessThan($start)) {
            throw ValidationException::withMessages([
                'period' => ['بازه اظهارنامه معتبر نیست.'],
            ]);
        }

        $vatReport = $this->reportService->getVATReport([
            'from_date' => $start->format('Y-m-d'),
            'to_date' => $end->format('Y-m-d'),
        ]);

        $quarter = $this->detectQuarter($start);
        $parentId = (int) ($payload['parent_declaration_id'] ?? 0);
        $version = $parentId > 0
            ? ((int) VatDeclaration::query()->where('parent_declaration_id', $parentId)->max('version')) + 1
            : 1;

        $createPayload = [
            'period_start' => $start->format('Y-m-d'),
            'period_end' => $end->format('Y-m-d'),
            'fiscal_year' => (int) $start->format('Y'),
            'fiscal_quarter' => $quarter,
            'version' => max(1, $version),
            'parent_declaration_id' => $parentId > 0 ? $parentId : null,
            'status' => $parentId > 0 ? VatDeclaration::STATUS_AMENDED : VatDeclaration::STATUS_DRAFT,
            'snapshot_json' => [
                'vat_report' => $vatReport,
                'generated_at' => now()->toDateTimeString(),
                'source' => 'system',
            ],
            'official_export_json' => $this->buildOfficialExportPayload($vatReport, $start, $end),
            'notes' => (string) ($payload['notes'] ?? ''),
        ];
        $createPayload = AuditActor::stamp($createPayload, 'vat_declarations', ['created', 'updated']);

        return VatDeclaration::query()->create($createPayload);
    }

    public function markSubmitted(VatDeclaration $declaration): VatDeclaration
    {
        $submitPayload = [
            'status' => VatDeclaration::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ];
        $submitPayload = AuditActor::stamp($submitPayload, 'vat_declarations', ['submitted', 'updated']);

        $declaration->update($submitPayload);

        return $declaration->fresh();
    }

    public function exportCsv(VatDeclaration $declaration): string
    {
        $payload = (array) ($declaration->official_export_json ?? []);
        $rows = (array) ($payload['rows'] ?? []);
        $csvRows = [
            ['Field', 'Value'],
        ];
        foreach ($rows as $row) {
            $csvRows[] = [(string) ($row['label'] ?? ''), (string) ($row['value'] ?? '')];
        }

        $lines = [];
        foreach ($csvRows as $row) {
            $escaped = array_map(static function (string $value): string {
                $safe = str_replace('"', '""', $value);
                return '"'.$safe.'"';
            }, $row);
            $lines[] = implode(',', $escaped);
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param array<string,mixed> $vatReport
     * @return array<string,mixed>
     */
    protected function buildOfficialExportPayload(array $vatReport, Carbon $start, Carbon $end): array
    {
        $outputVat = (float) data_get($vatReport, 'output_vat.vat', 0);
        $inputVat = (float) data_get($vatReport, 'input_vat.vat', 0);
        $accrualNet = (float) data_get($vatReport, 'vat_payable', 0);
        $remitted = (float) data_get($vatReport, 'remitted_vat', 0);
        $remaining = (float) data_get($vatReport, 'net_payable_remaining', $accrualNet - $remitted);

        return [
            'form' => '169',
            'period_start' => $start->format('Y-m-d'),
            'period_end' => $end->format('Y-m-d'),
            'generated_at' => now()->toDateTimeString(),
            'rows' => [
                ['label' => 'فروش مشمول', 'value' => (string) data_get($vatReport, 'output_vat.sales', 0)],
                ['label' => 'VAT خروجی', 'value' => (string) $outputVat],
                ['label' => 'خرید مشمول', 'value' => (string) data_get($vatReport, 'input_vat.purchases', 0)],
                ['label' => 'VAT ورودی', 'value' => (string) $inputVat],
                ['label' => 'خالص دوره', 'value' => (string) $accrualNet],
                ['label' => 'پرداخت‌شده دوره', 'value' => (string) $remitted],
                ['label' => 'مانده قابل پرداخت', 'value' => (string) $remaining],
            ],
        ];
    }

    protected function detectQuarter(Carbon $date): int
    {
        $month = (int) $date->format('n');
        return (int) ceil($month / 3);
    }
}
