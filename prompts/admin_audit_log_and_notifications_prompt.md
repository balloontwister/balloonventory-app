# Feature: Admin audit log + notifications for sensitive actions

**Status:** Planning (not started)
**Created:** 2026-06-30
**Owner:** Todd
**App:** Laravel 12 + Inertia v2 + Vue 3 + Tailwind 3 (Balloonventory). Prod: https://app.balloonventory.com

> ⚠️ Read `CLAUDE.md`, the memory index at
> `~/.claude/projects/-Users-todd-Documents-VS-Code-Projects-Balloonventory-App/memory/MEMORY.md`
> (esp. `project_status`, `project_notifications_system`, `project_prod_mysql_timezone`,
> `reference_usedatetime_composable`, and the `feedback_*` rules), and `DATA.md` before starting.
> Follow Laravel Boost conventions: `search-docs` first, `php artisan make:` for scaffolding,
> bilingual en+es for every user-facing string, `vendor/bin/pint --dirty --format agent`, and
> focused tests (`php artisan test --compact --filter=...`).

---

## Why (Todd's words)

> "There should be an Admin Notification for some actions, such as when a User deletes their
> account. How do most systems handle such actions?"

**How most systems handle it (the design this prompt follows):** two layers.
1. **An append-only audit log** of sensitive events — the canonical, always-on record (who did
   what, to whom/what, when, from where). This is the foundation.
2. **Selective notifications** on top — real-time alerts and/or a periodic digest for *high-signal*
   events only. Systems do **not** push an alert per action (that's noise); they log everything
   and notify on a curated subset.

So this feature = **stand up the audit log first**, then layer light notifications on it.
This is the "future audit-log project" already referenced in `project_status` memory
("freeze/thaw/promote/demote/delete/password/email changes are still unlogged").

## Verified current state (confirmed 2026-06-30 — reuse these patterns, don't reinvent)

- **Append-only table precedent — mirror this exactly:** `login_events`
  (`database/migrations/2026_06_20_160633_create_login_events_table.php`, model `LoginEvent`).
  Recorded via an **event subscriber** `app/Listeners/LoginHistorySubscriber.php`, registered with
  `Event::subscribe(LoginHistorySubscriber::class)` in `AppServiceProvider` (method names are NOT
  `handle*` for auto-discovery reasons — note the comment there). Surfaced at
  `GET /admin/login-log` (`SuperAdmin/LoginLogController`, `Pages/SuperAdmin/LoginLog/Index.vue`,
  route `admin.login-log.index`, `routes/web.php` ~line 288). **The audit log view should mirror
  the LoginLog page** (paginated 50, search, filter, teleported chrome, `useDateTime()` for
  timestamps per `reference_usedatetime_composable`). Other precedent: `barcode_link_audits`.
- **Notifications system (DB + mail) is live** (`project_notifications_system`):
  `notifications` table (`2026_06_23_221506_create_notifications_table.php`, notifiable is **uuid**
  — see the follow-up `fix_notifications_notifiable_to_uuid` migration), `NotificationController`,
  `app/Support/NotificationPresenter.php`, and ~11 notification classes in `app/Notifications/`
  (e.g. `AccountFrozen`, `BusinessFrozen`, `SiteAdminGranted`). Notifications are dispatched with
  `$user->notify(new SomeNotification(...))`.
- **Admin identity:** `User::isSuperAdmin()` / `isAnyAdmin()` (enum `admin_level`, `App\Enums\AdminLevel`).
  There is **no existing "notify all super admins" helper** — you'll add one (e.g.
  `User::query()->where('admin_level', AdminLevel::SuperAdmin)->get()` then `Notification::send($admins, ...)`).
- **Sensitive actions that are currently UNLOGGED** and are the candidate events:
  account deletion (self: `ProfileController@destroy` → `AccountDeletionService::handleSelfDeletion`;
  admin: `AdminUserController@destroy` → `handleAdminDeletion`), business freeze/thaw
  (`AdminBusinessController@suspend/thaw`), ownership transfer (new — `AccountDeletionService`),
  role changes (`MembershipController`), member remove/leave, admin grant/revoke
  (`AdminUserController` promote/demote, freeze/thaw user), password reset send, email change.
- **Timezone footgun:** `project_prod_mysql_timezone` — always set timestamps in app code with
  `now()`, never DB-side `useCurrent()`. Append-only tables here use `$timestamps=false` + a manual
  `created_at` (see `LoginEvent`); follow that.

## Goal

1. An **append-only `audit_events` table** + model recording sensitive actions with: actor
   (user id, nullable for system), `action` (enum/const string), target type+id (polymorphic-ish,
   but keep it simple — store `target_type` + `target_id` strings + a human label snapshot),
   a JSON `metadata` column, `ip_address`, `created_at` (manual, `$timestamps=false`, uuid7 id).
   Snapshot human-readable labels (actor name, target name) at write time so the log survives
   later deletions (mirrors how `SkuFeedback`/`BarcodeLinkAudit` snapshot context).
2. A **recording seam** — a small service (e.g. `App\Services\Audit\AuditLogger::record(...)`) or
   an event + subscriber. Prefer an explicit `AuditLogger` called from the action sites over
   broad model-event magic, so each logged action is deliberate and carries good metadata.
   Start by instrumenting **account deletion** (the motivating case) end-to-end, then add the
   other actions.
3. An **admin view** at `GET /admin/audit-log` mirroring the LoginLog page (super-admin-gated via
   `RequireSuperAdmin`/`RequireAdminAccess` group — confirm which; login-log is all-admin),
   with search + action-type filter + actor/target links to `admin.users.show` where applicable.
   Add a dashboard card + AdminMenu entry like the other admin areas.
4. **Selective notification** layer: for the curated high-signal subset (start with: account
   deletion, maybe admin-on-admin changes), notify super admins — decide **real-time per-event**
   vs **a daily digest** (recommend digest for volume, real-time only for rare/critical). Reuse
   the existing notification system + add a `notify-all-super-admins` helper.

## Decisions to make with Todd (ask before building)

1. **Which events** make the curated *notify* subset vs *log-only*? (Recommend: log everything
   sensitive; notify on account deletion + admin-account changes only, at least at first.)
2. **Notification cadence:** real-time vs daily digest vs both. (Recommend digest + real-time for
   a tiny critical set.)
3. **Retention:** how long to keep audit rows, and a prune command like
   `app:prune-login-events`? (Recommend keep ≥ 1–2 years; add a scheduled prune.)
4. **Scope of v1:** ship the table + recorder + account-deletion instrumentation + admin view
   first; add the remaining action sites + notifications as fast-follows. Confirm that staging.

## Likely files

- New migration `create_audit_events_table` + `App\Models\AuditEvent` (uuid7, `$timestamps=false`).
- New `App\Services\Audit\AuditLogger` (+ an `AuditAction` enum or const set).
- Instrument: `AccountDeletionService`, `ProfileController`, `AdminUserController`,
  `AdminBusinessController`, `MembershipController`, `InvitationController` (ownership transfer).
- New `SuperAdmin/AuditLogController` + `Pages/SuperAdmin/AuditLog/Index.vue` (mirror LoginLog).
- Dashboard card (`SuperAdminController` summary + `Dashboard.vue`/`AdminCard`), `AdminMenu.vue`
  + sidebar entry, route in `routes/web.php`.
- New notification class(es) (e.g. `AccountDeletedAdminNotice`) + `notify-super-admins` helper;
  optional digest command + schedule entry.
- en+es lang for the page chrome, action labels, notification copy.

## Out of scope

- Per-business (tenant) activity feeds — this is an **admin/site-level** audit log, cross-tenant
  (use `withoutGlobalScope(BusinessScope::class)` when reading related models, like LoginLog does).
- Reworking the existing user-facing notification center.

## Definition of done

- `audit_events` records at least account deletion (self + admin) with actor/target snapshots,
  ip, and metadata; rows survive subsequent user deletion (snapshots, not just FKs).
- `/admin/audit-log` lists events (paginated, searchable, filterable), reachable from the
  dashboard + AdminMenu, gated to the right admin level.
- The curated notification fires for the agreed events to super admins.
- Append-only semantics enforced (no update/delete paths in app code; timestamps via `now()`).
- Feature tests: an action writes the expected audit row; the admin view loads & filters;
  notification dispatched for the curated event. Bilingual strings, Pint clean, focused tests
  green. Ask Todd before running the full suite.
