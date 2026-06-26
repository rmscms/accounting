<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\Party;
use RMS\Accounting\Models\Customer;
use RMS\Accounting\Models\Supplier;
use RMS\Accounting\Models\Account;
use RMS\Accounting\Models\Currency;
use RMS\Core\Models\Setting;
use Illuminate\Validation\ValidationException;

/**
 * سرویس مدیریت طرف‌های تجاری (Parties)
 * - ایجاد و مدیریت parties
 * - لینک کردن parties به customers و suppliers
 * - ایجاد خودکار حساب‌های فرعی
 */
class PartyService
{
    protected AccountService $accountService;

    public function __construct(AccountService $accountService)
    {
        $this->accountService = $accountService;
    }

    /**
     * ایجاد party جدید
     */
    public function createParty(array $data): Party
    {
        return Party::create($data);
    }

    /**
     * پیدا کردن party بر اساس کد ملی
     */
    public function findPartyByNationalCode(string $code): ?Party
    {
        return Party::where('national_code', $code)->first();
    }

    /**
     * پیدا کردن party بر اساس تلفن
     */
    public function findPartyByPhone(string $phone): ?Party
    {
        return Party::where('phone', $phone)->first();
    }

    /**
     * پیدا کردن یا ایجاد party
     */
    public function findOrCreateParty(array $data): Party
    {
        // اول سعی می‌کنیم با کد ملی پیدا کنیم
        if (!empty($data['national_code'])) {
            $party = $this->findPartyByNationalCode($data['national_code']);
            if ($party) {
                return $party;
            }
        }

        // سپس با تلفن
        if (!empty($data['phone'])) {
            $party = $this->findPartyByPhone($data['phone']);
            if ($party) {
                return $party;
            }
        }

        // اگر پیدا نشد، ایجاد می‌کنیم
        return $this->createParty($data);
    }

    /**
     * لینک party به customer
     */
    public function linkAsCustomer(int $partyId, array $customerData): Customer
    {
        $party = Party::findOrFail($partyId);

        // بررسی اینکه آیا قبلاً customer بوده یا نه
        $existingCustomer = Customer::where('party_id', $partyId)->first();
        if ($existingCustomer) {
            return $existingCustomer;
        }

        // ایجاد customer
        $customerData['party_id'] = $partyId;
        
        // ایجاد حساب فرعی دریافتنی اگر وجود نداشت
        if (empty($customerData['account_id'])) {
            $account = $this->getOrCreateCustomerAccount($partyId);
            $customerData['account_id'] = $account->id;
        }

        return Customer::create($customerData);
    }

    /**
     * ایجاد/لینک مشتری با سازوکار Party (مسیر مشترک برای فرم و API های داخلی).
     *
     * @param  array<string, mixed>  $customerData
     */
    public function createOrLinkCustomer(array $customerData): Customer
    {
        $partyId = isset($customerData['party_id']) ? (int) $customerData['party_id'] : 0;
        if ($partyId > 0) {
            $party = Party::query()->findOrFail($partyId);
            return $this->linkAsCustomer((int) $party->id, $customerData);
        }

        $partyData = [
            'name' => (string) ($customerData['name'] ?? ''),
            'phone' => $customerData['phone'] ?? null,
            'national_code' => $customerData['national_code'] ?? null,
            'email' => $customerData['email'] ?? null,
            'address' => $customerData['address'] ?? null,
            'type' => 'individual',
        ];

        $party = $this->findOrCreateParty($partyData);

        return $this->linkAsCustomer((int) $party->id, $customerData);
    }

    /**
     * مشتری پیش‌فرض فروش (مشتری عمومی/نقد) را تضمین می‌کند و شناسه‌اش را در تنظیمات نگه می‌دارد.
     */
    public function ensureDefaultSalesCustomer(): Customer
    {
        $settingKey = 'accounting.sales.default_customer_id';
        $configuredId = (int) Setting::get($settingKey, 0);
        if ($configuredId > 0) {
            $configured = Customer::query()->find($configuredId);
            if ($configured) {
                return $configured;
            }
        }

        $fallbackNames = ['مشتری عمومی', 'نقد', 'مشتری نقدی', 'General Customer', 'Cash Customer'];
        $existing = Customer::query()
            ->whereIn('name', $fallbackNames)
            ->orderBy('id')
            ->first();

        if (! $existing) {
            $defaultCurrency = Currency::resolveBaseCurrencyCode('IRR');

            $existing = $this->createOrLinkCustomer([
                'name' => 'مشتری عمومی',
                'type' => 'regular',
                'national_code' => null,
                'phone' => null,
                'email' => null,
                'address' => null,
                'credit_limit' => 0,
                'default_currency_code' => strlen($defaultCurrency) === 3 ? $defaultCurrency : 'IRR',
                'active' => true,
            ]);
        }

        Setting::set($settingKey, (string) $existing->getKey());

        return $existing;
    }

    /**
     * لینک party به supplier
     */
    public function linkAsSupplier(int $partyId, array $supplierData): Supplier
    {
        $party = Party::findOrFail($partyId);

        // بررسی اینکه آیا قبلاً supplier بوده یا نه
        $existingSupplier = Supplier::where('party_id', $partyId)->first();
        if ($existingSupplier) {
            return $existingSupplier;
        }

        // ایجاد supplier
        $supplierData['party_id'] = $partyId;
        
        // ایجاد حساب فرعی پرداختنی اگر وجود نداشت
        if (empty($supplierData['account_id'])) {
            $account = $this->getOrCreateSupplierAccount($partyId);
            $supplierData['account_id'] = $account->id;
        }

        return Supplier::create($supplierData);
    }

    /**
     * پیشنهاد کد یکتا برای تأمین‌کنندهٔ جدید (الگوی SUP-000001).
     */
    public function suggestNextSupplierCode(): string
    {
        $next = ((int) Supplier::query()->max('id')) + 1;
        $code = 'SUP-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        $guard = 0;
        while (Supplier::query()->where('code', $code)->exists() && $guard < 100) {
            $next++;
            $code = 'SUP-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
            $guard++;
        }

        return $code;
    }

    /**
     * اگر مشتری هنوز party_id ندارد (دادهٔ قدیمی)، Party ساخته/پیدا می‌شود و به مشتری وصل می‌شود.
     */
    public function ensurePartyForCustomer(Customer $customer): Party
    {
        $customer->refresh();

        if ($customer->party_id) {
            return Party::query()->findOrFail((int) $customer->party_id);
        }

        $party = $this->findOrCreateParty([
            'name' => $customer->name,
            'phone' => $customer->phone,
            'national_code' => $customer->national_code,
            'email' => $customer->email,
            'type' => 'individual',
        ]);

        $customer->party_id = $party->id;
        $customer->save();

        if (! $customer->account_id) {
            $account = $this->getOrCreateCustomerAccount($party->id);
            $customer->account_id = $account->id;
            $customer->save();
        }

        return $party;
    }

    /**
     * اگر تامین‌کننده هنوز party_id ندارد (دادهٔ قدیمی)، Party ساخته/پیدا می‌شود و به تامین‌کننده وصل می‌شود.
     */
    public function ensurePartyForSupplier(Supplier $supplier): Party
    {
        $supplier->refresh();

        if ($supplier->party_id) {
            return Party::query()->findOrFail((int) $supplier->party_id);
        }

        $party = $this->findOrCreateParty([
            'name' => $supplier->name,
            'phone' => $supplier->phone,
            'email' => $supplier->email,
            'address' => $supplier->address,
            'tax_number' => $supplier->tax_number,
            'contact_person' => $supplier->contact_person,
            'type' => 'company',
        ]);

        $supplier->party_id = $party->id;
        $supplier->save();

        if (! $supplier->account_id) {
            $account = $this->getOrCreateSupplierAccount($party->id);
            $supplier->account_id = $account->id;
            $supplier->save();
        }

        return $party;
    }

    /**
     * ستون code در suppliers اغلب NOT NULL است؛ قبل از insert باید مقدار داشته باشد.
     *
     * @param  array<string, mixed>  $validated
     */
    public function ensureSupplierCodeForStore(array &$validated): void
    {
        $code = isset($validated['code']) ? trim((string) $validated['code']) : '';
        if ($code !== '') {
            return;
        }
        $validated['code'] = $this->suggestNextSupplierCode();
    }

    /**
     * ایجاد تأمین‌کنندهٔ مینیمال برای همان Partyِ مشتری (اگر قبلاً نبوده باشد).
     *
     * @throws ValidationException
     */
    public function ensureSupplierForCustomer(Customer $customer): Supplier
    {
        $party = $this->ensurePartyForCustomer($customer);
        $customer->refresh();

        if (Supplier::query()->where('party_id', $party->id)->exists()) {
            throw ValidationException::withMessages([
                'linked_customer_id' => trans('accounting::accounting.supplier.linked_party_already_supplier'),
            ]);
        }

        $email = $customer->email !== null && $customer->email !== ''
            ? substr((string) $customer->email, 0, 100)
            : null;

        $currency = $customer->getAttribute('default_currency_code');
        $currencyCode = is_string($currency) && strlen($currency) === 3
            ? strtoupper($currency)
            : Currency::resolveBaseCurrencyCode('IRR');

        $validated = [
            'name' => (string) $customer->name,
            'phone' => $customer->phone,
            'email' => $email,
            'address' => $customer->getAttribute('address'),
            'active' => (bool) ($customer->active ?? true),
            'code' => '',
            'currency_code' => $currencyCode,
        ];

        $this->ensureSupplierCodeForStore($validated);

        $supplier = $this->linkAsSupplier($party->id, $validated);

        if (trim((string) ($supplier->code ?? '')) === '') {
            $code = 'SUP-'.str_pad((string) $supplier->getKey(), 6, '0', STR_PAD_LEFT);
            if (Supplier::query()->where('code', $code)->where('id', '!=', $supplier->getKey())->exists()) {
                $code = $this->suggestNextSupplierCode();
            }
            $supplier->code = $code;
            $supplier->save();
        }

        return $supplier;
    }

    /**
     * دریافت یا ایجاد حساب فرعی customer (حساب دریافتنی)
     */
    public function getOrCreateCustomerAccount(int $partyId): Account
    {
        $party = Party::findOrFail($partyId);
        
        // اگر customer موجود است و حساب دارد
        $customer = Customer::where('party_id', $partyId)->first();
        if ($customer && $customer->account_id) {
            return $customer->account;
        }

        // پیدا کردن حساب کنترل "حساب‌های دریافتنی"
        $controlAccount = $this->getControlAccount('accounts_receivable');
        $accountCode = $this->generatePartySubAccountCode($controlAccount->code, $partyId);
        $existingAccount = Account::query()
            ->where('code', $accountCode)
            ->where('parent_id', $controlAccount->id)
            ->first();
        if ($existingAccount) {
            return $existingAccount;
        }
        
        $account = $this->accountService->createAccount([
            'code' => $accountCode,
            'name' => "حساب‌های دریافتنی - {$party->name}",
            'account_type' => 'asset', // حساب‌های دریافتنی از نوع دارایی هستند
            'parent_id' => $controlAccount->id,
            'level' => 3, // تفصیلی
            'active' => true,
        ]);

        return $account;
    }

    /**
     * دریافت یا ایجاد حساب فرعی supplier (حساب پرداختنی)
     */
    public function getOrCreateSupplierAccount(int $partyId): Account
    {
        $party = Party::findOrFail($partyId);
        
        // اگر supplier موجود است و حساب دارد
        $supplier = Supplier::where('party_id', $partyId)->first();
        if ($supplier && $supplier->account_id) {
            return $supplier->account;
        }

        // پیدا کردن حساب کنترل "حساب‌های پرداختنی"
        $controlAccount = $this->getControlAccount('accounts_payable');
        $accountCode = $this->generatePartySubAccountCode($controlAccount->code, $partyId);
        $existingAccount = Account::query()
            ->where('code', $accountCode)
            ->where('parent_id', $controlAccount->id)
            ->first();
        if ($existingAccount) {
            return $existingAccount;
        }
        
        $account = $this->accountService->createAccount([
            'code' => $accountCode,
            'name' => "حساب‌های پرداختنی - {$party->name}",
            'account_type' => 'liability', // حساب‌های پرداختنی از نوع بدهی هستند
            'parent_id' => $controlAccount->id,
            'level' => 3, // تفصیلی
            'active' => true,
        ]);

        return $account;
    }

    /**
     * دریافت یا ایجاد حساب فرعی درآمد customer
     */
    public function getOrCreateCustomerRevenueAccount(int $partyId): Account
    {
        $party = Party::findOrFail($partyId);

        // پیدا کردن حساب کنترل "درآمد فروش"
        $controlAccount = $this->getControlAccount('sales_revenue');
        
        // بررسی اینکه آیا حساب فرعی قبلاً ایجاد شده یا نه
        $accountName = "درآمد فروش - {$party->name}";
        $existingAccount = Account::where('name', $accountName)
            ->where('parent_id', $controlAccount->id)
            ->first();

        if ($existingAccount) {
            return $existingAccount;
        }

        // ایجاد حساب فرعی
        $accountCode = $this->generateSubAccountCode($controlAccount->code);
        
        $account = $this->accountService->createAccount([
            'code' => $accountCode,
            'name' => $accountName,
            'account_type' => 'income',
            'parent_id' => $controlAccount->id,
            'level' => 3, // تفصیلی
            'active' => true,
        ]);

        return $account;
    }

    /**
     * دریافت یا ایجاد حساب فرعی هزینه supplier
     */
    public function getOrCreateSupplierCostAccount(int $partyId): Account
    {
        $party = Party::findOrFail($partyId);

        // پیدا کردن حساب کنترل "موجودی کالا" یا "هزینه خرید"
        $controlAccount = $this->getControlAccount('inventory');
        
        // بررسی اینکه آیا حساب فرعی قبلاً ایجاد شده یا نه
        $accountName = "هزینه خرید - {$party->name}";
        $existingAccount = Account::where('name', $accountName)
            ->where('parent_id', $controlAccount->id)
            ->first();

        if ($existingAccount) {
            return $existingAccount;
        }

        // ایجاد حساب فرعی
        $accountCode = $this->generateSubAccountCode($controlAccount->code);
        
        $account = $this->accountService->createAccount([
            'code' => $accountCode,
            'name' => $accountName,
            'account_type' => 'asset', // موجودی کالا از نوع دارایی است
            'parent_id' => $controlAccount->id,
            'level' => 3, // تفصیلی
            'active' => true,
        ]);

        return $account;
    }

    /**
     * دریافت party با نقش‌هایش
     */
    public function getPartyWithRoles(int $partyId): Party
    {
        return Party::with(['customer', 'supplier'])->findOrFail($partyId);
    }

    /**
     * محاسبه مانده کلی party
     */
    public function getPartyBalance(int $partyId): array
    {
        $party = $this->getPartyWithRoles($partyId);
        
        $customerBalance = 0;
        $supplierBalance = 0;

        if ($party->customer && $party->customer->account_id) {
            $account = \RMS\Accounting\Models\Account::find($party->customer->account_id);
            if ($account) {
                $balanceData = $this->accountService->getAccountBalance(
                    accountId: $party->customer->account_id
                );
                $customerBalance = $balanceData['balance'] ?? $account->getBalance();
            }
        }

        if ($party->supplier && $party->supplier->account_id) {
            $account = \RMS\Accounting\Models\Account::find($party->supplier->account_id);
            if ($account) {
                $balanceData = $this->accountService->getAccountBalance(
                    accountId: $party->supplier->account_id
                );
                $supplierBalance = $balanceData['balance'] ?? $account->getBalance();
            }
        }

        return [
            'party_id' => $partyId,
            'customer_balance' => $customerBalance,
            'supplier_balance' => $supplierBalance,
            'net_balance' => $customerBalance - $supplierBalance,
        ];
    }

    /**
     * پیدا کردن حساب کنترل بر اساس نام
     */
    protected function getControlAccount(string $accountType): Account
    {
        // دریافت کد حساب از تنظیمات
        $settingPaths = [
            'accounts_receivable' => 'accounting.system_accounts.assets.accounts_receivable',
            'accounts_payable' => 'accounting.system_accounts.liabilities.accounts_payable',
            'sales_revenue' => 'accounting.system_accounts.income.sales_revenue',
            'inventory' => 'accounting.system_accounts.assets.inventory',
        ];
        
        $settingPath = $settingPaths[$accountType] ?? "accounting.system_accounts.{$accountType}";
        $accountCode = Setting::get($settingPath);
        
        if ($accountCode) {
            $account = Account::where('code', $accountCode)->first();
            if ($account) {
                return $account;
            }
        }

        // Fallback: جستجو بر اساس کد استاندارد
        $standardCodes = [
            'accounts_receivable' => '1201',
            'accounts_payable' => '2101',
            'sales_revenue' => '4101',
            'inventory' => '5101',
        ];
        
        if (isset($standardCodes[$accountType])) {
            $account = Account::where('code', $standardCodes[$accountType])->first();
            if ($account) {
                return $account;
            }
        }

        // Fallback: جستجو بر اساس نام
        $accountNames = [
            'accounts_receivable' => ['حساب‌های دریافتنی', 'Accounts Receivable'],
            'accounts_payable' => ['حساب‌های پرداختنی', 'Accounts Payable'],
            'sales_revenue' => ['درآمد فروش', 'Sales Revenue'],
            'inventory' => ['موجودی کالا', 'Inventory'],
        ];

        if (isset($accountNames[$accountType])) {
            foreach ($accountNames[$accountType] as $name) {
                $account = Account::where('name', 'like', "%{$name}%")
                    ->where('account_type', $this->getAccountTypeForControl($accountType))
                    ->first();
                if ($account) {
                    return $account;
                }
            }
        }

        throw new \Exception("حساب کنترل {$accountType} یافت نشد. لطفاً در تنظیمات حسابداری تنظیم کنید.");
    }

    /**
     * دریافت نوع حساب برای حساب کنترل
     */
    protected function getAccountTypeForControl(string $accountType): string
    {
        $types = [
            'accounts_receivable' => 'asset',
            'accounts_payable' => 'liability',
            'sales_revenue' => 'income',
            'inventory' => 'asset',
        ];
        
        return $types[$accountType] ?? 'asset';
    }

    /**
     * تولید کد حساب فرعی
     *
     * نکته: مرتب‌سازی رشته‌ای روی کد (مثلاً 1103-1000 در برابر 1103-999) در DB اشتباه است
     * و باعث تکرار کد می‌شود؛ بیشینهٔ عددی پسوند را مستقیم محاسبه می‌کنیم.
     */
    protected function generateSubAccountCode(string $parentCode): string
    {
        $prefix = $parentCode.'-';
        $maxNumber = 0;

        $codes = Account::where('code', 'like', "{$parentCode}-%")->pluck('code');

        foreach ($codes as $code) {
            if (! is_string($code) || ! str_starts_with($code, $prefix)) {
                continue;
            }
            $suffix = substr($code, strrpos($code, '-') + 1);
            if ($suffix !== '' && ctype_digit($suffix)) {
                $maxNumber = max($maxNumber, (int) $suffix);
            }
        }

        $nextNumber = $maxNumber + 1;
        $width = max(3, strlen((string) $nextNumber));

        return $parentCode.'-'.str_pad((string) $nextNumber, $width, '0', STR_PAD_LEFT);
    }

    /**
     * تولید کد تفصیلی ثابت بر اساس party_id.
     * مثال: 1103-001 و 2101-001 برای یک party مشترک.
     */
    protected function generatePartySubAccountCode(string $parentCode, int $partyId): string
    {
        $suffix = str_pad((string) $partyId, 3, '0', STR_PAD_LEFT);
        return $parentCode.'-'.$suffix;
    }
}
