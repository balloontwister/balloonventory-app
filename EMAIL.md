# EMAIL.md — Balloonventory

The email system design for Balloonventory. This file is the source of truth for how transactional emails are structured, authored, stored, and triggered. It complements AGENTS.md (infrastructure), DATA.md (schema), and PERMISSIONS.md (who can edit templates).

If anything in code conflicts with this file, this file wins. Update this file before adding a new email type or changing how templates are rendered.

---

## Design philosophy

Balloonventory sends a small number of high-trust transactional emails. Every email comes from **Tallie**, the friendly persona of the product (`tallie@balloonventory.com`, display name "Tallie at Balloonventory"). Tallie is warm but professional — she congratulates, confirms, and guides. She never markets, never uses hollow filler phrases, and never sends anything the user didn't expect.

The guiding tension: copy needs to be editable by a non-developer (the platform owner, without a deploy), but rendering needs to stay consistent and unbreakable. The hybrid approach below resolves this by separating what is editable from what is structural.

---

## Architecture: hybrid template model

### What lives in the database (editable via super-admin)

| Field | Purpose |
|---|---|
| Subject line | Shown in the recipient's inbox |
| Body copy (HTML) | The variable main content of the email — paragraphs, a CTA button |
| Body copy (plain text) | Plain-text fallback for email clients that don't render HTML |
| `is_active` flag | Controls whether the trigger fires. `false` = template is drafted but not sending |

These are the fields the super-admin editor can change without touching code or deploying.

### What lives in Blade (structural, code-only)

| Element | Why it's not editable |
|---|---|
| Email outer wrapper | Consistent max-width, background, padding |
| Tallie header (logo + from-name) | Brand consistency across all emails |
| Footer (unsubscribe link, legal copy, address) | Legal requirement; must not be accidentally deleted |
| Button and link styles | Rendering safety across email clients |
| Mobile responsive CSS | Technical; cannot be safely left to a text editor |

A single Blade layout file (`resources/views/mail/layout.blade.php`) provides this chrome. It accepts `$subject`, `$bodyHtml`, and `$bodyText` as variables from the Mailable and renders them inside the consistent wrapper.

### Variable interpolation

The body copy supports simple `{{variable}}` placeholders that are substituted at send time. No PHP, no Blade syntax — plain double-brace tokens only. This keeps the editing surface safe (no injection risk) and readable by a non-developer in the admin UI.

Each template's available variables are documented in the template registry below. The `TemplatedMailable` class substitutes them using a simple `str_replace()` pass before rendering.

---

## The `email_templates` table

One row per named template. See DATA.md for the full schema entry.

| Column | Type | Notes |
|---|---|---|
| `id` | uuid, pk | |
| `key` | text, unique | Machine name: `welcome`, `subscription_upgrade`. Never changes once set. |
| `label` | text | Human-readable name shown in the super-admin UI |
| `trigger_description` | text | Plain English description of when this fires, shown in the UI |
| `subject` | text | Email subject line. Supports `{{variables}}`. |
| `body_html` | longtext | HTML body fragment — the content between the chrome wrapper elements. Supports `{{variables}}`. |
| `body_text` | text | Plain-text fallback. Should mirror `body_html` without HTML. Supports `{{variables}}`. |
| `is_active` | boolean, default false | When `false`, the trigger is a no-op. Allows drafting before activating. |
| `last_edited_by_user_id` | uuid, fk → user.id, nullable | Audit trail for edits |
| `created_at`, `updated_at` | timestamps | |

No `deleted_at` — templates are never soft-deleted. Deactivate via `is_active = false`.

The table is seeded at install time with a row for every known template key, all with `is_active = false` and empty body fields. The super-admin UI detects empty-body templates and shows a "not yet written" state distinct from a deliberately blank template.

---

## The `TemplatedMailable` class

`app/Mail/TemplatedMailable.php` — a single reusable Mailable that handles all database-driven templates.

**Responsibilities:**
1. Load the `email_templates` row by `key`. If the row doesn't exist or `is_active = false`, silently return without sending (log a warning).
2. Substitute `{{variable}}` tokens in `subject`, `body_html`, and `body_text` using the `$variables` array passed at call time.
3. Render via `resources/views/mail/layout.blade.php`, passing the interpolated HTML and plain-text body.
4. Set the subject from the interpolated template subject.

**Signature:**
```php
new TemplatedMailable(key: 'welcome', variables: ['user_name' => $user->name])
```

**Sending:**
```php
Mail::to($user->email)->send(new TemplatedMailable('welcome', ['user_name' => $user->name]));
```

Always wrap in try/catch. A missing or inactive template should never crash the calling flow — log and continue.

---

## Template registry

All templates must be documented here before the code is written. If a template isn't in this list, it doesn't get built.

### `welcome`

| Property | Value |
|---|---|
| Label | Welcome to Balloonventory |
| Trigger | After a new user verifies their email address |
| Status | Not yet active — body not yet written |
| From | tallie@balloonventory.com ("Tallie at Balloonventory") |

**Available variables:**

| Token | Resolves to |
|---|---|
| `{{user_name}}` | The user's display name |
| `{{app_url}}` | The application URL (e.g., `https://app.balloonventory.com`) |

**Trigger location:** `VerifyEmailController` (or the event handler on `Verified`), after `email_verified_at` is set. Only fires once per user.

---

### `subscription_upgrade`

| Property | Value |
|---|---|
| Label | Subscription Upgrade Confirmation |
| Trigger | When a user upgrades their subscription plan |
| Status | Deferred — will be activated when subscription tiers are implemented |
| From | tallie@balloonventory.com ("Tallie at Balloonventory") |

**Available variables:**

| Token | Resolves to |
|---|---|
| `{{user_name}}` | The user's display name |
| `{{plan_name}}` | The name of the plan they upgraded to |
| `{{app_url}}` | The application URL |

**Trigger location:** TBD — subscription management controller.

---

## Queuing policy

Emails are split into two categories based on whether the user is waiting on them.

| Category | Queued? | Examples |
|---|---|---|
| **Time-critical** | No — sent synchronously | Verification code, password reset |
| **Non-urgent** | Yes — dispatched to the queue | Welcome email, subscription confirmation, future newsletters |

**Why queue non-urgent emails:**
- The web request completes instantly; the user is never waiting on Resend's API
- If Resend has a transient blip, the job retries automatically (3 attempts, 60-second backoff) instead of being silently lost
- "Batch sending every few minutes" is a marketing-email concept; here it just means the job runs within ~60 seconds of being dispatched, which is fine for a welcome email

**How it works on this server:**
The database queue driver is used (Redis is unavailable on the cPanel host). A Laravel scheduler entry runs `queue:work --stop-when-empty` every minute via the existing cron entry. The worker drains all pending jobs and exits cleanly. `withoutOverlapping()` prevents a second worker from starting if a batch runs long.

**`TemplatedMailable` is always queued** — it implements `ShouldQueue` with 3 retries and a 60-second backoff. Calling `Mail::to()->send(new TemplatedMailable(...))` dispatches to the queue automatically.

**Standalone Mailables (`EmailVerificationCode`)** do NOT implement `ShouldQueue` and send synchronously. Never add `ShouldQueue` to time-critical Mailables.

## Sending guidelines

- **Always wrap Mail::send in try/catch.** A failed email must never throw to the user or block a flow. Log the error and continue.
- **Check `is_active` before sending.** `TemplatedMailable` handles this internally, but callers should not assume a template is active.
- **One trigger, one template.** Don't reuse a template key for multiple triggers. Create a new row if a new trigger needs a similar-but-distinct email.
- **Never pass user-provided strings as variable values without sanitizing.** Variable substitution uses `str_replace()`, not `eval()` or Blade, so there's no injection risk — but HTML in values can break rendering. Strip HTML from values that come from user input before passing them.
- **Plain-text body is required, not optional.** Some email clients (and spam filters) penalize HTML-only email. The super-admin UI must require both fields before allowing `is_active = true`.

---

## The Blade chrome layout

`resources/views/mail/layout.blade.php` — the structural wrapper all emails share.

Contents (in order):
1. HTML email boilerplate (`<!DOCTYPE>`, meta charset, viewport, MSO conditional comments for Outlook)
2. Inline CSS reset + responsive styles
3. Max-width centered container (600px)
4. **Header**: Balloonventory logo + "From Tallie at Balloonventory" eyebrow
5. **Body region**: `{!! $bodyHtml !!}` — the database-driven content renders here
6. **Footer**: "You're receiving this because you have an account at Balloonventory." + address + unsubscribe link placeholder
7. Plain-text `@text` section: `{{ $bodyText }}`

The layout is built once to render correctly across Gmail, Apple Mail, Outlook 2016+, and mobile clients. Changes to the layout require a deploy and should be rare. Layout changes must be tested against the current email client compatibility matrix (see Testing below).

---

## Super-admin editor (implementation notes)

The Email Templates section in `/super-admin` will eventually render a real editor. The planned UX:

1. Each template card shows: label, trigger description, `is_active` status badge, last edited timestamp
2. Clicking "Edit template" opens a form (full page or slide-over) with:
   - **Subject** field (text input, character counter)
   - **Body** field (a simple rich-text editor — **not** a full WYSIWYG; a minimal toolbar with Bold, Italic, Link, and a CTA button inserter is enough. Markdown-to-HTML conversion is an acceptable alternative.)
   - **Plain text** field (textarea, auto-populated from body on initial edit, manually editable)
   - **Variable reference panel** — a read-only sidebar listing available `{{tokens}}` for this template with descriptions. Clicking a token inserts it at cursor.
   - **Preview** button — sends a real email to the logged-in super-admin's address with sample variable values (defined in the template registry above)
   - **Save** (saves without activating) and **Save & Activate** (saves and sets `is_active = true`) actions
3. `is_active = false` templates show a "Not yet active" badge in the card. `is_active = true` shows a "Live" badge in success green.
4. Deactivating a live template requires a confirmation: "This will stop sending the [Template Name] email immediately."

**Validation before activation:**
- Subject must not be empty
- `body_html` must not be empty
- `body_text` must not be empty
- All `{{tokens}}` referenced in subject and body must be in the template's documented variable list (warn on unknown tokens, block on activation)

---

## Testing

Before marking any template as ready to activate:

1. **Preview send** from the super-admin UI — confirms the Resend API accepts and delivers the email
2. **Variable substitution check** — verify all tokens resolve correctly with real user data
3. **Plain-text check** — open the received email in a plain-text client or Gmail's "Show original" view
4. **Mobile check** — open on iPhone Mail and Android Gmail (minimum)
5. **Outlook check** — if Outlook users are expected, test in Litmus or Email on Acid before activating

---

## Adding a new email type

1. Add a row to this file's **Template registry** section with key, label, trigger, status, and available variables
2. Add the corresponding entity notes to DATA.md (the `email_templates` table schema is already there; document any new trigger location)
3. Run the seeder or write a migration to insert the new row with `is_active = false`
4. Wire the trigger in the appropriate controller or event listener, calling `TemplatedMailable`
5. Write the body copy in the super-admin editor, preview, test, then activate

Never create a new Mailable class for database-driven templates. `TemplatedMailable` handles all of them. New standalone Mailable classes are reserved for system emails that are never user-editable (e.g., a future developer error alert or a platform-level abuse notification).

---

## Current standalone Mailables (not database-driven)

These are developer-owned, not editable via the super-admin UI. They are Blade-only and use the same chrome layout.

| Class | View | Trigger | Editable via UI |
|---|---|---|---|
| `App\Mail\EmailVerificationCode` | `mail.verification-code` | Registration and resend on verify page | No — subject to frequent UX iteration; keeping in code for now |

---

## Changing this file

When you add a template, change a variable set, or alter the rendering pipeline, update this file in the same change set. A template trigger wired in code with no EMAIL.md entry is incomplete. A super-admin editor that accepts variables not listed here is a bug.
