# Capture User & Business Contact Info — Build Instructions

Paste this into a fresh Claude Code session (Sonnet). It is a complete spec.

> **Note on the country list:** the canonical country source files have **already been
> created** in the repo — do **not** regenerate a list of country names. Use the existing
> `config/countries.php` (ISO 3166-1 alpha-2 codes) and `App\Support\Countries` (resolves
> localized names at runtime via the `intl` extension). They're verified working (249 codes;
> names localize in en + es). Just consume them.

---

## Context / intent
Balloonventory collects almost no contact detail today. We want to capture **two parallel sets**
of contact info:

- **Personal** (on the `users` table) — travels with the person even if they change businesses/jobs.
- **Business** (on the `businesses` table) — the shop's details.

They're often identical but not always, so they are stored and edited **separately**. All of it
is **private** — visible only to the user and Balloonventory support, never published, shared,
sold, or used to spam. Every edit form must reassure the user of that.

**Country is a standardized dropdown (not free text) on purpose:** storing a canonical country
code lets us count users/businesses per country — input for future translation-language decisions
and partnerships with balloon sellers in those regions. Phone numbers stay free-text (precision
there doesn't matter; we also collect email, website, and address).

The admin **User detail** page (`Pages/SuperAdmin/Users/Show.vue`) already has a "Contact" card
rendering `CONTACT_FIELDS = ['phone','website','city','country']` with "Not collected yet"
placeholders — this work fills those in for real and extends them.

## Decisions (already made — implement as-is)
- Two separate field sets (user + business), edited in two places: **Profile** page (personal)
  and **Settings → Businesses** (business).
- **Country = dropdown storing ISO 3166-1 alpha-2 code** (`US`, `CA`, `GB`, …). The code list in
  `config/countries.php` is the single source of truth; names are resolved by `App\Support\Countries`.
- **Phone = free-text** (`nullable|string|max:32`, no format enforcement).
- Website fields accept input without a scheme; normalize by prepending `https://` before validation.
- When adding fields to a model's `$fillable`, add **only** the new contact columns listed in this
  plan, and leave all other existing model attributes unchanged.
- Country names are localized automatically by `intl` (en + es), so no manual country-name
  translation is needed.

## Field set
**`users`** (all nullable): `phone`, `address_line1`, `address_line2`, `city`, `state_region`,
`postal_code`, `country` (2-char code), `website_url`, `website_url_2`

**`businesses`** (all nullable): the same nine, plus `contact_email` (public business email; users
already have `email`).

---

## Implementation

### 1. Country source — ALREADY DONE (just use it)
- `config/countries.php` — array of ISO 3166-1 alpha-2 codes (single source of truth).
- `App\Support\Countries`:
  - `all(?string $locale = null): array` → `code => localized name`, sorted by name.
  - `name(?string $code, ?string $locale = null): ?string` → display name for one code.
  - `isValid(string $code): bool` → membership check, for validation.
- Do not modify these unless a code is genuinely missing.

### 2. Migrations (run `database-schema` on both tables first)
- `add_contact_fields_to_users_table` — add the nine nullable columns: `phone` string(32),
  `address_line1/2`/`city`/`state_region` string(255), `postal_code` string(20), `country` char(2),
  `website_url`/`website_url_2` string(255).
- `add_contact_fields_to_businesses_table` — same nine + `contact_email` (string, nullable).

### 3. Models
- `App\Models\User`: add the nine new columns to `$fillable` (only those; leave existing attributes
  as-is). Confirm none are in `$hidden` so they serialize into the shared `auth.user`.
- `App\Models\Business`: add the ten new columns to `$fillable`.

### 4. Validation (reuse the normalize-then-validate pattern from `ProfileUpdateRequest`)
- **`ProfileUpdateRequest`** — add rules: `phone` `nullable|string|max:32`; address/city/region
  `nullable|string|max:255`; `postal_code` `nullable|string|max:20`; `country`
  `['nullable','string','size:2', Rule::in(config('countries'))]`; `website_url`/`website_url_2`
  `nullable|url|max:255`. In `prepareForValidation()`, for each website field: if non-empty and
  missing a scheme, prepend `https://`.
- **Business** — extend `SettingsController@updateBusiness`'s `$request->validate([...])` with the
  same rules + `contact_email` `nullable|email|max:255`. Keep the existing
  `Gate::authorize('business.edit_settings', $business)` and name/slug handling. (Or extract an
  `UpdateBusinessRequest` mirroring `ProfileUpdateRequest` — preferred for symmetry.)

### 5. Reusable `CountrySelect.vue` — `resources/js/Components/CountrySelect.vue`
A native `<select>` styled like the existing `TextInput`/the feedback-modal selects, with a leading
"Select a country…" empty option (maps to `null`). Props: `modelValue`, `countries` (the code→name
map passed from the controller), `id`, `disabled`. Emits `update:modelValue`. Used by both edit forms.

### 6. Profile edit form — `resources/js/Pages/Profile/Partials/UpdateProfileInformationForm.vue`
- Add the nine fields to `useForm`, prefilled from `usePage().props.auth.user`
  (e.g. `country: user.country ?? ''`).
- Render a "Contact details" subsection below name/email reusing `InputLabel` / `TextInput` /
  `InputError`; use `<CountrySelect>` for country. Group sensibly (line1, line2, then
  city / region / postal on a row, country, then the two website fields).
- Add the privacy note (copy below) under the section heading.
- `auth.user` already carries the values (the whole model is shared via `HandleInertiaRequests`),
  so no controller change is needed for prefill — but the form needs the country list: have
  `ProfileController@edit` pass `'countries' => \App\Support\Countries::all()`.

### 7. Business settings — `resources/js/Pages/Settings/Businesses.vue`
- Add a "Business contact" `<section>` with the same fields + `contact_email`, prefilled from the
  `business` prop; use `<CountrySelect>` for country.
- Extend `SettingsController@businesses`: add the contact fields to the `business` payload (it
  currently returns only id/name/slug/logoUrl) and pass `'countries' => \App\Support\Countries::all()`.
- Gate the inputs/submit on the existing `canEditSettings` (mirror the name form's `:disabled` +
  no-permission message).
- Same privacy note.

### 8. Admin display — `AdminUserController@show` + `Pages/SuperAdmin/Users/Show.vue`
- Controller: include the new user contact fields in the `user` payload and the business contact
  fields per business; resolve country code → name with `Countries::name($code)` before passing
  (so admins see "United States", not "US").
- `Show.vue`: replace the placeholder `CONTACT_FIELDS` list with the real values — show the value,
  or the existing "Not collected yet" string when null. Surface the same for the business.
  Read-only (admin view).

### 9. i18n (en + es, kept in sync by hand)
- `lang/{en,es}/profile.php`: `contact.heading`, `contact.subheading`, field labels (`phone`,
  `address_line1`, `address_line2`, `city`, `state_region`, `postal_code`, `country`, `website_url`,
  `website_url_2`), `contact.country_placeholder` ("Select a country…"), `contact.privacy_note`,
  `contact.saved`.
- `lang/{en,es}/settings.php`: a `businesses.contact.*` block — same labels + `contact_email` +
  `country_placeholder` + `privacy_note`.
- `lang/{en,es}/super_admin.php` `user_detail.contact.*`: add the missing labels (`address_line1`,
  `address_line2`, `state_region`, `postal_code`, `website_url_2`, `contact_email`) alongside the
  existing phone/website/city/country.
- Country **names** themselves are localized by `intl` (no translation needed); only the UI labels
  and placeholder need en/es entries.

### Privacy note copy (use in both edit forms)
> **en:** "Your contact details are private. They're only visible to you and the Balloonventory
> team for account support — we never publish, share, or sell them, and we won't use them to send
> you anything you didn't ask for."
> **es:** (translate to match.)

Render as muted helper text under the section heading; optionally also a small `(i)` info icon.
Inline is fine; a tiny `PrivacyNote.vue` is optional.

---

## Tests (PHPUnit; `php artisan make:test`)
- **Profile:** updating saves all nine fields; website normalization (`example.com` →
  `https://example.com`); invalid URL rejected; invalid country code rejected, valid code accepted;
  existing name/email behavior unchanged.
- **Business settings:** `updateBusiness` saves the ten fields; rejected without
  `business.edit_settings`; `contact_email` and country-code validation.
- **Admin show:** the new user + business contact fields appear in the `Show` props, with country
  shown as the resolved name.
- (`Countries` itself is already verified; a tiny test asserting `isValid('US')` and
  `name('US','en') === 'United States'` is fine but optional.)
- Run the full suite afterward (`php artisan test --compact`, ~599 tests — confirm the count from
  `project_status.md`).

## Verification & ship
1. `vendor/bin/pint --dirty --format agent`; `npm run build`.
2. Manual: edit personal contact on `/profile` and business contact on `/settings/businesses`;
   confirm both persist independently, the country dropdown works (and shows Spanish names when the
   UI is in Spanish), and the privacy note shows; confirm the admin user-detail page reflects both
   (country as a name).
3. Sanity-check the payoff: `select country, count(*) from users group by country` returns clean,
   countable codes.
4. Branch → PR → merge → deploy (never push to `main` directly). `git add` only the feature files —
   exclude the untracked `index.html`. Deploy with
   `ssh myvps "cd /home/balloonventory/balloonventory-app && bash bin/deploy.sh"` (runs the
   migration on prod).
5. Update `project_status.md` + `MEMORY.md`.

## Conventions reminder for the session
Read `CLAUDE.md` + the project memory files first; use Boost `search-docs` before framework code
and `database-schema` before the migrations; follow Laravel 12 structure (middleware/config in
`bootstrap/app.php`, casts via a `casts()` method); keep en/es in sync; every change needs test
coverage.
