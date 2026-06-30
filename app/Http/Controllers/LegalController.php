<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public legal/policy pages. Canonical copies live in the app as Markdown
 * (resources/legal/{locale}/{doc}.md), rendered server-side and shown as plain
 * prose. Pages are reachable without authentication so people can read them
 * before signing up and so payment processors / crawlers can fetch them.
 */
class LegalController extends Controller
{
    /**
     * Documents that may be rendered. Doubles as the activation allow-list — a
     * doc outside this set 404s (defence-in-depth; routes also pin the value).
     */
    public const DOCUMENTS = ['terms', 'privacy', 'cookies', 'acceptable-use', 'refunds'];

    /**
     * The legal hub — a card list of every policy.
     */
    public function index(): Response
    {
        return Inertia::render('Legal/Index', [
            'documents' => collect(self::DOCUMENTS)
                ->map(fn (string $doc): array => [
                    'doc' => $doc,
                    'title' => __("legal.docs.{$doc}.title"),
                    'summary' => __("legal.docs.{$doc}.summary"),
                    'url' => route("legal.{$doc}"),
                ])
                ->all(),
            'updatedAt' => config('legal.effective_date'),
        ]);
    }

    /**
     * A single policy. The doc key is pinned by the route via ->defaults().
     */
    public function show(string $doc): Response
    {
        abort_unless(in_array($doc, self::DOCUMENTS, true), 404);

        return Inertia::render('Legal/Show', [
            'doc' => $doc,
            'title' => __("legal.docs.{$doc}.title"),
            'html' => $this->renderMarkdown($doc, app()->getLocale()),
            'updatedAt' => config('legal.effective_date'),
        ]);
    }

    /**
     * Render the Markdown for a doc in the requested locale, falling back to
     * English when a localized file is missing. Raw HTML is stripped so the
     * output stays trusted (the source is our own author-controlled Markdown).
     */
    private function renderMarkdown(string $doc, string $locale): string
    {
        $path = resource_path("legal/{$locale}/{$doc}.md");

        if (! File::exists($path)) {
            $path = resource_path("legal/en/{$doc}.md");
        }

        $markdown = File::exists($path) ? File::get($path) : '';

        return Str::markdown($markdown, ['html_input' => 'strip']);
    }
}
