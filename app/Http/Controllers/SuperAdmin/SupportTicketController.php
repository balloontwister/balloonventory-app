<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Mail\SupportReplyMail;
use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class SupportTicketController extends Controller
{
    public function index(Request $request): Response
    {
        $showArchived = $request->boolean('showArchived');

        $tickets = SupportTicket::with('replies')
            ->when(
                $showArchived,
                fn ($q) => $q->whereNotNull('archived_at'),
                fn ($q) => $q->whereNull('archived_at'),
            )
            ->orderByDesc('created_at')
            ->limit(100)
            ->get(['id', 'user_id', 'user_name', 'user_email', 'subject', 'body', 'archived_at', 'created_at'])
            ->toArray();

        return Inertia::render('SuperAdmin/SupportTickets/Index', [
            'supportTickets' => $tickets,
            'showArchivedTickets' => $showArchived,
            'openCount' => SupportTicket::whereNull('archived_at')->count(),
        ]);
    }

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
