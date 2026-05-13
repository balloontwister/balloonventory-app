<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Mail\SupportReplyMail;
use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SupportTicketController extends Controller
{
    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $request->validate(['body' => ['required', 'string', 'max:10000']]);

        try {
            Mail::to($ticket->user_email)
                ->send(new SupportReplyMail($ticket, $request->body));
        } catch (\Throwable $e) {
            Log::error('Failed to send support reply', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('ticket_error', __('flash.support.reply_failed'));
        }

        $ticket->replies()->create(['body' => $request->body]);
        $ticket->update(['archived_at' => now()]);

        return back()->with('ticket_replied', $ticket->id);
    }

    public function archive(SupportTicket $ticket): RedirectResponse
    {
        $ticket->update(['archived_at' => now()]);

        return back();
    }

    public function unarchive(SupportTicket $ticket): RedirectResponse
    {
        $ticket->update(['archived_at' => null]);

        return back();
    }

    public function destroy(SupportTicket $ticket): RedirectResponse
    {
        $ticket->delete();

        return back();
    }
}
