<?php

return [
    'meta_title' => 'Bins',
    'heading' => 'Inventory',

    'tabs' => [
        'by_item' => 'By item',
        'by_bin' => 'By bin',
        'by_list' => 'By list',
    ],

    'add_location' => 'Add location',
    'add_bin' => 'Add bin',
    'default_badge' => 'Default',
    'view_label' => 'View label',
    'print_all' => 'Print all labels',
    'print_title' => 'Bin labels',

    'label' => [
        'view_title' => 'Bin label',
        'size' => 'Label size',
        'custom' => 'Custom size',
        'width_in' => 'Width (in)',
        'height_in' => 'Height (in)',
        'copy' => 'Copy image',
        'copied' => 'Copied to clipboard',
        'copy_error' => 'Copy failed — use Download instead',
        'download_png' => 'Download PNG',
        'download_svg' => 'Download SVG',
    ],

    'empty' => [
        'heading' => 'No bins yet',
        'body' => 'Add a location and bins to organize where your inventory lives.',
    ],

    'location' => [
        'bins_count_singular' => ':count bin',
        'bins_count_plural' => ':count bins',
        'empty' => 'This location has no bins yet.',
    ],

    'bin' => [
        'number_prefix' => '#:number',
        'summary_empty' => 'Empty',
        'summary_singular' => ':count item',
        'summary_plural' => ':count items',
        'full_bags' => ':count full',
        'open_bags' => ':count open',
        'empty' => 'This bin is empty.',
        'expand' => 'Show contents',
        'collapse' => 'Hide contents',
        'loading' => 'Loading contents…',
    ],

    'form' => [
        'location_name' => 'Location name',
        'location_name_placeholder' => 'e.g. Back Room, Storage Van',
        'bin_name' => 'Bin name',
        'bin_name_placeholder' => 'e.g. Top Shelf, Green Bin',
        'bin_number' => 'Number',
        'bin_number_placeholder' => 'Optional',
        'bin_location' => 'Location',
        'description' => 'Description',
        'description_placeholder' => 'Optional notes',
        'create_location_title' => 'Add location',
        'edit_location_title' => 'Edit location',
        'create_bin_title' => 'Add bin',
        'edit_bin_title' => 'Edit bin',
        'create' => 'Create',
        'save' => 'Save',
        'cancel' => 'Cancel',
        'edit' => 'Edit',
        'delete' => 'Delete',
    ],

    'delete' => [
        'bin_confirm' => 'Delete this bin? This cannot be undone.',
        'location_confirm' => 'Delete this location? This cannot be undone.',
    ],

    'flash' => [
        'location_created' => 'Location created.',
        'location_updated' => 'Location updated.',
        'location_deleted' => 'Location deleted.',
        'location_default_protected' => 'The Default location cannot be deleted.',
        'location_has_bins' => 'Remove this location’s bins before deleting it.',
        'bin_created' => 'Bin created.',
        'bin_updated' => 'Bin updated.',
        'bin_deleted' => 'Bin deleted.',
        'bin_default_protected' => 'The Default bin cannot be deleted.',
        'bin_has_stock' => 'This bin still holds stock. Check it out or transfer it first.',
    ],
];
