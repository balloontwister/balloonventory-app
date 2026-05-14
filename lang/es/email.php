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
    'from_tallie' => 'De Tallie en Balloonventory',
    'footer_account' => 'Recibes este correo porque tienes una cuenta en Balloonventory.',
    'footer_rights' => '© :year Balloonventory. Todos los derechos reservados.',

    'subjects' => [
        'verification_code' => 'Tu código de verificación de Balloonventory',
        'support_request' => '[Soporte] :subject',
        'support_reply' => 'Re: [Soporte] :subject',
    ],

    'reply_to_name' => 'Soporte de Balloonventory',
];
