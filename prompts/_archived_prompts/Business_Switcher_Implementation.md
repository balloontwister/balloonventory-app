# Business Switcher — Session Briefing

## What it is

Users can belong to multiple businesses (via the `memberships` table). The "Business Switcher" lets them change which business is active. The active business scopes all inventory, lists, bins, etc. through Eloquent global scopes.

## Roles

`memberships.role` is `enum('owner','manager','staff','guest','none')`. `none` = suspended — the user stays in the membership but loses all access. A migration added `none` in `database/migrations/2026_06_23_132849_add_none_to_memberships_role_enum.php`.

## Server-side request flow

**1. `SetBusinessContext` middleware** (`app/Http/Middleware/SetBusinessContext.php`)
Runs on every authenticated web request. Reads `current_business_id` from session, filters out `none`-role memberships so a suspended membership can't trap the user, sets `BusinessContext::set($id)`. Updates the session if the selected business changed (e.g. session pointed to a `none` business).

**2. `BusinessContext`** (`app/Support/BusinessContext.php`)
Simple static holder: `set(string $id)`, `currentId(): ?string`, `clear()`. Set once per request by the middleware; read everywhere else. Not persisted — just a per-request static.

**3. `HandleInertiaRequests` middleware** (`app/Http/Middleware/HandleInertiaRequests.php`)
Shares `business`, `businesses`, `membership`, and `permissions` as Inertia shared props on every page. Reads `BusinessContext::currentId()` to pick the current membership. The `permissions` array is derived from the role (see `permissionsForRole()` in the same file). Runs **after** `SetBusinessContext`.

**4. `BusinessController::switch()`** (`app/Http/Controllers/BusinessController.php`)
`POST /business/{business}/switch` — validates the user has a non-`none` membership in the target business, sets the session and `BusinessContext`, returns `back()` with a flash. Blocks `none` role with `abort_if`.

## Frontend

**`AuthenticatedLayout.vue`** (`resources/js/Layouts/AuthenticatedLayout.vue`)
Contains the switcher UI. Uses the `page.props.auth.businesses` array (all the user's businesses with role/color) and `page.props.auth.business` (the active one). Switching posts to `route('business.switch', id)`.

**`useBusiness.js`** (`resources/js/Composables/useBusiness.js`)
Composable that wraps `usePage().props.auth.business` and related props. Import this in components that need the current business.

**`RoleBadge.vue`** (`resources/js/Components/RoleBadge.vue`)
Displays role labels. Knows about `none` → "No Access".

**`Settings/Businesses.vue`** (`resources/js/Pages/Settings/Businesses.vue`)
The team management page. Shows members, their roles, pending invitations. Owners can invite and change roles.

## Invitations

**`InvitationController`** (`app/Http/Controllers/InvitationController.php`)
- Magic-link `accept()`: logs user in if needed, switches session to the invited business.
- In-app `acceptInApp()`: does NOT switch businesses — returns `back()` so the user stays in their current context. The dashboard shows a membership notice card instead.

**`MembershipController`** (`app/Http/Controllers/MembershipController.php`)
Handles role updates and member removal. Allows roles: `owner, staff, guest, none`.

## Routes to know

```
POST   /business/{business}/switch                business.switch
POST   /invitations/accept-in-app                 invitations.accept-in-app
GET    /invitations/{token}/accept                invitations.accept
POST   /settings/memberships/{membership}/role    membership.role.update
DELETE /settings/memberships/{membership}         membership.destroy
```

## Key invariants

- `none` members cannot switch to that business (`abort_if` in `BusinessController::switch`)
- `SetBusinessContext` always resolves to an accessible business — never leaves context as `null` for authenticated users with memberships
- `BusinessScope` (global Eloquent scope) uses `BusinessContext::currentId()` to filter all models — this is why setting context correctly matters
- The `HandleInertiaRequests` middleware order matters: it runs **after** `SetBusinessContext` so context is already set when props are built

## Tests

- `tests/Feature/BusinessSwitchTest.php` — covers switch, deny for non-member, deny for `none` role, and middleware skipping `none` memberships
- `tests/Feature/BusinessInvitationTest.php` — covers magic-link and in-app invitation acceptance flows

## Files to read before editing

1. `app/Http/Middleware/SetBusinessContext.php`
2. `app/Support/BusinessContext.php`
3. `app/Http/Controllers/BusinessController.php`
4. `app/Http/Middleware/HandleInertiaRequests.php` (lines 47–106, the `businessProps` method)
5. `app/Models/Membership.php`
6. `resources/js/Layouts/AuthenticatedLayout.vue` (the switcher UI block)
7. `resources/js/Composables/useBusiness.js`
8. `resources/js/Components/RoleBadge.vue`
9. `app/Http/Controllers/MembershipController.php`
10. `app/Http/Controllers/InvitationController.php`
11. `tests/Feature/BusinessSwitchTest.php`
