# Feature: Sign up & join a team without creating a business

**Status:** Planning (not started)
**Created:** 2026-06-30
**Owner:** Todd
**App:** Laravel 12 + Inertia v2 + Vue 3 + Tailwind 3 (Balloonventory). Prod: https://app.balloonventory.com

> ⚠️ Read `CLAUDE.md`, the memory index at
> `~/.claude/projects/-Users-todd-Documents-VS-Code-Projects-Balloonventory-App/memory/MEMORY.md`
> (esp. `project_status`, `project_multibusiness_invites`, `onboarding_seed_lists`,
> and the `feedback_*` rules), and `DATA.md` before starting. Follow Laravel Boost
> conventions: `search-docs` first, `php artisan make:` for scaffolding, bilingual
> en+es for every user-facing string, `vendor/bin/pint --dirty --format agent` before
> finalizing, and write/za run focused tests (`php artisan test --compact --filter=...`).

---

## The problem (Todd's words)

> "When a new user registers, the immediate onboarding asks them to set up their
> business. There is no way to skip that. A user should be able to sign up to join
> an account as a guest or artist and work on setting up their own business later."

Today, a freshly-registered user with **no business membership** is hard-bounced into
creating a business and cannot use the app until they do. Someone who only wants to
**join an existing team** (as guest/artist via an invitation) is forced to create a
throwaway business first.

## Verified current state (don't re-derive — confirmed 2026-06-30)

- **The forced gate:** `app/Http/Middleware/EnsureHasBusiness.php` (alias `ensure.business`,
  registered in `bootstrap/app.php`). If the user has no active membership it does
  `redirect()->route('onboarding.create-business')`. Every business-gated route is behind
  the `['auth','verified','ensure.business','ensure.business.active']` group in
  `routes/web.php` (~line 98), so a no-business user can never reach the app shell.
- **Create-business flow:** `BusinessController@create` renders `Onboarding/CreateBusiness.vue`;
  `@store` creates the business (+ auto-seeds Default location/bin/favorites) and redirects to
  `route('onboarding.wizard')`. Routes `onboarding.create-business` / `onboarding.store-business`
  live in an **auth-only** group (no business required), `routes/web.php` ~line 58.
- **The wizard IS already skippable** (`onboarding.wizard.skip`) — the *non*-skippable step is
  business *creation* itself, not the wizard.
- **Joining an existing business already works** for users who have a membership: the
  multi-business invite flow (`InvitationController`, `MembershipController@invite`,
  `BusinessInvitation` model) lets an owner invite an existing user by email; the invitee
  accepts via magic-link (`/invitations/{token}/accept`, **outside** the business gate) or the
  in-app dashboard notice (`DashboardController::buildPendingInvitations`). See
  `project_multibusiness_invites` memory.
- **Registration:** `Auth/RegisteredUserController@store` → email-code verification
  (`verification.code`) → then the app, where `EnsureHasBusiness` takes over.
- **The whole app shell assumes a "current business"**: `SetBusinessContext` middleware,
  `BusinessSwitcher.vue`, `HandleInertiaRequests` shared props (`business`, `businesses`,
  `membership`, `auth.*`), nav, and `DashboardController` all read a current business.
  **This is the main source of work** — gracefully supporting a *no-current-business* state.

## The chicken-and-egg to solve

A self-registered user with no invitation and no business currently has nowhere to land.
We must let "authenticated + verified + **no business**" be a valid, navigable state with a
clear path forward: create a business now, **or** wait for / accept a team invitation.

## Goal

1. A new user can **finish registration and reach a usable landing page without creating a
   business.** Business creation becomes a *choice*, not a forced redirect.
2. From that landing they can either **create their own business** (existing flow) or **see &
   accept a pending team invitation** (existing accept flow) — and otherwise understand they're
   "waiting to be added to a team."
3. Once they have any membership (their own business or an accepted invite), the normal app
   shell works as today.

## Suggested design direction (confirm specifics as you go)

- **Replace the hard redirect** in `EnsureHasBusiness` with a redirect to a new neutral
  **"no business yet" landing** (e.g. `GET /welcome` / `onboarding.welcome`) that renders in a
  layout that does **not** require a business context. That page offers: "Set up my shop"
  (→ `onboarding.create-business`) and a panel for pending invitations (reuse
  `BusinessInvitation` pending query + the existing accept/decline POST routes) plus an
  explainer that a team owner can invite them by their account email.
- **Audit every `HandleInertiaRequests` shared prop and the AuthenticatedLayout** for
  null-safety when there is no current business (`business`/`membership` null). Decide whether
  the no-business landing uses `GuestLayout`, a trimmed `AuthenticatedLayout`, or a dedicated
  minimal layout. Heed the shared-prop-collision rule (`feedback_inertia_shared_prop_collision`).
- **Keep the magic-link accept path working** for a no-business invitee (it already bypasses the
  gate) and make sure accepting lands them in the business cleanly.
- **`SetBusinessContext`** already early-returns when `memberships->isEmpty()` — verify it leaves
  context null safely and that downstream code tolerates it.

## Decisions to make with Todd (ask before building)

1. **Registration intent:** Add an explicit choice at sign-up ("I'm setting up my own shop" vs
   "I'm joining a team")? Or keep one generic sign-up and let the landing page offer both paths?
   (Recommend: one sign-up, choice on the landing — less friction, invitation-driven joining.)
2. **No-business landing layout:** minimal standalone vs trimmed authenticated shell.
3. **Can a no-business user enter an invite code themselves**, or is joining strictly
   owner-initiated (invite-by-email) as today? (Today it's owner-initiated.)
4. **Verification ordering:** must they verify email before reaching the landing? (Recommend yes —
   keep `verified` in the chain.)

## Likely files

- `app/Http/Middleware/EnsureHasBusiness.php` (redirect target)
- `routes/web.php` (new neutral landing route; possibly relax the gated group)
- New `Onboarding/Welcome.vue` (or similar) + a controller method
- `app/Http/Middleware/HandleInertiaRequests.php`, `SetBusinessContext.php` (null-business safety)
- `resources/js/Layouts/*` and `BusinessSwitcher.vue` (no-current-business rendering)
- `DashboardController` / `Dashboard.vue` (ensure they aren't the only place invites surface)
- en+es lang files for all new copy

## Out of scope

- Changing roles/permissions semantics (the `guest`/`staff`/`owner` matrix already exists).
- The ownership-handoff/freeze work (already shipped, PRs #136/#137).

## Definition of done

- A brand-new verified user with no business lands on the welcome page, not a forced
  create-business redirect, and can navigate to create a business or accept an invite.
- App shell renders without errors when there is no current business.
- Existing onboarding (create business → wizard) and invite-accept flows still pass.
- Feature tests cover: no-business user reaches the landing (not redirected to create-business);
  can still create a business; an invited no-business user can accept and land in the team.
- Bilingual strings, Pint clean, focused tests green. Ask Todd before running the full suite.
