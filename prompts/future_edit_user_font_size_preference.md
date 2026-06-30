# Future Project: User Font Size Preference

Self-contained spec for a fresh session. Goal: let users choose a larger or
smaller base font size in Account → Preferences, the same way they choose
light/dark/system theme.

## Why this doesn't exist yet

The codebase uses hardcoded arbitrary pixel values throughout (`text-[13px]`,
`text-[14px]`, `text-[15px]`, etc. — ~1,200 occurrences across ~82 Vue
components). CSS font-size preferences only cascade when sizes are expressed
in `rem` relative to the root `html` element. With pixel values, changing a
root size has no effect.

The root cause: the design uses `15px` body copy, which doesn't fall cleanly
on Tailwind's standard rem scale (which assumes a 16px root). Rather than
customizing the scale, arbitrary pixel classes were used for exact control.
This is a very common Tailwind antipattern.

## What needs to happen first (prerequisite)

Before a font size preference can work, the design system must be converted to
relative units. This is the real work and should be treated as its own session.

### Step 1 — Move the base font size to `html`

In `resources/css/app.css`, move `font-size: 15px` from `body` to `html`:

```css
html {
    font-size: 15px; /* rem base — was on body */
}
body {
    @apply bg-background text-ink-primary font-sans;
    line-height: 1.5;
}
```

Now `1rem = 15px` for all relative calculations.

### Step 2 — Extend the Tailwind config with named size steps

In `tailwind.config.js`, add custom font size tokens that map to the design's
actual pixel values on the 15px base:

```js
fontSize: {
    'xs':   ['0.733rem', { lineHeight: '1rem'   }],  // ~11px
    'sm':   ['0.867rem', { lineHeight: '1.25rem'}],  // ~13px
    'body': ['0.933rem', { lineHeight: '1.5rem' }],  // ~14px
    'base': ['1rem',     { lineHeight: '1.5rem' }],  // 15px
    'md':   ['1.133rem', { lineHeight: '1.5rem' }],  // ~17px
    'lg':   ['1.2rem',   { lineHeight: '1.75rem'}],  // 18px
    'xl':   ['1.467rem', { lineHeight: '2rem'   }],  // 22px
    '2xl':  ['1.733rem', { lineHeight: '2.25rem'}],  // 26px
    // keep arbitrary values for one-off display sizes (40px, 56px, etc.)
},
```

These names should be validated against the existing pixel values in the
codebase before committing.

### Step 3 — Convert all arbitrary pixel classes

Find and replace the ~1,200 occurrences across `resources/js/**/*.vue`:

| Current class    | Replace with    |
|------------------|-----------------|
| `text-[11px]`    | `text-xs`       |
| `text-[13px]`    | `text-sm`       |
| `text-[14px]`    | `text-body`     |
| `text-[15px]`    | `text-base`     |
| `text-[17px]`    | `text-md`       |
| `text-[18px]`    | `text-lg`       |
| `text-[22px]`    | `text-xl`       |
| `text-[26px]`    | `text-2xl`      |

Replacements can be done with `sed` or VS Code find-and-replace with regex.
Do a careful visual review after — even a 1px slip is noticeable in a dense
UI. Mobile sizes and the scan page are the most sensitive.

---

## The font size preference (the actual feature)

Once the prerequisite is done, this is straightforward — essentially the same
pattern as the light/dark/system theme added on 2026-06-28.

### Database

```php
// Add to the theme migration or create a new one
$table->enum('font_size', ['small', 'default', 'large'])->default('default')->after('theme');
```

### CSS

In `resources/css/app.css`, add size overrides scoped to a class on `html`:

```css
html.font-small { font-size: 13px; }
html              { font-size: 15px; } /* default, already set */
html.font-large  { font-size: 17px; }
```

Because all component sizes will now be in `rem`, these three rules cascade
to the entire UI with no per-component changes needed.

### Backend

- Add `font_size` to `User::$fillable`
- Add `'font_size' => $user->font_size ?? 'default'` to `SettingsController::index()` preferences props
- Add `'font_size' => ['required', 'string', 'in:small,default,large']` to `updatePreferences()` validation

### Blade (`resources/views/app.blade.php`)

Apply the class server-side on page load (same pattern as `dark`):

```php
@php
    $themeClass = '';
    if (auth()->check()) {
        $theme = auth()->user()->theme ?? 'system';
        $fontSize = auth()->user()->font_size ?? 'default';
        if ($theme === 'dark') $themeClass .= ' dark';
        if ($fontSize !== 'default') $themeClass .= ' font-' . $fontSize;
    }
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ trim($themeClass) }}">
```

### Vue (`Settings/Index.vue`)

Add a three-button segmented toggle (Small · Default · Large) next to or
below the theme toggle, and update the `onSuccess` callback:

```js
const applyFontSize = (size) => {
    document.documentElement.classList.remove('font-small', 'font-large');
    if (size !== 'default') document.documentElement.classList.add(`font-${size}`);
};

const submit = () => form.patch(route('settings.preferences.update'), {
    onSuccess: () => {
        applyTheme(form.theme);
        applyFontSize(form.font_size);
    },
});
```

### Tests

Mirror `UpdatePreferencesThemeTest` — happy paths for small/default/large,
rejection of invalid values, unauthenticated redirect.

---

## Effort estimate

| Step                              | Effort   |
|-----------------------------------|----------|
| Prerequisite: CSS/Tailwind refactor | 4–6 hrs |
| Font size preference feature        | 1–2 hrs |
| **Total**                           | **5–8 hrs** |

The prerequisite is the bulk of the work. The feature itself is small once the
design system uses relative units.
