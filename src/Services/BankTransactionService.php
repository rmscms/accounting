<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\BankTransaction;
use RMS\Accounting\Models\Bank;
use Illuminate\Support\Facades\DB;
use RMS\Accounting\Support\InteractsWithAuditActor;

class BankTransactionService
{
    use InteractsWithAuditActor;

    protected LedgerService $ledgerService;

    public function __construct(LedgerService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
    }

    /**
     * ثبت کارمزد بانکی
     * Debit: Bank Charges Expense
     * Credit: Bank Account
     */
    public function recordBankCharge(array $data): BankTransaction
    {
        return $this->recordTransaction($data, 'charge');
    }

    /**
     * ثبت سود بانکی
     * Debit: Bank Account
     * Credit: Interest Income
     */
    public function recordInterestIncome(array $data): BankTransaction
    {
        return $this->recordTransaction($data, 'interest_income');
    }

    /**
     * ثبت بهره بانکی (برای وام)
     * Debit: Interest Expense
     * Credit: Bank Account
     */
    public function recordInterestExpense(array $data): BankTransaction
    {
        return $this->recordTransaction($data, 'interest_expense');
    }

    /**
     * ثبت تراکنش بانکی عمومی
     */
    protected function recordTransaction(array $data, string $type): BankTransaction
    {
        return DB::transaction(function () use ($data, $type) {
            $createPayload = [
                'transaction_number' => $data['transaction_number'] ?? BankTransaction::generateTransactionNumber(),
                'bank_id' => $data['bank_id'],
                'transaction_type' => $type,
                'transaction_date' => $data['transaction_date'],
                'amount' => $data['amount'],
                'currency_code' => $data['currency_code'] ?? 'IRR',
                'fx_rate' => $data['fx_rate'] ?? 1,
                'charge_type_account_id' => $data['charge_type_account_id'],
                'reference_number' => $data['reference_number'] ?? null,
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'draft',
            ];
            $createPayload = $this->stampAudit($createPayload, 'bank_transactions', 'created');

            $transaction = BankTransaction::create($createPayload);

            // ثبت خودکار اگر درخواست شده باشد
            if ($data['auto_post'] ?? false) {
                $this->postTransaction($transaction->id);
            }

            return $transaction->fresh();
        });
    }

    /**
     * ثبت تراکنش در دفاتر
     */
    public function postTransaction(int $transactionId): BankTransaction
    {
        return DB::transaction(function () use ($transactionId) {
            $transaction = BankTransaction::findOrFail($transactionId);

            if ($transaction->status === 'posted') {
                throw new \Exception('Transaction already posted');
            }

            $bank = $transaction->bank;
            $entries = [];

            switch ($transaction->transaction_type) {
                case 'charge':
                case 'fee':
                    // کارمزد: Debit: Expense, Credit: Bank
                    $entries = [
                        [
                            'account_id' => $transaction->charge_type_account_id,
                            'debit' => $transaction->amount,
                            'credit' => 0,
                            'description' => $transaction->description ?? "کارمزد بانک {$bank->name}",
                        ],
                        [
                            'account_id' => $bank->account_id,
                            'debit' => 0,
                            'credit' => $transaction->amount,
                            'description' => "برداشت کارمزد از {$bank->name}",
                        ],
                    ];
                    // کاهش موجودی بانک
                    $bank->decrement('balance', $transaction->amount);
                    break;

                case 'interest_income':
                    // سود: Debit: Bank, Credit: Interest Income
                    $entries = [
                        [
                            'account_id' => $bank->account_id,
                            'debit' => $transaction->amount,
                            'credit' => 0,
                            'description' => "دریافت سود بانک {$bank->name}",
                        ],
                        [
                            'account_id' => $transaction->charge_type_account_id,
                            'debit' => 0,
                            'credit' => $transaction->amount,
                            'description' => $transaction->description ?? "درآمد سود بانکی",
                        ],
                    ];
                    // افزایش موجودی بانک
                    $bank->increment('balance', $transaction->amount);
                    break;

                case 'interest_expense':
                    // بهره وام: Debit: Interest Expense, Credit: Bank
                    $entries = [
                        [
                            'account_id' => $transaction->charge_type_account_id,
                            'debit' => $transaction->amount,
                            'credit' => 0,
                            'description' => $transaction->description ?? "هزینه بهره وام",
                        ],
                        [
                            'account_id' => $bank->account_id,
                            'debit' => 0,
                            'credit' => $transaction->amount,
                            'description' => "پرداخت بهره از {$bank->name}",
                        ],
                    ];
                    // کاهش موجودی بانک
                    $bank->decrement('balance', $transaction->amount);
                    break;

                default:
                    throw new \Exception('Unknown transaction type: ' . $transaction->transaction_type);
            }

            $document = $this->ledgerService->recordTransaction([
                'document_type' => 'bank_transaction',
                'reference_type' => 'bank_transaction',
                'reference_id' => $transactionId,
                'description' => "تراکنش بانکی ({$transaction->transaction_type}) - {$bank->name} - شماره: {$transaction->transaction_number}",
            ], $entries);

            $postPayload = [
                'status' => 'posted',
                'accounting_document_id' => $document->id,
                'posted_at' => now(),
            ];
            $postPayload = $this->stampAudit($postPayload, 'bank_transactions', 'posted');

            $transaction->update($postPayload);

            return $transaction->fresh();
        });
    }

    /**
     * واردسازی صورتحساب بانک (Placeholder - نیاز به توسعه بیشتر)
     */
    public function importBankStatement(int $bankId, $file): array
    {
        // این متد نیاز به پارسر CSV/Excel دارد
        // می‌توان با استفاده از Laravel Excel یا سایر کتابخانه‌ها پیاده‌سازی کرد

        throw new \Exception('Bank statement import not yet implemented');

        // نمونه ساختار:
        // 1. خواندن فایل
        // 2. پارس کردن هر خط
        // 3. ایجاد BankTransaction برای هر تراکنش
        // 4. تطبیق خودکار با تراکنش‌های موجود
        // 5. بازگشت لیست تراکنش‌های وارد شده
    }
}
