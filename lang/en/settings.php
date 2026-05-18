<?php

return [
    'meta_title' => 'Settings',
    'heading' => 'Settings',

    'preferences' => [
        'heading' => 'Preferences',
        'subheading' => 'Choose the language and timezone Balloonventory uses for you.',
        'language_label' => 'Language',
        'more_languages_coming' => 'More languages coming soon.',
        'timezone_label' => 'Timezone',
        'timezone_unset' => 'Not set',
        'timezone_help' => 'Dates and times in the app are shown in this timezone.',
        'submit' => 'Save preferences',
        'saved' => 'Preferences saved.',
    ],

    'businesses' => [
        'meta_title' => 'Manage Business',
        'heading' => 'Manage Business',
        'name' => [
            'heading' => 'Business name',
            'subheading' => 'This name appears throughout the app and in shared views.',
            'label' => 'Name',
            'submit' => 'Save changes',
            'saved' => 'Name updated.',
            'no_permission' => 'Only the business owner can change the business name.',
        ],
        'logo' => [
            'heading' => 'Business logo',
            'subheading' => 'Upload a logo to personalise your business. Shown in the nav and on shared views.',
            'help' => 'PNG, JPG, or WebP. Max 5 MB. Square images work best.',
            'submit' => 'Save logo',
            'saved' => 'Logo updated.',
            'no_permission' => 'Only the business owner can change the logo.',
            'preview_alt' => 'Business logo preview',
        ],
        'subscription' => [
            'heading' => 'Subscription',
            'subheading' => 'Manage your Balloonventory plan and billing.',
            'status_free_beta' => 'Free beta',
            'footnote' => 'Billing and plan management coming soon.',
        ],
    ],
];
