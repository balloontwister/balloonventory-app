<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Legal document version & dates
    |--------------------------------------------------------------------------
    |
    | Bump 'terms_version' whenever the Terms or Privacy Policy materially
    | change — it is the value recorded against a user when they accept (Phase 3),
    | and a change is what triggers re-acceptance. 'effective_date' is shown as
    | "Last updated" on the policy pages.
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
