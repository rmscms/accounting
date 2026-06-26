<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use RMS\Accounting\Http\Controllers\Admin\Concerns\RendersAccountingStructuredResourceForm;

use RMS\Accounting\Models\BankTransaction;
use RMS\Accounting\Services\BankTransactionService;
use Illuminate\Http\Request;
use Illuminate\Filesystem\Filesystem;
use RMS\Core\Data\Field;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;

class BankTransactionsController extends AccountingAdminController implements
    HasList,
    HasForm,
    ShouldFilter
{
    use RendersAccountingStructuredResourceForm;

    protected BankTransactionService $transactionService;

    public function __construct(Filesystem $filesystem, BankTransactionService $transactionService)
    {
        parent::__construct($filesystem);
        $this->transactionService = $transactionService;
    }

    public function table(): string
    {
        return 'bank_transactions';
    }

    public function modelName(): string
    {
        return BankTransaction::class;
    }

    public function baseRoute(): string
    {
        return 'accounting.bank-transactions';
    }

    public function routeParameter(): string
    {
        return 'bank_transaction';
    }

    public function getFieldsForm(): array
    {
        return [
            Field::select('bank_id', 'بانک')
                ->setOptions($this->getBankOptions())
                ->required(),

            Field::select('transaction_type', 'نوع تراکنش')
                ->setOptions([
                    'charge' => 'کارمزد',
                    'interest_income' => 'سود دریافتی',
                    'interest_expense' => 'بهره پرداختی',
                    'fee' => 'هزینه',
                    'other' => 'سایر',
                ])
                ->required(),

            Field::date('transaction_date', 'تاریخ')
                ->withDefaultValue(now())
                ->required(),

            Field::number('amount', 'مبلغ')
                ->required(),

            Field::select('charge_type_account_id', 'حساب')
                ->setOptions($this->getAccountOptions())
                ->required(),

            Field::string('reference_number', 'شماره مرجع')
                ->optional(),

            Field::textarea('description', 'توضیحات', 2)
                ->optional(),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle('شناسه')->sortable()->width('80px'),
            Field::make('transaction_number')->withTitle('شماره')->searchable()->sortable()->width('150px'),
            Field::make('bank.name')->withTitle('بانک')->searchable()->width('150px'),
            Field::make('transaction_type')->withTitle('نوع')->customMethod('renderType')->width('120px'),
            Field::number('amount')->withTitle('مبلغ')->sortable()->width('120px'),
            Field::make('status')->withTitle('وضعیت')->customMethod('renderStatus')->sortable()->width('100px'),
            Field::date('transaction_date')->withTitle('تاریخ')->sortable()->width('120px'),
        ];
    }

    public function rules(): array
    {
        return [
            'bank_id' => ['required', 'exists:banks,id'],
            'transaction_type' => ['required', 'in:charge,interest_income,interest_expense,fee,other'],
            'transaction_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0'],
            'charge_type_account_id' => ['required', 'exists:accounts,id'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function post(Request $request, $id)
    {
        try {
            $this->transactionService->postTransaction($id);
            return redirect()->back()->with('success', 'تراکنش ثبت شد');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function renderStatus($row): string
    {
        return $row->status === 'posted'
            ? '<span class="badge badge-success">ثبت شده</span>'
            : '<span class="badge badge-warning">پیش‌نویس</span>';
    }

    public function renderType($row): string
    {
        $types = [
            'charge' => 'کارمزد',
            'interest_income' => 'سود',
            'interest_expense' => 'بهره',
            'fee' => 'هزینه',
            'other' => 'سایر',
        ];

        return $types[$row->transaction_type] ?? $row->transaction_type;
    }

    protected function getBankOptions(): array
    {
        return \RMS\Accounting\Models\Bank::where('active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected function getAccountOptions(): array
    {
        return \RMS\Accounting\Models\Account::where('active', true)
            ->where('level', '>=', 2)
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn($account) => [$account->id => "{$account->code} - {$account->name}"])
            ->toArray();
    }
}
