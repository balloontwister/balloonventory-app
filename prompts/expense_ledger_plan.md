# Expense Ledger — Implementation Plan

**Status:** Planning (not started)
**Created:** 2026-06-29
**Owner:** Todd
**Context:** Balloonventory needs one internal ledger to record **business expenses** (money out) —
**contractor pay, hosting, email (Resend), online advertising, live marketing, software
subscriptions, fees, and more**. Todd manages it from the **Super-Admin side**, captures each
expense **from his phone right after spending**, and **attaches receipts** so his accountant has
everything. Contractor payments are just **one category** of this ledger (the category that also
captures a tax ID for 1099s).

> Separate subsystem from the Stripe/membership billing (`prompts/membership_payments_plan.md`).
> This is an **internal expense record**, platform-level (NOT tenant-scoped, like `email_logs` /
> `support_tickets`), Super-Admin only.
>
> **Renamed/restructured 2026-06-29** from the original "Contractor Payments Ledger" — contractor
> payments are now one category in a general Expense Ledger.

---

## Scope (decisions)

- **Record, don't send.** No Wise/PayPal/Stripe payout API. Todd pays via the vendor's/PayPal's/
  card's own app; this ledger only *records* what was spent.
- **Capture → categorize → total → export for the accountant.** Receipts attached.
- **Super-Admin managed.** Sensitive (money + PII + financial docs), Super-Admin gated.
- **Contractor payments = one category**, capturing an encrypted **tax ID** (accountant files the
  1099s; we just store the inputs + yearly totals).
- **Receipt attachments are in scope (Phase 1)** — Todd's accountant wants them; snapping a receipt
  photo is part of the fast mobile capture.
- **Low-friction, mobile-first capture** is the core design goal.

## Out of scope

- Sending money / payout APIs.
- 1099 generation/filing (accountant).
- **Accounting software behavior** — see the boundary below.
- Anything Stripe/Cashier/subscription-related (separate plan).

## The boundary (important — don't let this become QuickBooks)

This is a **capture-and-export tool, NOT accounting software.** It records what was spent, by
category/vendor/date, with receipts, and produces totals + a year-end export the accountant pulls
from. It must **not** drift into double-entry bookkeeping, P&L statements, bank reconciliation, or
tax categorization — the accountant's real software does that. The app-specific value here is
convenient **mobile capture + receipts + contractor 1099 inputs**; general expenses ride the same
rails.

---

## Intake methods (all feed one `draft → confirmed` pipeline via a `source` column)

1. **Fast manual entry (PRIMARY — Phase 1, zero infra).** Mobile-optimized Super-Admin "Log expense"
   form: category → vendor (pick or free-text) → amount → method → date → **optional receipt photo/
   file** → Save. Seconds, on the phone, right after spending. The most reliable path.
2. **Email-forward parsing (LATER — optional).** Forward a vendor receipt / PayPal confirmation to a
   dedicated address → inbound webhook → parser → **draft** entry to confirm. Caveats: vendor email
   templates drift + forwarded mail loses DKIM → draft-then-confirm only; needs an inbound-email
   provider (Postmark / Mailgun / Cloudflare) + MX (the app's Resend is outbound-only).
3. **CSV / statement import (LATER — optional, bulk).** Import a card/bank/PayPal export →
   column-map → **draft** entries → confirm in batch. Good for catch-up / reconciliation; PayPal/Wise
   only export CSV as a desktop batch (no per-transaction CSV), so this is NOT the per-expense path.

Schema is built so all three are just `source` values landing as `draft`. Build #1 now; #2/#3 add
later with no schema change.

---

## Data model (platform-level; UUID PKs, soft deletes, app conventions)

### `vendors` (who/what gets paid)
- `id` (uuid, pk)
- `type` (enum: `contractor` / `vendor`) — contractors are people (1099); vendors are companies
  (Resend, the host, ad platforms)
- `user_id` (uuid, nullable, fk → users.id) — link when the payee is also a Balloonventory user
  (admins); null for external contractors and company vendors
- `name` (string)
- `email` (string, nullable) — also used to match forwarded/imported entries
- `tax_id` (text, **encrypted** cast, nullable) — SSN/EIN; only for `contractor` type; masked in UI
- `default_method` (enum: `paypal`/`wise`/`card`/`bank`/`other`, nullable)
- `payment_handle` (string, nullable) — PayPal email / Wise ref / account hint
- `typical_amount_cents` (integer, nullable) — pre-fills the log form
- `is_active` (boolean, default true)
- `notes` (text, nullable)
- `created_at`, `updated_at`, `deleted_at`

### `expenses` (the ledger)
- `id` (uuid, pk)
- `vendor_id` (uuid, nullable, fk → vendors.id, idx) — link a vendor, OR…
- `vendor_name` (string, nullable) — free-text for quick one-off entries without creating a vendor
  (reduces mobile friction; snapshot)
- `category` (enum: `contractor`/`hosting`/`software_subscription`/`advertising`/`marketing`/`fees`/
  `other`) — extensible
- `amount_cents` (integer); `currency` (string(3), default `USD`)
- `spent_at` (date) — when the money went out
- `period_start` / `period_end` (date, nullable) — work period (contractor) or service period
  (subscription)
- `hours` (decimal, nullable) — contractor only
- `method` (enum: `paypal`/`wise`/`card`/`bank`/`other`)
- `external_reference` (string, nullable) — transaction ID / invoice number
- `status` (enum: `draft`/`confirmed`) — imports/forwards land `draft`; manual may save `confirmed`
- `source` (enum: `manual`/`email`/`csv`)
- `notes` (text, nullable)
- `recorded_by_user_id` (uuid, fk → users.id)
- `confirmed_by_user_id` (uuid, nullable, fk → users.id); `confirmed_at` (timestamp, nullable)
- `created_at`, `updated_at`, `deleted_at`

### `expense_attachments` (receipts — 1-to-many)
- `id` (uuid, pk)
- `expense_id` (uuid, fk → expenses.id, cascade, idx)
- `file_path` (string) — stored on a **PRIVATE** disk (receipts are sensitive financial docs — NOT
  the public disk used for SKU images/logos)
- `original_filename` (string); `mime` (string); `size_bytes` (integer)
- `uploaded_by_user_id` (uuid, fk → users.id)
- `created_at`
- Supports **images + PDF**. Download via a gated Super-Admin route (no public URL). Reuse the
  existing upload-handling patterns (`ImageAttachmentService`) but target a private disk + allow PDF.

---

## Super-Admin UI

- **Vendors**: list + CRUD; `type`; capture/edit `tax_id` for contractors (masked `•••-••-1234`,
  reveal gated); typical amount + default method.
- **Log expense** (mobile-first, PRIMARY): category → vendor (pick or free-text) → amount → method →
  date → **snap/attach receipt** → Save. One-tap confirm.
- **Expense ledger**: filter by category / vendor / status / date; **totals by category, by vendor,
  and by year**; receipt-attached indicator; draft/confirmed management.
- **Year-end export for the accountant**: per-category + per-vendor totals, contractor tax_ids, and
  access to the attached receipts (e.g. downloadable). This is the deliverable that "makes the
  accountant happy."

## Permissions & security

- Super-Admin only (new gate, e.g. `admin.manage_expenses`). Not all admins.
- `tax_id` → Laravel `encrypted` cast; masked by default; reveal is a deliberate gated action.
- **Receipts on a private disk**, downloaded only through a gated Super-Admin route — never a public
  URL.
- Audit `recorded_by` / `confirmed_by` on every entry.

## Testing & conventions

- Feature-test: log-expense (incl. receipt upload to private disk), draft↔confirmed transitions,
  encrypted `tax_id` round-trip + masking, per-category/per-vendor/per-year totals, gated receipt
  download.
- Mirror existing admin-table patterns (Users / feedback / login-log). UUID PKs, soft deletes, Pint
  clean, en/es i18n for user-facing strings.
- Deploy via `bin/deploy.sh`; the deploy script skips seeders.

---

## Reporting, summaries & exports (DEFERRED — but design data for it now)

Once the ledger has years of data, we'll want period summaries and flexible exports. **This is a
later phase, not Phase 1** — but it requires NO schema change, because every row already carries
`spent_at` + `category` + `vendor` + `amount_cents`. All of these are just date-range queries with
grouping:

- **Period presets:** this month, this quarter, **year-to-date**, last year, a **specific year**,
  **all-time**, plus an arbitrary custom range.
- **Group/summarize by:** category, vendor, month/quarter/year.
- **Exports:** CSV (primary, for the accountant); optionally a printable PDF summary.

**Why deferred:** building reports pre-launch with no data is premature. The Phase-1 job is to
capture **clean, structured data** (dates, categories, cents) so these reports are trivial to add
later. They are.

**Income parity:** these same period views + exports should apply to the **income** side (Stripe
subscription revenue). Income's system of record is Stripe — see `prompts/membership_payments_plan.md`
(Phase 2 Payments/Ledger). Recommended: back the in-app income view with a **local table populated
from Stripe webhooks** (e.g. `invoice.paid`) so income is locally queryable with the *same* period
logic as expenses, enabling a future unified "Financials" view (income + expense side-by-side
totals). **Boundary holds:** summaries + exports for the accountant, NOT authoritative P&L /
net-profit statements.

## Build order

1. **Phase 1 (now):** `vendors` + `expenses` + `expense_attachments` schema; Super-Admin vendor CRUD
   (encrypted tax_id); **mobile-first Log-expense form with receipt upload (private disk)**; ledger
   list with category/vendor/year totals; accountant export. Contractor payments = `category =
   contractor` with tax_id/hours/period. Zero external infra.
2. **Phase 2 (later, optional):** email-forward → inbound webhook → parser → `draft`. Needs an
   inbound-email provider + MX subdomain.
3. **Phase 3 (later, optional):** CSV / card-statement import → `draft` (bulk).
4. **Future ideas:** reporting/period-summary + export layer (see Reporting section — month/quarter/
   YTD/last-year/specific-year/all-time/custom, by category & vendor, CSV/PDF); income-side parity
   via a Stripe-webhook-fed local income table; a unified "Financials" view; recurring-expense
   templates (hosting/Resend monthly prefill); receipt OCR.

## Resolved decisions (Todd, 2026-06-29)

- Generalize from "contractor payments" to a full **Expense Ledger**; contractor pay is one
  category.
- **Receipt attachments are IN (Phase 1)** — accountant wants them; private-disk storage.
- **Start with Phase 1 only** (manual fast entry + receipts). Email-forward + CSV deferred; schema
  built so they drop in later with no changes.
- **`vendors.user_id` is a nullable link** (payee/vendor record stays primary; link to the app user
  when the contractor is one).

## Todd's open items

1. Confirm the **category set** (current: contractor / hosting / software_subscription / advertising
   / marketing / fees / other) — add any you know you'll need.
2. Confirm the **method set** (paypal / wise / card / bank / other).
3. OK to store receipts on a **private disk** with gated Super-Admin download (recommended)?
