<?php

return [
    'meta_title' => 'Profile',
    'heading' => 'Profile',

    'avatar' => [
        'heading' => 'Profile picture',
        'subheading' => 'Upload a photo so your teammates can recognise you.',
        'help' => 'PNG, JPG, or WebP. Max 5 MB. Square images work best.',
        'submit' => 'Save picture',
        'saved' => 'Picture updated.',
        'preview_alt' => 'Your profile picture',
    ],

    'information' => [
        'heading' => 'Profile information',
        'subheading' => 'Update your name and email address.',
        'name_label' => 'Name',
        'email_label' => 'Email',
        'submit' => 'Save changes',
        'saved' => 'Profile updated.',
    ],

    'contact' => [
        'heading' => 'Contact details',
        'subheading' => 'Optional. Only visible to you and the Balloonventory team.',
        'privacy_note' => 'Your contact details are private. They\'re only visible to you and the Balloonventory team for account support — we never publish, share, or sell them, and we won\'t use them to send you anything you didn\'t ask for.',
        'phone' => 'Phone',
        'address_line1' => 'Address line 1',
        'address_line2' => 'Address line 2',
        'city' => 'City',
        'state_region' => 'State / Region',
        'postal_code' => 'Postal code',
        'country' => 'Country',
        'country_placeholder' => 'Select a country…',
        'website_url' => 'Website',
        'website_url_2' => 'Website 2',
        'saved' => 'Contact details saved.',
    ],

    'password' => [
        'heading' => 'Update password',
        'subheading' => 'Use a long, random password to keep your account secure.',
        'current_password_label' => 'Current password',
        'new_password_label' => 'New password',
        'confirm_password_label' => 'Confirm new password',
        'submit' => 'Update password',
        'saved' => 'Password updated.',
    ],

    'delete' => [
        'heading' => 'Delete account',
        'subheading' => 'Permanently delete your account and all associated data. This cannot be undone.',
        'open_button' => 'Delete account',
        'confirm_heading' => 'Are you sure?',
        'confirm_body' => 'This will permanently delete your account and all your data. Enter your password to confirm.',
        'password_label' => 'Password',
        'password_placeholder' => 'Your password',
        'cancel' => 'Cancel',
        'submit' => 'Delete my account',
        'handoff_heading' => 'Businesses you own',
        'handoff_intro' => 'You are the only owner of the business(es) below. You can nominate a member to take over — they will be invited to accept ownership. Until someone accepts, the business is frozen, and it stays frozen if no one is nominated or the invitation is declined. A frozen business can be restored later by contacting support.',
        'handoff_assign_label' => 'Nominate someone to take over :business',
        'handoff_freeze_option' => 'No one — freeze this business',
        'handoff_no_members' => ':business has no other members, so it will be frozen when you delete your account.',
    ],
];
