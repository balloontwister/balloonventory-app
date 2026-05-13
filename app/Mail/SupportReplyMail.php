<?php

namespace App\Mail;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupportReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly SupportTicket $ticket,
        public readonly string $replyBody,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('email.subjects.support_reply', ['subject' => $this->ticket->subject]),
            replyTo: [new Address(config('mail.support_address'), __('email.reply_to_name'))],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.support-reply',
            text: 'mail.support-reply-text',
        );
    }
}
