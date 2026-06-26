<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\ManualJournal;
use RMS\Accounting\Models\ManualJournalLine;
use RMS\Accounting\Models\FiscalYear;
use Illuminate\Support\Facades\DB;
use RMS\Accounting\Support\InteractsWithAuditActor;

class ManualJournalService
{
    use InteractsWithAuditActor;

    protected LedgerService $ledgerService;

    public function __construct(LedgerService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function withNormalizedJournalDate(array $data): array
    {
        if (! isset($data['journal_date'])) {
            return $data;
        }

        $g = app(AccountingDateInputNormalizer::class)->normalizeFilterDateToGregorian((string) $data['journal_date']);
        if ($g === null) {
            throw new \InvalidArgumentException((string) trans('accounting::errors.invalid_date'));
        }
        $data['journal_date'] = $g;

        return $data;
    }

    /**
     * ایجاد سند دستی (پیش‌نویس)
     */
    public function createJournal(array $data): ManualJournal
    {
        $data = $this->withNormalizedJournalDate($data);

        return DB::transaction(function () use ($data) {
            // تعیین سال مالی
            $fiscalYearId = $data['fiscal_year_id'] ?? $this->getCurrentFiscalYearId($data['journal_date']);

            $createPayload = [
                'journal_number' => $data['journal_number'] ?? ManualJournal::generateJournalNumber(),
                'journal_date' => $data['journal_date'],
                'posting_date' => $data['posting_date'] ?? null,
                'fiscal_year_id' => $fiscalYearId,
                'description' => $data['description'],
                'notes' => $data['notes'] ?? null,
                'total_debit' => 0,
                'total_credit' => 0,
                'status' => 'draft',
            ];
            $createPayload = $this->stampAudit($createPayload, 'manual_journals', 'created');

            $journal = ManualJournal::create($createPayload);

            // افزودن سطرها اگر وجود داشته باشد
            if (isset($data['lines']) && is_array($data['lines'])) {
                foreach ($data['lines'] as $index => $lineData) {
                    $this->addLine($journal->id, array_merge($lineData, ['line_number' => $index + 1]));
                }
            }

            return $journal->fresh('lines');
        });
    }

    /**
     * افزودن سطر به سند
     */
    public function addLine(int $journalId, array $lineData): ManualJournalLine
    {
        $journal = ManualJournal::findOrFail($journalId);

        if ($journal->status !== 'draft') {
            throw new \Exception('Cannot modify posted or reversed journal');
        }

        $line = ManualJournalLine::create([
            'manual_journal_id' => $journalId,
            'line_number' => $lineData['line_number'] ?? ($journal->lines()->max('line_number') + 1),
            'account_id' => $lineData['account_id'],
            'debit_amount' => $lineData['debit_amount'] ?? 0,
            'credit_amount' => $lineData['credit_amount'] ?? 0,
            'currency_code' => $lineData['currency_code'] ?? 'IRR',
            'fx_rate' => $lineData['fx_rate'] ?? 1,
            'description' => $lineData['description'] ?? null,
        ]);

        // به‌روزرسانی مجموع
        $this->recalculateTotals($journalId);

        return $line;
    }

    /**
     * ویرایش سطر سند (فقط پیش‌نویس)
     *
     * @param  array<string, mixed>  $lineData
     */
    public function updateLine(int $journalId, int $lineId, array $lineData): ManualJournalLine
    {
        $journal = ManualJournal::findOrFail($journalId);

        if ($journal->status !== 'draft') {
            throw new \Exception('Cannot modify posted or reversed journal');
        }

        $line = ManualJournalLine::query()
            ->where('manual_journal_id', $journalId)
            ->whereKey($lineId)
            ->firstOrFail();

        $line->update([
            'account_id' => (int) $lineData['account_id'],
            'debit_amount' => $lineData['debit_amount'] ?? 0,
            'credit_amount' => $lineData['credit_amount'] ?? 0,
            'description' => $lineData['description'] ?? null,
        ]);

        $this->recalculateTotals($journalId);

        return $line->fresh(['account']);
    }

    /**
     * محاسبه مجدد مجموع بدهکار و بستانکار
     */
    protected function recalculateTotals(int $journalId): void
    {
        $journal = ManualJournal::findOrFail($journalId);

        $totalDebit = $journal->lines()->sum('debit_amount');
        $totalCredit = $journal->lines()->sum('credit_amount');

        $journal->update([
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
        ]);
    }

    /**
     * اعتبارسنجی تعادل بدهکار/بستانکار
     */
    public function validateBalance(int $journalId): bool
    {
        $journal = ManualJournal::with('lines')->findOrFail($journalId);

        if ($journal->lines->isEmpty()) {
            throw new \Exception('Journal must have at least one line');
        }

        $totalDebit = $journal->lines->sum('debit_amount');
        $totalCredit = $journal->lines->sum('credit_amount');

        if (abs($totalDebit - $totalCredit) > 0.01) { // tolerance for rounding
            throw new \Exception("Journal is not balanced. Debit: {$totalDebit}, Credit: {$totalCredit}");
        }

        return true;
    }

    /**
     * ثبت قطعی سند در دفاتر
     */
    public function postJournal(int $journalId): ManualJournal
    {
        return DB::transaction(function () use ($journalId) {
            $journal = ManualJournal::with('lines')->findOrFail($journalId);

            if ($journal->status === 'posted') {
                throw new \Exception('Journal already posted');
            }

            if ($journal->status === 'reversed') {
                throw new \Exception('Cannot post reversed journal');
            }

            // اعتبارسنجی تعادل
            $this->validateBalance($journalId);

            // تبدیل سطرها به فرمت LedgerService
            $entries = [];
            foreach ($journal->lines as $line) {
                $entries[] = [
                    'account_id' => $line->account_id,
                    'debit' => $line->debit_amount,
                    'credit' => $line->credit_amount,
                    'currency_code' => $line->currency_code,
                    'fx_rate' => $line->fx_rate,
                    'description' => $line->description ?? $journal->description,
                ];
            }

            // ثبت سند
            $document = $this->ledgerService->recordTransaction([
                'document_type' => 'manual_journal',
                'reference_type' => 'manual_journal',
                'reference_id' => $journalId,
                'fiscal_year_id' => $journal->fiscal_year_id,
                'description' => $journal->description,
            ], $entries);

            // به‌روزرسانی journal
            $postPayload = [
                'status' => 'posted',
                'accounting_document_id' => $document->id,
                'posted_at' => now(),
                'posting_date' => now()->toDateString(),
            ];
            $postPayload = $this->stampAudit($postPayload, 'manual_journals', 'posted');

            $journal->update($postPayload);

            return $journal->fresh();
        });
    }

    /**
     * برگشت سند (ایجاد سند معکوس)
     */
    public function reverseJournal(int $journalId, string $reason): ManualJournal
    {
        return DB::transaction(function () use ($journalId, $reason) {
            $originalJournal = ManualJournal::with('lines')->findOrFail($journalId);

            if ($originalJournal->status !== 'posted') {
                throw new \Exception('Can only reverse posted journals');
            }

            if ($originalJournal->status === 'reversed') {
                throw new \Exception('Journal already reversed');
            }

            // ایجاد سند معکوس
            $reversalPayload = [
                'journal_number' => ManualJournal::generateJournalNumber(),
                'journal_date' => now()->toDateString(),
                'posting_date' => now()->toDateString(),
                'fiscal_year_id' => $this->getCurrentFiscalYearId(),
                'description' => "برگشت سند: {$originalJournal->journal_number} - {$reason}",
                'notes' => $reason,
                'total_debit' => $originalJournal->total_debit,
                'total_credit' => $originalJournal->total_credit,
                'status' => 'draft',
            ];
            $reversalPayload = $this->stampAudit($reversalPayload, 'manual_journals', 'created');

            $reversalJournal = ManualJournal::create($reversalPayload);

            // ایجاد سطرهای معکوس
            foreach ($originalJournal->lines as $index => $originalLine) {
                ManualJournalLine::create([
                    'manual_journal_id' => $reversalJournal->id,
                    'line_number' => $index + 1,
                    'account_id' => $originalLine->account_id,
                    // معکوس کردن بدهکار و بستانکار
                    'debit_amount' => $originalLine->credit_amount,
                    'credit_amount' => $originalLine->debit_amount,
                    'currency_code' => $originalLine->currency_code,
                    'fx_rate' => $originalLine->fx_rate,
                    'description' => "برگشت: " . ($originalLine->description ?? $originalJournal->description),
                ]);
            }

            // ثبت قطعی سند برگشتی
            $this->postJournal($reversalJournal->id);

            // به‌روزرسانی سند اصلی
            $originalJournal->update([
                'status' => 'reversed',
                'reversed_journal_id' => $reversalJournal->id,
                'reversal_reason' => $reason,
                'reversed_at' => now(),
            ]);

            return $reversalJournal->fresh();
        });
    }

    /**
     * کپی سند
     */
    public function duplicateJournal(int $journalId): ManualJournal
    {
        return DB::transaction(function () use ($journalId) {
            $originalJournal = ManualJournal::with('lines')->findOrFail($journalId);

            // ایجاد سند جدید
            $duplicatePayload = [
                'journal_number' => ManualJournal::generateJournalNumber(),
                'journal_date' => now()->toDateString(),
                'fiscal_year_id' => $this->getCurrentFiscalYearId(),
                'description' => "کپی از: {$originalJournal->journal_number} - {$originalJournal->description}",
                'notes' => $originalJournal->notes,
                'total_debit' => $originalJournal->total_debit,
                'total_credit' => $originalJournal->total_credit,
                'status' => 'draft',
            ];
            $duplicatePayload = $this->stampAudit($duplicatePayload, 'manual_journals', 'created');

            $newJournal = ManualJournal::create($duplicatePayload);

            // کپی سطرها
            foreach ($originalJournal->lines as $index => $originalLine) {
                ManualJournalLine::create([
                    'manual_journal_id' => $newJournal->id,
                    'line_number' => $index + 1,
                    'account_id' => $originalLine->account_id,
                    'debit_amount' => $originalLine->debit_amount,
                    'credit_amount' => $originalLine->credit_amount,
                    'currency_code' => $originalLine->currency_code,
                    'fx_rate' => $originalLine->fx_rate,
                    'description' => $originalLine->description,
                ]);
            }

            return $newJournal->fresh('lines');
        });
    }

    /**
     * حذف سطر
     */
    public function deleteLine(int $journalId, int $lineId): void
    {
        $journal = ManualJournal::findOrFail($journalId);

        if ($journal->status !== 'draft') {
            throw new \Exception('Cannot modify posted or reversed journal');
        }

        ManualJournalLine::where('manual_journal_id', $journalId)
            ->where('id', $lineId)
            ->delete();

        // محاسبه مجدد مجموع
        $this->recalculateTotals($journalId);
    }

    /**
     * تعیین شناسهٔ سال مالی فعال برای تاریخ سند (مثلاً هنگام ذخیره از فرم CRUD ادمین).
     */
    public function resolveFiscalYearIdForJournalDate(?string $journalDate = null): int
    {
        return $this->getCurrentFiscalYearId($journalDate);
    }

    /**
     * دریافت سال مالی جاری
     */
    protected function getCurrentFiscalYearId(string $date = null): int
    {
        $date = $date ?? now()->toDateString();

        $fiscalYear = FiscalYear::query()
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->where(function ($q) {
                $q->whereIn('status', ['active', 'open'])
                    ->orWhere('is_current', 1);
            })
            ->orderByDesc('is_current')
            ->orderByDesc('start_date')
            ->first();

        if (!$fiscalYear) {
            throw new \Exception('No active fiscal year found for date: ' . $date);
        }

        return $fiscalYear->id;
    }
}
