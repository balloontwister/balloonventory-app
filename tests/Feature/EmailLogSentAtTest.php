<?php

namespace Tests\Feature;

use App\Models\EmailLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailLogSentAtTest extends TestCase
{
    use RefreshDatabase;

    public function test_sent_email_is_logged_with_app_utc_time(): void
    {
        $this->freezeTime();

        Mail::raw('Hello there', function ($message) {
            $message->to('recipient@example.com')->subject('Hello subject');
        });

        $log = EmailLog::first();

        $this->assertNotNull($log, 'A MessageSent event should create an EmailLog row.');
        $this->assertNotNull($log->sent_at, 'sent_at must be populated.');

        // sent_at must be the application clock (UTC), set explicitly by the
        // listener — not the column's useCurrent() default, which on MySQL
        // records the DB server's local wall-clock and renders hours off.
        $this->assertSame(
            now()->format('Y-m-d H:i:s'),
            $log->sent_at->format('Y-m-d H:i:s'),
        );
    }
}
