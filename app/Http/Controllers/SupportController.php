<?php

namespace App\Http\Controllers;

use App\Mail\SupportRequestMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SupportController extends Controller
{
    public function send(Request $request): RedirectResponse
    {
        $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $user = $request->user()->load('memberships.business');

        try {
            Mail::to(config('mail.support_address'))
                ->send(new SupportRequestMail(
                    user: $user,
                    subject: $request->subject,
                    body: $request->message,
                ));
        } catch (\Throwable $e) {
            Log::error('Failed to send support request email', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('support_error', 'Something went wrong sending your message. Please try again.');
        }

        return back()->with('support_sent', true);
    }
}
