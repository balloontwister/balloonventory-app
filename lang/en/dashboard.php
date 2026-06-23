<?php

return [
    'meta_title' => 'Dashboard',
    'heading' => 'Dashboard',

    'greeting_morning' => 'Good morning, :name',
    'greeting_afternoon' => 'Good afternoon, :name',
    'greeting_evening' => 'Good evening, :name',

    'quick_actions' => [
        'title' => 'Quick Actions',
        'scan_in' => 'Scan In',
        'scan_out' => 'Scan Out',
        'add_inventory' => 'Add Inventory',
        'reorder_list' => 'Reorder List',
        'view_inventory' => 'View Inventory',
        'no_actions' => 'You have view-only access. Ask an owner to update your role.',
    ],

    'low_stock' => [
        'title' => 'Low Stock',
        'empty' => 'Nothing needs restocking.',
        'on_hand' => 'on hand',
        'threshold' => 'threshold',
        'view_all' => 'View reorder list',
        'count' => '{0} All stocked up|{1} :count item needs restocking|[2,*] :count items need restocking',
    ],

    'kpis' => [
        'title' => 'Inventory Overview',
        'distinct_skus' => 'SKUs',
        'total_bags' => 'Total Bags',
        'bins' => 'Bins',
        'low_stock' => 'Low Stock',
    ],

    'activity' => [
        'title' => 'Recent Activity',
        'empty' => 'No activity yet. Start by scanning in some balloons.',
        'direction' => [
            'in' => 'Scanned in',
            'out' => 'Scanned out',
            'removed' => 'Removed',
            'restored' => 'Restored',
            'adjusted' => 'Adjusted',
        ],
        'bags' => ':count bag|:count bags',
        'open_bags' => ':count open bag|:count open bags',
        'show_more' => 'Show :count more',
        'show_less' => 'Show less',
    ],

    'nudges' => [
        'clear_samples' => 'You have sample inventory. Clear it when you\'re ready to track real stock.',
        'clear_samples_action' => 'Clear Samples',
        'verify_email' => 'Please verify your email address to keep your account secure.',
        'verify_email_action' => 'Resend Verification',
        'user_contact' => 'Add your phone number so team members can reach you.',
        'user_contact_action' => 'Update Profile',
        'business_contact' => 'Add your business contact details so clients can reach you.',
        'business_contact_action' => 'Update Business Info',
        'onboarding' => 'Finish setting up your account to get the most out of Balloonventory.',
        'onboarding_action' => 'Continue Setup',
        'dismiss' => 'Dismiss',
    ],

    'empty_state' => [
        'title' => 'Welcome to Balloonventory',
        'body' => 'Add your first balloon SKU or scan a bag barcode to get started.',
        'add' => 'Browse Inventory',
        'scan' => 'Scan a Bag',
    ],

    'invitations' => [
        'notice' => ':inviter invited you to join :business as :role.',
        'accept' => 'Accept',
        'decline' => 'Decline',
    ],

    'notifications' => [
        'business_access_granted' => 'You\'re now a :role at :business.',
        'invitation_accepted' => ':name accepted your invitation to join :business.',
        'member_left' => ':name left :business.',
        'member_role_changed' => 'Your role at :business is now :role.',
        'switch' => 'Switch to :business',
        'dismiss' => 'Dismiss',
    ],
];
