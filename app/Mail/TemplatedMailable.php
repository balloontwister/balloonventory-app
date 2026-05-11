<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TemplatedMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    private function __construct(
        private readonly EmailTemplate $template,
        private readonly array $variables = [],
    ) {}

    /**
     * Returns a ready-to-send Mailable, or null if the template is missing or inactive.
     * Callers should always use this factory rather than new TemplatedMailable().
     *
     * Usage:
     *   if ($mail = TemplatedMailable::forKey('welcome', ['user_name' => $user->name])) {
     *       Mail::to($email)->send($mail);
     *   }
     */
    public static function forKey(string $key, array $variables = []): ?self
    {
        $template = EmailTemplate::findByKey($key);

        if (! $template) {
            Log::warning("TemplatedMailable: template key [{$key}] not found — skipping.");
            return null;
        }

        if (! $template->is_active) {
            Log::info("TemplatedMailable: template key [{$key}] is inactive — skipping.");
            return null;
        }

        if (blank($template->body_html)) {
            Log::warning("TemplatedMailable: template key [{$key}] has an empty body — skipping.");
            return null;
        }

        return new self($template, $variables);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->interpolate($this->template->subject),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.templated',
            text: 'mail.templated-text',
            with: [
                'bodyHtml' => $this->interpolateHtml($this->template->body_html),
                'bodyText' => $this->interpolate($this->template->body_text),
            ],
        );
    }

    /** Interpolate {{tokens}} — escapes values for safe HTML output. */
    private function interpolateHtml(string $text): string
    {
        foreach ($this->variables as $key => $value) {
            $text = str_replace(
                '{{'.$key.'}}',
                htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                $text,
            );
        }
        return $text;
    }

    /** Interpolate {{tokens}} — no escaping for plain-text context. */
    private function interpolate(string $text): string
    {
        foreach ($this->variables as $key => $value) {
            $text = str_replace('{{'.$key.'}}', (string) $value, $text);
        }
        return $text;
    }
}
