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
   `profile.destroy`) — but it needs an **audit** (soft vs hard delete, last-business-owner
   edge case). Self-serve "Download my data" is **Tier B**; for Alpha a documented
   email-request path in the Privacy Policy is legally sufficient (respond within 30 days).

---

## Current-state findings (what exists today)

- **Marketing landing** = `/index.html` at repo root (untracked, served at balloonventory.com).
  Footer is just `© 2026 Balloonventory. All rights reserved.` — **no legal links**.
- **App landing** = `resources/js/Pages/Welcome.vue` (route `/`). Footer is just `© Balloonventory`.
- **GuestLayout** (wraps Login/Register/Forgot) — **no footer, no legal links**.
- **Register.vue** — name/email/password only. **No terms acceptance.**
- **Account hub** (`Pages/Account/Index.vue`) — rows: Profile · Business · Preferences ·
  Help & Support · Super Admin · Log out. **No legal/about entry.**
- **Account deletion exists** — `Profile/Partials/DeleteUserForm.vue` → `profile.destroy`
  (needs behavior audit; see Phase 5).
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

### i18n
- **`lang/en/legal.php`** + **`lang/es/legal.php`** — page chrome only (titles, "Last updated",
  footer link labels, cookie-notice text, the registration consent sentence). The long prose
  lives in the Markdown files, **not** in lang files.

---

## Consent at registration (Phase 3)

- **Migration** `add_terms_acceptance_to_users`: `terms_accepted_at` (timestamp, nullable),
  `terms_version` (string, nullable).
- **Register.vue**: add a required checkbox above the submit button —
  *"I agree to the [Terms of Service] and [Privacy Policy]"* with links opening `/terms` and
  `/privacy` (target=_blank). Bind `form.terms = false`.
- **Backend** (`Auth/RegisteredUserController@store` or its form request): validate
  `terms => ['accepted']`; on success set `terms_accepted_at = now()` and
  `terms_version = config('legal.terms_version')`.
- **Re-acceptance (future):** when `terms_version` changes, you *can* prompt existing users to
  re-accept via a dashboard interstitial. Not needed for Alpha — note it and move on.
- **Invited users** (magic-link auto-accept flow): decide whether acceptance is captured at
  first login. For Alpha, a line in the ToS ("by using the service you agree") plus the
  signup checkbox for self-serve registrations is enough; flag the invited-user path as a
  follow-up.

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

### Deletion ("right to erasure") — audit existing first
- `profile.destroy` / `DeleteUserForm` already exists. **Audit before relying on it:**
  1. Soft delete or hard delete? (Users appear to soft-delete — `withTrashed` is used in admin.)
     The Privacy Policy must describe retention honestly (e.g. "deactivated immediately, purged
     after N days").
  2. **Last-owner edge case:** what happens to a Business (and its other members' access +
     inventory) when the sole owner deletes their account? There's a last-owner guard for
     *leaving*; confirm it also governs *account deletion*. This is the riskiest gap.
  3. A true "erase my data" request may need a manual/admin path beyond the self-serve button
     for Alpha. Document the request path.

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

- **Phase 0 — Decisions:** confirm legal entity name, contact email (privacy@ vs support@),
  whether es ships day-one or fast-follow, and whether you'll have a lawyer review or use a
  generator. *(Blocks final prose, not the scaffolding.)*
- **Phase 1 — Plumbing:** `config/legal.php`, `LegalController`, routes, `Legal/Show.vue`,
  placeholder Markdown for all five docs (lorem/skeleton headings), `legal.php` lang files.
  Test: every route returns 200 and is reachable while logged out.
- **Phase 2 — Surfacing:** `LegalFooter` + `CookieNotice` components; wire into GuestLayout,
  Welcome, AuthenticatedLayout; add Account hub row; update `index.html` footer.
- **Phase 3 — Consent:** migration + Register checkbox + backend recording. Tests: registration
  rejected without acceptance; `terms_accepted_at`/`terms_version` persisted.
- **Phase 4 — Content:** drop in real (lawyer-reviewed) prose for Terms, Privacy, Cookies,
  incl. trademark + data-source disclaimers. Spanish translations.
- **Phase 5 — Data rights audit:** audit `profile.destroy` (soft/hard, last-owner case);
  document export/deletion request path in Privacy Policy. (Self-serve export = later.)
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
