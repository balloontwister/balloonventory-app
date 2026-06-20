<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Mail\TemplatedMailable;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Support\EmailTemplateRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EmailTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $composeUser = null;

        if ($request->filled('user')) {
            $composeUser = User::find($request->input('user'))?->only(['id', 'name', 'email']);
        }

        return Inertia::render('SuperAdmin/EmailTemplates/Index', [
            'templates' => $this->templates(),
            'emailByDay' => $this->emailByDay(),
            'emailByMonth' => $this->emailByMonth(),
            'sentEmails' => $this->sentEmails(),
            'composeUser' => $composeUser,
            'draftTemplates' => $this->draftTemplates(),
            'appUrl' => (string) config('app.url'),
        ]);
    }

    public function edit(EmailTemplate $template): Response
    {
        return Inertia::render('SuperAdmin/EmailTemplates/Edit', [
            'template' => [
                'id' => $template->id,
                'key' => $template->key,
                'label' => $template->label,
                'trigger_description' => $template->trigger_description,
                'subject' => $template->subject,
                'body_html' => $template->body_html ?? '',
                'body_text' => $template->body_text ?? '',
                'is_active' => $template->is_active,
                'updated_at' => $template->updated_at,
                'last_edited_by' => $template->lastEditedBy?->only(['id', 'name']),
            ],
            'variables' => EmailTemplateRegistry::variablesFor($template->key),
        ]);
    }

    public function update(Request $request, EmailTemplate $template): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body_html' => ['nullable', 'string'],
            'body_text' => ['nullable', 'string'],
            'action' => ['required', 'in:save,activate,deactivate'],
        ]);

        // Activation requires complete copy and only-known tokens.
        if ($data['action'] === 'activate') {
            $this->ensureActivatable($template->key, $data);
        }

        $template->fill([
            'subject' => $data['subject'],
            'body_html' => $data['body_html'] ?? '',
            'body_text' => $data['body_text'] ?? '',
            'last_edited_by_user_id' => $request->user()->id,
        ]);

        if ($data['action'] === 'activate') {
            $template->is_active = true;
        } elseif ($data['action'] === 'deactivate') {
            $template->is_active = false;
        }

        $template->save();

        $flash = match ($data['action']) {
            'activate' => __('flash.email_template.saved_activated'),
            'deactivate' => __('flash.email_template.saved_deactivated'),
            default => __('flash.email_template.saved_draft'),
        };

        return redirect()
            ->route('admin.email-templates.edit', $template)
            ->with('success', $flash);
    }

    public function preview(Request $request, EmailTemplate $template): RedirectResponse
    {
        // Persist the in-flight draft so the preview reflects what's on screen.
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body_html' => ['required', 'string'],
            'body_text' => ['nullable', 'string'],
        ]);

        $template->fill([
            'subject' => $data['subject'],
            'body_html' => $data['body_html'],
            'body_text' => $data['body_text'] ?? '',
            'last_edited_by_user_id' => $request->user()->id,
        ])->save();

        $mailable = TemplatedMailable::forPreview(
            $template->refresh(),
            EmailTemplateRegistry::sampleValuesFor($template->key),
        );

        if (! $mailable) {
            return back()->with('error', __('flash.email_template.preview_empty_body'));
        }

        try {
            Mail::to($request->user()->email)->send($mailable);
        } catch (\Throwable $e) {
            Log::error('Failed to send template preview', [
                'template' => $template->key,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', __('flash.email_template.preview_failed'));
        }

        return back()->with('success', __('flash.email_template.preview_sent', ['email' => $request->user()->email]));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function templates(): array
    {
        return EmailTemplate::with('lastEditedBy:id,name')
            ->orderBy('label')
            ->get()
            ->map(fn (EmailTemplate $t) => [
                'id' => $t->id,
                'key' => $t->key,
                'label' => $t->label,
                'trigger_description' => $t->trigger_description,
                'is_active' => $t->is_active,
                'has_body' => filled($t->body_html),
                'updated_at' => $t->updated_at,
                'last_edited_by' => $t->lastEditedBy?->only(['id', 'name']),
            ])
            ->toArray();
    }

    /**
     * Templates that have copy written, offered in the composer as editable
     * starting drafts. Returns the plain-text body since the composer is plain text.
     *
     * @return array<int,array<string,mixed>>
     */
    private function draftTemplates(): array
    {
        return EmailTemplate::whereNotNull('body_html')
            ->where('body_html', '!=', '')
            ->orderBy('label')
            ->get()
            ->map(fn (EmailTemplate $t) => [
                'key' => $t->key,
                'label' => $t->label,
                'subject' => $t->subject ?? '',
                'body_text' => $t->body_text ?? '',
            ])
            ->toArray();
    }

    /**
     * The most recent individual emails the system has sent — the outgoing log.
     * Capped at 50 (the page previews the first few with a show-more toggle).
     *
     * @return array<int,array<string,mixed>>
     */
    private function sentEmails(): array
    {
        return EmailLog::orderByDesc('sent_at')
            ->limit(50)
            ->get(['id', 'to', 'subject', 'mailable', 'user_id', 'sent_at'])
            ->toArray();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function emailByDay(): array
    {
        return EmailLog::select(
            DB::raw('DATE(sent_at) as date'),
            DB::raw('COUNT(*) as count'),
            'mailable',
        )
            ->where('sent_at', '>=', now()->subDays(30))
            ->groupBy('date', 'mailable')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function emailByMonth(): array
    {
        // substr keeps this portable across MySQL (prod) and SQLite (tests);
        // sent_at serializes as 'YYYY-MM-DD …', so chars 1-7 are the month.
        return EmailLog::select(
            DB::raw('substr(sent_at, 1, 7) as month'),
            DB::raw('COUNT(*) as count'),
            'mailable',
        )
            ->where('sent_at', '>=', now()->subMonths(12))
            ->groupBy('month', 'mailable')
            ->orderBy('month')
            ->get()
            ->toArray();
    }

    /**
     * Throws ValidationException if the template isn't ready to activate.
     */
    private function ensureActivatable(string $key, array $data): void
    {
        $errors = [];

        if (blank($data['body_html'] ?? null)) {
            $errors['body_html'] = ['HTML body is required before activating.'];
        }

        if (blank($data['body_text'] ?? null)) {
            $errors['body_text'] = ['Plain-text body is required before activating.'];
        }

        $unknown = EmailTemplateRegistry::unknownTokens(
            $key,
            $data['subject'] ?? '',
            $data['body_html'] ?? '',
            $data['body_text'] ?? '',
        );

        if (! empty($unknown)) {
            $errors['body_html'] = $errors['body_html'] ?? [];
            $errors['body_html'][] = 'Unknown tokens used: '.implode(', ', array_map(fn ($t) => '{{'.$t.'}}', $unknown))
                .'. Remove them or pick from the variable reference.';
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }
}
