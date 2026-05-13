<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Email chrome + subject lines
    |--------------------------------------------------------------------------
    |
    | Phase 1: these are loaded by the active request locale. Phase 4 will
    | switch to recipient-locale resolution when email_templates becomes
    | keyed (key, locale).
    |
    */

    'brand' => 'Balloonventory',
    'from_tallie' => 'From Tallie at Balloonventory',
    'footer_account' => "You're receiving this because you have an account at Balloonventory.",
    'footer_rights' => '© :year Balloonventory. All rights reserved.',

    'subjects' => [
        'verification_code' => 'Your Balloonventory verification code',
        'support_request' => '[Support] :subject',
        'support_reply' => 'Re: [Support] :subject',
    ],

    'reply_to_name' => 'Balloonventory Support',
];
