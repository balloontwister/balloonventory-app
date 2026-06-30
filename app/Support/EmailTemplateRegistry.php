<?php

namespace App\Support;

/**
 * Static registry of variables and preview sample values for each email
 * template key. This mirrors EMAIL.md's Template registry section — the
 * editor reads from here to render the variable sidebar and to substitute
 * realistic values when sending a preview.
 *
 * Variables are layered:
 *   - BASE_VARIABLES are available to *every* registered template (they
 *     resolve from the recipient User + config, which always exist).
 *   - Each template then declares its own context-specific variables, which
 *     only resolve where that trigger actually carries the data.
 *
 * NOTE: this registry currently only *documents* tokens (for the editor
 * sidebar + activation whitelist); the actual values are still passed by the
 * calling code. Keeping the two in sync is manual. The planned unified
 * resolver that removes that drift is documented in EMAIL.md →
 * "Roadmap: unified merge-tag resolver". Until then, when you add or change a
 * template's variables you MUST update both the calling code and this file.
 *
 * Adding a new template: add a row to `email_templates`, add an entry here,
 * and document the new variables in EMAIL.md.
 */
class EmailTemplateRegistry
{
    /**
     * Available to every registered template. Resolved from the recipient and
     * app config, so they are always present regardless of trigger context.
     *
     * @var array<string, array{description: string, sample: ?string}>
     */
    private const BASE_VARIABLES = [
        'user_name' => [
            'description' => "The recipient's display name.",
            'sample' => 'Alex',
        ],
    ];

    /**
     * @var array<string, array{description: string, variables: array<string, array{description: string, sample: ?string}>}>
     */
    private const TEMPLATES = [
        'welcome' => [
            'description' => 'Sent automatically after a new user verifies their email address.',
            'variables' => [
                'app_url' => [
                    'description' => 'The application URL (e.g. https://app.balloonventory.com).',
                    'sample' => null, // resolved from config('app.url')
                ],
            ],
        ],
        'subscription_upgrade' => [
            'description' => 'Sent when a user upgrades their subscription plan.',
            'variables' => [
                'plan_name' => [
                    'description' => 'Name of the plan the user upgraded to.',
                    'sample' => 'Pro',
                ],
                'app_url' => [
                    'description' => 'The application URL.',
                    'sample' => null,
                ],
            ],
        ],
        'business_invitation' => [
            'description' => 'Sent to an existing user when they are invited to join a business.',
            'variables' => [
                'inviter_name' => [
                    'description' => 'Name of the person who sent the invitation.',
                    'sample' => 'Sam Rivera',
                ],
                'business_name' => [
                    'description' => 'Name of the business they are invited to.',
                    'sample' => 'Twisted Balloon',
                ],
                'role_label' => [
                    'description' => 'The role they are invited as (e.g. Owner, Manager, Artist, Guest Artist).',
                    'sample' => 'Artist',
                ],
                'accept_url' => [
                    'description' => 'Magic link the invitee clicks to accept and join.',
                    'sample' => null, // resolved from config('app.url') for previews
                ],
            ],
        ],
        'invitation_accepted' => [
            'description' => 'Sent to a business owner when someone they invited accepts and joins.',
            'variables' => [
                'actor_name' => [
                    'description' => 'Name of the user who accepted and joined.',
                    'sample' => 'Jordan Lee',
                ],
                'business_name' => [
                    'description' => 'Name of the business.',
                    'sample' => 'Twisted Balloon',
                ],
                'app_url' => [
                    'description' => 'The application URL.',
                    'sample' => null,
                ],
            ],
        ],
        'ownership_transfer' => [
            'description' => 'Sent to a member when the sole owner deletes their account and nominates them to take over the business.',
            'variables' => [
                'inviter_name' => [
                    'description' => 'Name of the departing owner who nominated them.',
                    'sample' => 'Sam Rivera',
                ],
                'business_name' => [
                    'description' => 'Name of the business being handed over.',
                    'sample' => 'Twisted Balloon',
                ],
                'accept_url' => [
                    'description' => 'Magic link the nominee clicks to accept ownership.',
                    'sample' => null, // resolved from config('app.url') for previews
                ],
            ],
        ],
        'member_left_business' => [
            'description' => 'Sent to every owner of a business when a member removes themselves from it.',
            'variables' => [
                'actor_name' => [
                    'description' => 'Name of the member who left.',
                    'sample' => 'Jordan Lee',
                ],
                'business_name' => [
                    'description' => 'Name of the business.',
                    'sample' => 'Twisted Balloon',
                ],
                'app_url' => [
                    'description' => 'The application URL.',
                    'sample' => null,
                ],
            ],
        ],
        'member_role_changed' => [
            'description' => 'Sent to a member when an owner changes their role within a business.',
            'variables' => [
                'role_label' => [
                    'description' => 'The new role label (e.g. Owner, Manager, Artist, Guest).',
                    'sample' => 'Manager',
                ],
                'business_name' => [
                    'description' => 'Name of the business.',
                    'sample' => 'Twisted Balloon',
                ],
                'app_url' => [
                    'description' => 'The application URL.',
                    'sample' => null,
                ],
            ],
        ],
        'member_removed' => [
            'description' => 'Sent to a member when an owner removes them from a business.',
            'variables' => [
                'business_name' => [
                    'description' => 'Name of the business they were removed from.',
                    'sample' => 'Twisted Balloon',
                ],
                'app_url' => [
                    'description' => 'The application URL.',
                    'sample' => null,
                ],
            ],
        ],
        'password_changed_by_admin' => [
            'description' => 'Sent to a user when an administrator sets a new password for their account.',
            'variables' => [
                'app_url' => [
                    'description' => 'The application URL.',
                    'sample' => null,
                ],
            ],
        ],
    ];

    /**
     * Variable definitions (base + template-specific) for a template key.
     * Returns an empty array for unregistered keys.
     *
     * @return array<string, array{description: string, sample: string}>
     */
    public static function variablesFor(string $key): array
    {
        if (! isset(self::TEMPLATES[$key])) {
            return [];
        }

        $vars = array_merge(self::BASE_VARIABLES, self::TEMPLATES[$key]['variables']);

        return collect($vars)
            ->map(fn ($v) => [
                'description' => $v['description'],
                'sample' => $v['sample'] ?? (string) config('app.url'),
            ])
            ->all();
    }

    /**
     * Token list (just the keys, base + template-specific) for a template.
     *
     * @return array<int, string>
     */
    public static function tokensFor(string $key): array
    {
        if (! isset(self::TEMPLATES[$key])) {
            return [];
        }

        return array_keys(array_merge(self::BASE_VARIABLES, self::TEMPLATES[$key]['variables']));
    }

    /**
     * Sample values used when sending a preview.
     *
     * @return array<string, string>
     */
    public static function sampleValuesFor(string $key): array
    {
        return collect(self::variablesFor($key))
            ->map(fn ($v) => $v['sample'])
            ->all();
    }

    /**
     * Returns any tokens referenced in $text that are not in the template's
     * documented variable list. Used to block activation when copy uses
     * unknown placeholders.
     *
     * @return array<int, string>
     */
    public static function unknownTokens(string $key, string ...$texts): array
    {
        $known = self::tokensFor($key);
        $found = [];

        foreach ($texts as $text) {
            if (preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $text, $matches)) {
                $found = array_merge($found, $matches[1]);
            }
        }

        return array_values(array_unique(array_diff($found, $known)));
    }
}
