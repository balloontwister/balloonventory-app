<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Legal document version & dates
    |--------------------------------------------------------------------------
    |
    | 'terms_version' is the value recorded against a user when they accept. The
    | EnsureTermsAccepted middleware compares it to each user's stored version on
    | every request.
    |
    | ⚠️  CHANGING 'terms_version' FORCES EVERY USER TO RE-ACCEPT.
    |
    |     The moment this value differs from a user's stored terms_version, they
    |     are HARD-REDIRECTED to the /accept-terms interstitial on their next
    |     request and cannot use the app until they tick the box and accept again.
    |     This applies to ALL users at once — a material, app-wide interruption.
    |
    |     So:
    |       • Material change (new data uses, billing terms, liability, etc.)
    |         → bump this version. Re-consent is legally required and intended.
    |       • Typo / formatting / minor wording fix
    |         → DO NOT bump. Just edit the Markdown in resources/legal/**; it goes
    |           live on the next deploy with NObody re-prompted.
    |
    |     Editing the prose alone never triggers re-acceptance — only changing
    |     this value does. Set it to the new effective date when you bump.
    |
    | 'effective_date' is shown as "Last updated" on the policy pages (display
    | only; it does not affect the re-acceptance gate).
    |
    */

    'terms_version' => env('LEGAL_TERMS_VERSION', '2026-07-01'),

    'effective_date' => env('LEGAL_EFFECTIVE_DATE', '2026-07-01'),

    /*
    |--------------------------------------------------------------------------
    | Company & contact (Phase 0 — confirmed 2026-06-30)
    |--------------------------------------------------------------------------
    |
    | Operating entity: ManagerSal LLC. Re-audit (and surface it in the prose +
    | match the Stripe account) if a dedicated Balloonventory entity is formed.
    |
    | 'contact_email' is the published support/privacy address (also hardcoded in
    | resources/legal/en/privacy.md). It must remain a monitored inbox — Resend is
    | send-only, so inbound is routed elsewhere (e.g. Cloudflare Email Routing).
    |
    | Policy prose is generator-based; a lawyer review is advisable before a full
    | public (non-beta) launch.
    |
    */

    'company' => env('LEGAL_COMPANY', 'ManagerSal LLC'),

    'contact_email' => env('LEGAL_CONTACT_EMAIL', 'support@balloonventory.com'),

];
