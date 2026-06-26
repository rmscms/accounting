## Audit Actor Deployment Checklist

### 1) Pre-Deploy
- Confirm branch includes the new audit actor core (`AuditActorResolver`, `AuditActorContext`, `AuditColumnWriter`, `AuditActor`).
- Confirm phase-1 migration file exists: `database/migrations/2026_06_03_190000_add_admin_actor_columns_phase1.php`.
- Confirm direct `auth()->id()`/`auth('admin')->id()` usage is removed from `src/Services`.
- Run targeted tests:
  - `php artisan test tests/Feature/Accounting/AuditActorResolverTest.php tests/Feature/Accounting/AuditColumnWriterTest.php`

### 2) Rollout (Additive / Safe)
- Put application in maintenance mode if required by your policy.
- Run migrations normally (no destructive commands):
  - `php artisan migrate`
- Publish accounting package resources:
  - `php artisan vendor:publish --tag=accounting-views --force`
  - `php artisan vendor:publish --tag=accounting-lang --force`
  - `php artisan vendor:publish --tag=accounting-assets --force`
  - `php artisan optimize:clear`

### 3) Post-Deploy Validation
- Admin flow checks:
  - Create and process a treasury transfer from admin panel.
  - Verify `bank_transfers.created_by_admin_id`/`processed_by_admin_id` populated.
  - Verify corresponding `*_by_user_id` columns remain `null` for admin actor.
- API/user flow checks:
  - Trigger one payment/refund flow from API/user-authenticated path.
  - Verify `*_by_user_id` is populated and `*_by_admin_id` is `null`.
- Integrity checks:
  - Confirm no FK error occurs when admin is authenticated and `users.id` does not match that admin.
  - Confirm scenario-runner transfer create/process still works end-to-end.

### 4) Monitoring Window
- Monitor logs for:
  - `SQLSTATE[23000]` FK failures on audit columns.
  - Null actor values where actor should exist (misconfigured guard/session).
- Compare a sample of newly created rows in:
  - `bank_transfers`, `bank_transactions`, `accounting_documents`, `customer_payments`, `supplier_payments`, `customer_refunds`, `supplier_refunds`, `customer_advances`, `supplier_advances`, `manual_journals`, `inventory_adjustments`.

### 5) Rollback Strategy
- Code rollback can be done independently from schema because migration is additive.
- If rollback is required:
  - Revert application code to prior tag/commit.
  - Keep new columns in place (recommended) to avoid data loss.
  - Only run `migrate:rollback` for this migration in controlled environments after data review.
