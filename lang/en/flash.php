<?php

return [
    'settings' => [
        'preferences_updated' => 'Preferences updated.',
        'business_name_updated' => 'Business name updated.',
        'business_logo_updated' => 'Business logo updated.',
    ],

    'profile' => [
        'avatar_updated' => 'Profile picture updated.',
    ],

    'catalog' => [
        'reference' => [
            'added' => 'Item added.',
            'updated' => 'Item updated.',
            'deleted' => 'Item deleted.',
        ],
        'sku' => [
            'created' => 'SKU ":name" created.',
            'updated' => 'SKU ":name" updated.',
            'deleted' => 'SKU deleted.',
        ],
        'color' => [
            'added' => 'Color ":name" added.',
            'updated' => 'Color ":name" updated.',
            'deleted' => 'Color deleted.',
        ],
        'brand' => [
            'added' => 'Brand ":name" added.',
            'updated' => 'Brand ":name" updated.',
            'gs1_added' => 'GS1 prefix :prefix added.',
            'gs1_removed' => 'GS1 prefix :prefix removed.',
        ],
    ],

    'support' => [
        'reply_failed' => 'Failed to send reply. Please try again.',
    ],

    'email_template' => [
        'saved_activated' => 'Template saved and activated. It will now fire on its trigger.',
        'saved_deactivated' => 'Template saved and deactivated. It will not fire until activated.',
        'saved_draft' => 'Template saved as a draft.',
        'preview_empty_body' => 'Cannot preview a template with an empty HTML body.',
        'preview_failed' => 'Failed to send preview. Check the application log for details.',
        'preview_sent' => 'Preview sent to :email.',
    ],

    'auth' => [
        'verification_code_resent' => 'A new code has been sent.',
    ],

    'inventory' => [
        'sku_added' => 'Added to your inventory.',
        'sku_removed' => 'Removed from inventory.',
        'override_saved' => 'Changes saved.',
        'added_to_list' => 'Added to ":list".',
        'transfer_done' => 'Stock transferred.',
        'transfer_nothing' => 'Enter at least one bag to transfer.',
        'transfer_insufficient' => 'Not enough stock in the source bin. Available: :full full / :open open bags.',
    ],
];
