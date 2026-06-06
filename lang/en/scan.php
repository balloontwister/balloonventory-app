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

    // Unknown UPC
    'unknown_upc' => 'Unknown barcode',
    'unknown_upc_body' => 'This barcode isn\'t linked to any SKU in your catalog.',
    'unknown_assign' => 'Assign to SKU',
    'unknown_sku' => 'Unknown SKU',

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
];
