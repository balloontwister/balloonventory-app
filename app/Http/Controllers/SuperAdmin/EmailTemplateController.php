<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Mail\TemplatedMailable;
use App\Models\EmailTemplate;
use App\Support\EmailTemplateRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EmailTemplateController extends Controller
{
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
            ->route('super-admin.email-templates.edit', $template)
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
