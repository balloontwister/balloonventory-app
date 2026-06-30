# Legal & Compliance Pages — Launch Readiness Plan

**Status:** Planning (not started)
**Created:** 2026-06-28
**Owner:** Todd
**Context:** App is entering real-world Alpha at `app.balloonventory.com`. Membership tiers,
affiliate program, and payments/ledger are NOT built yet. This plan covers the "legalese"
layer needed to open the doors to real people, plus what to stub now vs. build when money
starts changing hands.

> ⚠️ **This is a developer/scaffolding plan, not legal advice.** It tells you what pages to
> build, where they live, how to wire consent, and what the engineering work is. The actual
> *wording* of Terms / Privacy / Cookies must be reviewed (ideally drafted) by a lawyer or a
> reputable generator before you rely on it. Build the plumbing now; drop in lawyer-reviewed
> prose before public launch.

---

## TL;DR — the decisions

1. **Where do they live?** Canonical copies live **in the Laravel app** (`app.balloonventory.com`),
   as **public** Inertia pages. The marketing landing page (`/index.html` → balloonventory.com)
   just links across to them. One source of truth, bilingual (en/es), version-controlled,
   testable, and a stable URL for Stripe/app-store/processor review later.
2. **Public, not login-gated.** People must read Terms + Privacy *before* they sign up, and
   payment processors / crawlers need them reachable without auth.
3. **Content as Markdown, not hardcoded Vue.** `resources/legal/{en,es}/*.md` rendered with
   Laravel's built-in `Str::markdown()` (league/commonmark is already vendored — **no new
   dependency**). Todd/lawyer edits plain Markdown; git is the version history.
4. **Cookie banner: don't over-build.** The app currently sets **only strictly-necessary
   cookies** (session, CSRF/XSRF, locale) and **no analytics/tracking exists** (verified — no
   GA, FB pixel, Hotjar, etc.). Under GDPR/ePrivacy a consent *gate* is **not legally required
   for essential-only cookies**. So: ship a **Cookie Policy page + a one-time dismissible
   notice** now, and only build a real consent manager *if/when* analytics or marketing cookies
   are added.
5. **Consent at signup:** add a Terms/Privacy acceptance checkbox to Register, and record
   `terms_accepted_at` + `terms_version` on the user.
6. **Data export / deletion:** account deletion already exists (`DeleteUserForm` →
   `profile.destroy`). ⚠️ **Confirmed live gap (verified 2026-06-30):** `ProfileController::destroy`
   is a bare `$user->delete()` with **no owner-handoff step** — a sole owner deleting their account
   silently orphans the business, its inventory, and every other member's access, with nothing
   flagging it. The existing last-owner guard only covers *leaving* a business
   (`MembershipPolicy`/`MembershipController`), not account deletion. **Decision (Todd,
   2026-06-30): handle this via business-freeze + an explicit successor choice, NOT a hard block
   and NOT auto-promotion** — see the "Owner handoff on account deletion" spec in Phase 5. Fix
   before beta. Self-serve "Download my data" is **Tier B**; for Alpha a documented email-request
   path in the Privacy Policy is legally sufficient (respond within 30 days).

---

## Current-state findings (what exists today)

- **Marketing landing** = `/index.html` at repo root (untracked, served at balloonventory.com).
  Footer is just `© 2026 Balloonventory. All rights reserved.` — **no legal links**.
- **App landing** = `resources/js/Pages/Welcome.vue` (route `/`). Footer is just `© Balloonventory`.
- **GuestLayout** (wraps Login/Register/Forgot) — **no footer, no legal links**.
- **Register.vue** — name/email/password only. **No terms acceptance.**
- **Account hub** (`Pages/Account/Index.vue`) — rows: Profile · Business · Preferences ·
  Help & Support · Super Admin · Log out. **No legal/about entry.**
- **Account deletion exists** — `Profile/Partials/DeleteUserForm.vue` → `profile.destroy`.
  ⚠️ Verified: `ProfileController::destroy` does a bare soft-delete with **no owner-handoff step**
  (see TL;DR #6 and the Phase 5 spec). User model uses `SoftDeletes`.
- **Business freeze already exists and is enforced** (verified) — `businesses.frozen_at` +
  `Business::isFrozen()`; `SetBusinessContext` drops frozen businesses from active context;
  `EnsureBusinessActive` redirects members out with a warning; `BusinessController` aborts 403;
  admin can freeze/unfreeze + filter active/frozen/deleted (`AdminBusinessController::suspend`).
  The owner-handoff flow reuses this — no new freeze machinery needed.
- **Impersonation / "View as Business" exists** — `Impersonation.php`, `AdminBusinessView.php`,
  `ImpersonationController`, `AdminBusinessController`, `MagicLoginLinkController`. Admin recovery
  of a frozen/ownerless business is possible today.
- **Ownership is *derived*, not a column** — `Business::owner()` = earliest member with
  `role='owner'`; there is **no `owner_id` column and no transfer-owner action**. "Assign a new
  owner" today means writing a membership role change; a first-class admin/owner transfer action
  is a gap to build (see Phase 5).
- **Invited users bypass Register entirely** — the multi-business invite flow auto-accepts via
  magic-link and never renders `Register.vue`, so a terms checkbox there alone would **miss every
  invited user** (likely a large share of beta). See Phase 3.
- **No analytics/tracking anywhere** (grep-verified). Only essential cookies + some `localStorage`
  prefs (localStorage is not a cookie and is covered by the Privacy Policy, not cookie law).
- **i18n is en + es** across the app → legal pages should be bilingual (es can ship as a
  fast-follow with English fallback).
- `league/commonmark` is present → Markdown rendering needs no new package.
- Existing pattern to copy: `ComingSoonController` uses `->defaults('area', ...)` controller
  routes that survive `route:cache`. Legal routes should follow the same shape.

---

## The document set (and priority tiers)

### Tier A — launch blockers (build before real users)
| Doc | Route | Notes |
|---|---|---|
| **Terms of Service** | `/terms` | The click-through agreement. Fold **Acceptable Use** in as a section unless you want it standalone. Include the **as-is / no-warranty**, limitation-of-liability, and **catalog-data disclaimer** (data may be wrong; user verifies). |
| **Privacy Policy** | `/privacy` | What you collect (account, contact info, inventory, login/IP history, emails), why, legal basis, **subprocessors** (Resend for email, the cPanel/LiteSpeed host, Stripe later), retention, and the **data-access/export/deletion request** path + contact. |
| **Cookie Policy** | `/cookies` | List the essential cookies (session, CSRF, locale) + localStorage prefs. State that no tracking/marketing cookies are used. |
| **Trademark / IP notice** | section in ToS or footer line | "Qualatex, Sempertex, Kalisan, Gemar, Decomex, TufTex, Betallic, etc. are trademarks of their respective owners. Balloonventory is not affiliated with or endorsed by them." |
| **Data-sources disclosure** | section in ToS/Privacy | Catalog is aggregated from manufacturer charts, distributor listings (BargainBalloons, LA Balloons, Larocks), and legacy data; provided "as is" for identification, not authoritative pricing/availability. |

### Tier B — before you charge money (stub now, fill when billing lands)
| Doc | Route | Notes |
|---|---|---|
| **Refund / Billing / Cancellation policy** | `/refunds` | Trial terms, billing cycle, proration, refund window, how to cancel. Ship as a "coming soon / contact us" stub now so the route exists. |
| **Affiliate Program terms** | `/affiliates-terms` (later) | When the affiliate program is built. Not needed for Alpha. |
| **(optional) DPA / Subprocessors page** | `/subprocessors` | If you ever sell to a business that asks for a Data Processing Addendum. Defer. |

### Explicitly NOT needed yet
- **Cookie consent *gate*** — not required for essential-only cookies (revisit when analytics added).
- **German Impressum** — only matters if you actively target Germany; skip for US Alpha.
- **Accessibility statement** — good citizen, not a blocker.

---

## Architecture / file plan

### Backend
- **`config/legal.php`** — single source of the current version + dates:
  ```php
  return [
      'terms_version' => '2026-07-01',      // bump when ToS/Privacy materially change
      'effective_date' => '2026-07-01',
      'company' => 'Twisted Balloon LLC',   // confirm legal entity name
      'contact_email' => 'privacy@balloonventory.com', // or support@
  ];
  ```
- **`app/Http/Controllers/LegalController.php`**
  - `index()` → renders `Legal/Index.vue` (a hub listing all docs). Optional but nice.
  - `show()` → reads `resources/legal/{locale}/{doc}.md`, falls back to `en` if the locale
    file is missing, renders with `Str::markdown(..., ['html_input' => 'strip'])` (strip raw
    HTML so the Markdown stays trusted/safe), and passes `{ doc, html, version, updatedAt }`
    to `Legal/Show.vue`. Validate `doc` against an allow-list constant (`['terms','privacy',
    'cookies','acceptable-use','refunds']`) → 404 otherwise.
- **Routes** (`routes/web.php`, public — outside the auth group, near the `/` route):
  ```php
  Route::get('/legal', [LegalController::class, 'index'])->name('legal.index');
  Route::get('/terms', [LegalController::class, 'show'])->defaults('doc', 'terms')->name('legal.terms');
  Route::get('/privacy', [LegalController::class, 'show'])->defaults('doc', 'privacy')->name('legal.privacy');
  Route::get('/cookies', [LegalController::class, 'show'])->defaults('doc', 'cookies')->name('legal.cookies');
  Route::get('/acceptable-use', [LegalController::class, 'show'])->defaults('doc', 'acceptable-use')->name('legal.acceptable-use');
  Route::get('/refunds', [LegalController::class, 'show'])->defaults('doc', 'refunds')->name('legal.refunds');
  ```
  (Controller-based with `->defaults()` so `route:cache`/optimize stays valid — same pattern as
  `ComingSoonController`.)

### Content
- **`resources/legal/en/terms.md`**, `privacy.md`, `cookies.md`, `acceptable-use.md`, `refunds.md`
- **`resources/legal/es/…`** (same filenames; can land as a fast-follow — controller falls back
  to `en` so a missing es file won't 500).
- **Controlling-language clause (required if es legal prose ships):** a binding contract in two
  languages invites "which version wins?" ambiguity, made worse by rough machine translation. Add
  a standard *"the English-language version of these terms controls in the event of any conflict"*
  line to both the ToS and Privacy prose. Cheap insurance.

### Frontend
- **`resources/js/Pages/Legal/Show.vue`** — simple prose page in `GuestLayout` (or a minimal
  standalone layout so it renders for both guests and logged-in users). Shows title +
  "Last updated {date}" + the rendered HTML.
  - **`v-html` note:** this is the one sanctioned `v-html` in the app. It's safe because the
    source is our own author-controlled Markdown rendered server-side with raw-HTML stripped —
    not user input. Add a code comment saying so (the codebase otherwise avoids `v-html`).
- **`resources/js/Pages/Legal/Index.vue`** — optional hub card-list of the docs.
- **`resources/js/Components/LegalFooter.vue`** — shared footer row of links (Terms · Privacy ·
  Cookies · © year). Drop into **GuestLayout.vue** (currently has none) and **Welcome.vue**
  (replace the bare copyright line).
- **`resources/js/Components/CookieNotice.vue`** — one-time dismissible banner, persisted to
  `localStorage['cookie-notice.dismissed']`. Plain notice + link to `/cookies`. **Not** a
  consent gate. Mount once in `GuestLayout` and `AuthenticatedLayout`.
  - **Keep dismissal client-side only — do NOT persist it server-side.** Storing dismissal on the
    user/server would imply you're *recording consent* you neither need (no tracking to gate) nor
    can honor. Re-showing per-browser after a cache clear is correct and expected for a notice.
    Guard against scope-creep turning this into a consent record.

### i18n
- **`lang/en/legal.php`** + **`lang/es/legal.php`** — page chrome only (titles, "Last updated",
  footer link labels, cookie-notice text, the registration consent sentence). The long prose
  lives in the Markdown files, **not** in lang files.

---

## Consent at registration (Phase 3)

> ⚠️ **Ordering guard:** Phase 3 records *acceptance of a specific `terms_version`*. Do **not**
> enable the checkbox/interstitial against placeholder prose, or you'll have users on record as
> having accepted lorem ipsum. Either (a) gate Phase 3 behind Phase 4 (real lawyer-reviewed prose
> live first), or (b) accept that the first real-prose version bump must force re-acceptance. The
> interstitial mechanism below makes (b) cheap, so either path is fine — just decide consciously.

- **Migration** `add_terms_acceptance_to_users`: `terms_accepted_at` (timestamp, nullable),
  `terms_version` (string, nullable).
- **Register.vue**: add a required checkbox above the submit button —
  *"I agree to the [Terms of Service] and [Privacy Policy]"* with links opening `/terms` and
  `/privacy` (target=_blank). Bind `form.terms = false`.
- **Backend** (`Auth/RegisteredUserController@store` or its form request): validate
  `terms => ['accepted']`; on success set `terms_accepted_at = now()` and
  `terms_version = config('legal.terms_version')`.
- **Acceptance interstitial (covers invited users AND re-acceptance) — build this, don't defer it.**
  The Register checkbox alone misses every magic-link/invited user (verified — they never render
  `Register.vue`). Add a lightweight middleware/interstitial that catches any authenticated user
  whose `terms_accepted_at IS NULL` **or** whose stored `terms_version` is older than
  `config('legal.terms_version')`, and shows a blocking "review & accept the updated terms" screen
  before they can proceed (allowlist logout/legal pages so they can still read & leave). This one
  mechanism solves three things at once: invited-user consent, the placeholder→real-prose
  re-acceptance bump, and all future material updates. Clickwrap-by-use ("by using the service you
  agree") is a weaker fallback in the ToS prose, not a substitute for the affirmative record.

---

## Data rights (Phase 5)

### Export ("download my data") — Tier B
- **Alpha:** document an email request path in the Privacy Policy (you must respond within ~30
  days for GDPR). No build required to launch.
- **Build later:** an Account row "Download my data" → queued job assembles a JSON/ZIP of the
  user's personal data (profile, contact, memberships, login history, emails, feedback/tickets
  they filed) and emails a download link. **Decide the ownership line:** a *business's* inventory
  data belongs to the business, not the individual — the personal export covers the person's
  data; business data export is a separate (owner-only) feature. State this in the docs.

### Deletion ("right to erasure") — ⚠️ pre-beta, requires the owner-handoff flow below
- `profile.destroy` / `DeleteUserForm` already exists, but **`ProfileController::destroy` is a bare
  `$user->delete()` with no owner handoff (verified 2026-06-30).**

#### Owner handoff on account deletion (DECIDED — Todd, 2026-06-30)
When a user starts account deletion, branch per business **where they are the sole owner**:

1. **Another owner already exists** → no action; delete the user. Business still has an owner.
2. **Other members exist (any role, incl. guests) but no other owner** → the deletion flow
   **prompts the departing owner to assign ownership to one of those existing members** (their
   explicit choice from a list). **No auto-promotion** — a member must never be saddled with a
   business they didn't ask for, so the system never picks for them.
   - If they assign → promote the chosen member to `owner`, then delete the user.
   - If they decline → the business follows the **frozen path** (below).
3. **Truly solo** (deleter is the only member) or **declined handoff** → set `frozen_at = now()`
   on the business (reusing the existing, enforced freeze machinery), then delete the user. The
   business is preserved, surfaces in the admin **frozen** filter + dashboard count, and is
   recoverable via impersonation / a future transfer-owner action.

> **Do NOT** hard-block deletion, and do NOT silently orphan with no state change — the freeze flag
> is what makes an ownerless business discoverable and what actually protects its data (members
> blocked, dropped from context).

**Build notes:**
- The Register/Profile delete UI needs the conditional successor-picker step (only shown when
  case 2 applies); the backend computes the per-business branch and validates the chosen member is
  a real current member of that business.
- Ownership is derived from membership role (no `owner_id`), so "assign owner" = set the chosen
  membership's role to `owner`. Consider a small shared "make this member the owner" action so the
  deletion flow and a future admin transfer-owner action share one code path.
- **Open downstream consideration (not a beta blocker):** the assigned successor doesn't consent
  in-flow. Decide later whether to notify them and/or let them decline-back-to-frozen afterward.

#### Other deletion notes
- Soft delete vs hard delete: `User` uses `SoftDeletes` (`withTrashed` used in admin). The Privacy
  Policy must describe retention honestly (e.g. "deactivated immediately, purged after N days").
  A business frozen-and-ownerless for N days → purge is the matching business-side retention rule
  to state.
- A true "erase my data" request may need a manual/admin path beyond the self-serve button for
  Alpha. Document the request path.

---

## Surfacing (where the links appear)

1. **GuestLayout footer** (login/register/forgot pages) → `LegalFooter` (Terms · Privacy · Cookies).
2. **Welcome.vue footer** → replace bare copyright with `LegalFooter`.
3. **Register.vue** → consent checkbox with inline Terms/Privacy links.
4. **Marketing landing `/index.html` footer** → add Terms · Privacy · Cookies links (point at
   `https://app.balloonventory.com/terms` etc.).
5. **Account hub** (`Account/Index.vue`) → new row **"Legal & Policies"** → `/legal`
   (or directly to the three docs). Add `nav`/`account` i18n keys + an icon to match the row style.
6. **CookieNotice** banner mounted app-wide (guest + authenticated).

---

## Execution phases (suggested order)

- **Phase 0 — Decisions (real-world, not code):**
  - **Legal entity name + state** — load-bearing: it appears verbatim in ToS/Privacy and must
    later match the Stripe account exactly. Grep found **no entity name anywhere in config** today,
    so this is unconfirmed. If the LLC doesn't formally exist yet, that gates the prose more than
    any code does.
  - **Published contact email must be a real, monitored inbox** — Resend is **send-only** and won't
    receive. A Privacy Policy promising a 30-day response to `privacy@balloonventory.com` is a
    compliance liability if nobody receives/watches that address. Provision + monitor whatever you
    publish before it goes in the prose.
  - Decide `privacy@` vs `support@`, whether es ships day-one or fast-follow, and whether you'll
    have a lawyer review or use a generator. *(Blocks final prose, not the scaffolding.)*
- **Phase 1 — Plumbing:** `config/legal.php`, `LegalController`, routes, `Legal/Show.vue`,
  placeholder Markdown for all five docs (lorem/skeleton headings), `legal.php` lang files.
  Test: every route returns 200 and is reachable while logged out.
- **Phase 2 — Surfacing:** `LegalFooter` + `CookieNotice` components; wire into GuestLayout,
  Welcome, AuthenticatedLayout; add Account hub row; update `index.html` footer.
- **Phase 3 — Consent:** migration + Register checkbox + backend recording + **acceptance
  interstitial** (catches invited/magic-link users with null `terms_accepted_at` and stale-version
  re-acceptance). ⚠️ Don't run against placeholder prose — see the ordering guard in the Consent
  section. Tests: registration rejected without acceptance; `terms_accepted_at`/`terms_version`
  persisted; invited user with null acceptance is forced through the interstitial; stale
  `terms_version` triggers re-acceptance.
- **Phase 4 — Content:** drop in real (lawyer-reviewed) prose for Terms, Privacy, Cookies,
  incl. trademark + data-source disclaimers. Spanish translations.
- **Phase 5 — Data rights + owner-handoff on deletion (⚠️ pre-beta):** implement the owner-handoff
  flow (successor-picker → assign, or decline → freeze; solo → freeze) per the Phase 5 spec, reusing
  the existing `frozen_at` machinery; confirm soft-delete retention wording; document
  export/deletion request path in the Privacy Policy. (Self-serve export = later.) The owner-handoff
  fix should ship before beta even if the rest of Phase 5 slips. Tests: sole-owner + other members
  → handoff required and assigns owner; declined/solo → business frozen + user deleted; existing
  co-owner → plain delete.
- **Phase 6 — Billing-time (deferred):** fill the `/refunds` stub and add affiliate terms when
  payments/affiliate features ship.

---

## Tests to add
- `LegalPagesTest`: each of `/legal`, `/terms`, `/privacy`, `/cookies`, `/acceptable-use`,
  `/refunds` returns 200 **while unauthenticated**; unknown `doc` → 404; locale fallback works
  (es request with no es file still 200s with en content).
- `RegistrationConsentTest`: register without `terms` accepted → validation error; with it →
  user has `terms_accepted_at` + `terms_version` set.
- (Phase 5) account-deletion last-owner behavior test, if a gap is found.

## Conventions reminder (from CLAUDE.md)
- Use `php artisan make:` for controller/migration/test scaffolding; `--no-interaction`.
- Run `vendor/bin/pint --dirty --format agent` before finalizing PHP.
- `search-docs` before coding (Inertia public-page rendering, validation `accepted` rule,
  `Str::markdown` options).
- Bilingual: every new user-facing string needs en **and** es keys.
- Run the focused test file with `php artisan test --compact --filter=...` after each phase.
```
