<?php

declare(strict_types=1);

namespace RMS\Accounting\Services;

use Illuminate\Support\Collection;
use RMS\Accounting\Models\Currency;
use RMS\Accounting\Models\Shareholder;
use RMS\Accounting\Models\ShareholderCapitalContribution;
use RMS\Accounting\Models\ShareholderWithdrawal;

/**
 * خلاصهٔ واریز سرمایه و برداشت به‌ازای هر سهامدار و ارز؛ سهم عادلانهٔ برداشت از روی نسبت سرمایه.
 */
class ShareholderEquitySummaryService
{
    /**
     * @return array{currencies: list<string>, sections: list<array{currency: string, total_capital: float, total_withdrawals: float, rows: list<array<string, mixed>}>}
     */
    public function build(): array
    {
        $shareholders = Shareholder::query()->orderBy('name')->get();
        $currencies = $this->distinctCurrencies();

        $sections = [];
        foreach ($currencies as $ccy) {
            $contrib = $this->sumsByShareholder(ShareholderCapitalContribution::query()->where('currency_code', $ccy), 'shareholder_id');
            $withdraw = $this->sumsByShareholder(ShareholderWithdrawal::query()->where('currency_code', $ccy), 'shareholder_id');

            $totalCapital = (float) $contrib->sum('total');
            $totalWithdrawals = (float) $withdraw->sum('total');

            $contribMap = $contrib->keyBy('shareholder_id');
            $withdrawMap = $withdraw->keyBy('shareholder_id');

            $rows = [];
            foreach ($shareholders as $sh) {
                $capital = (float) ($contribMap->get($sh->id)->total ?? 0);
                $wd = (float) ($withdrawMap->get($sh->id)->total ?? 0);

                $capitalSharePct = $totalCapital > 0 ? ($capital / $totalCapital) * 100.0 : null;
                $fairWithdrawal = ($capitalSharePct !== null && $totalWithdrawals > 0)
                    ? $totalWithdrawals * ($capitalSharePct / 100.0)
                    : null;
                $diff = ($fairWithdrawal !== null) ? $wd - $fairWithdrawal : null;

                $status = 'neutral';
                if ($fairWithdrawal === null || $totalCapital <= 0) {
                    $status = $wd > 0 && $totalCapital <= 0 ? 'no_capital_base' : 'no_split';
                } elseif ($diff > 0.01) {
                    $status = 'over';
                } elseif ($diff < -0.01) {
                    $status = 'under';
                }

                $rows[] = [
                    'shareholder' => $sh,
                    'capital' => $capital,
                    'withdrawals' => $wd,
                    'capital_share_pct' => $capitalSharePct,
                    'fair_withdrawal' => $fairWithdrawal,
                    'diff_vs_fair' => $diff,
                    'status' => $status,
                ];
            }

            $sections[] = [
                'currency' => $ccy,
                'total_capital' => $totalCapital,
                'total_withdrawals' => $totalWithdrawals,
                'rows' => $rows,
            ];
        }

        return [
            'currencies' => $currencies,
            'sections' => $sections,
        ];
    }

    /**
     * @return list<string>
     */
    protected function distinctCurrencies(): array
    {
        $a = ShareholderCapitalContribution::query()->distinct()->pluck('currency_code');
        $b = ShareholderWithdrawal::query()->distinct()->pluck('currency_code');
        $merged = $a->merge($b)->filter()->unique()->sort()->values()->all();
        $out = array_map('strval', $merged);

        if ($out === []) {
            $out = [Currency::resolveBaseCurrencyCode('IRR')];
        }

        return $out;
    }

    /**
     * @return Collection<int, object{shareholder_id: int, total: string}>
     */
    protected function sumsByShareholder($query, string $fk): Collection
    {
        return $query
            ->selectRaw($fk.' as shareholder_id, SUM(amount) as total')
            ->groupBy($fk)
            ->get();
    }
}
