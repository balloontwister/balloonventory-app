<?php

namespace App\Http\Controllers;

use App\Mail\SupportRequestMail;
use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SupportController extends Controller
{
    public function send(Request $request): RedirectResponse
    {
        $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $user = $request->user()->load('memberships.business');

        // Save the ticket first so it survives a Resend outage. The admin can
        // see and follow up even if the notification email never reaches Todd.
        SupportTicket::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'subject' => $request->subject,
            'body' => $request->message,
        ]);

        try {
            Mail::to(config('mail.support_address'))
                ->send(new SupportRequestMail(
                    user: $user,
                    userSubject: $request->subject,
                    body: $request->message,
                ));
        } catch (\Throwable $e) {
            Log::error('Failed to send support request email', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            // Ticket is already persisted; surface success to the user and rely
            // on the super-admin dashboard to surface the unread ticket.
        }

        return back()->with('support_sent', true);
    }
}
