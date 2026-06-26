# Admin create/edit form UX profiles (accounting package)

Resources that still rely on RMS field metadata use `RendersAccountingStructuredResourceForm`, which maps fields to a structured Blade layout. The layout varies by **profile** (`catalog`, `entity`, `document`, `treasury`) derived from the resource slug. Dedicated controllers (for example bank transfers, banks, expenses) use their own Blade templates and copy should live in `lang/*/accounting.php` per feature.

Help callouts use the package anonymous component: `<x-accounting::page-description>` (registered via `anonymousComponentPath` with the `accounting` prefix).

## When to use each profile

- **catalog** — Short settings-style records (fiscal years, currencies, payment methods, POS terminals, cash boxes, fixed asset categories). Prefer compact spacing, minimal chrome, and labels that read like configuration rather than narrative workflow.
- **entity** — Counterparties and durable master data (customers, suppliers, fixed assets). Emphasise identity, contact, and classification fields; avoid treating the form like a financial document unless the field set is explicitly monetary.
- **document** — Invoices, orders, journals, accruals, notes, payments, advances, refunds, reconciliations, and similar. Treat the form as a document header: dates, parties, currency, and amounts should scan as a coherent block; status or workflow hints belong next to fields that drive state.
- **treasury** — Bank-side flows (bank transactions, cheques, transfers when not using a fully custom view). Stress from/to accounts, value dates, amounts, and risk or reconciliation context.

## Shared technical rules

- Amount-like fields use package money parsing (`ParsesAccountingMoneyInput` / structured form `prepareForValidation`); do not depend on `App\` services in package controllers.
- Dates go through `AccountingDateInputNormalizer` where applicable; enable Jalali picker assets only when the site uses Jalali.
- Long option lists use enhanced select; keep help text in translations (`*_form`, `*_help`) per resource, not one generic paragraph for all screens.

## Avoiding “copy-paste” layouts

Profiles only tune spacing and section grouping. Product-specific flows (line items, approvals, attachments) still belong in dedicated controllers and views when the default field export is insufficient.
