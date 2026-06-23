<?php

return [
    'meta_title' => 'Scan',
    'heading' => 'Scan',

    // Mode toggle
    'mode_add' => 'Add',
    'mode_remove' => 'Remove',

    // Quantity / open bag
    'qty_label' => 'Quantity',
    'qty_preset_3' => '3',
    'qty_preset_5' => '5',
    'qty_preset_10' => '10',
    'open_bag_label' => 'Open bag',

    // Context hints — pluralized via $tChoice. :count is the quantity.
    'adding_full_context' => '{1} Adding :count bag to inventory.|[2,*] Adding :count bags to inventory.',
    'adding_open_context' => '{1} Adding :count open bag to inventory.|[2,*] Adding :count open bags to inventory.',
    'removing_full_context' => '{1} Removing :count bag from inventory.|[2,*] Removing :count bags from inventory.',
    'removing_open_context' => '{1} Removing :count open bag from inventory.|[2,*] Removing :count open bags from inventory.',

    // Scan field
    'scan_placeholder' => 'Scan a barcode…',
    'ready_to_scan' => 'Ready to scan',
    'looking_up' => 'Looking up…',
    'scan_error' => 'Scan error',
    'scanning' => 'Scanning…',
    'duplicate' => 'Already scanned',
    'duplicate_hint' => 'You just scanned that barcode — change it to record again.',
    'checking_in_to' => 'Checking in to :business',
    'checking_out_to' => 'Checking out from :business',
    'checking_out_for' => 'Checking out for :job · :business',

    // Camera
    'camera_button' => 'Scan with camera',
    'camera_start' => 'Start camera',
    'camera_stop' => 'Stop camera',
    'camera_unsupported' => 'Camera scanning is not supported on this device.',
    'camera_error' => 'Could not access camera.',
    'camera_permission_denied' => 'Camera access was denied. Enable it in your browser settings and try again.',
    'camera_not_found' => 'No camera was found on this device.',
    'camera_in_use' => 'The camera is in use by another app. Close it and try again.',
    'camera_hint' => 'Center the barcode in the box',
    'camera_retry' => 'Retry',
    'captured' => 'Got it!',

    // General actions
    'close' => 'Close',

    // Confirm match (ambiguous / low-confidence)
    'confirm_heading' => 'Which item did you scan?',
    'confirm_body' => 'This wasn\'t an exact match, so pick the right SKU for',
    'confirm_select' => 'This one',
    'confirm_cancel' => 'Cancel',

    // Barcode not detected — typed text fell back to a product search
    'no_barcode_heading' => 'Barcode not detected',
    'no_barcode_body' => 'Is this the product you\'re trying to :action? Top matches for',
    'no_barcode_empty' => 'No products matched what you typed. Check the spelling, or scan or enter the barcode.',
    'action_add' => 'add',
    'action_remove' => 'remove',

    // Unknown UPC
    'unknown_upc' => 'Unknown barcode',
    'unknown_upc_body' => 'This barcode isn\'t linked to any SKU in your catalog.',
    'unknown_assign' => 'Link to a product',
    'unknown_sku' => 'Unknown SKU',

    // Link barcode to SKU
    'link' => [
        'title' => 'Link barcode to a product',
        'subtitle' => 'Find the product this bag is, and we\'ll remember this barcode next time.',
        'search_placeholder' => 'Search by name, size, colour…',
        'searching' => 'Searching…',
        'no_results' => 'No matching products. Try a different search.',
        'has_barcode_badge' => 'Already has a barcode',
        'confirm' => 'Link this barcode',
        'cancel' => 'Cancel',
        'linked_toast' => 'Barcode linked to :name.',
        'invalid_barcode' => 'That barcode doesn\'t look valid — re-scan and try again.',
        'already_used' => 'That barcode is already linked to ":name".',
        'has_other_code' => 'This product already has a different barcode on file. Edit it in the catalog.',
    ],

    // Recent scans
    'recent_heading' => 'Recent scans',
    'recent_empty' => 'No scans yet',
    'hide_recent' => 'Hide all',

    // Actions
    'undo' => 'Undo',
    'undone' => 'Undone',

    // Bag-type badges
    'bag_open' => 'open',
    'bag_mixed' => 'mixed',

    // Status / errors
    'error_network' => 'Network error — retry',
    'error_lookup' => 'Could not look up barcode.',
    'error_insufficient_stock' => 'Not enough stock on hand.',

    // Misc
    'stock_label' => 'On hand',

    // Working bin selector
    'working_bin_label' => 'Working bin',
    'working_bin_auto' => 'Auto — item\'s location',
    'working_bin_hint' => 'New items go here. Items already in stock stay in their bin.',
    'bin_default_suffix' => '(default)',
    'recorded_to_bin' => 'in :bin',

    // Bin choice (which bin to pull from on removal across multiple bins)
    'pick_bin_heading' => 'Pick a bin to remove from',
    'pick_bin_body' => 'This item is stored in more than one bin. Which one are you pulling from?',
    'pick_bin_cancel' => 'Cancel',
    'bin_holds' => ':full full / :open open',

    // Bin-label scan
    'bin_set' => 'Working bin set to :bin',
    'bin_not_recognized' => 'That bin barcode wasn\'t recognized.',

    // Lookup-only (guest) mode
    'lookup_only_notice' => 'You have read-only access. Scan or type to look up products.',
    'found_heading' => 'Product found',
    'view_item' => 'View',
];
