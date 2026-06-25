<?php

return [
    'settings' => [
        'preferences_updated' => 'Preferences updated.',
        'business_name_updated' => 'Business name updated.',
        'business_logo_updated' => 'Business logo updated.',
        'distributors_updated' => 'Preferred distributors updated.',
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

    'distributors' => [
        'created' => 'Distributor ":name" created.',
        'updated' => 'Distributor ":name" updated.',
        'deleted' => 'Distributor ":name" deleted.',
        'sync_started' => 'Sync started — products will appear in staging shortly.',
    ],

    'distributor_proposals' => [
        'approved' => 'Proposal approved.',
        'approved_created' => 'Proposal approved and a catalog SKU was created.',
        'approved_needs_mapping' => 'Proposal approved, but no SKU was created — map the missing attributes (:attributes) and approve again.',
        'approved_upc_conflict' => 'Proposal approved, but its UPC already belongs to a catalog SKU, so none was created.',
        'updated' => 'Proposal updated.',
        'rejected' => 'Proposal rejected.',
        'reject_blocked_has_sku' => 'This proposal already created a catalog SKU — remove that SKU from the catalog instead of rejecting here.',
    ],

    'support' => [
        'reply_failed' => 'Failed to send reply. Please try again.',
    ],

    'feedback' => [
        'reply_failed' => 'Failed to send the reply email. Please try again.',
    ],

    'user_email' => [
        'sent' => 'Email sent to :name.',
        'failed' => 'Failed to send the email. Please try again.',
    ],

    'users' => [
        'frozen' => ':name’s account has been frozen.',
        'thawed' => ':name’s account has been thawed.',
        'reset_sent' => 'Password reset link sent to :email.',
        'reset_failed' => 'Could not send a reset link to that address.',
        'deleted' => ':name’s account has been deleted.',
        'frozen_notice' => 'Your account is limited right now. Contact support to restore full access.',
        'password_set' => 'Password updated for :name.',
        'password_set_notified' => 'Password updated for :name and a notification email was sent.',
        'password_set_no_email' => 'Password updated for :name, but no email address is on file — no notification was sent.',
        'password_set_notify_unavailable' => 'Password updated for :name, but the notification email template is inactive — no notification was sent.',
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
        'stock_adjusted' => 'Stock updated.',
        'bin_removed' => 'Bin removed for this item.',
        'bin_not_empty' => 'That bin still holds stock — empty it before removing.',
        'feedback_submitted' => 'Thanks! Your feedback has been sent to our team.',
    ],

    'onboarding' => [
        'completed' => 'Your shop is set up! Welcome aboard.',
        'samples_cleared' => 'Removed :count sample products.',
    ],

    'lists' => [
        'created' => 'List ":list" created.',
        'updated' => 'List ":list" updated.',
        'deleted' => 'List ":list" deleted.',
        'item_added' => 'Added to ":list".',
        'item_removed' => 'Item removed from the list.',
    ],

    'memberships' => [
        'invited' => ':name has been invited.',
        'invite_sent_no_email' => ':name has been invited but the notification email template is inactive — no email was sent.',
        'unknown_email' => 'No Balloonventory account found for that email address.',
        'self_invite' => 'You cannot invite yourself.',
        'already_member' => 'That person is already a member of this business.',
        'role_updated' => ':name\'s role has been updated.',
        'removed' => ':name has been removed from the business.',
        'invite_revoked' => 'Invitation revoked.',
        'left' => 'You have left :business.',
        'last_owner_leave' => 'You cannot leave because you are the only owner. Transfer ownership first.',
    ],

    'invitations' => [
        'accepted' => 'Welcome to :business!',
        'declined' => 'Invitation declined.',
        'invalid_link' => 'This invitation link is invalid or has expired.',
        'wrong_account' => 'This invitation is for a different account. Please sign in with the correct account.',
    ],

    'business' => [
        'switched' => 'Switched to :name.',
    ],
];
