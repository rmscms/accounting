<?php

declare(strict_types=1);

namespace RMS\Accounting\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RMS\Accounting\Events\FiscalYearClosedEvent;
use RMS\Accounting\Models\AccountingDocument;
use RMS\Accounting\Models\FiscalYear;

/**
 * مسیر واحد بستن سال مالی: بستن اداری، بستن با اسناد اختتامیه، و همگام‌سازی is_current.
 */
class FiscalYearCloseOrchestrationService
{
    public function __construct(
        protected FiscalYearService $fiscalYearService,
        protected FiscalYearClosingService $fiscalYearClosingService
    ) {
    }

    /**
     * بستن اداری (بدون اسناد اختتامیهٔ خودکار) — همان رویداد و مانده‌های پایان سال در FiscalYearService.
     */
    public function closeAdministrative(
        int $fiscalYearId,
        int $closedByUserId,
        ?int $nextFiscalYearIdToActivate = null,
        bool $createNextYearIfMissing = false
    ): FiscalYear {
        $fiscalYear = FiscalYear::findOrFail($fiscalYearId);

        if ($fiscalYear->status === FiscalYear::STATUS_CLOSED) {
            throw new \InvalidArgumentException(trans('accounting::accounting.errors.fiscal_year_already_closed'));
        }

        $this->fiscalYearService->closeFiscalYear($fiscalYearId, $closedByUserId);

        $fiscalYear->refresh();

        if (Schema::hasColumn('fiscal_years', 'is_closed')) {
            $fiscalYear->forceFill(['is_closed' => false])->save();
        }

        $this->finalizeCurrentFlag($fiscalYear, $nextFiscalYearIdToActivate, $createNextYearIfMissing, $closedByUserId);

        return $fiscalYear->fresh();
    }

    /**
     * بستن با اسناد اختتامیه (مالیات، سود انباشته، …) سپس وضعیت بسته و is_current.
     */
    public function closeWithClosingEntries(
        int $fiscalYearId,
        int $closedByUserId,
        ?int $nextFiscalYearIdToActivate = null,
        bool $createNextYearIfMissing = false,
        bool $deferNextYearActivation = false
    ): array {
        $fiscalYear = FiscalYear::findOrFail($fiscalYearId);

        if ($fiscalYear->status === FiscalYear::STATUS_CLOSED) {
            throw new \InvalidArgumentException(trans('accounting::accounting.errors.fiscal_year_already_closed'));
        }

        if (! Schema::hasColumn('fiscal_years', 'is_closed')) {
            throw new \RuntimeException(trans('accounting::accounting.fiscal_year_close.wizard.errors.missing_db_columns'));
        }

        return DB::transaction(function () use ($fiscalYear, $closedByUserId, $nextFiscalYearIdToActivate, $createNextYearIfMissing, $deferNextYearActivation): array {
            $result = $this->fiscalYearClosingService->closeFiscalYear($fiscalYear, $closedByUserId);

            $fiscalYear->refresh();

            $balances = $this->fiscalYearService->yearEndBalancesForFiscalYear($fiscalYear);
            event(new FiscalYearClosedEvent($fiscalYear, $closedByUserId, $balances));

            if (! $deferNextYearActivation) {
                $this->finalizeCurrentFlag($fiscalYear, $nextFiscalYearIdToActivate, $createNextYearIfMissing, $closedByUserId);
            }

            return $result;
        });
    }

    /**
     * پیش‌نیازها و گزینه‌های سال بعد برای ویزارد.
     *
     * @return array{fiscal_year: FiscalYear, draft_documents: int, is_current: bool, can_full_close: bool, full_close_block_reason: string|null, next_year_candidates: \Illuminate\Support\Collection}
     */
    public function getWizardContext(int $fiscalYearId): array
    {
        $fiscalYear = FiscalYear::findOrFail($fiscalYearId);

        $draftDocuments = \RMS\Accounting\Models\AccountingDocument::query()
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('status', \RMS\Accounting\Models\AccountingDocument::STATUS_DRAFT)
            ->count();

        $canFullClose = true;
        $fullCloseBlockReason = null;

        if (! Schema::hasColumn('fiscal_years', 'is_closed')) {
            $canFullClose = false;
            $fullCloseBlockReason = trans('accounting::accounting.fiscal_year_close.wizard.errors.missing_db_columns');
        }

        $nextYearCandidates = FiscalYear::query()
            ->where('id', '!=', $fiscalYearId)
            ->where('status', FiscalYear::STATUS_OPEN)
            ->orderBy('start_date')
            ->get(['id', 'year_code', 'start_date', 'end_date', 'is_current']);

        return [
            'fiscal_year' => $fiscalYear,
            'draft_documents' => $draftDocuments,
            'is_current' => (bool) $fiscalYear->is_current,
            'can_full_close' => $canFullClose,
            'full_close_block_reason' => $fullCloseBlockReason,
            'next_year_candidates' => $nextYearCandidates,
        ];
    }

    /**
     * پیش‌بررسی ویزارد: مانده حساب‌های موقت + پیش‌نیازها.
     *
     * @return array<string, mixed>
     */
    public function runPrecheck(int $fiscalYearId): array
    {
        $context = $this->getWizardContext($fiscalYearId);
        $fiscalYear = $context['fiscal_year'];
        $temporary = $this->fiscalYearClosingService->temporaryAccountsSnapshot($fiscalYear);

        return [
            'fiscal_year' => [
                'id' => (int) $fiscalYear->id,
                'year_code' => (string) $fiscalYear->year_code,
                'status' => (string) $fiscalYear->status,
                'is_current' => (bool) $fiscalYear->is_current,
                'is_closed' => (bool) ($fiscalYear->status === FiscalYear::STATUS_CLOSED),
            ],
            'draft_documents' => (int) $context['draft_documents'],
            'can_full_close' => (bool) $context['can_full_close'],
            'full_close_block_reason' => $context['full_close_block_reason'],
            'temporary_accounts' => $temporary,
            'can_proceed' => (bool) $context['can_full_close'] && $fiscalYear->status !== FiscalYear::STATUS_CLOSED,
        ];
    }

    /**
     * پیش‌نمایش بستن: تخمین مالیات + انتقال سود/زیان.
     *
     * @return array<string, mixed>
     */
    public function buildPreview(int $fiscalYearId): array
    {
        $fiscalYear = FiscalYear::findOrFail($fiscalYearId);
        $precheck = $this->runPrecheck($fiscalYearId);
        $tax = $this->fiscalYearClosingService->calculateIncomeTax($fiscalYear);
        $estimatedSummary = (float) ($precheck['temporary_accounts']['income_summary_net_estimate'] ?? 0);
        $incomeTaxExpense = (float) ($tax['income_tax_expense'] ?? 0);

        return [
            'precheck' => $precheck,
            'tax' => $tax,
            'preview' => [
                'temporary_close_entries_count' => (int) (($precheck['temporary_accounts']['non_zero_count'] ?? 0) + 1),
                'estimated_income_summary_before_tax' => $estimatedSummary,
                'estimated_income_summary_after_tax' => round($estimatedSummary - $incomeTaxExpense, 4),
                'will_create_tax_document' => $incomeTaxExpense > 0,
                'will_transfer_to_retained_earnings' => true,
            ],
        ];
    }

    /**
     * اجرای مرحله بستن کامل بدون بازکردن سال جدید (برای Step-3).
     *
     * @return array<string, mixed>
     */
    public function executeCloseStep(int $fiscalYearId, int $userId): array
    {
        return $this->closeWithClosingEntries($fiscalYearId, $userId, null, false, true);
    }

    /**
     * کنترل پس از بستن: حساب‌های موقت باید صفر باشند.
     *
     * @return array<string, mixed>
     */
    public function runPostcheck(int $fiscalYearId): array
    {
        $fiscalYear = FiscalYear::findOrFail($fiscalYearId);
        $temporary = $this->fiscalYearClosingService->temporaryAccountsSnapshot($fiscalYear);
        $closingDocs = AccountingDocument::query()
            ->where('fiscal_year_id', $fiscalYear->id)
            ->where('document_type', AccountingDocument::TYPE_CLOSING)
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'document_number', 'description', 'status', 'posted_at'])
            ->values();

        return [
            'fiscal_year' => [
                'id' => (int) $fiscalYear->id,
                'year_code' => (string) $fiscalYear->year_code,
                'status' => (string) $fiscalYear->status,
                'is_closed' => (bool) ($fiscalYear->status === FiscalYear::STATUS_CLOSED),
            ],
            'temporary_accounts' => $temporary,
            'temporary_accounts_zero' => (int) ($temporary['non_zero_count'] ?? 0) === 0,
            'closing_documents' => $closingDocs,
        ];
    }

    /**
     * بازکردن/ایجاد سال بعد و ساخت سند افتتاحیه.
     *
     * @return array<string, mixed>
     */
    public function openNextYearAndCreateOpening(
        int $closedFiscalYearId,
        int $actingUserId,
        ?int $nextFiscalYearIdToActivate = null,
        bool $createNextYearIfMissing = false
    ): array {
        return DB::transaction(function () use ($closedFiscalYearId, $actingUserId, $nextFiscalYearIdToActivate, $createNextYearIfMissing): array {
            $closedYear = FiscalYear::query()->findOrFail($closedFiscalYearId);
            if ($closedYear->status !== FiscalYear::STATUS_CLOSED) {
                throw new \InvalidArgumentException(trans('accounting::accounting.fiscal_year_close.wizard.errors.year_must_be_closed'));
            }

            $this->finalizeCurrentFlag($closedYear, $nextFiscalYearIdToActivate, $createNextYearIfMissing, $actingUserId);

            $nextYear = FiscalYear::query()
                ->where('id', '!=', $closedYear->id)
                ->where('is_current', true)
                ->first();

            if ($nextYear === null) {
                throw new \RuntimeException(trans('accounting::accounting.fiscal_year_close.wizard.errors.next_year_not_open'));
            }

            $openingDocument = $this->fiscalYearService->createOpeningEntryFromYearEndBalances(
                $closedYear,
                $nextYear,
                $actingUserId
            );

            return [
                'closed_year_id' => (int) $closedYear->id,
                'next_year' => [
                    'id' => (int) $nextYear->id,
                    'year_code' => (string) $nextYear->year_code,
                    'status' => (string) $nextYear->status,
                    'is_current' => (bool) $nextYear->is_current,
                ],
                'opening_document_id' => $openingDocument?->id,
                'opening_document_number' => $openingDocument?->document_number,
            ];
        });
    }

    protected function finalizeCurrentFlag(
        FiscalYear $closedYear,
        ?int $nextFiscalYearIdToActivate,
        bool $createNextYearIfMissing,
        int $_actingUserId
    ): void {
        $closedYear->refresh();

        if ($nextFiscalYearIdToActivate) {
            $next = FiscalYear::findOrFail($nextFiscalYearIdToActivate);
            if ($next->id === $closedYear->id) {
                throw new \InvalidArgumentException(trans('accounting::accounting.fiscal_year_close.wizard.errors.invalid_next_year'));
            }
            if ($next->status !== FiscalYear::STATUS_OPEN) {
                throw new \InvalidArgumentException(trans('accounting::accounting.fiscal_year_close.wizard.errors.next_year_not_open'));
            }
            $next->setCurrent();

            return;
        }

        if ($createNextYearIfMissing) {
            $this->fiscalYearService->createNextFiscalYear($closedYear->id);

            return;
        }

        $stillCurrent = FiscalYear::query()->where('is_current', true)->exists();
        if (! $stillCurrent) {
            $this->fiscalYearService->getOrCreateCurrentFiscalYear();
        }
    }
}
