<?php

namespace App\Mail;

use App\Models\SkuFeedback;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The admin's reply to a user's item-feedback report, emailed back to close the
 * loop. Mirrors {@see SupportReplyMail}.
 */
class SkuFeedbackReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly SkuFeedback $feedback,
        public readonly string $replyBody,
        public readonly string $fieldLabel,
        public readonly string $recipientName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('email.subjects.feedback_reply', ['product' => $this->feedback->sku_name]),
            replyTo: [new Address(config('mail.support_address'), __('email.reply_to_name'))],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.feedback-reply',
            text: 'mail.feedback-reply-text',
        );
    }
}
