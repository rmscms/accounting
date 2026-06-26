<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\Settlement;
use RMS\Accounting\Models\CustomerPayment;
use RMS\Accounting\Models\SupplierPayment;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Settlement Service
 * 
 * مدیریت تسویه حساب‌ها
 */
class SettlementService
{
    /**
     * ایجاد تسویه حساب جدید
     */
    public function createSettlement(array $data): Settlement
    {
        try {
            DB::beginTransaction();

            $settlement = new Settlement();
            $settlement->store_id = $data['store_id'];
            $settlement->party_type = $data['party_type']; // 'customer' or 'supplier'
            $settlement->party_id = $data['party_id'];
            $settlement->settlement_date = $data['settlement_date'] ?? Carbon::now();
            $settlement->period_from = $data['period_from'];
            $settlement->period_to = $data['period_to'];
            $settlement->total_invoiced = $data['total_invoiced'] ?? 0;
            $settlement->total_paid = $data['total_paid'] ?? 0;
            $settlement->adjustments = $data['adjustments'] ?? 0;
            $settlement->final_balance = $this->calculateFinalBalance($data);
            $settlement->status = $data['status'] ?? 'pending';
            $settlement->notes = $data['notes'] ?? null;
            $settlement->created_by = \RMS\Accounting\Support\AuditActor::actorId();
            $settlement->save();

            DB::commit();
            return $settlement->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * محاسبه موجودی نهایی
     */
    protected function calculateFinalBalance(array $data): float
    {
        $balance = $data['total_invoiced'] - $data['total_paid'];
        $balance += ($data['adjustments'] ?? 0);

        return $balance;
    }

    /**
     * تایید تسویه حساب
     */
    public function approveSettlement(Settlement $settlement): Settlement
    {
        $settlement->status = 'approved';
        $settlement->approved_at = Carbon::now();
        $settlement->approved_by = \RMS\Accounting\Support\AuditActor::actorId();
        $settlement->save();

        return $settlement;
    }

    /**
     * محاسبه خودکار تسویه برای مشتری
     */
    public function calculateCustomerSettlement(
        int $customerId,
        int $storeId,
        Carbon $periodFrom,
        Carbon $periodTo
    ): array {
        // Get all invoices in period
        $invoices = \RMS\Accounting\Models\CustomerInvoice::where('customer_id', $customerId)
            ->where('store_id', $storeId)
            ->whereBetween('invoice_date', [$periodFrom, $periodTo])
            ->get();

        // Get all payments in period
        $payments = CustomerPayment::where('customer_id', $customerId)
            ->where('store_id', $storeId)
            ->whereBetween('payment_date', [$periodFrom, $periodTo])
            ->where('status', 'confirmed')
            ->get();

        $totalInvoiced = $invoices->sum('total_amount');
        $totalPaid = $payments->sum('amount');

        return [
            'party_type' => 'customer',
            'party_id' => $customerId,
            'store_id' => $storeId,
            'period_from' => $periodFrom,
            'period_to' => $periodTo,
            'total_invoiced' => $totalInvoiced,
            'total_paid' => $totalPaid,
            'invoice_count' => $invoices->count(),
            'payment_count' => $payments->count(),
            'balance' => $totalInvoiced - $totalPaid,
            'invoices' => $invoices,
            'payments' => $payments,
        ];
    }

    /**
     * محاسبه خودکار تسویه برای تامین‌کننده
     */
    public function calculateSupplierSettlement(
        int $supplierId,
        int $storeId,
        Carbon $periodFrom,
        Carbon $periodTo
    ): array {
        // Get all invoices in period
        $invoices = \RMS\Accounting\Models\SupplierInvoice::where('supplier_id', $supplierId)
            ->where('store_id', $storeId)
            ->whereBetween('invoice_date', [$periodFrom, $periodTo])
            ->get();

        // Get all payments in period
        $payments = SupplierPayment::where('supplier_id', $supplierId)
            ->where('store_id', $storeId)
            ->whereBetween('payment_date', [$periodFrom, $periodTo])
            ->where('status', 'completed')
            ->get();

        $totalInvoiced = $invoices->sum('total_amount');
        $totalPaid = $payments->sum('amount');

        return [
            'party_type' => 'supplier',
            'party_id' => $supplierId,
            'store_id' => $storeId,
            'period_from' => $periodFrom,
            'period_to' => $periodTo,
            'total_invoiced' => $totalInvoiced,
            'total_paid' => $totalPaid,
            'invoice_count' => $invoices->count(),
            'payment_count' => $payments->count(),
            'balance' => $totalInvoiced - $totalPaid,
            'invoices' => $invoices,
            'payments' => $payments,
        ];
    }

    /**
     * ایجاد تسویه خودکار از محاسبات
     */
    public function createFromCalculation(array $calculation): Settlement
    {
        return $this->createSettlement([
            'store_id' => $calculation['store_id'],
            'party_type' => $calculation['party_type'],
            'party_id' => $calculation['party_id'],
            'period_from' => $calculation['period_from'],
            'period_to' => $calculation['period_to'],
            'total_invoiced' => $calculation['total_invoiced'],
            'total_paid' => $calculation['total_paid'],
            'adjustments' => 0,
            'notes' => "تسویه خودکار - {$calculation['invoice_count']} فاکتور، {$calculation['payment_count']} پرداخت",
        ]);
    }

    /**
     * افزودن تعدیل به تسویه
     */
    public function addAdjustment(Settlement $settlement, float $amount, string $reason): Settlement
    {
        $settlement->adjustments = ($settlement->adjustments ?? 0) + $amount;
        $settlement->final_balance = $settlement->total_invoiced - $settlement->total_paid + $settlement->adjustments;
        
        $currentNotes = $settlement->notes ? $settlement->notes . "\n" : '';
        $settlement->notes = $currentNotes . "تعدیل: " . number_format($amount) . " - " . $reason;
        
        $settlement->save();

        return $settlement;
    }

    /**
     * دریافت تسویه‌های در انتظار
     */
    public function getPendingSettlements(int $storeId = null)
    {
        $query = Settlement::where('status', 'pending')
            ->orderBy('settlement_date', 'desc');

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        return $query->get();
    }

    /**
     * دریافت تاریخچه تسویه‌ها
     */
    public function getSettlementHistory(string $partyType, int $partyId, int $storeId = null)
    {
        $query = Settlement::where('party_type', $partyType)
            ->where('party_id', $partyId)
            ->orderBy('settlement_date', 'desc');

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        return $query->get();
    }

    /**
     * لغو تسویه
     */
    public function cancelSettlement(Settlement $settlement, string $reason): Settlement
    {
        $settlement->status = 'cancelled';
        $settlement->cancelled_at = Carbon::now();
        $settlement->cancellation_reason = $reason;
        $settlement->save();

        return $settlement;
    }
}
