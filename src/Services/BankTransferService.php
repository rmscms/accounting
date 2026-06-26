<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\BankTransfer;
use RMS\Accounting\Models\Bank;
use RMS\Accounting\Models\CashBox;
use RMS\Accounting\Models\Wallet;
use Illuminate\Support\Facades\DB;
use RMS\Accounting\Support\InteractsWithAuditActor;

class BankTransferService
{
    use InteractsWithAuditActor;

    protected LedgerService $ledgerService;

    public function __construct(LedgerService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
    }

    /**
     * ثبت انتقال بین بانکی
     */
    public function createTransfer(array $data): BankTransfer
    {
        return DB::transaction(function () use ($data) {
            $fromType = (string) ($data['from_treasury_type'] ?? BankTransfer::TREASURY_TYPE_BANK);
            $fromId = (int) ($data['from_treasury_id'] ?? 0);
            $toType = (string) ($data['to_treasury_type'] ?? BankTransfer::TREASURY_TYPE_BANK);
            $toId = (int) ($data['to_treasury_id'] ?? 0);

            $transferPayload = [
                'transfer_number' => $data['transfer_number'] ?? BankTransfer::generateTransferNumber(),
                'from_bank_id' => $fromType === BankTransfer::TREASURY_TYPE_BANK ? $fromId : null,
                'to_bank_id' => $toType === BankTransfer::TREASURY_TYPE_BANK ? $toId : null,
                'from_treasury_type' => $fromType,
                'from_treasury_id' => $fromId,
                'to_treasury_type' => $toType,
                'to_treasury_id' => $toId,
                'amount' => $data['amount'],
                'currency_code' => $data['currency_code'] ?? 'IRR',
                'fx_rate' => $data['fx_rate'] ?? 1,
                'transfer_date' => $data['transfer_date'],
                'value_date' => $data['value_date'] ?? $data['transfer_date'],
                'transfer_fee' => $data['transfer_fee'] ?? 0,
                'transfer_fee_account_id' => $data['transfer_fee_account_id'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'pending',
            ];
            $transferPayload = $this->stampAudit($transferPayload, 'bank_transfers', 'created');

            $transfer = BankTransfer::create($transferPayload);

            // پردازش خودکار اگر درخواست شده باشد
            if ($data['auto_process'] ?? false) {
                $this->processTransfer($transfer->id);
            }

            return $transfer->fresh();
        });
    }

    /**
     * پردازش انتقال و ثبت سند
     */
    public function processTransfer(int $transferId): BankTransfer
    {
        return DB::transaction(function () use ($transferId) {
            $transfer = BankTransfer::findOrFail($transferId);

            if ($transfer->status !== 'pending') {
                throw new \Exception('Transfer already processed or cancelled');
            }

            $from = $this->resolveTreasuryEndpoint($transfer, true);
            $to = $this->resolveTreasuryEndpoint($transfer, false);

            // بررسی موجودی
            if (((float) $from['balance']) < ((float) $transfer->amount + (float) $transfer->transfer_fee)) {
                throw new \Exception('Insufficient balance in source treasury account');
            }

            // ثبت سند انتقال خزانه‌داری
            $entries = [
                [
                    'account_id' => (int) $to['account_id'],
                    'debit' => $transfer->amount,
                    'credit' => 0,
                    'currency_code' => $transfer->currency_code,
                    'fx_rate' => $transfer->fx_rate,
                    'description' => "دریافت انتقال از {$from['label']}",
                ],
                [
                    'account_id' => (int) $from['account_id'],
                    'debit' => 0,
                    'credit' => $transfer->amount,
                    'currency_code' => $transfer->currency_code,
                    'fx_rate' => $transfer->fx_rate,
                    'description' => "انتقال به {$to['label']}",
                ],
            ];

            // اگر کارمزد وجود دارد
            if ($transfer->transfer_fee > 0 && $transfer->transfer_fee_account_id) {
                $entries[] = [
                    'account_id' => $transfer->transfer_fee_account_id,
                    'debit' => $transfer->transfer_fee,
                    'credit' => 0,
                    'description' => "کارمزد انتقال بین بانکی",
                ];
                $entries[] = [
                    'account_id' => (int) $from['account_id'],
                    'debit' => 0,
                    'credit' => $transfer->transfer_fee,
                    'description' => "کارمزد انتقال",
                ];
            }

            $document = $this->ledgerService->recordTransaction([
                'document_type' => 'bank_transfer',
                'reference_type' => 'bank_transfer',
                'reference_id' => $transferId,
                'description' => "انتقال خزانه از {$from['label']} به {$to['label']} - شماره: {$transfer->transfer_number}",
            ], $entries);

            // به‌روزرسانی موجودی مبدا و مقصد
            $this->adjustTreasuryBalance($from['model'], -((float) $transfer->amount + (float) $transfer->transfer_fee));
            $this->adjustTreasuryBalance($to['model'], (float) $transfer->amount);

            // به‌روزرسانی transfer
            $updatePayload = [
                'status' => 'completed',
                'accounting_document_id' => $document->id,
                'processed_at' => now(),
            ];
            $updatePayload = $this->stampAudit($updatePayload, 'bank_transfers', 'processed');

            $transfer->update($updatePayload);

            return $transfer->fresh();
        });
    }

    /**
     * تکمیل انتقال (بدون ثبت مجدد - فقط تغییر وضعیت)
     */
    public function completeTransfer(int $transferId): BankTransfer
    {
        $transfer = BankTransfer::findOrFail($transferId);

        if ($transfer->status === 'completed') {
            throw new \Exception('Transfer already completed');
        }

        if ($transfer->status === 'pending') {
            return $this->processTransfer($transferId);
        }

        throw new \Exception('Cannot complete transfer with status: ' . $transfer->status);
    }

    /**
     * لغو انتقال + برگشت سند
     */
    public function cancelTransfer(int $transferId, string $reason = null): BankTransfer
    {
        return DB::transaction(function () use ($transferId, $reason) {
            $transfer = BankTransfer::findOrFail($transferId);

            if ($transfer->status === 'cancelled') {
                throw new \Exception('Transfer already cancelled');
            }

            // اگر قبلاً ثبت شده، سند را برگردان
            if ($transfer->status === 'completed' && $transfer->accounting_document_id) {
                $from = $this->resolveTreasuryEndpoint($transfer, true);
                $to = $this->resolveTreasuryEndpoint($transfer, false);

                // برگشت موجودی
                $this->adjustTreasuryBalance($from['model'], (float) $transfer->amount + (float) $transfer->transfer_fee);
                $this->adjustTreasuryBalance($to['model'], -((float) $transfer->amount));

                // ثبت سند برگشتی
                $entries = [
                    [
                        'account_id' => (int) $from['account_id'],
                        'debit' => $transfer->amount,
                        'credit' => 0,
                        'description' => "برگشت انتقال از {$to['label']}",
                    ],
                    [
                        'account_id' => (int) $to['account_id'],
                        'debit' => 0,
                        'credit' => $transfer->amount,
                        'description' => "برگشت انتقال به {$from['label']}",
                    ],
                ];

                if ($transfer->transfer_fee > 0) {
                    $entries[] = [
                        'account_id' => (int) $from['account_id'],
                        'debit' => $transfer->transfer_fee,
                        'credit' => 0,
                        'description' => "برگشت کارمزد انتقال",
                    ];
                    $entries[] = [
                        'account_id' => $transfer->transfer_fee_account_id,
                        'debit' => 0,
                        'credit' => $transfer->transfer_fee,
                        'description' => "برگشت کارمزد انتقال بین بانکی",
                    ];
                }

                $this->ledgerService->recordTransaction([
                    'document_type' => 'bank_transfer_reversal',
                    'reference_type' => 'bank_transfer',
                    'reference_id' => $transferId,
                    'description' => "برگشت انتقال خزانه - شماره: {$transfer->transfer_number}" . ($reason ? " - دلیل: {$reason}" : ''),
                ], $entries);
            }

            $transfer->update([
                'status' => 'cancelled',
                'notes' => ($transfer->notes ?? '') . "\nلغو شد: " . ($reason ?? 'بدون دلیل'),
            ]);

            return $transfer->fresh();
        });
    }

    /**
     * @return array{type:string,id:int,model:object,label:string,balance:float,account_id:int}
     */
    protected function resolveTreasuryEndpoint(BankTransfer $transfer, bool $source): array
    {
        $type = $source
            ? (string) ($transfer->from_treasury_type ?: ($transfer->from_bank_id ? BankTransfer::TREASURY_TYPE_BANK : ''))
            : (string) ($transfer->to_treasury_type ?: ($transfer->to_bank_id ? BankTransfer::TREASURY_TYPE_BANK : ''));

        $id = $source
            ? (int) ($transfer->from_treasury_id ?: $transfer->from_bank_id)
            : (int) ($transfer->to_treasury_id ?: $transfer->to_bank_id);

        if ($type === '' || $id <= 0) {
            throw new \Exception('Treasury endpoint is not configured.');
        }

        if ($type === BankTransfer::TREASURY_TYPE_BANK) {
            $model = Bank::query()->findOrFail($id);
            $label = (string) $model->name;
        } elseif ($type === BankTransfer::TREASURY_TYPE_CASHBOX) {
            $model = CashBox::query()->findOrFail($id);
            $label = (string) $model->name;
        } elseif ($type === BankTransfer::TREASURY_TYPE_WALLET) {
            $model = Wallet::query()->findOrFail($id);
            $label = 'Wallet #' . $model->id;
        } else {
            throw new \Exception('Unsupported treasury endpoint type: ' . $type);
        }

        $accountId = (int) ($model->account_id ?? 0);
        if ($accountId <= 0) {
            throw new \Exception('Treasury endpoint account is not configured.');
        }

        return [
            'type' => $type,
            'id' => $id,
            'model' => $model,
            'label' => $label,
            'balance' => (float) ($model->balance ?? 0),
            'account_id' => $accountId,
        ];
    }

    protected function adjustTreasuryBalance(object $model, float $delta): void
    {
        if (! method_exists($model, 'increment') || ! method_exists($model, 'decrement')) {
            throw new \Exception('Treasury model does not support balance updates.');
        }

        if ($delta > 0) {
            $model->increment('balance', $delta);
            return;
        }

        if ($delta < 0) {
            $model->decrement('balance', abs($delta));
        }
    }

}
