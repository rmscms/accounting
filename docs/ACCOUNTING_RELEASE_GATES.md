# چک‌لیست Release Gates اکانتینگ

این سند برای فاز نهایی برنامه تکمیل اکانتینگ است و قبل از اعلام release باید کامل شود.

## Gate 1 — سناریوهای حیاتی (P0/P1)

- اجرای سناریوها با `Scenario Runner` نباید خطای بلوکه‌کننده داشته باشد.
- `post-check`های پایه برای همه سناریوهای اجرایی برقرار باشد:
  - وجود سند
  - تراز بدهکار/بستانکار
  - وجود ردیف دفترکل

## Gate 2 — گزارش‌های اولویت‌دار

- این گزارش‌ها نباید `stub` باشند:
  - `purchase-orders-history`
  - `income-tax-report`
  - `taxable-transactions`
  - `discrepancies`

## Gate 3 — امنیت و API

- مسیرهای Tax API باید حداقل `throttle` و `idempotency` داشته باشند.
- فعال‌سازی `auth.api` و `api.scope` برای Tax API باید با تنظیمات محیط production قابل enforce باشد.
- مسیرهای write در Service API باید idempotency-aware باشند.

## Gate 4 — سلامت عملیاتی

- فرمان `accounting:health` باید خروجی معتبر JSON تولید کند.
- در وضعیت نرمال، `ok=true` و `unbalanced_documents=0` و `orphan_ledger_rows=0` باشد.

## Gate 5 — تست‌های اجباری قبل از release

- `php artisan test --filter=ScenarioRunnerFaLabelsTest`
- `php artisan test --filter=ReportServiceConversionTest`
- `php artisan test --filter=ApiIdempotencyMiddlewareTest`
- `php artisan test --filter=SecurityApiHardeningTest`
- `php artisan accounting:health --json`

## Definition of Done

- تمام Gateهای بالا پاس باشند.
- KPIهای ماتریس تکمیل (`ACCOUNTING_COMPLETION_MATRIX.md`) از baseline جلوتر رفته باشند.
- خروجی‌ها publish و cacheها پاکسازی شده باشند:
  - `php artisan vendor:publish --tag=accounting-views --force`
  - `php artisan vendor:publish --tag=accounting-lang --force`
  - `php artisan vendor:publish --tag=accounting-assets --force`
  - `php artisan optimize:clear`
