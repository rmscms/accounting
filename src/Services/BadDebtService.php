<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\{BadDebtProvision, BadDebtWriteoff, CustomerInvoice};
use Illuminate\Support\Facades\{DB, Log};
use RMS\Accounting\Support\AuditActor;

class BadDebtService
{
    protected LedgerService $ledgerService;
    public function __construct(LedgerService $ledgerService) { $this->ledgerService = $ledgerService; }

    public function calculateProvision(array $filters = []): float
    {
        $method = $filters['method'] ?? 'aging_analysis';
        
        if ($method === 'percentage_sales') {
            $totalSales = CustomerInvoice::whereBetween('invoice_date', [$filters['start_date'], $filters['end_date']])->sum('total_amount');
            $percentage = $filters['percentage'] ?? 2; // 2% default
            return $totalSales * ($percentage / 100);
        }
        
        if ($method === 'aging_analysis') {
            $overdueInvoices = CustomerInvoice::where('payment_status', '!=', 'paid')->where('due_date', '<', now())->get();
            $provision = 0;
            foreach ($overdueInvoices as $invoice) {
                $daysPastDue = now()->diffInDays($invoice->due_date);
                $percentage = match(true) {
                    $daysPastDue > 180 => 100,
                    $daysPastDue > 90 => 50,
                    $daysPastDue > 60 => 25,
                    $daysPastDue > 30 => 10,
                    default => 0
                };
                $provision += $invoice->balance_due * ($percentage / 100);
            }
            return $provision;
        }
        
        return 0;
    }

    public function recordProvision(array $data): BadDebtProvision
    {
        DB::beginTransaction();
        try {
            $provisionPayload = [
                'provision_number' => $data['provision_number'] ?? $this->generateNumber('BDP'),
                'customer_id' => $data['customer_id'] ?? null,
                'provision_date' => $data['provision_date'] ?? now(),
                'provision_amount' => $data['provision_amount'],
                'calculation_method' => $data['calculation_method'] ?? 'aging_analysis',
                'percentage_used' => $data['percentage_used'] ?? null,
                'notes' => $data['notes'] ?? null,
            ];
            $provisionPayload = AuditActor::stamp($provisionPayload, 'bad_debt_provisions', 'created');

            $provision = BadDebtProvision::create($provisionPayload);
            
            $document = $this->ledgerService->recordTransaction([
                'document_type' => 'bad_debt_provision',
                'reference_type' => BadDebtProvision::class,
                'reference_id' => $provision->id,
                'description' => "ذخیره مطالبات مشکوک {$provision->provision_number}",
            ], [
                ['account_id' => $this->getBadDebtExpenseAccountId(), 'debit_amount' => (float) $provision->provision_amount, 'credit_amount' => 0, 'description' => 'هزینه مطالبات مشکوک'],
                ['account_id' => $this->getAllowanceAccountId(), 'debit_amount' => 0, 'credit_amount' => (float) $provision->provision_amount, 'description' => 'ذخیره مطالبات مشکوک'],
            ]);
            
            $provision->update(['accounting_document_id' => $document->id]);
            DB::commit();
            return $provision;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function writeOffBadDebt(array $data): BadDebtWriteoff
    {
        DB::beginTransaction();
        try {
            $writeoffPayload = [
                'writeoff_number' => $data['writeoff_number'] ?? $this->generateNumber('BDW'),
                'bad_debt_provision_id' => $data['bad_debt_provision_id'] ?? null,
                'customer_id' => $data['customer_id'],
                'customer_invoice_id' => $data['customer_invoice_id'] ?? null,
                'writeoff_date' => $data['writeoff_date'] ?? now(),
                'writeoff_amount' => $data['writeoff_amount'],
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'approved_at' => now(),
                'status' => BadDebtWriteoff::STATUS_APPROVED,
            ];
            $writeoffPayload = AuditActor::stamp($writeoffPayload, 'bad_debt_writeoffs', ['created', 'approved']);

            $writeoff = BadDebtWriteoff::create($writeoffPayload);
            
            if ($writeoff->customer_invoice_id) {
                $invoice = CustomerInvoice::find($writeoff->customer_invoice_id);
                $invoice->balance_due = 0;
                $invoice->payment_status = 'written_off';
                $invoice->save();
            }
            
            $document = $this->ledgerService->recordTransaction([
                'document_type' => 'bad_debt_writeoff',
                'reference_type' => BadDebtWriteoff::class,
                'reference_id' => $writeoff->id,
                'description' => "حذف مطالبات مشکوک {$writeoff->writeoff_number}",
            ], [
                ['account_id' => $this->getAllowanceAccountId(), 'debit_amount' => $writeoff->writeoff_amount, 'credit_amount' => 0, 'description' => 'کاهش ذخیره'],
                ['account_id' => $this->getARAccountId(), 'debit_amount' => 0, 'credit_amount' => $writeoff->writeoff_amount, 'description' => 'حذف دریافتنی'],
            ]);
            
            $writeoff->update(['accounting_document_id' => $document->id]);
            DB::commit();
            return $writeoff;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function generateNumber(string $prefix): string
    {
        $year = date('Y'); $month = date('m');
        $table = $prefix === 'BDP' ? 'bad_debt_provisions' : 'bad_debt_writeoffs';
        $last = DB::table($table)->whereYear('created_at', $year)->whereMonth('created_at', $month)->orderBy('id', 'desc')->first();
        $nextNumber = $last ? intval(substr($last->{$prefix === 'BDP' ? 'provision_number' : 'writeoff_number'}, -6)) + 1 : 1;
        return sprintf('%s-%s%s-%06d', $prefix, $year, $month, $nextNumber);
    }

    protected function getARAccountId(): int { return \RMS\Core\Models\Setting::get('accounting.ar_account_id', 1); }
    protected function getBadDebtExpenseAccountId(): int { return \RMS\Core\Models\Setting::get('accounting.bad_debt_expense_account_id', 1); }
    protected function getAllowanceAccountId(): int { return \RMS\Core\Models\Setting::get('accounting.allowance_doubtful_accounts_id', 1); }
}
