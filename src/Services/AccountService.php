<?php

namespace RMS\Accounting\Services;

use RMS\Accounting\Models\Account;
use Illuminate\Support\Facades\DB;

/**
 * سرویس مدیریت حساب‌ها
 * - ایجاد حساب
 * - مدیریت درخت حساب‌ها
 * - محاسبه مانده حساب‌ها
 */
class AccountService
{
    protected LedgerService $ledgerService;

    public function __construct(LedgerService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
    }

    /**
     * ایجاد حساب جدید
     */
    public function createAccount(array $data): Account
    {
        // اعتبارسنجی سطح بندی
        if (!empty($data['parent_id'])) {
            $parent = Account::findOrFail($data['parent_id']);
            $data['level'] = $parent->level + 1;
        } else {
            $data['level'] = 1;
        }

        // تولید کد خودکار در صورت عدم وجود
        if (empty($data['code'])) {
            $data['code'] = $this->generateAccountCode($data['parent_id'], $data['level']);
        }

        return Account::create($data);
    }

    /**
     * تولید کد حساب خودکار
     */
    protected function generateAccountCode(?int $parentId, int $level): string
    {
        if (!$parentId) {
            // حساب سطح 1
            $lastAccount = Account::whereNull('parent_id')
                ->orderBy('code', 'desc')
                ->first();

            $nextCode = $lastAccount ? intval($lastAccount->code) + 1 : 1000;
            return (string) $nextCode;
        }

        // حساب‌های زیرمجموعه
        $parent = Account::findOrFail($parentId);
        $lastChild = Account::where('parent_id', $parentId)
            ->orderBy('code', 'desc')
            ->first();

        if (!$lastChild) {
            return $parent->code . '01';
        }

        $lastChildSuffix = intval(substr($lastChild->code, -2));
        return $parent->code . str_pad($lastChildSuffix + 1, 2, '0', STR_PAD_LEFT);
    }

    /**
     * دریافت درخت حساب‌ها
     */
    public function getAccountTree(?int $parentId = null): array
    {
        $accounts = Account::where('parent_id', $parentId)
            ->orderBy('code')
            ->get();

        $tree = [];
        foreach ($accounts as $account) {
            $tree[] = [
                'account' => $account,
                'children' => $this->getAccountTree($account->id),
            ];
        }

        return $tree;
    }

    /**
     * محاسبه مانده حساب
     */
    public function getAccountBalance(
        int $accountId,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?int $storeId = null
    ): array {
        return $this->ledgerService->getBalance(
            accountId: $accountId,
            fromDate: $fromDate,
            toDate: $toDate,
            storeId: $storeId
        );
    }

    /**
     * دریافت گردش حساب
     */
    public function getAccountStatement(
        int $accountId,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?int $storeId = null
    ) {
        $account = Account::findOrFail($accountId);
        
        $query = $account->ledgers()
            ->with(['account', 'document'])
            ->orderBy('created_at');

        if ($fromDate) {
            $query->whereDate('created_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        return $query->paginate(100);
    }

    /**
     * دریافت حساب‌های بر اساس نوع
     */
    public function getAccountsByType(string $type)
    {
        return Account::where('account_type', $type)
            ->where('active', true)
            ->orderBy('code')
            ->get();
    }

    /**
     * فعال/غیرفعال کردن حساب
     */
    public function toggleAccount(int $accountId, bool $active): bool
    {
        $account = Account::findOrFail($accountId);

        if ($account->is_system) {
            throw new \Exception('حساب‌های سیستمی قابل غیرفعال‌سازی نیستند');
        }

        return $account->update(['active' => $active]);
    }
}
