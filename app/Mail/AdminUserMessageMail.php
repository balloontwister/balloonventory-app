<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A one-off email composed by an admin and sent to a specific user, wrapped in
 * the standard Balloonventory mail chrome. The subject is admin-typed; the body
 * is plain text rendered with white-space:pre-wrap. Mirrors {@see SkuFeedbackReplyMail}.
 */
class AdminUserMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $recipient,
        public readonly string $subjectLine,
        public readonly string $body,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
            replyTo: [new Address(config('mail.support_address'), __('email.reply_to_name'))],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.admin-user-message',
            text: 'mail.admin-user-message-text',
            with: [
                'recipientName' => $this->recipient->name,
                'bodyText' => $this->body,
            ],
        );
    }
}
