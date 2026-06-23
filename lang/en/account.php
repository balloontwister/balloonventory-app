<?php

return [
    'meta_title' => 'Account',
    'heading' => 'Account',

    'frozen_banner' => [
        'title' => 'Your account is limited',
        'body' => 'Access to the app is paused for now. You can still update your profile and preferences here. Contact support to restore full access.',
    ],

    'rows' => [
        'profile' => [
            'label' => 'Profile',
            'subtext' => 'Name, email, password, avatar',
        ],
        'business' => [
            'label' => 'My Business',
            'subtext_fallback' => 'Manage business name and logo',
        ],
        'preferences' => [
            'label' => 'Preferences',
            'subtext' => 'Language and timezone',
        ],
        'support' => [
            'label' => 'Help & Support',
            'subtext' => 'Contact the Balloonventory team',
        ],
        'super_admin' => [
            'label' => 'Super Admin',
            'subtext' => 'Site-wide administration',
        ],
        'log_out' => [
            'label' => 'Log out',
        ],
    ],

    'other_businesses' => [
        'heading' => 'Other Businesses',
        'switch' => 'Switch',
    ],
];
