# Notification Overhaul — Planning Document

## Convention: Flash toast vs Notification (read this first)

A standing rule for *every* future event, so we stay consistent. One user action often produces
feedback for two different people — decide each independently.

> **The test:** Is this **immediate confirmation to the person who just acted**, or a **durable
> message to someone else** (who may be offline, in another business, or not looking)?
> Actor → **flash toast**. Everyone else → **notification**.

| | **Flash / toast** | **Notification** |
|---|---|---|
| Who | The **actor** — the person who just did it | **Someone else** (or the actor, for something they'll handle later) |
| When | Synchronously, in the response to their click | Out-of-band; recipient may be away |
| Lifespan | Ephemeral — gone on next navigation | Durable — sits until read/dismissed |
| Answers | "Did my action work?" | "Something happened that concerns me" |
| Off-site reach | No | Optionally, via the email channel |

Worked example — **owner changes a member's role**: the owner is present and acted → **flash
toast**; the affected member is a third party who needs a durable, possibly-emailed record →
**notification**. (`MembershipController::updateRole` does exactly this.)

**Channel choice is a *separate*, second decision** — only after you've decided it's a
notification. Pick `via()` channels by urgency + whether they'd want it off-site:
- **DB + email** — materially affects them and they may be away (left business, role changed, invite accepted).
- **Email only** — no in-app surface to show it (member removed — they've lost access).
- **DB only** — low-stakes and tied to being in the app (joiner's "you now have access" notice).

**Guard rails (easy to forget):**
- **Never notify the actor about their own action.** Guard the recipient set (already done in
  `updateRole`; apply the same anywhere the actor could be a recipient, e.g. an owner leaving).
- **Don't double up.** If the actor got a toast, don't also write them a notification for the same
  event. Notifications are for the people who *weren't* in the room.
- **If you want a toast to persist or reach someone who navigated away — it should have been a
  notification.** That craving is the signal.

---

## The Problem

The app has no central notification system. Notification-like behaviors have been built ad-hoc
on a case-by-case basis, each with its own bespoke storage and rendering logic. As the app grows,
this approach will become hard to maintain and inconsistent for users.

---

## What Exists Today (Ad-Hoc)

### 1. Invitation / Membership Status Notice
- **Where:** Dashboard, via `MembershipStatusNotice.vue`
- **How it works:** `BusinessInvitation.acknowledged_at` is `null` until the user dismisses the
  card. The dashboard controller queries for unacknowledged accepted invitations and passes them
  as `membershipNotices`. Acknowledging posts to `invitations.acknowledge`.
- **Problem:** It's a one-off tied to the `business_invitations` table. Not reusable for any
  other kind of notice.

### 2. Flash Messages
- **Where:** All pages, via `HandleInertiaRequests` shared `flash` prop
- **How they work:** Standard `back()->with('success', ...)` / `redirect()->with('error', ...)`
- **These are fine** — ephemeral per-request feedback is correct as flash. Not part of the overhaul.

---

## What Laravel Offers: The Notification System

Laravel ships a first-class notification system that should be the foundation going forward.

### How it works
- One `Notification` class per event type (e.g. `MemberLeftBusiness`, `MemberRoleChanged`)
- Each class declares which **channels** to use: `['mail', 'database']`
- The `database` channel writes to a `notifications` table:
  - `id` (UUID), `type`, `notifiable_type`, `notifiable_id`, `data` (JSON), `read_at`, timestamps
- The `mail` channel calls `toMail()` on the same class. **In this app, `toMail()` must return a
  `TemplatedMailable::forKey(...)`, NOT a native `MailMessage`** — see the "Email System Impact"
  section below for why this is non-negotiable.
- Dispatch from anywhere: `$owner->notify(new MemberLeftBusiness($membership))`
- Read via: `$user->unreadNotifications`, `$user->notifications`
- Mark read: `$notification->markAsRead()`
- Requires: one standard migration (`php artisan notifications:table`) and the `Notifiable`
  trait on `User` (already present via Breeze)

### Why this is the right path
- Multi-channel (DB + email) from a single class
- Built-in `read_at` tracking — no custom "acknowledged" columns needed
- Standardizes the dashboard notice layer — one query, one rendering component
- All future notification types follow the same pattern

---

## Proposed Architecture

### Backend
1. Run `php artisan notifications:table` → migrate
2. Create a `Notification` class per event type in `app/Notifications/`
3. Dispatch from controllers or dedicated event listeners
4. In dashboard controller: pass `auth()->user()->unreadNotifications` as an Inertia prop

### Frontend
1. Replace the bespoke `MembershipStatusNotice.vue` with a generic `NotificationCard.vue`.
   **It must support an optional typed action, not just a `message` string** — the existing
   notice carries a "Switch to this business" CTA (`MembershipStatusNotice.vue:34-42`). A
   message-only card would be a feature regression. Model the JSON `data` to carry an optional
   action `{ label, route, params }`, or switch on notification `type` in the component.
2. One notice list on the dashboard, driven by the unified notifications prop
3. Dismiss = mark as read via a new `DELETE /notifications/{notification}` route

### Notice Scope: user-level (global), not business-scoped
The dashboard shows **all** of the user's unread notifications regardless of which business is
active. This matches user expectation and is also the simpler implementation — notifications are
inherently user-level (`notifiable_id` = the user). A notice may therefore reference a business
the user isn't currently in, which is exactly why each card needs to name its business and offer
the Switch CTA when that business isn't the active one.

### Migration of Existing Invitation Notice — split into TWO notifications
The plan originally treated `MembershipStatusNotice` → `InvitationAccepted` as a like-for-like
migration. It is not. The existing notice and "the owner was told someone accepted" are two
different notifications to two different audiences:

- **`BusinessAccessGranted`** — shown to the *joiner*: "You now have {role} at {business}."
  Carries the **Switch-to-business action**. This is what `MembershipStatusNotice` does today.
- **`InvitationAccepted`** — shown to the *business owner*: "{name} accepted your invitation."

Collapsing them into one type loses the joiner's switch action and conflates audiences. Keep
them separate.

### "Revoked" is ambiguous in the current code — pick one
There are two distinct owner-initiated removals, plus a third implicit one:
- `MembershipController::destroy()` — owner removes an **active member** (← the "Member revoked"
  email targets this)
- `MembershipController::revokeInvite()` — owner cancels a **pending invitation** (different event)
- `MembershipController::updateRole()` with role `'none'` — effectively a removal too

Default assumption: the "Member revoked by owner" email fires on `destroy()`.

---

## Notification Types to Implement

The following are known events that warrant a notification, email, or both:

| Event | Notification class | Who is notified | Channels | Priority |
|---|---|---|---|---|
| Joiner gained access (existing notice) | `BusinessAccessGranted` | The joiner (with Switch CTA) | DB notice | High |
| Member accepted invitation | `InvitationAccepted` | Business owner | DB notice + email | High |
| **Member left the business** | `MemberLeftBusiness` | **All owners** of that business | DB notice + email | High |
| Member's role was changed | `MemberRoleChanged` | Affected member | DB notice + email | Medium |
| Member removed by owner (`destroy`) | `MemberRemoved` | Affected member | Email only | Medium |
| New member invited (pending) | — | Invited user | Email (already done via magic link) | Done |

> Note: `MemberLeftBusiness` must notify **every** owner of the business (there can be more than
> one), not a single owner.

> **"Member left the business"** is the immediate trigger for this overhaul. When a user
> clicks "Leave" on the Account page, the business owner currently receives no notice at all.
> This was deferred pending the notification system being in place.

---

## Email System Impact

**Switching to the notification system does NOT materially change the email log or the admin
emailing system — as long as the mail channel routes through `TemplatedMailable::forKey()`.**

### Why logging is safe: it's decoupled from how mail is sent
`App\Listeners\LogSentEmail` listens for Laravel's framework-level `MessageSent` event (auto-
discovered). It fires for **every outbound email** regardless of trigger — admin freeform, admin
template send, invitation, or notification. It is not wired into `TemplatedMailable` or any
controller specifically; it observes the mailer. Therefore notification emails get written to
`email_logs` **automatically**, with no new logging code.

### What stays unchanged
| Aspect | Effect |
|---|---|
| `email_logs` rows | Still written automatically via `MessageSent`. One notification → one row. No duplication. |
| Admin Email page (freeform + template send) | **Untouched.** Separate path (`AdminUserEmailController` / `EmailTemplateController` → `Mail::to()->send()`). Notifications are additive beside it. |
| `EmailTemplate` management | Reused — notification emails become new template keys, admin-editable like the rest. |
| Resend transport | Same. Notification emails go out through Resend like everything else. |
| Future delivery/bounce/open webhooks | **Unaffected.** Notification emails land in `email_logs` the same way, so future Resend message-id webhook matching covers them with no special handling. |

### Why `TemplatedMailable` (not native `MailMessage`) is required
If a notification's `toMail()` returns a native `MailMessage`:
- the email is **still logged** (`MessageSent` fires either way), BUT
- the `mailable` column records `'unknown'` (`LogSentEmail.php:18`), and
- the email **bypasses the admin-editable templates** entirely.

Returning `TemplatedMailable::forKey(...)` keeps both the log metadata and the template system
intact. This is the single decision that makes the overhaul a no-impact change to email.

### Downsides / things to watch (none are blockers)
1. **Coarse log granularity.** All templated emails log as `"TemplatedMailable"` in the `mailable`
   column — you can't distinguish "member left" from "role changed" by that column. Already true
   of invitation emails today (not a regression). Add a template-key column if per-type log
   visibility is wanted.
2. **Queue worker dependency.** `TemplatedMailable` is `ShouldQueue` already, so this isn't new —
   just note it now covers more send paths.
3. **`null`-template edge case.** `forKey()` returns `null` if a template is missing/inactive. A
   notification's `toMail()` can't cleanly return null, so `via()` must drop the `mail` channel
   when no active template exists (the DB notice still shows). Must be written deliberately or
   queued mail jobs will error.
4. **New mail where there was silence.** `leave` / `updateRole` / `destroy` currently send nothing.
   After this they email the affected user(s). Confirm whether any of these should be opt-out.

---

## Session Checklist (for the overhaul session)

- [ ] Run `notifications:table` migration
- [ ] `BusinessAccessGranted` notification (joiner-facing, carries Switch action) — replaces the
      `MembershipStatusNotice` / `acknowledged_at` flow
- [ ] `InvitationAccepted` notification (owner-facing) — DB + email
- [ ] `MemberLeftBusiness` notification → **all owners** (DB + email)
- [ ] `MemberRoleChanged` notification (affected member, DB + email)
- [ ] `MemberRemoved` notification (affected member, email only; fires on `destroy()`)
- [ ] All `toMail()` methods return `TemplatedMailable::forKey(...)`; add the `EmailTemplate` rows;
      handle the null-template case in `via()`
- [ ] Build generic `NotificationCard.vue` with optional typed action (Switch CTA), replacing the
      bespoke notice component
- [ ] Add `DELETE /notifications/{notification}` dismiss route (marks as read)
- [ ] Update dashboard controller to pass `auth()->user()->unreadNotifications` (global, not
      business-scoped); remove the bespoke `buildMembershipNotices()` query

---

## Notification center — build plan (bell + dropdown + page)

A stacked list of notice cards on the dashboard doesn't scale past a few items. Move the feed into
a standard **notification center**: a bell with an unread badge in the header, a dropdown of recent
items, and (later) a full history page. Surface discipline: **events → notification center; ongoing
states (low stock, availability) → dashboard widgets; critical/blocking → banners.** Don't turn
states into notifications.

### Pinned contracts (do not drift)
- **Data source:** one shared prop in `HandleInertiaRequests::share()` (on every page):
  `notifications => { unreadCount, recent[] }` (recent = latest 10, read+unread). Badge reads
  `unreadCount`; dropdown reads `recent` — no extra fetch. If it ever gets heavy, move `recent`
  behind an Inertia optional prop.
- **Item shape:** `{ id, type, business_id, business_name, role_label, actor_name, created_at, read_at }`.
- **One mapper each side:** backend `App\Support\NotificationPresenter` (`present`/`recent`/`unread`,
  later `paginated`); frontend `@/Composables/useNotifications` (`notificationMessageKey/Params`).
- **Routes:** `GET /notifications` (index, Phase 2), `POST /notifications/read-all`,
  `DELETE /notifications/{notification}` (exists).

### Phases (each = one commit + deploy + tests)
- **Phase 0 — refactor, no UX change:** extract `NotificationPresenter` + `useNotifications`; point
  `NotificationCard` and `DashboardController` at them.
- **Phase 1 — bell + badge + dropdown:** shared prop; `NotificationBell.vue` (mirrors `AdminMenu`
  dropdown) mounted beside the avatar in both headers; recent list; mark-one-read (existing route)
  + `markAllRead`. "See all" link deferred to Phase 2.
- **Phase 2 — full center page:** `Pages/Notifications/Index.vue` + `NotificationController::index`
  with `paginated($user, $filter)`; Unread/All toggle; infinite scroll. Add "See all" to dropdown.
- **Phase 3 — dashboard cleanup:** remove the general notice list from the dashboard body; keep
  pending invitations (actionable). 
- **Phase 4 — deferred:** grouping/batching, category filters, per-type preferences, digests.

### Notes for whoever builds later
- Bell placement mirrors `AdminMenu`: it shows wherever the avatar shows (desktop header when a
  page has a `header` slot or the sidebar is collapsed; always on mobile). If an always-on desktop
  bell is wanted for header-less pages, also drop it in the expanded sidebar logo area.
- `NotificationBell` copies `AdminMenu`'s teleported-dropdown mechanics (getBoundingClientRect
  positioning, click-outside overlay, Escape/scroll/resize close).
