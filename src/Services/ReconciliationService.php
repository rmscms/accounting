<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\PaymentReconciliation;
use RMS\Accounting\Models\CustomerPayment;
use Illuminate\Support\Facades\DB;

/**
 * سرویس تطبیق پرداخت‌ها با صورت‌حساب بانکی
 * - تطبیق دریافت‌ها
 * - تشخیص اختلاف
 * - ثبت رسید و تایید
 */
class ReconciliationService
{
    /**
     * ایجاد تطبیق جدید
     */
    public function createReconciliation(array $data): PaymentReconciliation
    {
        DB::beginTransaction();
        try {
            // تولید شماره تطبیق
            if (empty($data['reconciliation_number'])) {
                $data['reconciliation_number'] = $this->generateReconciliationNumber();
            }

            // محاسبه اختلاف
            $data['discrepancy_amount'] = ($data['actual_amount'] ?? 0) - ($data['expected_amount'] ?? 0);

            // تعیین وضعیت
            if (abs($data['discrepancy_amount']) < 0.01) {
                $data['status'] = PaymentReconciliation::STATUS_MATCHED;
                $data['is_reconciled'] = true;
            } else {
                $data['status'] = PaymentReconciliation::STATUS_DISCREPANCY;
                $data['is_reconciled'] = false;
            }

            $reconciliation = PaymentReconciliation::create($data);

            DB::commit();
            return $reconciliation;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * تایید تطبیق (با checkbox)
     */
    public function confirmReconciliation(int $reconciliationId, ?string $notes = null): bool
    {
        DB::beginTransaction();
        try {
            $reconciliation = PaymentReconciliation::findOrFail($reconciliationId);

            $reconciliation->update([
                'is_reconciled' => true,
                'status' => PaymentReconciliation::STATUS_RESOLVED,
                'reconciled_by_user_id' => \RMS\Accounting\Support\AuditActor::userId(),
                'reconciled_at' => now(),
                'discrepancy_notes' => $notes ?? $reconciliation->discrepancy_notes,
            ]);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * تطبیق خودکار پرداخت‌ها
     */
    public function autoReconcilePayments(?int $bankId = null, ?string $date = null): array
    {
        $results = [
            'matched' => 0,
            'discrepancy' => 0,
            'failed' => 0,
        ];

        // دریافت پرداخت‌های تایید شده بدون تطبیق
        $query = CustomerPayment::where('status', CustomerPayment::STATUS_COMPLETED)
            ->whereDoesntHave('reconciliation');

        if ($bankId) {
            $query->where('bank_id', $bankId);
        }

        if ($date) {
            $query->whereDate('payment_date', $date);
        }

        $payments = $query->get();

        foreach ($payments as $payment) {
            try {
                $reconciliation = $this->createReconciliation([
                    'payment_id' => $payment->id,
                    'reconciliation_type' => PaymentReconciliation::TYPE_BANK,
                    'bank_id' => $payment->bank_id,
                    'expected_amount' => $payment->amount,
                    'actual_amount' => $payment->amount, // در حالت خودکار فرض می‌کنیم مساوی است
                    'reconciliation_date' => $payment->payment_date,
                ]);

                if ($reconciliation->status === PaymentReconciliation::STATUS_MATCHED) {
                    $results['matched']++;
                } else {
                    $results['discrepancy']++;
                }
            } catch (\Exception $e) {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * تولید شماره تطبیق
     */
    protected function generateReconciliationNumber(): string
    {
        $year = date('Y');
        $month = date('m');

        $last = PaymentReconciliation::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $last ? intval(substr($last->reconciliation_number, -6)) + 1 : 1;

        return sprintf('REC-%s%s-%06d', $year, $month, $nextNumber);
    }

    /**
     * گزارش تطبیق‌های دارای اختلاف
     */
    public function getDiscrepancies(?int $bankId = null)
    {
        $query = PaymentReconciliation::withDiscrepancy()
            ->with(['bank', 'cashBox', 'posTerminal']);

        if ($bankId) {
            $query->where('bank_id', $bankId);
        }

        return $query->orderBy('reconciliation_date', 'desc')->get();
    }
}
