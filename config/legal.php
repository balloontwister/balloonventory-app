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
    | Company & contact (Phase 0 — confirm before real prose ships)
    |--------------------------------------------------------------------------
    |
    | TODO(Phase 0): 'company' must be the exact registered legal entity name +
    | state, and must match the Stripe account later. If the LLC does not yet
    | formally exist, that gates the prose (not the scaffolding).
    |
    | TODO(Phase 0): 'contact_email' must be a real, monitored inbox — Resend is
    | send-only, so a published address that nobody receives is a compliance
    | liability (Privacy Policy promises a ~30-day response).
    |
    */

    'company' => env('LEGAL_COMPANY', 'Twisted Balloon LLC'),

    'contact_email' => env('LEGAL_CONTACT_EMAIL', 'privacy@balloonventory.com'),

];
