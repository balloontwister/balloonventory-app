# PERMISSIONS.md — Balloonventory

The role × action matrix for the Balloonventory inventory system. This file is the source of truth for what each user role is allowed to do. It complements DATA.md (which defines the `role` enum on `membership` and the `is_super_admin` flag on `user`) and DESIGN.md (which defines the `PermissionGate` component and `RoleBadge`).

If anything in code conflicts with this file, this file wins. Update this file before changing permission rules, not after.

---

## Roles

Balloonventory has two scopes of role: per-business and global.

### Per-business roles

A user holds at most one role per business they're a member of, set on the `membership.role` enum. A user can hold different roles in different businesses.

| System name | Display label | One-line description |
|---|---|---|
| `owner` | Owner | Founder or business partner; full control |
| `manager` | Manager | Operations and money-adjacent settings; can manage Artist and Guest |
| `staff` | Artist | Inventory operations; cannot manage other users or settings |
| `guest` | Guest Artist | Read-mostly visibility; for occasional collaborators who need to check stock |

The system uses `staff` and `guest` as the underlying enum values, but the UI always shows "Artist" and "Guest Artist." This separation lets us change display labels per business or per locale later without touching the enum or migrations. RoleBadge in DESIGN.md renders the display label.

### Global roles

| System name | Display label | Description |
|---|---|---|
| `site_admin` | Site Admin | Platform-level admin. Has all the same platform powers as Super Admin in v1; distinction will matter when subscriptions and plan management are introduced. Can be granted or revoked by a Super Admin. |
| `super_admin` | Super Admin | Highest platform authority. Maintains the shared SKU catalog, resolves cross-business issues, and is the only role that can promote or demote Site Admins. Cannot be promoted or demoted through the UI. |

Both admin levels are stored in the `admin_level` enum column on `user` (nullable; `NULL` = regular user). The `App\Enums\AdminLevel` PHP enum provides typed constants: `AdminLevel::SiteAdmin` and `AdminLevel::SuperAdmin`.

Neither admin level grants access to a business's tenant-scoped data without a `membership` row. Admin powers are platform-level only.

---

## Naming convention

When you see a permission referenced in code, use the system name (`staff`, `guest`). When you see a role rendered in the UI, use the display label ("Artist", "Guest Artist"). Don't mix them — the system name leaks technical jargon into the product, and the display label leaks product copy into the database.

---

## Role × action matrix

A `✓` means the role can perform the action. `✗` means they cannot. `-` means not applicable in that scope. The Owner column applies the **last-Owner guard**: see Special rules below.

### Inventory operations

| Action | Owner | Manager | Artist | Guest |
|---|:-:|:-:|:-:|:-:|
| Check In (scan a UPC, count goes up) | ✓ | ✓ | ✓ | ✗ |
| Check Out (scan a UPC, count goes down) | ✓ | ✓ | ✓ | ✗ |
| Manual stock adjustment (correct a count without scanning) | ✓ | ✓ | ✓ | ✗ |
| Override the count on a recent scan (e.g. partial bag `+0.4`) | ✓ | ✓ | ✓ | ✗ |
| View current stock counts | ✓ | ✓ | ✓ | ✓ |
| View audit log (per-SKU stock movement history) | ✓ | ✓ | ✓ | ✗ |

### Catalog: SKUs, UPCs, overrides, and error reports

| Action | Owner | Manager | Artist | Guest |
|---|:-:|:-:|:-:|:-:|
| Create a private SKU | ✓ | ✓ | ✓ | ✗ |
| Edit a private SKU (any field, including price code) | ✓ | ✓ | ✓ | ✗ |
| Soft-delete a private SKU | ✓ | ✓ | ✗ | ✗ |
| Edit a shared catalog SKU | ✗ | ✗ | ✗ | ✗ |
| Edit a `business_sku_override` row | ✓ | ✓ | ✗ | ✗ |
| Add or edit a UPC mapping for a private SKU | ✓ | ✓ | ✓ | ✗ |
| Resolve an unknown UPC from the pending queue | ✓ | ✓ | ✗ | ✗ |
| Trigger the unknown-UPC pending record (by scanning) | ✓ | ✓ | ✓ | ✗ |
| Report a SKU error (sends to platform admin / business admin per scope) | ✓ | ✓ | ✓ | ✓ |

**Why Artist can create and edit private SKUs but not delete them:** edits are reversible through the audit log; deletion (even soft) is harder to inspect after the fact. Manager+ for delete is a small friction point that catches accidents without slowing the scan flow. Loosen this if Manager-availability becomes a bottleneck.

**Why edits to `business_sku_override` are Manager+:** these are settings (custom names, custom colors, reorder thresholds, hide-from-search). Mistakes propagate to every Artist's view of the inventory. Settings are Manager territory in this model.

**Why all four roles can report SKU errors:** Guest sees inventory and may notice problems Artists miss because they're not in scan-flow tunnel vision. Lowering the bar for reports is high-value for the SuperAdmin curating the shared catalog.

### Lists, Favorites, and Jobs

The Favorites list is a single canonical "what this business stocks" collection per business, modeled in DATA.md as a `list` row with `is_business_favorites = true`. Custom Lists are everything else.

| Action | Owner | Manager | Artist | Guest |
|---|:-:|:-:|:-:|:-:|
| **Custom Lists** | | | | |
| View a custom List | ✓ | ✓ | ✓ | ✓ |
| Create a custom List | ✓ | ✓ | ✓ | ✓ |
| Edit a custom List (rename, notes, items) | ✓ | ✓ | ✓ | ✓ |
| Soft-delete a custom List | ✓ | ✓ | ✓ | ✗ |
| **Favorites** | | | | |
| View the Favorites list | ✓ | ✓ | ✓ | ✓ |
| Edit the Favorites list (add/remove SKUs) | ✓ | ✓ | ✓ | ✗ |
| Rename or delete the Favorites list | ✗ | ✗ | ✗ | ✗ |
| **Jobs** | | | | |
| View Jobs | ✓ | ✓ | ✓ | ✗ |
| Create a Job | ✓ | ✓ | ✓ | ✗ |
| Edit a Job (date, client, notes, line items) | ✓ | ✓ | ✓ | ✗ |
| Soft-delete a Job | ✓ | ✓ | ✗ | ✗ |
| Set or change Job status | ✓ | ✓ | ✓ | ✗ |

**Why Guest cannot delete custom Lists:** Guests are temporary collaborators by design. A Guest deleting a List set up by the Owner is a worst-case mistake. Edit and create are fine; delete needs at least Artist trust.

**Why the Favorites list cannot be renamed or deleted by anyone:** it's a canonical concept of the product, not a user-named collection. The seed row is created at business signup and persists for the life of the business. If you ever want to repurpose the slot, that's a v2 product call.

**Why Guest doesn't see Jobs in v1:** Jobs contain client names, event dates, and project details. A Guest who's working at a competitor next month shouldn't carry that information out the door. Per-Guest customization (deferred to v2) will let an Owner grant specific Guests Job visibility.

### Local Prices

| Action | Owner | Manager | Artist | Guest |
|---|:-:|:-:|:-:|:-:|
| View Local Prices table | ✓ | ✓ | ✓ | ✗ |
| Edit Local Prices (add, change, remove rows) | ✓ | ✓ | ✓ | ✗ |

Artists who work multiple businesses often know the price codes by heart and can update them faster than a Manager could. Local Prices are reference-only data in v1 (no calculations), so the risk of an Artist edit causing harm is low.

**Why Guest doesn't see Local Prices:** competitive information. Per-Guest customization in v2 will allow exceptions.

### Membership and business management

The Manager column here applies a hard ceiling: **Manager cannot create or modify peers (other Managers) or superiors (Owners).** Manager's role-change powers stop at Artist↔Guest moves.

| Action | Owner | Manager | Artist | Guest |
|---|:-:|:-:|:-:|:-:|
| Invite a new user as Owner | ✓ | ✗ | ✗ | ✗ |
| Invite a new user as Manager | ✓ | ✗ | ✗ | ✗ |
| Invite a new user as Artist | ✓ | ✓ | ✗ | ✗ |
| Invite a new user as Guest | ✓ | ✓ | ✗ | ✗ |
| Change a user's role (Owner ↔ anything) | ✓¹ | ✗ | ✗ | ✗ |
| Change a user's role (Manager ↔ Owner / Artist / Guest) | ✓ | ✗ | ✗ | ✗ |
| Change a user's role (Artist ↔ Guest) | ✓ | ✓ | ✗ | ✗ |
| Remove an Owner from the business | ✓¹ | ✗ | ✗ | ✗ |
| Remove a Manager from the business | ✓ | ✗ | ✗ | ✗ |
| Remove an Artist or Guest from the business | ✓ | ✓ | ✗ | ✗ |
| Edit business settings (name, badge color, etc.) | ✓ | ✓ | ✗ | ✗ |
| Delete the entire business | ✗² | ✗² | ✗² | ✗² |

¹ Subject to the **last-Owner guard**: an Owner cannot be demoted or removed if they are the only remaining Owner.

² Business deletion is SuperAdmin-only in v1. Owners who want to delete a business contact platform support, who confirms the request out-of-band before triggering deletion. This is intentionally high-friction in v1.

---

## Admin actions (platform-level)

These are not in the per-business matrix because they happen outside any single business's scope. In v1, Site Admin and Super Admin share the same platform action set — the columns will diverge in v2 when subscriptions and restricted access surfaces are introduced.

| Action | Super Admin | Site Admin |
|---|:-:|:-:|
| Edit the shared SKU catalog (any field, any SKU with `owned_by_business_id IS NULL`) | ✓ | ✓ |
| Edit the `brand` table | ✓ | ✓ |
| Resolve a `pending_upc_scan` queue item | ✓ | ✓ (also any business Owner/Manager for their own business) |
| Triage `sku_error_report` entries against shared SKUs | ✓ | ✓ |
| Delete a business (after out-of-band confirmation with an Owner) | ✓ | ✓ |
| Promote a regular user to Site Admin | ✓ | ✗ |
| Demote a Site Admin back to regular user | ✓ | ✗ |
| Read any business's data in a read-only "support view" | ✓ (deferred — see Special rules) | ✓ (deferred) |
| Become a member of a business with a per-business role | ✓ (same path as any other user; requires invitation) | ✓ |

**Important:** Admin status alone does not grant access to a business's data. An admin who isn't a member of business X cannot see business X's stock levels, jobs, or members. To act inside a business, the admin must be invited like anyone else and gets the role they're invited as. The `admin_level` field adds platform powers; it does not bypass tenant scoping.

---

## Special rules

### Last-Owner guard

A business must have at least one active Owner at all times. The system rejects:

- Demoting the only remaining Owner to any other role
- Removing the only remaining Owner from the business
- The only remaining Owner removing themselves

The error message in all three cases: "This business needs at least one Owner. Promote another member to Owner before changing your role." There is no override, even for SuperAdmin — if a business has no Owner, that's a broken state. SuperAdmin business-deletion is the escape hatch for an abandoned business.

### Manager invitation ceiling

Managers can invite at the Artist or Guest role. They cannot invite Owners or other Managers. This protects against a Manager silently filling the business with peer-level Managers and gradually marginalizing the Owner.

A Manager who needs to bring on another Manager asks an Owner to send the invitation. This adds friction by design.

### Artist scan flow when UPC is unknown

When an Artist scans a UPC that doesn't resolve to any SKU visible to their business:

1. The scan is captured into a `pending_upc_scan` row (status: `pending`)
2. **No `stock_movement` is recorded yet** — the count is not changed
3. A notification fires to: all Owners of the business, all Managers of the business, all SuperAdmins
4. The Artist sees a ScanToast in `warning-soft` style indicating "Pending Manager review" — distinct from the success toasts for resolved scans
5. The Artist's scan flow is not blocked; they can keep scanning

When a Manager+ (or SuperAdmin) resolves the pending record:

- **Assign to existing SKU**: the pending record becomes status `resolved_assigned`, a `upc` row is created mapping the UPC to that SKU, and a `stock_movement` is created with the original Artist's `user_id`, the resolver's `user_id`, the original `created_at`, and a flag indicating it was a delayed apply
- **Create new SKU**: the resolver creates a new private SKU (or, if SuperAdmin, a new shared SKU), the pending record becomes status `resolved_created`, the `upc` row is created, and a `stock_movement` is created the same way
- **Reject**: the pending record becomes status `rejected` with notes; no `stock_movement` is created

The "delayed apply" detail matters because it preserves the audit trail. The Artist's name is on the scan; the Manager's name is on the resolution. Both are visible in the audit log.

### SKU error reports

Any user (including Guest) can report an error against any SKU they can see. The report flows to:

- **Shared SKUs** (`owned_by_business_id IS NULL`): notification to all SuperAdmins
- **Private SKUs** (`owned_by_business_id` is set): notification to all Owners and Managers of the owning business

The reporter is recorded in `reported_by_user_id`. The reporter can include free-text notes describing the issue.

### Per-Guest custom permissions (deferred to v2)

The product intent is for Owners to be able to override default Guest permissions per-membership: "Guest A can see Jobs; Guest B can see Local Prices; Guest C is fully read-only." This requires a `membership_permission_override` table and a Guest-management UI.

For v1, every Guest gets the same default permissions. This is documented as a known limitation. Owners who need to share Job details with a specific Guest can promote them to Artist temporarily, or wait for v2.

### Favorites cannot be renamed or deleted

The Favorites list (one row per business in the `list` table with `is_business_favorites = true`) is a system-managed seed. Its name and existence are not user-editable in v1. Adding and removing SKUs is the only Favorites-list mutation available.

If we ever want to let users rename it ("Stock Essentials" instead of "Favorites"), that's a deliberate v2 design call, not an accident.

### SuperAdmin support view (deferred)

A "read-only support view" for SuperAdmins to inspect any business's data without being a member is on the roadmap but not in v1. v1 SuperAdmins who need to debug a business's data either request an Owner invite them as a Guest temporarily or rely on direct database queries.

This is a real gap and it'll matter the first time a customer support ticket needs investigation. Build it once that becomes a real workflow, not before.

---

## Notification triggers

These are the events that fire user-facing notifications. Each lists the targets. Implementation uses Laravel's built-in notifications framework (`Notifiable` trait, `Notification::send()`, database channel for in-app, mail channel for email).

| Event | Notification targets |
|---|---|
| Unknown UPC scanned (creates `pending_upc_scan`) | All Owners + all Managers of the scanning business; all SuperAdmins |
| Pending UPC scan resolved | The Artist who originally scanned (so they know their action posted) |
| SKU error report on a shared SKU | All SuperAdmins |
| SKU error report on a private SKU | All Owners + all Managers of the owning business |
| Membership invitation accepted | The Owner or Manager who sent the invitation |
| Membership role changed | The user whose role changed |
| Last-Owner-guard violation attempted | The Owner who attempted the action (in-form error, not a notification per se) |

Notifications must respect tenant scope: an in-app notification about business X's pending UPC scan must not leak to users who are not members of business X. The notification target list is computed at fire time, not at delivery time.

---

## Implementation notes

### Spatie laravel-permission

DATA.md locks in `spatie/laravel-permission` as the package for the role × action mapping. The matrix above translates to:

- Roles: `owner`, `manager`, `staff`, `guest` (the system names) plus a non-Spatie `admin_level` enum on `user` (`site_admin` | `super_admin` | NULL)
- Permissions: dot-namespaced strings like `inventory.check_in`, `sku.create_private`, `list.delete`, `membership.invite`, `business.edit_settings`
- A seeder populates the role × permission assignments from this file at install time
- Permission checks in code use `$user->can('inventory.check_in', $business)` style with Laravel policies

Spatie's package is multi-tenant-friendly when used with the `team_id` feature renamed to fit our `business_id`.

### Last-Owner guard

This rule cannot live in Spatie's permission tables — it requires runtime row counting. Implement it in the relevant policy methods (`MembershipPolicy@changeRole`, `MembershipPolicy@remove`) and in a model observer on `Membership` for safety. Both layers throw a `LastOwnerGuardException` that the controller catches and renders as a user-facing error.

### Manager invitation ceiling

Live in policy: `MembershipPolicy@invite` checks both that the inviter has `membership.invite` AND that the role they're inviting at is allowed for their own role. Don't try to express this in the Spatie permission matrix; it's a relational rule.

### Permission-gated UI

The `PermissionGate` component in DESIGN.md disables (does not hide) gated actions. The Vue/Inertia layer needs the current user's permission set in the page props on every request — the Inertia middleware should compute and inject the permission set scoped to the current business context.

Don't trust client-side gating alone. Every server action re-checks permissions; the client gating is a UX courtesy, not a security boundary.

---

## DATA.md additions required by this file

PERMISSIONS.md introduces two new entities and two schema changes that need to be reflected in DATA.md.

**New entities:**

1. `pending_upc_scan` — queue of unknown UPC scans awaiting Manager+ resolution
2. `sku_error_report` — user-submitted reports of issues with any SKU

**Schema changes:**

3. Add `admin_level` enum (`site_admin` | `super_admin`, nullable) to the `users` table — replaced the earlier `is_super_admin` boolean
4. Replace the `favorite` table entirely:
   - Remove the `favorite` table
   - Add `is_business_favorites` boolean (default false) to the `list` table
   - Add an automatic seed at business creation: one `list` row with `is_business_favorites = true`, name `"Favorites"`, that cannot be renamed or deleted
   - Update query patterns that reference `favorite` to use `list` and `list_item` with the new flag

**Deferred to v2 (note in DATA.md's Decisions Deferred section):**

5. `membership_permission_override` table — per-Guest custom permissions
6. SuperAdmin support view — read-only access to any business's data without membership

These changes should land in DATA.md as a coordinated update, not piecemeal.

---

## Changing this file

When you add a permission, change a role's powers, or relax a special rule: update this file in the same change set as the policy code. A permission policy without a corresponding PERMISSIONS.md update is incomplete.

When the role enum or `admin_level` enum changes, get a second pair of eyes. These are load-bearing for every authorization decision in the product.
