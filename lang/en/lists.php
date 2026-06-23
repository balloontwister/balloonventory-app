<?php

return [
    'index' => [
        'meta_title' => 'Lists & Jobs',
        'heading' => 'Lists & Jobs',
        'new_list' => 'New list',
        'section_lists' => 'Lists',
        'section_jobs' => 'Jobs',
        'sku_count' => '{0} No items|{1} :count item|[2,*] :count items',
        'favorites_empty' => 'No favorites yet — star items from your inventory.',
        'no_custom_lists' => 'No lists yet. Create one to group SKUs for an event, a client, or a recurring order.',
        'no_archived_lists' => 'No archived lists.',
        'show_archived' => '{1} Show :count archived list|[2,*] Show :count archived lists',
        'hide_archived' => 'Hide archived',
        'section_archived' => 'Archived',
        'jobs_coming_title' => 'Jobs are coming soon',
        'jobs_coming_hint' => 'A job is a list with an event date and client details. You\'ll be able to plan and pull stock for an event here.',
    ],

    'inventory' => [
        'meta_title' => 'Inventory — By list',
        'manage_lists' => 'Manage lists',
        'no_lists' => 'No lists to show yet.',
    ],

    'detail' => [
        'col_item' => 'Item',
        'col_brand' => 'Brand',
        'col_size' => 'Size',
        'col_stock' => 'On hand',
        'edit' => 'Edit List',
        'delete' => 'Delete',
        'archived_badge' => 'Archived',
        'archived_notice' => 'This list is archived and hidden from the main view.',
        'unarchive' => 'Unarchive',
        'remove_item' => 'Remove from list',
        'empty_title' => 'This list is empty',
        'empty_hint' => 'Search above to add items, or browse your inventory.',
        'browse_inventory' => 'Browse inventory',
        'add_placeholder' => 'Search items to add…',
        'add_searching' => 'Searching…',
        'add_no_results' => 'No matching items.',
        'add_already' => 'Added',
        'delete_confirm_title' => 'Delete this list?',
        'delete_confirm_body' => 'List ":list" will be removed. Items in it are not deleted from your inventory.',
    ],

    'items' => [
        'planned_quantity' => 'Planned qty',
        'reorder_threshold' => 'Reorder at',
        'none' => '—',
    ],

    'form' => [
        'name_label' => 'List name',
        'name_placeholder' => 'e.g. Smith Wedding, Monthly reorder',
        'notes_label' => 'Notes',
        'notes_placeholder' => 'Optional notes for your team',
        'visibility_label' => 'List type',
        'visibility_standard' => 'Standard — team can view and edit',
        'visibility_owner_editable' => 'Owner\'s List — team views, only you can edit',
        'visibility_private' => 'Private — only you can see and edit',
        'archive_label' => 'Archive this list',
        'archive_hint' => 'Hidden from the main view but available when you show archived lists.',
        'create_submit' => 'Create list',
        'save_submit' => 'Save changes',
    ],

    'create' => [
        'meta_title' => 'New List',
        'heading' => 'New List',
    ],
    'show' => [
        'meta_title' => 'List',
        'heading' => 'List',
    ],
    'edit' => [
        'meta_title' => 'Edit List',
        'heading' => 'Edit List',
    ],

    'history' => [
        'heading' => 'History',
        'created' => ':user created this list',
        'renamed' => ':user renamed from ":old" to ":new"',
        'archived' => ':user archived this list',
        'unarchived' => ':user unarchived this list',
        'visibility_changed' => ':user changed the list type',
        'item_added' => ':user added :sku',
        'item_removed' => ':user removed :sku',
        'item_qty_changed' => ':user changed planned qty for :sku from :old to :new',
    ],
];
