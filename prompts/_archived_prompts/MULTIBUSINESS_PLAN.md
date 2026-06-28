# Plan: Multi-business invitations + switching

> Build spec for a fresh session. Read `CLAUDE.md`, `DATA.md` (membership/list/invitation),
> `PERMISSIONS.md` (role × action matrix + the invitation/role-change notification rows), and the
> memory file `project_multibusiness_invites.md` before starting. Follow Laravel Boost conventions,
> Pint, and the test-enforcement rules.

## Goal
A Business Owner invites an **existing** Balloonventory user to join their business as Owner,
Artist, or Guest. Tallie emails a magic link that **auto-accepts and logs them in**. The invitee's
dashboard shows notices (pending invite + post-join status). The owner can toggle any member's role
(Owner / Artist / Guest) or remove them (None) at any time on Settings → Businesses. Switching
between businesses already works (`business.switch` + `BusinessSwitcher.vue`).

## Decisions (locked — do not re-litigate)
1. **Magic link auto-accepts immediately**: clicking Tallie's link passwordlessly logs the invitee
   in (existing account) AND accepts the invite in one step → dashboard with a status notice. The
   dashboard ALSO shows a pending-invite Accept/Decline notice for users who never clicked the
   email; both paths share one accept action.
2. **Unknown email → reject** with a clear message. Existing accounts only; new-user signup invites
   are a later project.
3. **Invite picker + owner's role toggle offer: Owner / Artist / Guest / None** only (omit Manager
   from the UI even though backend supports it). "None" = remove membership. Backend still enforces
   the full matrix + manager-ceiling + last-owner guard.
4. **Member-management UI** = new "Team / Members" section on Settings → Businesses
   (`Settings/Businesses.vue`), gated owner/manager, scoped to the current business.

## What already exists — reuse, don't rebuild
- **`MembershipPolicy`** (`app/Policies/MembershipPolicy.php`): `invite($user,$business,$role)`,
  `changeRole($user,$membership,$newRole)`, `remove($user,$membership)` — includes the last-Owner
  guard (`App\Exceptions\LastOwnerGuardException`) and manager-ceiling, via the
  `App\Policies\Concerns\ChecksMembership` trait. **Never wired to a controller yet** — this plan
  wires it.
- **Spatie permissions** `membership.invite_*` / `change_role_*` / `remove_*` seeded for owner +
  manager (`database/seeders/PermissionSeeder.php`); frontend permission strings live in
  `HandleInertiaRequests::permissionsForRole`.
- **Email**: `App\Mail\TemplatedMailable::forKey($key, $vars)` returns a ready Mailable or null.
  Reply-to is already "Balloonventory Support"; bodies are signed "Tallie". Mirror
  `SuperAdmin\AdminUserController` set-password usage of the `password_changed_by_admin` template
  (around line 367).
- **Dashboard nudges**: `DashboardController::buildNudges()` → `Components/Dashboard/SetupNudges.vue`
  → `DashboardController::dismissNudge` (route `dashboard.nudges.dismiss`) writes
  `users.dismissed_nudges` (json cast on `User`).
- **Switching**: `BusinessController@switch` (route `business.switch`, in the `auth` group);
  `Components/BusinessSwitcher.vue` renders the `businesses` shared prop.
- **Shared props** (`app/Http/Middleware/HandleInertiaRequests.php`): already exposes `businesses`
  (cross-business, read with `withoutGlobalScope(BusinessScope::class)`), `business`, `membership`,
  `permissions`.

## Build steps

### 1. Schema + model
`php artisan make:model BusinessInvitation -mf`
- Migration `business_invitations`: `id` char(36) pk; `business_id` foreignUuid idx; `invited_email`
  string idx; `invited_user_id` foreignUuid→users idx; `role` string (enum values
  `owner|manager|staff|guest`); `token` string(64) unique idx; `invited_by_user_id`
  foreignUuid→users; `status` string default `pending` (`pending|accepted|declined|revoked|expired`);
  `expires_at` nullable ts; `acknowledged_at` nullable ts; `responded_at` nullable ts; timestamps +
  softDeletes. Add `unique(business_id, invited_user_id, deleted_at)` (per DATA.md soft-delete-unique
  note — also enforce active-row uniqueness at the validation layer).
- Model: `BelongsToBusiness`, `SoftDeletes`, uuid7 in a `creating` hook (mirror `Membership`).
  Relations `business()`, `invitedUser()`, `inviter()`. Helper `isAcceptable(): bool`
  (`status === 'pending'` && not expired). Status constants or a small enum.
- **Update DATA.md**: add the `business_invitations` entity (new tenant-scoped table) to the Entities
  section, the relationships diagram, and the tenant-scoped list. Note in PERMISSIONS.md that the
  invitation-accepted / role-changed notification rows are now implemented.

### 2. Controllers + routes
**`MembershipController`** (routes under `Route::middleware(['auth','verified','ensure.business'])`):
- `invite` (POST `memberships.invite`): validate `email` (must exist in `users`; else reject with a
  flash error "No Balloonventory account found for that email."), `role ∈ {owner,staff,guest}`.
  `Gate::authorize` via `MembershipPolicy@invite`. Block self-invite and already-active-member
  (flash). Reuse a soft-deleted/prior pending invite or create a new one with random `token`
  (`Str::random(64)`), `expires_at = now()->addDays(14)`. Send
  `TemplatedMailable::forKey('business_invitation', [...])` to `invited_email` (skip + warning flash
  if the Mailable is null). Flash success.
- `updateRole` (PATCH `memberships.update-role`): bind `{membership}`; authorize via
  `MembershipPolicy@changeRole`; catch `LastOwnerGuardException` → flash error.
- `destroy` (DELETE `memberships.destroy`): authorize via `MembershipPolicy@remove`; catch the guard;
  soft-delete membership. This is the "None" action.
- `revokeInvite` (DELETE `memberships.invitations.revoke`): owner/manager revokes a pending invite
  (`status=revoked`).

**`InvitationController`**:
- `accept` (GET `invitations.accept` `/invitations/{token}/accept`, **auth optional** — NOT in the
  ensure.business group): look up an acceptable invite by token. If guest → `Auth::login($invitedUser)`
  + `session()->regenerate()`. If logged in as a *different* user → redirect to login with a message
  (do not silently switch accounts). Create/restore the `Membership` (role, default badge color,
  `joined_at = now()`); if an active membership already exists, mark the invite accepted without
  downgrading. Set `status=accepted`, `responded_at`; switch `current_business_id` to that business +
  `BusinessContext::set(...)`. **Token is single-use** — rotate/clear it on accept. Redirect to
  dashboard with a flash.
- `acceptInApp` (POST `invitations.accept-in-app`, auth) and `decline` (POST `invitations.decline`,
  auth): the dashboard-notice path — same accept logic (no login step needed; already authed).
  Decline sets `status=declined`, `responded_at`.
- `acknowledge` (POST `invitations.acknowledge`, auth): sets `acknowledged_at` to dismiss the
  post-join status notice.

Register `memberships.*` + invitation accept-in-app/decline/acknowledge in the appropriate auth
groups; register `invitations.accept` (token) in the `auth`-optional area alongside `business.switch`
patterns. Confirm the magic-link route works for a logged-out user.

### 3. Email template
Add a `business_invitation` row to `EmailTemplateSeeder` (`is_active => true`), mirroring the
existing HTML + text chrome and signed "Tallie." Tokens: `{{user_name}}`, `{{inviter_name}}`,
`{{business_name}}`, `{{role_label}}`, `{{accept_url}}`. The seeder uses `firstOrCreate`, so the row
is only inserted where missing — **must run the seeder on prod after deploy** (migrations won't add
it). Verify `config('mail.from')` reads acceptably; the body + reply-to already deliver the "from
Tallie" feel.

### 4. Dashboard notices (cross-business)
In `DashboardController@index`, add two props read with `withoutGlobalScope(BusinessScope::class)` for
the current user:
- `pendingInvitations`: acceptable invites where `invited_user_id = auth id` → each
  `{ token, business_name, inviter_name, role_label }`.
- `membershipNotices`: invites with `status=accepted && acknowledged_at null` →
  "You're now a {role} at {business}" with a Switch action.

New components in `resources/js/Components/Dashboard/`:
- `InvitationNotice.vue` — Accept → `invitations.accept-in-app`; Decline → `invitations.decline`.
- `MembershipStatusNotice.vue` — Switch → `business.switch`; dismiss → `invitations.acknowledge`.
Render both at the top of the notice stack in `resources/js/Pages/Dashboard.vue` (above SetupNudges).

### 5. Team / Members section
`SettingsController@businesses`: add `members` (current business's memberships with user name/email/
role), `pendingInvitations`, and a `can` map (`invite`, `manageMembers`) — populated only when
`Gate::allows('business.edit_settings', $business)`. Read memberships with `withoutGlobalScope` where
needed and eager-load the user.
`resources/js/Pages/Settings/Businesses.vue`: add a "Team" card —
- member rows with a role dropdown (**Owner / Artist / Guest** + **Remove**) → `memberships.update-role`
  / `memberships.destroy`;
- an invite form (email + role select) → `memberships.invite`;
- a pending-invite list with Revoke.
The role options shown depend on the inviter's role (a manager sees only Artist/Guest). Use the global
`Toaster` for flashes.

### 6. i18n (en + es)
`settings.team.*`, `dashboard.invitations.*` / `dashboard.membership_status.*`, `flash.memberships.*`
/ `flash.invitations.*`, and `email.subjects.business_invitation` if subjects are keyed in
`lang/{en,es}/email.php`.

### 7. Tests (PHPUnit feature)
Cover: invite happy path + email sent; unknown-email reject; self/duplicate-member reject;
manager-ceiling (manager cannot invite Owner); magic-link auto-accept **logs in + creates membership
+ single-use token**; in-app accept/decline; `changeRole` incl. last-Owner guard → 403; `remove`
(None) incl. last-Owner guard; tenant isolation (cannot manage another business's members);
acknowledge clears the status notice.
**Test gotcha**: `SubstituteBindings` runs before `SetBusinessContext`, so a second-business actor
needs `BusinessContext::set($business)` before the request or the bound tenant model 404s under the
setUp context.

### 8. Finalize
- `vendor/bin/pint --dirty --format agent`.
- Run the new test files with `php -d memory_limit=1G vendor/bin/phpunit` (the documented 128M
  image-decoder flake otherwise aborts the full run); ask Todd before running the full suite.
- `npm run build`.
- Deploy via `ssh myvps "cd /home/balloonventory/balloonventory-app && bash bin/deploy.sh"`, then run
  `php artisan db:seed --class=EmailTemplateSeeder` on prod to insert the new template row.

## Security note (flag to Todd, already decided)
The auto-accept magic link logs the invitee in, so the token is effectively a login secret.
Mitigations baked in: 64-char random token, single-use (rotated on accept), 14-day expiry, https.
If a no-passwordless-login variant is ever preferred, only step 2's `accept` handler changes.
