<?php

declare(strict_types=1);

namespace RMS\Accounting\Support\Reports;

final class InsuranceMonthlyReportDto
{
    /**
     * @param array<int, array<string, mixed>> $sourceRows
     */
    public function __construct(
        public readonly string $periodStart,
        public readonly string $periodEnd,
        public readonly string $generatedAt,
        public readonly ?int $generatedByUserId,
        public readonly float $openingBalance,
        public readonly float $accrualEmployee,
        public readonly float $accrualEmployer,
        public readonly float $paymentTotal,
        public readonly float $ledgerAccrualTotal,
        public readonly float $ledgerPaymentTotal,
        public readonly float $closingBalance,
        public readonly float $formulaClosingBalance,
        public readonly float $formulaDifference,
        public readonly float $ledgerDifference,
        public readonly bool $isBalanced,
        public readonly array $sourceRows
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $accrualTotal = $this->accrualEmployee + $this->accrualEmployer;

        return [
            'period' => [
                'start' => $this->periodStart,
                'end' => $this->periodEnd,
            ],
            'generated_at' => $this->generatedAt,
            'generated_by_user_id' => $this->generatedByUserId,
            'totals' => [
                'opening_balance' => $this->openingBalance,
                'accrual_employee' => $this->accrualEmployee,
                'accrual_employer' => $this->accrualEmployer,
                'accrual_total' => $accrualTotal,
                'payment_total' => $this->paymentTotal,
                'closing_balance' => $this->closingBalance,
                'formula_closing_balance' => $this->formulaClosingBalance,
                'formula_difference' => $this->formulaDifference,
                'ledger_accrual_total' => $this->ledgerAccrualTotal,
                'ledger_payment_total' => $this->ledgerPaymentTotal,
                'ledger_difference' => $this->ledgerDifference,
                'is_balanced' => $this->isBalanced,
            ],
            'source_rows' => $this->sourceRows,
        ];
    }
}
