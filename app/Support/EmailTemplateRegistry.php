<?php

namespace App\Support;

/**
 * Static registry of variables and preview sample values for each email
 * template key. This mirrors EMAIL.md's Template registry section — the
 * editor reads from here to render the variable sidebar and to substitute
 * realistic values when sending a preview.
 *
 * Adding a new template: add a row to `email_templates`, add an entry here,
 * and document the new variables in EMAIL.md.
 */
class EmailTemplateRegistry
{
    /**
     * @var array<string, array{description: string, variables: array<string, array{description: string, sample: string}>}>
     */
    private const TEMPLATES = [
        'welcome' => [
            'description' => 'Sent automatically after a new user verifies their email address.',
            'variables' => [
                'user_name' => [
                    'description' => "The user's display name.",
                    'sample' => 'Alex',
                ],
                'app_url' => [
                    'description' => 'The application URL (e.g. https://app.balloonventory.com).',
                    'sample' => null, // resolved from config('app.url')
                ],
            ],
        ],
        'subscription_upgrade' => [
            'description' => 'Sent when a user upgrades their subscription plan.',
            'variables' => [
                'user_name' => [
                    'description' => "The user's display name.",
                    'sample' => 'Alex',
                ],
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
    ];

    /**
     * Variable definitions for a template key.
     *
     * @return array<string, array{description: string, sample: string}>
     */
    public static function variablesFor(string $key): array
    {
        $vars = self::TEMPLATES[$key]['variables'] ?? [];

        return collect($vars)
            ->map(fn ($v) => [
                'description' => $v['description'],
                'sample' => $v['sample'] ?? (string) config('app.url'),
            ])
            ->all();
    }

    /**
     * Token list (just the keys) for a template.
     *
     * @return array<int, string>
     */
    public static function tokensFor(string $key): array
    {
        return array_keys(self::TEMPLATES[$key]['variables'] ?? []);
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
