<?php

declare(strict_types=1);

namespace RMS\Accounting\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RMS\Accounting\Models\AccountingDocument;
use RMS\Accounting\Models\Bank;
use RMS\Accounting\Models\CashBox;
use RMS\Accounting\Models\VatRemittance;
use RMS\Accounting\Models\Wallet;
use RMS\Accounting\Support\AccountingVatAccounts;
use RMS\Accounting\Support\AuditActor;

class VatRemittanceService
{
    public function __construct(
        protected DocumentService $documentService,
        protected LedgerService $ledgerService,
    ) {}

    /**
     * @param array<string,mixed> $data
     */
    public function createAndPost(array $data): VatRemittance
    {
        $amount = round((float) ($data['amount'] ?? 0), 4);
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['مبلغ پرداخت VAT باید بزرگ‌تر از صفر باشد.'],
            ]);
        }

        $vatPayableAccountId = AccountingVatAccounts::resolvePayableAccountId();
        if (! $vatPayableAccountId) {
            throw ValidationException::withMessages([
                'vat' => [(string) trans('accounting::accounting.invoice.errors.vat_payable_account_missing')],
            ]);
        }

        [$treasuryAccountId, $sourceDescription] = $this->resolveTreasuryAccount((array) $data);
        $paymentDate = (string) ($data['payment_date'] ?? now()->toDateString());
        $currencyCode = strtoupper((string) ($data['currency_code'] ?? 'IRR'));
        $description = trim((string) ($data['notes'] ?? ''));
        if ($description === '') {
            $description = 'پرداخت مالیات بر ارزش افزوده';
        }

        return DB::transaction(function () use (
            $data,
            $amount,
            $vatPayableAccountId,
            $treasuryAccountId,
            $sourceDescription,
            $paymentDate,
            $currencyCode,
            $description
        ): VatRemittance {
            $document = $this->documentService->createDocument([
                'document_type' => AccountingDocument::TYPE_TAX,
                'store_id' => (int) ($data['store_id'] ?? 0),
                'fiscal_year_id' => config('accounting.current_fiscal_year_id'),
                'reference_type' => AccountingDocument::REF_EVENT,
                'reference_id' => null,
                'description' => $description,
                'total_debit' => $amount,
                'total_credit' => $amount,
            ]);

            $this->ledgerService->recordEntry([
                'event_type' => 'tax',
                'event_source' => 'system',
                'source_reference_type' => VatRemittance::class,
                'source_reference_id' => null,
                'store_id' => (int) ($data['store_id'] ?? 0),
                'account_id' => (int) $vatPayableAccountId,
                'currency_code' => $currencyCode,
                'debit_amount' => $amount,
                'credit_amount' => 0,
                'accounting_document_id' => $document->id,
                'description' => $description.' (کاهش پرداختنی VAT)',
            ]);

            $this->ledgerService->recordEntry([
                'event_type' => 'payment',
                'event_source' => 'manual',
                'source_reference_type' => VatRemittance::class,
                'source_reference_id' => null,
                'store_id' => (int) ($data['store_id'] ?? 0),
                'account_id' => (int) $treasuryAccountId,
                'currency_code' => $currencyCode,
                'debit_amount' => 0,
                'credit_amount' => $amount,
                'accounting_document_id' => $document->id,
                'description' => $description.' ('.$sourceDescription.')',
            ]);

            $this->documentService->postDocument((int) $document->id);

            $payload = [
                'period_start' => $data['period_start'] ?? null,
                'period_end' => $data['period_end'] ?? null,
                'payment_date' => $paymentDate,
                'amount' => $amount,
                'bank_id' => isset($data['bank_id']) ? (int) $data['bank_id'] : null,
                'cash_box_id' => isset($data['cash_box_id']) ? (int) $data['cash_box_id'] : null,
                'wallet_id' => isset($data['wallet_id']) ? (int) $data['wallet_id'] : null,
                'accounting_document_id' => (int) $document->id,
                'status' => VatRemittance::STATUS_POSTED,
                'notes' => $description,
            ];
            $payload = AuditActor::stamp($payload, 'vat_remittances', ['created', 'updated']);

            return VatRemittance::query()->create($payload);
        });
    }

    /**
     * @param array<string,mixed> $data
     * @return array{0:int,1:string}
     */
    protected function resolveTreasuryAccount(array $data): array
    {
        $bankId = (int) ($data['bank_id'] ?? 0);
        if ($bankId > 0) {
            $bank = Bank::query()->find($bankId);
            if (! $bank || (int) ($bank->account_id ?? 0) <= 0) {
                throw ValidationException::withMessages([
                    'bank_id' => ['حساب کل بانک انتخاب‌شده تنظیم نشده است.'],
                ]);
            }

            return [(int) $bank->account_id, 'بانک'];
        }

        $cashBoxId = (int) ($data['cash_box_id'] ?? 0);
        if ($cashBoxId > 0) {
            $cash = CashBox::query()->find($cashBoxId);
            if (! $cash || (int) ($cash->account_id ?? 0) <= 0) {
                throw ValidationException::withMessages([
                    'cash_box_id' => ['حساب کل صندوق انتخاب‌شده تنظیم نشده است.'],
                ]);
            }

            return [(int) $cash->account_id, 'صندوق'];
        }

        $walletId = (int) ($data['wallet_id'] ?? 0);
        if ($walletId > 0) {
            $wallet = Wallet::query()->find($walletId);
            if (! $wallet || (int) ($wallet->account_id ?? 0) <= 0) {
                throw ValidationException::withMessages([
                    'wallet_id' => ['حساب کل کیف‌پول انتخاب‌شده تنظیم نشده است.'],
                ]);
            }

            return [(int) $wallet->account_id, 'کیف‌پول'];
        }

        throw ValidationException::withMessages([
            'treasury' => ['یکی از مراجع بانکی/صندوق/کیف‌پول را برای پرداخت VAT انتخاب کنید.'],
        ]);
    }
}
