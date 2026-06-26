<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\Accrual;
use Illuminate\Support\Facades\{DB, Log};
use RMS\Accounting\Support\AuditActor;

class AccrualService
{
    protected LedgerService $ledgerService;
    public function __construct(LedgerService $ledgerService) { $this->ledgerService = $ledgerService; }

    public function createAccrual(array $data): Accrual
    {
        DB::beginTransaction();
        try {
            $payload = [
                'accrual_number' => $data['accrual_number'] ?? $this->generateNumber(),
                'accrual_type' => $data['accrual_type'],
                'accrual_date' => $data['accrual_date'] ?? now(),
                'amount' => $data['amount'],
                'account_id' => $data['account_id'],
                'description' => $data['description'],
                'reversal_date' => $data['reversal_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ];
            $payload = AuditActor::stamp($payload, 'accruals', 'created');

            $accrual = Accrual::create($payload);
            
            $this->recordInLedger($accrual);
            DB::commit();
            return $accrual;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function recordInLedger(Accrual $accrual): void
    {
        $entries = match($accrual->accrual_type) {
            Accrual::TYPE_ACCRUED_REVENUE => [
                ['account_id' => $this->getARAccountId(), 'debit_amount' => $accrual->amount, 'credit_amount' => 0, 'description' => 'درآمد تعهدی'],
                ['account_id' => $accrual->account_id, 'debit_amount' => 0, 'credit_amount' => $accrual->amount, 'description' => 'شناسایی درآمد'],
            ],
            Accrual::TYPE_ACCRUED_EXPENSE => [
                ['account_id' => $accrual->account_id, 'debit_amount' => $accrual->amount, 'credit_amount' => 0, 'description' => 'هزینه تعهدی'],
                ['account_id' => $this->getAPAccountId(), 'debit_amount' => 0, 'credit_amount' => $accrual->amount, 'description' => 'شناسایی هزینه'],
            ],
            Accrual::TYPE_DEFERRED_REVENUE => [
                ['account_id' => $this->getCashAccountId(), 'debit_amount' => $accrual->amount, 'credit_amount' => 0, 'description' => 'دریافت وجه'],
                ['account_id' => $accrual->account_id, 'debit_amount' => 0, 'credit_amount' => $accrual->amount, 'description' => 'درآمد موکول'],
            ],
            Accrual::TYPE_DEFERRED_EXPENSE => [
                ['account_id' => $accrual->account_id, 'debit_amount' => $accrual->amount, 'credit_amount' => 0, 'description' => 'هزینه موکول'],
                ['account_id' => $this->getCashAccountId(), 'debit_amount' => 0, 'credit_amount' => $accrual->amount, 'description' => 'پرداخت وجه'],
            ],
        };
        
        $document = $this->ledgerService->recordTransaction([
            'document_type' => 'accrual',
            'reference_type' => Accrual::class,
            'reference_id' => $accrual->id,
            'description' => $accrual->description,
        ], $entries);
        
        $accrual->update(['accounting_document_id' => $document->id]);
    }

    public function reverseAccrual(int $accrualId): void
    {
        DB::beginTransaction();
        try {
            $accrual = Accrual::findOrFail($accrualId);
            if ($accrual->is_reversed) throw new \Exception('Already reversed');
            
            $reversal = $this->ledgerService->reverseDocument($accrual->accounting_document_id, 'برگشت تعهد');
            $accrual->update(['is_reversed' => true, 'reversal_document_id' => $reversal->id]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function generateNumber(): string
    {
        $year = date('Y'); $month = date('m');
        $last = Accrual::whereYear('created_at', $year)->whereMonth('created_at', $month)->orderBy('id', 'desc')->first();
        $nextNumber = $last ? intval(substr($last->accrual_number, -6)) + 1 : 1;
        return sprintf('ACR-%s%s-%06d', $year, $month, $nextNumber);
    }

    protected function getARAccountId(): int { return \RMS\Core\Models\Setting::get('accounting.ar_account_id', 1); }
    protected function getAPAccountId(): int { return \RMS\Core\Models\Setting::get('accounting.ap_account_id', 1); }
    protected function getCashAccountId(): int { return \RMS\Core\Models\Setting::get('accounting.cash_account_id', 1); }
}
