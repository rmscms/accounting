<?php

namespace RMS\Accounting\Services;

use Illuminate\Support\Facades\Schema;
use RMS\Accounting\Models\Bank;
use RMS\Accounting\Models\CashBox;
use RMS\Accounting\Models\Cheque;
use RMS\Accounting\Models\PaymentMethod;
use RMS\Accounting\Models\POSTerminal;
use RMS\Accounting\Models\Wallet;

/**
 * کاتالوگ JSON برای ویجت «کانال تسویه»: بانک / صندوق / چک (+ POS / کیف در context دریافت مشتری).
 */
final class PaymentDestinationCatalog
{
    public const CONTEXT_SUPPLIER_PAYMENT = 'supplier_payment';

    public const CONTEXT_CUSTOMER_PAYMENT = 'customer_payment';

    /**
     * @return array<string, mixed>
     */
    public function build(string $context): array
    {
        $context = $this->normalizeContext($context);
        $methods = $this->activePaymentMethods();

        $channels = [];

        $channels[] = $this->bankChannel($methods);
        $channels[] = $this->cashBoxChannel($methods);
        $channels[] = $this->chequeChannel($methods);

        if ($context === self::CONTEXT_CUSTOMER_PAYMENT) {
            $channels[] = $this->posChannel($methods);
        }
        $channels[] = $this->walletChannel($methods, $context);

        return [
            'context' => $context,
            'channels' => array_values(array_filter($channels)),
        ];
    }

    /**
     * @return array{ok: bool, message: string|null}
     */
    public function validateSelection(
        string $context,
        int $paymentMethodId,
        ?int $bankId,
        ?int $cashBoxId,
        ?int $chequeId,
        ?int $posTerminalId,
        ?int $walletId
    ): array {
        $context = $this->normalizeContext($context);
        $method = PaymentMethod::query()->whereKey($paymentMethodId)->where('active', true)->first();
        if (! $method) {
            return ['ok' => false, 'message' => (string) trans('accounting::accounting.payment_destination.invalid_method')];
        }

        $bankId = $bankId !== null && (int) $bankId > 0 ? (int) $bankId : null;
        $cashBoxId = $cashBoxId !== null && (int) $cashBoxId > 0 ? (int) $cashBoxId : null;
        $chequeId = $chequeId !== null && (int) $chequeId > 0 ? (int) $chequeId : null;
        $posTerminalId = $posTerminalId !== null && (int) $posTerminalId > 0 ? (int) $posTerminalId : null;
        $walletId = $walletId !== null && (int) $walletId > 0 ? (int) $walletId : null;

        $nonNull = array_filter([$bankId, $cashBoxId, $chequeId, $posTerminalId, $walletId], static fn ($v) => $v !== null);
        if (count($nonNull) > 1) {
            return ['ok' => false, 'message' => (string) trans('accounting::accounting.payment_destination.multiple_destinations')];
        }

        $type = (string) $method->type;
        $requiresBank = (bool) $method->requires_bank;
        $requiresPos = (bool) $method->requires_pos;

        if ($type === PaymentMethod::TYPE_CASH) {
            return $cashBoxId !== null
                ? ['ok' => true, 'message' => null]
                : ['ok' => false, 'message' => (string) trans('accounting::accounting.payment_destination.cash_box_required')];
        }

        if ($type === PaymentMethod::TYPE_CHEQUE) {
            return $chequeId !== null
                ? ['ok' => true, 'message' => null]
                : ['ok' => false, 'message' => (string) trans('accounting::accounting.payment_destination.cheque_required')];
        }

        if ($type === PaymentMethod::TYPE_WALLET) {
            if ($walletId !== null && ! $this->walletAllowedForContext($walletId, $context)) {
                return ['ok' => false, 'message' => (string) trans('accounting::accounting.payment_destination.wallet_not_allowed')];
            }

            return $walletId !== null
                ? ['ok' => true, 'message' => null]
                : ['ok' => false, 'message' => (string) trans('accounting::accounting.payment_destination.wallet_required')];
        }

        if ($type === PaymentMethod::TYPE_POS || ($requiresPos && $context === self::CONTEXT_CUSTOMER_PAYMENT)) {
            if ($context !== self::CONTEXT_CUSTOMER_PAYMENT) {
                return ['ok' => false, 'message' => (string) trans('accounting::accounting.payment_destination.pos_not_allowed')];
            }

            return $posTerminalId !== null
                ? ['ok' => true, 'message' => null]
                : ['ok' => false, 'message' => (string) trans('accounting::accounting.payment_destination.pos_required')];
        }

        if ($requiresBank || in_array($type, [PaymentMethod::TYPE_ONLINE, PaymentMethod::TYPE_CARD_TRANSFER, PaymentMethod::TYPE_BANK_TRANSFER], true)) {
            return $bankId !== null
                ? ['ok' => true, 'message' => null]
                : ['ok' => false, 'message' => (string) trans('accounting::accounting.payment_destination.bank_required')];
        }

        return ['ok' => true, 'message' => null];
    }

    private function normalizeContext(string $context): string
    {
        return $context === self::CONTEXT_CUSTOMER_PAYMENT
            ? self::CONTEXT_CUSTOMER_PAYMENT
            : self::CONTEXT_SUPPLIER_PAYMENT;
    }

    /**
     * @return \Illuminate\Support\Collection<int, PaymentMethod>
     */
    private function activePaymentMethods()
    {
        return PaymentMethod::query()->active()->get();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PaymentMethod>  $methods
     * @return array<string, mixed>|null
     */
    private function bankChannel($methods): ?array
    {
        $forChannel = $methods->filter(function (PaymentMethod $m) {
            $t = (string) $m->type;

            return (bool) $m->requires_bank
                || in_array($t, [PaymentMethod::TYPE_ONLINE, PaymentMethod::TYPE_CARD_TRANSFER, PaymentMethod::TYPE_BANK_TRANSFER], true);
        });

        if ($forChannel->isEmpty()) {
            return null;
        }

        $banks = Bank::query()->where('active', true)->orderBy('name')->get();

        return [
            'id' => 'bank',
            'payment_methods' => $this->mapMethods($forChannel),
            'destinations' => $banks->map(fn (Bank $b) => [
                'id' => (int) $b->getKey(),
                'label' => (string) $b->name,
                'subtitle' => $b->iban ? (string) $b->iban : (string) ($b->account_number ?? ''),
            ])->values()->all(),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PaymentMethod>  $methods
     * @return array<string, mixed>|null
     */
    private function cashBoxChannel($methods): ?array
    {
        $forChannel = $methods->filter(fn (PaymentMethod $m) => (string) $m->type === PaymentMethod::TYPE_CASH);
        if ($forChannel->isEmpty()) {
            return null;
        }

        $boxes = CashBox::query()->where('active', true)->orderBy('name')->get();

        return [
            'id' => 'cash_box',
            'payment_methods' => $this->mapMethods($forChannel),
            'destinations' => $boxes->map(fn (CashBox $c) => [
                'id' => (int) $c->getKey(),
                'label' => (string) $c->name,
                'subtitle' => (string) ($c->location ?? ''),
            ])->values()->all(),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PaymentMethod>  $methods
     * @return array<string, mixed>|null
     */
    private function chequeChannel($methods): ?array
    {
        $forChannel = $methods->filter(fn (PaymentMethod $m) => (string) $m->type === PaymentMethod::TYPE_CHEQUE);
        if ($forChannel->isEmpty()) {
            return null;
        }

        $q = Cheque::query()
            ->whereNull('payment_id')
            ->whereIn('status', [Cheque::STATUS_PENDING, Cheque::STATUS_ISSUED])
            ->with('bank')
            ->orderByDesc('due_date');

        $cheques = $q->limit(500)->get();

        return [
            'id' => 'cheque',
            'payment_methods' => $this->mapMethods($forChannel),
            'destinations' => $cheques->map(function (Cheque $ch) {
                $bankName = $ch->bank?->name;

                return [
                    'id' => (int) $ch->getKey(),
                    'label' => (string) ($ch->cheque_number ?: '#'.$ch->getKey()),
                    'subtitle' => trim(implode(' · ', array_filter([
                        $bankName,
                        $ch->due_date ? $ch->due_date->format('Y-m-d') : null,
                    ]))),
                ];
            })->values()->all(),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PaymentMethod>  $methods
     * @return array<string, mixed>|null
     */
    private function posChannel($methods): ?array
    {
        $forChannel = $methods->filter(fn (PaymentMethod $m) => (string) $m->type === PaymentMethod::TYPE_POS);
        if ($forChannel->isEmpty()) {
            return null;
        }

        $rows = POSTerminal::query()->active()->orderBy('name')->get();

        return [
            'id' => 'pos',
            'payment_methods' => $this->mapMethods($forChannel),
            'destinations' => $rows->map(fn (POSTerminal $p) => [
                'id' => (int) $p->getKey(),
                'label' => (string) $p->name,
                'subtitle' => (string) ($p->location ?? ''),
            ])->values()->all(),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PaymentMethod>  $methods
     * @return array<string, mixed>|null
     */
    private function walletChannel($methods, string $context): ?array
    {
        $forChannel = $methods->filter(fn (PaymentMethod $m) => (string) $m->type === PaymentMethod::TYPE_WALLET);
        if ($forChannel->isEmpty()) {
            return null;
        }

        $q = Wallet::query()->where('active', true)->orderBy('id');
        if (Schema::hasColumn('wallets', 'wallet_type')) {
            // کانال پرداخت/دریافت در این ویجت باید فقط منابع خزانه را نشان دهد.
            $q->where('wallet_type', Wallet::TYPE_TREASURY);
        }

        $rows = $q->limit(200)->get();

        return [
            'id' => 'wallet',
            'payment_methods' => $this->mapMethods($forChannel),
            'destinations' => $rows->map(fn (Wallet $w) => [
                'id' => (int) $w->getKey(),
                'label' => '#'.(string) $w->getKey(),
                'subtitle' => (string) ($w->currency_code ?? ''),
            ])->values()->all(),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PaymentMethod>  $collection
     * @return list<array<string, mixed>>
     */
    private function mapMethods($collection): array
    {
        return $collection->sortBy('sort_order')->values()->map(static fn (PaymentMethod $m) => [
            'id' => (int) $m->getKey(),
            'name' => (string) $m->name,
            'type' => (string) $m->type,
            'code' => (string) $m->code,
        ])->all();
    }

    private function walletAllowedForContext(int $walletId, string $context): bool
    {
        $query = Wallet::query()->whereKey($walletId)->where('active', true);
        if (! Schema::hasColumn('wallets', 'wallet_type')) {
            return $query->exists();
        }

        return $query->where('wallet_type', Wallet::TYPE_TREASURY)->exists();
    }
}
